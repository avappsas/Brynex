<?php

namespace App\Services\NuevaEps;

use App\Models\Tarea;
use App\Services\TareaAutomaticaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * La mora que Nueva EPS le cobra a una empresa, convertida en tareas.
 *
 * La mora no dice qué hacer: dice que falta plata. Lo que hay que hacer sale de
 * cruzarla con lo que BryNex ya sabe, y son cosas opuestas —a veces hay que
 * pagar y a veces hay que dejar de pagar—:
 *
 *  - Si la planilla de ese mes está pagada, la EPS cobra algo que ya recibió y
 *    basta con mandarle el soporte.
 *  - Si no hay planilla porque la persona está retirada, el retiro no le llegó
 *    a la EPS y cada mes que pase es plata que se cobra de más.
 *  - Si no hay planilla y sigue vigente, entonces sí: falta pagar.
 *
 * Por eso la tarea es una por trabajador y no una por mes: el trámite es el
 * mismo para todos sus meses en mora y se resuelve de una vez.
 */
class NuevaEpsMoraService
{
    public const ENTIDAD = 'NUEVA EPS';

    private const PREFIJO = 'nuevaeps:mora';

    public function __construct(private TareaAutomaticaService $tareas) {}

    /**
     * @return array{ok:bool, error?:string, nit?:string, corte?:string, nuevas?:int, cerradas?:int, detalle?:array}
     */
    public function revisar(string $nit, bool $simular = false, ?string $fechaCorte = null): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $lectura = NuevaEpsPortalService::mora($nit, $fechaCorte);

        if (! ($lectura['ok'] ?? false)) {
            return ['ok' => false, 'error' => $lectura['error'] ?? 'El portal no respondió.', 'nit' => $nit];
        }

        $detalle = [];
        $nuevas = 0;
        $vistas = [];

        foreach ($lectura['trabajadores'] ?? [] as $fila) {
            $caso = $this->analizar($nit, $fila);
            $fin = count($detalle);
            $detalle[] = $caso;

            if (! $caso['aliado_id']) {
                $detalle[$fin]['accion'] = 'sin_aliado';
                continue;
            }

            $llave = self::PREFIJO.":{$nit}:".$caso['documento'];
            $vistas[$llave] = true;

            if ($ya = $this->tareas->activaPorLlave($caso['aliado_id'], $llave)) {
                $detalle[$fin]['accion'] = 'ya_existe';
                $detalle[$fin]['tarea_id'] = $ya->id;

                // La mora crece: si aparecieron meses nuevos, la tarea abierta
                // se entera en vez de quedarse con la foto del primer día.
                if (! $simular && $this->cambio($ya, $caso)) {
                    $this->tareas->anotar($ya, '🤖 '.$caso['observacion'], 'nota');
                    $detalle[$fin]['accion'] = 'anotada';
                }

                continue;
            }

            if ($simular) {
                $nuevas++;
                $detalle[$fin]['accion'] = 'abriria';

                continue;
            }

            $tarea = $this->tareas->abrir([
                'aliado_id' => $caso['aliado_id'],
                'tipo' => 'mora_eps',
                'cedula' => $caso['documento'],
                'contrato_id' => $caso['contrato_id'],
                'razon_social_id' => $caso['razon_social_id'],
                'entidad' => self::ENTIDAD,
                'tarea' => $caso['tarea'],
                'observacion' => $caso['observacion'],
                'llave_auto' => $llave,
            ]);

            if ($tarea) {
                $nuevas++;
                $detalle[$fin]['accion'] = 'abierta';
                $detalle[$fin]['tarea_id'] = $tarea->id;
            }
        }

        return [
            'ok' => true,
            'nit' => $nit,
            'corte' => $lectura['fecha_corte'] ?? null,
            'nuevas' => $nuevas,
            'cerradas' => $this->cerrarResueltas($nit, array_keys($vistas), $simular, $detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * Qué le pasa a este trabajador, según lo que BryNex tenga de él.
     */
    private function analizar(string $nit, array $fila): array
    {
        $documento = ltrim(preg_replace('/\D/', '', (string) ($fila['documento'] ?? '')), '0');
        $periodos = collect($fila['periodos'] ?? []);
        $total = (int) ($fila['total_mora'] ?? $periodos->sum('valor'));
        $meses = $periodos->map(fn ($p) => self::mesEnLetras($p['periodo']))->implode(', ');
        $plata = '$'.number_format($total, 0, ',', '.');

        $contrato = $this->contrato($nit, $documento);
        $planos = $this->planos($documento, $periodos->pluck('periodo')->all());
        $aqui = $planos->firstWhere('nit', $nit);
        $fuera = $planos->first(fn ($p) => $p->nit !== $nit);

        $base = [
            'documento' => $documento,
            'nombre' => $fila['nombre'] ?? null,
            'periodos' => $periodos->pluck('periodo')->all(),
            'total' => $total,
            'aliado_id' => $contrato?->aliado_id ?: $this->aliadoDe($nit),
            'contrato_id' => $contrato?->id,
            'razon_social_id' => $contrato?->razon_social_id ?: $this->razonSocialDe($nit),
        ];

        // Hay planilla de esos meses en esta empresa: la EPS cobra algo que ya
        // se pagó, o que se generó y hay que confirmar.
        if ($aqui) {
            $pago = $aqui->numero_planilla
                ? DB::table('planillas_pago_operador')->where('numero_planilla', $aqui->numero_planilla)->first(['fecha_pago'])
                : null;

            return $base + ($pago
                ? [
                    'causa' => 'planilla_pagada',
                    'tarea' => "Enviar a Nueva EPS el soporte de pago: cobra mora de {$meses} ({$plata}) y esa planilla ya está pagada.",
                    'observacion' => "Nueva EPS reporta mora de {$meses} por {$plata}. En BryNex la planilla {$aqui->numero_planilla} se pagó el "
                        .Carbon::parse($pago->fecha_pago)->format('d/m/Y').'. Enviar el soporte para que retiren el cobro.',
                ]
                : [
                    'causa' => 'planilla_sin_pago',
                    'tarea' => "Confirmar el pago de la planilla {$aqui->numero_planilla}: Nueva EPS cobra mora de {$meses} ({$plata}).",
                    'observacion' => "Nueva EPS reporta mora de {$meses} por {$plata}. La planilla {$aqui->numero_planilla} está en BryNex pero sin pago registrado: "
                        .'confirmar si se pagó y enviar el soporte, o pagarla.',
                ]);
        }

        // La planilla salió por otra empresa: no es mora, es una afiliación que
        // quedó en la razón social equivocada.
        if ($fuera) {
            return $base + [
                'causa' => 'otra_empresa',
                'tarea' => "Revisar la afiliación en Nueva EPS: cobra mora de {$meses} ({$plata}) aquí, pero cotizó por {$fuera->razon_social}.",
                'observacion' => "Nueva EPS reporta mora de {$meses} por {$plata} en el NIT {$nit}, pero las planillas de esos meses salieron por "
                    ."{$fuera->razon_social} (NIT {$fuera->nit}). Revisar en cuál empresa debe estar afiliado.",
            ];
        }

        if (! $contrato) {
            return $base + [
                'causa' => 'sin_contrato',
                'tarea' => "Revisar la afiliación en Nueva EPS: cobra mora de {$meses} ({$plata}) de alguien sin contrato en esta empresa.",
                'observacion' => "Nueva EPS reporta mora de {$meses} por {$plata} en el NIT {$nit}, pero en BryNex esta persona no tiene contrato ahí. "
                    .'Revisar si la afiliación quedó mal o si el retiro nunca se reportó.',
            ];
        }

        // Retirado en BryNex y la EPS sin enterarse: cada mes suma plata que no
        // se debe.
        if ($contrato->fecha_retiro) {
            $retiro = Carbon::parse($contrato->fecha_retiro);

            return $base + [
                'causa' => 'retiro_no_reportado',
                'tarea' => "Reportar a Nueva EPS el retiro del ".$retiro->format('d/m/Y').": cobra mora de {$meses} ({$plata}) de alguien ya retirado.",
                'observacion' => "Nueva EPS reporta mora de {$meses} por {$plata}. En BryNex el contrato está retirado desde el "
                    .$retiro->format('d/m/Y').' y la EPS lo tiene activo'
                    .($fila['fecha_retiro'] ? ' (su fecha de retiro: '.$fila['fecha_retiro'].')' : ' (sin fecha de retiro)')
                    .'. Reportar el retiro para que anulen el cobro.',
            ];
        }

        return $base + [
            'causa' => 'falta_pagar',
            'tarea' => "Revisar y pagar el aporte: Nueva EPS cobra mora de {$meses} ({$plata}) y no hay planilla en BryNex.",
            'observacion' => "Nueva EPS reporta mora de {$meses} por {$plata}. El contrato sigue vigente y no hay planilla de esos meses en BryNex: "
                .'revisar por qué no se liquidó y pagar.',
        ];
    }

    /** El contrato más reciente de esa cédula en esa razón social. */
    private function contrato(string $nit, string $documento)
    {
        return DB::table('contratos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('rs.nit', $nit)
            ->where('c.cedula', $documento)
            ->orderByDesc('c.id')
            ->first(['c.id', 'c.aliado_id', 'c.razon_social_id', 'c.fecha_retiro', 'c.estado']);
    }

    /** Los planos de esa cédula en esos períodos, venga de la empresa que venga. */
    private function planos(string $documento, array $periodos)
    {
        if (! $periodos) {
            return collect();
        }

        $consulta = DB::table('planos as p')
            ->leftJoin('razones_sociales as rs', 'rs.id', '=', 'p.razon_social_id')
            ->where('p.no_identifi', $documento)
            ->whereNull('p.deleted_at')
            ->where(function ($q) use ($periodos) {
                foreach ($periodos as $periodo) {
                    [$anio, $mes] = explode('-', $periodo);
                    $q->orWhere(fn ($w) => $w->where('p.anio_plano', (int) $anio)->where('p.mes_plano', (int) $mes));
                }
            });

        return collect($consulta->get(['p.id', 'p.numero_planilla', 'p.mes_plano', 'p.anio_plano', 'rs.nit', 'rs.razon_social']));
    }

    /** Un aliado que tenga esa razón social, para cuando no hay contrato. */
    private function aliadoDe(string $nit): ?int
    {
        return DB::table('razones_sociales')->where('nit', $nit)->orderBy('id')->value('aliado_id');
    }

    private function razonSocialDe(string $nit): ?int
    {
        return DB::table('razones_sociales')->where('nit', $nit)->orderBy('id')->value('id');
    }

    /** ¿La tarea abierta ya decía esto, o el cobro cambió? */
    private function cambio(Tarea $tarea, array $caso): bool
    {
        return trim((string) $tarea->observacion) !== trim((string) $caso['observacion']);
    }

    /**
     * Cierra las tareas de quien ya no aparece en el reporte.
     *
     * Solo se cierran las de esta empresa: el reporte es de una sola, y la
     * ausencia en otra no prueba nada.
     */
    private function cerrarResueltas(string $nit, array $vistas, bool $simular, array &$detalle): int
    {
        $abiertas = Tarea::whereNotNull('llave_auto')
            ->where('llave_auto', 'like', self::PREFIJO.":{$nit}:%")
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
            ->get();

        $cerradas = 0;

        foreach ($abiertas as $tarea) {
            if (in_array($tarea->llave_auto, $vistas, true)) {
                continue;
            }

            if ($simular) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerraria', 'tarea_id' => $tarea->id];

                continue;
            }

            if ($this->tareas->cerrar($tarea, 'Nueva EPS ya no lo reporta en mora el '.now()->format('d/m/Y').'.')) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerrada', 'tarea_id' => $tarea->id];
            }
        }

        return $cerradas;
    }

    /** '2026-08' → 'agosto de 2026', que es como se lee una tarea. */
    private static function mesEnLetras(string $periodo): string
    {
        [$anio, $mes] = array_pad(explode('-', $periodo), 2, '1');

        return Carbon::createFromDate((int) $anio, (int) $mes, 1)->locale('es')->isoFormat('MMMM [de] YYYY');
    }
}
