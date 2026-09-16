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
 * Conciliación de los radicados de caja con la Sucursal Virtual Empresas de
 * Comfandi.
 *
 * La extensión BryNex Portales baja dos listas con la sesión de la empresa:
 *
 *  - **Listado de trabajadores**: quién está afiliado hoy, con su fecha de
 *    ingreso y de afiliación. Es la fuente de verdad para cerrar un radicado.
 *  - **Radicados**: número (002-002-…), tipo de solicitud, estado y fecha. No
 *    dice quién está afiliado —solo qué se tramitó— pero explica a los que
 *    todavía no aparecen: si la solicitud va "En proceso" el radicado queda en
 *    trámite con su número, y si salió "Rechazado" se marca para revisar en vez
 *    de volver a radicarla a ciegas.
 *
 * Esto es lo que no tiene Comfenalco Valle, donde solo hay listado de afiliados
 * y quien no aparece siempre queda como "falta".
 */
class ComfandiCajaConciliacionService
{
    public const CONFIRMADOR = 'caja_comfandi';

    /** Aliado 1 (BryNex): solo pruebas, nunca entra en consolidados. */
    public const ALIADO_PRUEBAS = 1;

    private const CAJA = 'COMFANDI';

    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR, Radicado::ESTADO_OK];

    /** Si la corrida abarca varios aliados y cada fila debe decir de cuál es. */
    private bool $conAliado = false;

    /** Aliados donde el NIT es razón social (lo que ve un usuario BryNex), sin el de pruebas. */
    public function aliadosDelNit(string $nit): array
    {
        return RazonSocial::where('nit', $this->nitBryNex($nit))->where('aliado_id', '<>', self::ALIADO_PRUEBAS)
            ->distinct()->pluck('aliado_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /**
     * El NIT como lo guarda BryNex, a partir del que muestra el portal.
     *
     * Comfandi lo escribe con el dígito de verificación pegado —CONSTRUTECH es
     * 9016037383 allá y 901603738 aquí—, así que si el número completo no es
     * ninguna razón social se prueba sin el último dígito. Se comprueba contra
     * la tabla en vez de recortar a ciegas: hay NITs legítimos de 10 dígitos.
     */
    private function nitBryNex(string $nit): string
    {
        $limpio = preg_replace('/\D/', '', $nit);
        if ($limpio === '' || RazonSocial::where('nit', $limpio)->exists()) {
            return $limpio;
        }

        $sinDv = substr($limpio, 0, -1);

        return strlen($limpio) >= 10 && RazonSocial::where('nit', $sinDv)->exists() ? $sinDv : $limpio;
    }

    /**
     * @param  array  $filas  del Listado de trabajadores: [documento, nombre, ingreso empresa, ingreso caja]
     * @param  array  $radicados  de la pestaña Radicados: [numero, tipo, estado, fecha, beneficiario, trabajador]
     */
    public function conciliar(array $aliadoIds, string $nit, array $filas, array $radicados, bool $simular, ?int $usuarioId): array
    {
        $delPortal = preg_replace('/\D/', '', $nit);
        $nit = $this->nitBryNex($nit);
        $aliadoIds = array_values(array_unique(array_map('intval', $aliadoIds)));
        $razones = RazonSocial::whereIn('aliado_id', $aliadoIds)->where('nit', $nit)->get();
        if ($razones->isEmpty()) {
            throw new RuntimeException("El NIT {$delPortal} de la sesión de Comfandi no es una razón social de este aliado"
                .($nit !== $delPortal ? " (tampoco {$nit}, sin el dígito de verificación)." : '.'));
        }
        $aliadoIds = $razones->pluck('aliado_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->conAliado = count($aliadoIds) > 1;

        $enCaja = $this->leer($filas);
        $tramites = $this->leerRadicados($radicados);
        $detalle = [];
        foreach ($this->candidatos($aliadoIds, $nit) as $r) {
            $doc = ltrim((string) $r->contrato->cedula, '0');
            $detalle[] = $this->cruzar($r, $enCaja->get($doc), $tramites->get($doc), $simular, $usuarioId);
        }
        $cuenta = collect($detalle)->countBy('accion');

        return [
            'empresa' => $razones->first()->razon_social,
            'aliados' => Aliado::whereIn('id', $aliadoIds)->orderBy('id')->pluck('nombre')->all(),
            'total' => count($detalle),
            'cerrados' => $cuenta->get('cerrado', 0) + $cuenta->get('cerraria', 0),
            'tramite' => $cuenta->get('tramite', 0) + $cuenta->get('tramitaria', 0),
            'faltan' => $cuenta->get('falta', 0),
            'revisar' => $cuenta->get('revisar', 0),
            'confirmados_ok' => $cuenta->get('ya_ok', 0),
            'errores' => $cuenta->get('error', 0),
            'sobran' => $this->sobrantes($aliadoIds, $nit, $enCaja),
            'afiliados_caja' => $enCaja->count(),
            'radicados_portal' => $tramites->count(),
            'simulado' => $simular,
            'detalle' => array_values(array_filter($detalle, fn ($f) => $f['accion'] !== 'ya_ok')),
        ];
    }

    /** @return Collection<string, array{documento:string, nombre:string, ingreso_empresa:?string, ingreso_caja:?string}> */
    public function leer(array $filas): Collection
    {
        return collect($filas)
            ->map(fn ($f) => [
                'documento' => ltrim(preg_replace('/\D/', '', (string) ($f[0] ?? '')), '0'),
                'nombre' => trim((string) ($f[1] ?? '')),
                'ingreso_empresa' => trim((string) ($f[2] ?? '')) ?: null,
                'ingreso_caja' => trim((string) ($f[3] ?? '')) ?: null,
            ])
            ->filter(fn ($f) => $f['documento'] !== '')
            ->keyBy('documento');
    }

    /**
     * Radicados de afiliación del portal, el más reciente por trabajador.
     *
     * El documento va dentro de la columna "Trabajador" ("JOSE JIMENEZ - CC
     * 16289572"), y las filas llegan de la más nueva a la más vieja, así que la
     * primera de cada persona es la que manda.
     *
     * @return Collection<string, array{numero:string, tipo:string, estado:string, fecha:?string}>
     */
    public function leerRadicados(array $radicados): Collection
    {
        return collect($radicados)
            ->map(fn ($f) => [
                'numero' => trim((string) ($f[0] ?? '')),
                'tipo' => trim((string) ($f[1] ?? '')),
                'estado' => trim((string) ($f[2] ?? '')),
                'fecha' => trim((string) ($f[3] ?? '')) ?: null,
                'documento' => ltrim(preg_replace('/\D/', '', Str::afterLast((string) ($f[5] ?? ''), '-')), '0'),
            ])
            // Los de beneficiarios no dicen nada del trabajador: se ignoran.
            ->filter(fn ($f) => $f['documento'] !== '' && ! Str::contains(Str::lower($f['tipo']), 'beneficiari'))
            ->groupBy('documento')
            ->map(fn ($g) => $g->first());
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

    private function cruzar(Radicado $r, ?array $a, ?array $tramite, bool $simular, ?int $usuarioId): array
    {
        if (! $a) {
            return $this->sinAfiliar($r, $tramite, $simular, $usuarioId);
        }

        $apellido = $this->texto((string) $r->contrato->cliente?->primer_apellido);
        if ($apellido === '' || ! str_contains($this->texto($a['nombre']), $apellido)) {
            return $this->fila($r, 'revisar', "En Comfandi figura como {$a['nombre']}, que no coincide con el apellido de BryNex.");
        }

        $observacion = sprintf('Confirmado en Comfandi el %s: afiliado a la caja con %s desde %s (Listado de trabajadores de la Sucursal Virtual Empresas).',
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
                'estado' => Radicado::ESTADO_OK,
                'fecha_confirmacion' => $fresco->fecha_confirmacion ?? now(),
                'observacion' => trim(($fresco->observacion ? $fresco->observacion.' | ' : '').$observacion),
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

    /**
     * No está afiliado todavía: lo que se haga depende de si hay una solicitud
     * en el portal y de cómo quedó.
     */
    private function sinAfiliar(Radicado $r, ?array $tramite, bool $simular, ?int $usuarioId): array
    {
        if (! $tramite) {
            return $r->estado === Radicado::ESTADO_OK
                ? $this->fila($r, 'revisar', 'Está en OK en BryNex pero no aparece entre los trabajadores afiliados de la empresa en Comfandi, ni tiene radicado en el portal.')
                : $this->fila($r, 'falta', 'No aparece afiliado a la caja ni tiene radicado en el portal: falta radicar la afiliación (🏢 Afiliar a Comfandi).');
        }

        // texto() ya devuelve mayúsculas sin acentos: "Rechazado" → "RECHAZADO".
        if (Str::contains($this->texto($tramite['estado']), 'RECHAZ')) {
            return $this->fila($r, 'revisar', sprintf('Comfandi RECHAZÓ la solicitud N° %s del %s (%s): hay que mirar el motivo en el portal antes de volver a radicar.',
                $tramite['numero'], $tramite['fecha'] ?? '—', $tramite['tipo']));
        }

        $observacion = sprintf('Comfandi (caja): solicitud N° %s del %s en el portal, estado "%s". Todavía no aparece en el Listado de trabajadores.',
            $tramite['numero'], $tramite['fecha'] ?? '—', $tramite['estado']);

        // Ya está radicada: se guarda el número y queda en trámite, pero nunca
        // se retrocede un OK ni se pisa un número que ya esté escrito.
        if ($r->estado === Radicado::ESTADO_OK) {
            return $this->fila($r, 'revisar', 'Está en OK en BryNex pero en Comfandi la solicitud '.$tramite['numero'].' sigue en "'.$tramite['estado'].'".');
        }
        if ($simular) {
            return $this->fila($r, 'tramitaria', $observacion);
        }

        DB::transaction(function () use ($r, $tramite, $observacion, $usuarioId) {
            $fresco = Radicado::whereKey($r->id)->lockForUpdate()->first();
            if (! $fresco || $fresco->estado === Radicado::ESTADO_OK) {
                return;
            }
            $anterior = $fresco->estado;
            $fresco->update([
                'estado' => Radicado::ESTADO_TRAMITE,
                'numero_radicado' => $fresco->numero_radicado ?: $tramite['numero'],
                'canal_envio' => $fresco->canal_envio ?: 'portal',
                'fecha_inicio_tramite' => $fresco->fecha_inicio_tramite ?? now(),
                'observacion' => trim(($fresco->observacion ? $fresco->observacion.' | ' : '').$observacion),
            ]);
            RadicadoMovimiento::create([
                'radicado_id' => $fresco->id, 'contrato_id' => $fresco->contrato_id, 'tipo_proceso' => 'afiliacion',
                'entidad' => 'caja', 'user_id' => $usuarioId, 'estado_anterior' => $anterior,
                'estado_nuevo' => Radicado::ESTADO_TRAMITE, 'observacion' => $observacion,
            ]);
        });

        return $this->fila($r, 'tramite', $observacion);
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
            'radicado_id' => $r->id,
            'contrato_id' => $c->id,
            'cedula' => (string) $c->cedula,
            'nombre' => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa' => $c->razonSocial?->razon_social.($this->conAliado ? ' · '.$this->nombreAliado($c) : ''),
            'estado_antes' => $r->estado,
            'accion' => $accion,
            'mensaje' => $mensaje,
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
