<?php

namespace App\Services\Sanitas;

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
 * Conciliación de radicados de EPS con el "Estado de Afiliación" de la Oficina
 * Virtual de Empleadores de Sanitas.
 *
 * Sanitas está detrás de Radware y pide captcha y código al correo, así que el
 * archivo lo baja la extensión BryNex Portales con la sesión de la persona y lo
 * manda aquí. Es un Txt con `;` y una fila por periodo de cada afiliado de la
 * empresa (Tipo identificación; Numero identificación; Nombre completo; UAP
 * básica asignada; Fecha inicio vigencia; Fecha retiro; Edad; Género; Estado de
 * servicios; Municipio; Departamento).
 *
 * Igual que el cruce de EPS SURA: los radicados sin confirmar de contratos
 * vigentes con Sanitas de esa empresa pasan a OK confirmado si la persona está
 * HABILITADA y el apellido coincide. Además lista a los habilitados de la empresa
 * que BryNex no tiene como contrato vigente con Sanitas.
 *
 * Un mismo NIT puede ser razón social en varios aliados (ELITES está en Brygar,
 * Grupo Fecop, Luis Lopez y BryNex). El usuario BryNex concilia todos a la vez;
 * el del aliado, solo el suyo.
 */
class SanitasConciliacionService
{
    public const CODIGO_EPS = 'EPS005';

    public const CONFIRMADOR = 'eps_sanitas';

    /** Si la corrida abarca varios aliados y cada fila debe decir de cuál es. */
    private bool $conAliado = false;

    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR, Radicado::ESTADO_OK];

    /**
     * @return Collection<string, array{tipo:string, numero:string, nombre:string, inicio:?string, retiro:?string, estado:string, municipio:string}> por documento, la fila que manda
     */
    public function leer(string $txt): Collection
    {
        $txt = ltrim($txt, "\xEF\xBB\xBF");
        $lineas = preg_split('/\r?\n/', trim($txt));
        $encabezado = array_map(fn ($c) => Str::lower(Str::ascii(trim($c))), explode(';', array_shift($lineas) ?? ''));

        if (! in_array('numero identificacion', $encabezado, true) || ! in_array('estado de servicios', $encabezado, true)) {
            throw new RuntimeException('El archivo no es el Estado de Afiliación de Sanitas (faltan columnas).');
        }
        $col = array_flip($encabezado);

        return collect($lineas)
            ->filter(fn ($l) => trim($l) !== '')
            ->map(function ($l) use ($col) {
                $c = explode(';', $l);

                return [
                    'tipo'      => trim($c[$col['tipo identificacion']] ?? ''),
                    'numero'    => ltrim(preg_replace('/\D/', '', $c[$col['numero identificacion']] ?? ''), '0'),
                    'nombre'    => trim($c[$col['nombre completo']] ?? ''),
                    'inicio'    => trim($c[$col['fecha inicio vigencia']] ?? '') ?: null,
                    'retiro'    => trim($c[$col['fecha retiro']] ?? '') ?: null,
                    'estado'    => Str::upper(trim($c[$col['estado de servicios']] ?? '')),
                    'municipio' => trim($c[$col['municipio']] ?? ''),
                ];
            })
            ->filter(fn ($a) => $a['numero'] !== '')
            ->groupBy('numero')
            // Una persona sale una vez por periodo: manda la habilitada; si no hay, la más reciente.
            ->map(fn ($filas) => $filas->first(fn ($a) => $a['estado'] === 'HABILITADO')
                ?? $filas->sortByDesc(fn ($a) => $this->fecha($a['inicio'])?->timestamp ?? 0)->first());
    }

    /** Aliados donde el NIT es razón social (lo que ve un usuario BryNex). */
    public function aliadosDelNit(string $nit): array
    {
        return RazonSocial::where('nit', preg_replace('/\D/', '', $nit))->distinct()->pluck('aliado_id')
            ->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /**
     * @param  int[]  $aliadoIds  aliados a conciliar: el activo, o todos los del NIT para BryNex
     * @return array{empresa:string, aliados:array, total:int, cerrados:int, faltan:int, revisar:int, errores:int, sobran:array, simulado:bool, detalle:array}
     */
    public function conciliar(array $aliadoIds, string $nit, string $txt, bool $simular, ?int $usuarioId): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $aliadoIds = array_values(array_unique(array_map('intval', $aliadoIds)));
        $razones = RazonSocial::whereIn('aliado_id', $aliadoIds)->where('nit', $nit)->get();
        if ($razones->isEmpty()) {
            throw new RuntimeException("El NIT {$nit} de la sesión de Sanitas no es una razón social de este aliado.");
        }
        // Solo los aliados que de verdad tienen la empresa; con más de uno se dice de cuál es cada fila.
        $aliadoIds = $razones->pluck('aliado_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->conAliado = count($aliadoIds) > 1;

        $enSanitas = $this->leer($txt);
        $detalle = [];

        foreach ($this->candidatos($aliadoIds, $nit) as $r) {
            $detalle[] = $this->cruzar($r, $enSanitas->get(ltrim((string) $r->contrato->cedula, '0')), $simular, $usuarioId);
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
            'sobran'   => $this->sobrantes($aliadoIds, $nit, $enSanitas),
            'afiliados_sanitas' => $enSanitas->count(),
            'habilitados_sanitas' => $enSanitas->where('estado', 'HABILITADO')->count(),
            'simulado' => $simular,
            // Los ya confirmados antes no se muestran: solo lo que cambia o hay que mirar.
            'detalle'  => array_values(array_filter($detalle, fn ($f) => $f['accion'] !== 'ya_ok')),
        ];
    }

    /** Radicados de EPS sin confirmar de contratos vigentes con Sanitas (la del contrato o, si no tiene, la del cliente). */
    private function candidatos(array $aliadoIds, string $nit): Collection
    {
        return Radicado::query()
            ->whereIn('radicados.aliado_id', $aliadoIds)
            ->where('radicados.tipo', Radicado::TIPO_EPS)
            ->whereIn('radicados.estado', self::ESTADOS)
            ->whereNull('radicados.confirmado_por')
            ->whereHas('contrato', fn ($c) => $c
                ->whereColumn('contratos.aliado_id', 'radicados.aliado_id')
                ->where('estado', 'vigente')
                ->whereDate('fecha_ingreso', '<=', today())
                ->whereHas('plan', fn ($p) => $p->where('incluye_eps', true))
                ->where(fn ($e) => $e
                    ->whereHas('eps', fn ($x) => $x->where('codigo', self::CODIGO_EPS))
                    ->orWhere(fn ($sin) => $sin->whereNull('eps_id')->whereHas('cliente.eps', fn ($x) => $x->where('codigo', self::CODIGO_EPS))))
                ->whereHas('razonSocial', fn ($rs) => $rs->where('es_independiente', false)->where('nit', $nit)))
            ->with(['contrato.cliente', 'contrato.razonSocial', 'contrato.aliado'])
            ->orderBy('radicados.id')
            ->get();
    }

    private function cruzar(Radicado $r, ?array $a, bool $simular, ?int $usuarioId): array
    {
        if (! $a) {
            return $r->estado === Radicado::ESTADO_OK
                ? $this->fila($r, 'revisar', 'Está en OK en BryNex pero no aparece en el Estado de Afiliación de Sanitas de la empresa.')
                : $this->fila($r, 'falta', 'No aparece en el Estado de Afiliación de Sanitas: falta radicar la novedad (cambio de empleador).');
        }

        if ($a['estado'] !== 'HABILITADO') {
            return $this->fila($r, 'revisar', "En Sanitas figura {$a['estado']}".($a['retiro'] ? " con retiro {$a['retiro']}" : '').'.');
        }

        // Protección contra cruzar la cédula con otra persona.
        $apellido = $this->texto((string) $r->contrato->cliente?->primer_apellido);
        if ($apellido === '' || ! str_contains($this->texto($a['nombre']), $apellido)) {
            return $this->fila($r, 'revisar', "En Sanitas figura como {$a['nombre']}, que no coincide con el apellido de BryNex.");
        }

        $observacion = sprintf('Confirmado en Sanitas el %s: HABILITADO con %s desde %s (Estado de Afiliación de la Oficina Virtual).',
            now()->format('d/m/Y'), $r->contrato->razonSocial->razon_social, $a['inicio'] ?? '—');

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
                'entidad' => Radicado::TIPO_EPS, 'user_id' => $usuarioId, 'estado_anterior' => $anterior,
                'estado_nuevo' => Radicado::ESTADO_OK, 'observacion' => $observacion,
            ]);

            return $anterior;
        });

        if ($hecho === false) {
            return $this->fila($r, 'revisar', 'El radicado cambió mientras se cruzaba; no se tocó.');
        }

        // Los que ya estaban en OK a mano solo quedan confirmados: no hace falta listarlos.
        return $this->fila($r, $hecho === Radicado::ESTADO_OK ? 'ya_ok' : 'cerrado', $observacion);
    }

    /**
     * Habilitados de la empresa en Sanitas que BryNex no tiene como contrato vigente
     * con Sanitas en esa razón social (retirados que siguen activos, otra EPS en
     * BryNex, o personas que no están).
     */
    private function sobrantes(array $aliadoIds, string $nit, Collection $enSanitas): array
    {
        $habilitados = $enSanitas->where('estado', 'HABILITADO');
        if ($habilitados->isEmpty()) {
            return [];
        }

        $contratos = Contrato::with(['eps', 'cliente.eps', 'razonSocial', 'aliado'])
            ->whereIn('aliado_id', $aliadoIds)
            ->whereIn('cedula', $habilitados->keys()->all())
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($c) => ltrim((string) $c->cedula, '0'));

        $salida = [];
        foreach ($habilitados as $doc => $a) {
            $suyos = $contratos->get($doc, collect());
            $vigenteSanitas = $suyos->first(fn ($c) => $c->estado === 'vigente'
                && preg_replace('/\D/', '', (string) $c->razonSocial?->nit) === $nit
                && (($c->eps ?: $c->cliente?->eps)?->codigo === self::CODIGO_EPS));
            if ($vigenteSanitas) {
                continue;
            }

            $vigente = $suyos->first(fn ($c) => $c->estado === 'vigente');
            $referencia = $vigente ?: $suyos->first();
            $donde = $this->conAliado && $referencia ? ' ('.$this->nombreAliado($referencia).')' : '';
            $motivo = match (true) {
                $suyos->isEmpty() => $this->conAliado ? 'No tiene contratos en ningún aliado de BryNex.' : 'No tiene contratos en BryNex.',
                (bool) $vigente   => 'Contrato vigente en BryNex con '.(($vigente->eps ?: $vigente->cliente?->eps)?->nombre ?? 'otra EPS').' en '.($vigente->razonSocial?->razon_social ?? '—').$donde.'.',
                default           => 'Retirado en BryNex'.$donde.' (último contrato '.$suyos->first()->estado.') pero sigue habilitado en Sanitas.',
            };
            $salida[] = [
                'cedula' => (string) $doc, 'nombre' => $a['nombre'], 'desde' => $a['inicio'], 'motivo' => $motivo,
                'aliado' => $referencia ? $this->nombreAliado($referencia) : null,
            ];
        }

        return $salida;
    }

    private function nombreAliado(Contrato $c): string
    {
        return $c->aliado?->nombre ?? 'Aliado '.$c->aliado_id;
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
            'aliado'       => $this->nombreAliado($c),
            'estado_antes' => $r->estado,
            'accion'       => $accion,
            'mensaje'      => $mensaje,
        ];
    }

    private function fecha(?string $d): ?\Illuminate\Support\Carbon
    {
        try {
            return $d ? \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $d)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function texto(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii($s))));
    }
}
