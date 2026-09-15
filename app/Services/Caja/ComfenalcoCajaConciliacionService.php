<?php

namespace App\Services\Caja;

use App\Models\Aliado;
use App\Models\Contrato;
use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use App\Models\RazonSocial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Conciliación de los radicados de caja con "Trabajadores por Empresa" de la
 * Sucursal Virtual de Comfenalco Valle.
 *
 * La extensión BryNex Portales baja la lista con la sesión de la empresa
 * (documento, nombre, fecha de ingreso a la empresa y fecha de ingreso a la caja)
 * y aquí se cruza igual que en Sanitas: el radicado de caja sin confirmar de un
 * contrato vigente con esa caja pasa a OK confirmado si la persona aparece
 * afiliada y el apellido coincide. Además lista a los afiliados de la empresa que
 * BryNex no tiene como contrato vigente con esa caja.
 */
class ComfenalcoCajaConciliacionService
{
    public const CONFIRMADOR = 'caja_comfenalco';

    /** Aliado 1 (BryNex): solo pruebas, nunca entra en consolidados. */
    public const ALIADO_PRUEBAS = 1;

    private const CAJA = 'COMFENALCO VALLE';

    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR, Radicado::ESTADO_OK];

    /** Si la corrida abarca varios aliados y cada fila debe decir de cuál es. */
    private bool $conAliado = false;

    /** Aliados donde el NIT es razón social (lo que ve un usuario BryNex), sin el de pruebas. */
    public function aliadosDelNit(string $nit): array
    {
        return RazonSocial::where('nit', preg_replace('/\D/', '', $nit))->where('aliado_id', '<>', self::ALIADO_PRUEBAS)
            ->distinct()->pluck('aliado_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /**
     * @param  array<int, array{0:string,1:string,2:string,3:string}>  $filas  del portal
     * @return array{empresa:string, aliados:array, total:int, cerrados:int, faltan:int, revisar:int, sobran:array, detalle:array}
     */
    public function conciliar(array $aliadoIds, string $nit, array $filas, bool $simular, ?int $usuarioId): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $aliadoIds = array_values(array_unique(array_map('intval', $aliadoIds)));
        $razones = RazonSocial::whereIn('aliado_id', $aliadoIds)->where('nit', $nit)->get();
        if ($razones->isEmpty()) {
            throw new RuntimeException("El NIT {$nit} de la sesión de Comfenalco no es una razón social de este aliado.");
        }
        $aliadoIds = $razones->pluck('aliado_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->conAliado = count($aliadoIds) > 1;

        $enCaja = $this->leer($filas);
        $detalle = [];
        foreach ($this->candidatos($aliadoIds, $nit) as $r) {
            $detalle[] = $this->cruzar($r, $enCaja->get(ltrim((string) $r->contrato->cedula, '0')), $simular, $usuarioId);
        }
        $cuenta = collect($detalle)->countBy('accion');

        return [
            'empresa'  => $razones->first()->razon_social,
            'aliados'  => Aliado::whereIn('id', $aliadoIds)->orderBy('id')->pluck('nombre')->all(),
            'total'    => count($detalle),
            'cerrados' => $cuenta->get('cerrado', 0) + $cuenta->get('cerraria', 0),
            'tramite'  => 0,
            'faltan'   => $cuenta->get('falta', 0),
            'revisar'  => $cuenta->get('revisar', 0),
            'confirmados_ok' => $cuenta->get('ya_ok', 0),
            'errores'  => $cuenta->get('error', 0),
            'sobran'   => $this->sobrantes($aliadoIds, $nit, $enCaja),
            'afiliados_caja' => $enCaja->count(),
            'simulado' => $simular,
            'detalle'  => array_values(array_filter($detalle, fn ($f) => $f['accion'] !== 'ya_ok')),
        ];
    }

    /** @return Collection<string, array{documento:string, nombre:string, ingreso_empresa:?string, ingreso_caja:?string}> */
    public function leer(array $filas): Collection
    {
        return collect($filas)
            ->map(fn ($f) => [
                'documento'       => ltrim(preg_replace('/\D/', '', (string) ($f[0] ?? '')), '0'),
                'nombre'          => trim((string) ($f[1] ?? '')),
                'ingreso_empresa' => trim((string) ($f[2] ?? '')) ?: null,
                'ingreso_caja'    => trim((string) ($f[3] ?? '')) ?: null,
            ])
            ->filter(fn ($f) => $f['documento'] !== '')
            ->keyBy('documento');
    }

    /** Radicados de caja sin confirmar de contratos vigentes con esa caja en esa empresa. */
    private function candidatos(array $aliadoIds, string $nit): Collection
    {
        return Radicado::query()
            ->whereIn('radicados.aliado_id', $aliadoIds)
            ->where('radicados.tipo', 'caja')
            ->whereIn('radicados.estado', self::ESTADOS)
            ->whereNull('radicados.confirmado_por')
            ->whereHas('contrato', fn ($c) => $c
                ->whereColumn('contratos.aliado_id', 'radicados.aliado_id')
                ->where('estado', 'vigente')
                ->whereDate('fecha_ingreso', '<=', today())
                ->whereHas('caja', fn ($k) => $k->where('nombre', 'like', '%'.self::CAJA.'%'))
                ->whereHas('razonSocial', fn ($rs) => $rs->where('es_independiente', false)->where('nit', $nit)))
            ->with(['contrato.cliente', 'contrato.razonSocial', 'contrato.aliado'])
            ->orderBy('radicados.id')
            ->get();
    }

    private function cruzar(Radicado $r, ?array $a, bool $simular, ?int $usuarioId): array
    {
        if (! $a) {
            return $r->estado === Radicado::ESTADO_OK
                ? $this->fila($r, 'revisar', 'Está en OK en BryNex pero no aparece entre los trabajadores afiliados de la empresa en Comfenalco.')
                : $this->fila($r, 'falta', 'No aparece afiliado a la caja: falta radicar la afiliación (🏢 Afiliar a la caja).');
        }

        $apellido = $this->texto((string) $r->contrato->cliente?->primer_apellido);
        if ($apellido === '' || ! str_contains($this->texto($a['nombre']), $apellido)) {
            return $this->fila($r, 'revisar', "En Comfenalco figura como {$a['nombre']}, que no coincide con el apellido de BryNex.");
        }

        $observacion = sprintf('Confirmado en Comfenalco Valle el %s: afiliado a la caja con %s desde %s (Trabajadores por Empresa de la Sucursal Virtual).',
            now()->format('d/m/Y'), $r->contrato->razonSocial->razon_social, $a['ingreso_caja'] ?? $a['ingreso_empresa'] ?? '—');

        if ($simular) {
            return $this->fila($r, $r->estado === Radicado::ESTADO_OK ? 'ya_ok' : 'cerraria', $observacion);
        }

        $hecho = DB::transaction(function () use ($r, $observacion, $usuarioId) {
            $fresco = Radicado::whereKey($r->id)->lockForUpdate()->first();
            if (! $fresco || $fresco->confirmado_por || ! in_array($fresco->estado, self::ESTADOS, true)) {
                return false;
            }
            $anterior = $fresco->estado;
            $fresco->update([
                'estado'             => Radicado::ESTADO_OK,
                'fecha_confirmacion' => $fresco->fecha_confirmacion ?? now(),
                'observacion'        => trim(($fresco->observacion ? $fresco->observacion.' | ' : '').$observacion),
            ] + $fresco->datosConfirmacion(self::CONFIRMADOR));
            RadicadoMovimiento::create([
                'radicado_id' => $fresco->id, 'contrato_id' => $fresco->contrato_id, 'tipo_proceso' => 'afiliacion',
                'entidad' => 'caja', 'user_id' => $usuarioId, 'estado_anterior' => $anterior,
                'estado_nuevo' => Radicado::ESTADO_OK, 'observacion' => $observacion,
            ]);

            return $anterior;
        });

        if ($hecho === false) {
            return $this->fila($r, 'revisar', 'El radicado cambió mientras se cruzaba; no se tocó.');
        }

        return $this->fila($r, $hecho === Radicado::ESTADO_OK ? 'ya_ok' : 'cerrado', $observacion);
    }

    /** Afiliados a la caja por esa empresa que BryNex no tiene como contrato vigente con esa caja. */
    private function sobrantes(array $aliadoIds, string $nit, Collection $enCaja): array
    {
        if ($enCaja->isEmpty()) {
            return [];
        }

        $contratos = Contrato::with(['caja', 'razonSocial', 'aliado'])
            ->whereIn('aliado_id', $aliadoIds)
            ->whereIn('cedula', $enCaja->keys()->all())
            ->orderByDesc('id')->get()
            ->groupBy(fn ($c) => ltrim((string) $c->cedula, '0'));

        $salida = [];
        foreach ($enCaja as $doc => $a) {
            $suyos = $contratos->get($doc, collect());
            $vigente = $suyos->first(fn ($c) => $c->estado === 'vigente'
                && preg_replace('/\D/', '', (string) $c->razonSocial?->nit) === $nit
                && str_contains(mb_strtoupper((string) $c->caja?->nombre), self::CAJA));
            if ($vigente) {
                continue;
            }

            $otro = $suyos->first(fn ($c) => $c->estado === 'vigente') ?: $suyos->first();
            $donde = $this->conAliado && $otro ? ' ('.$this->nombreAliado($otro).')' : '';
            $motivo = match (true) {
                $suyos->isEmpty() => $this->conAliado ? 'No tiene contratos en ningún aliado de BryNex.' : 'No tiene contratos en BryNex.',
                $otro && $otro->estado === 'vigente' => 'Contrato vigente en BryNex con '.($otro->caja?->nombre ?? 'otra caja o sin caja').' en '.($otro->razonSocial?->razon_social ?? '—').$donde.'.',
                default => 'Retirado en BryNex'.$donde.' (último contrato '.$suyos->first()->estado.') pero sigue afiliado a la caja.',
            };
            $salida[] = ['cedula' => (string) $doc, 'nombre' => $a['nombre'], 'desde' => $a['ingreso_caja'], 'motivo' => $motivo];
        }

        return $salida;
    }

    private function fila(Radicado $r, string $accion, string $mensaje): array
    {
        $c = $r->contrato;

        return [
            'radicado_id'  => $r->id,
            'contrato_id'  => $c->id,
            'cedula'       => (string) $c->cedula,
            'nombre'       => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa'      => $c->razonSocial?->razon_social.($this->conAliado ? ' · '.$this->nombreAliado($c) : ''),
            'estado_antes' => $r->estado,
            'accion'       => $accion,
            'mensaje'      => $mensaje,
        ];
    }

    private function nombreAliado(Contrato $c): string
    {
        return $c->aliado?->nombre ?? 'Aliado '.$c->aliado_id;
    }

    private function texto(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii($s))));
    }
}
