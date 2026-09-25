<?php

namespace App\Services\SaludTotal;

use App\Models\Tarea;
use App\Services\EpsPortal\CruceAportes;
use App\Services\EpsPortal\EpsClavePortal;
use App\Services\TareaAutomaticaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Los reportes de cartera de Salud Total, convertidos en tareas.
 *
 * A diferencia de Nueva EPS, aquí la EPS ya viene clasificando: dice si el
 * problema es que no se pagó, que se pagó por alguien a quien ya había
 * excluido, o que se pagó por quien nunca estuvo afiliado. Los dos últimos son
 * plata de la empresa, no deuda, y se reclaman por *Cartera → Reporte
 * novedades*, que tiene una causal para cada caso.
 *
 * Se miran varios meses hacia atrás y nunca el mes en curso: un aporte del mes
 * que corre todavía está a tiempo de pagarse.
 */
class SaludTotalCarteraService
{
    public const ENTIDAD = 'SALUD TOTAL';

    private const PREFIJO = 'saludtotal:cartera';

    /** Qué significa cada reporte y cómo se trabaja. */
    private const REPORTES = [
        'sin_pago' => [
            'tipo' => 'mora_eps',
            'titulo' => 'no tiene el aporte pagado',
        ],
        'desafiliados_con_pago' => [
            'tipo' => 'devolucion_aportes',
            'titulo' => 'recibió aportes de alguien que ya no estaba afiliado',
        ],
        'pago_sin_afiliacion' => [
            'tipo' => 'devolucion_aportes',
            'titulo' => 'recibió aportes de alguien sin afiliación',
        ],
    ];

    public function __construct(private TareaAutomaticaService $tareas) {}

    /**
     * @param  int  $meses  cuántos meses hacia atrás, sin contar el actual
     * @return array{ok:bool, error?:string, nit?:string, nuevas?:int, cerradas?:int, detalle?:array}
     */
    public function revisar(string $nit, bool $simular = false, int $meses = 4): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $cred = EpsClavePortal::para(SaludTotalCliente::ENTIDAD, '%SALUD%TOTAL%', 'Salud Total', $nit, 'EPS');

        if (isset($cred['error'])) {
            return ['ok' => false, 'error' => $cred['error'], 'nit' => $nit];
        }

        try {
            $portal = SaludTotalCliente::entrar($nit, $cred['usuario'], $cred['contrasena']);
        } catch (SaludTotalLoginException $e) {
            EpsClavePortal::rechazada($cred['empresa'], $cred['usuario'], $cred['contrasena'], $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage(), 'nit' => $nit];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200), 'nit' => $nit];
        }

        EpsClavePortal::exito($cred['empresa']);

        // Una fila por persona y reporte, con todos los meses en los que salió:
        // el trámite es el mismo para todos sus meses.
        $casos = [];

        foreach ($this->periodos($meses) as $periodo) {
            try {
                $reportes = $portal->cartera(self::periodoPortal($periodo));
            } catch (\Throwable $e) {
                Log::warning('Salud Total: falló un período de cartera', ['nit' => $nit, 'periodo' => $periodo, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($reportes as $reporte => $r) {
                foreach ($r['filas'] ?? [] as $fila) {
                    $documento = ltrim(preg_replace('/\D/', '', (string) ($fila['Cotizante'] ?? '')), '0');

                    if (! $documento) {
                        continue;
                    }

                    $clave = "{$reporte}:{$documento}";
                    $casos[$clave] ??= [
                        'reporte' => $reporte,
                        'documento' => $documento,
                        'nombre' => trim(($fila['Nombre'] ?? '').' '.($fila['Apellido'] ?? '')),
                        'estado_eps' => $fila['Descripción estado afiliado'] ?? null,
                        'periodos' => [],
                        'valor' => 0,
                        'planillas' => [],
                    ];

                    $casos[$clave]['periodos'][] = $periodo;
                    $casos[$clave]['valor'] += (int) preg_replace('/\D/', '', (string) ($fila['Valor Cotización'] ?? '0'));

                    if ($planilla = trim((string) ($fila['No Planilla'] ?? ''))) {
                        $casos[$clave]['planillas'][$planilla] = $planilla;
                    }
                }
            }
        }

        $detalle = [];
        $nuevas = 0;
        $vistas = [];

        foreach ($casos as $caso) {
            $analisis = $this->analizar($nit, $caso);
            $fin = count($detalle);
            $detalle[] = $analisis;

            if (! $analisis['aliado_id']) {
                $detalle[$fin]['accion'] = 'sin_aliado';

                continue;
            }

            $llave = self::PREFIJO.":{$nit}:{$caso['reporte']}:{$caso['documento']}";
            $vistas[] = $llave;

            if ($ya = $this->tareas->activaPorLlave($analisis['aliado_id'], $llave)) {
                $detalle[$fin]['accion'] = 'ya_existe';
                $detalle[$fin]['tarea_id'] = $ya->id;

                if (! $simular && trim((string) $ya->observacion) !== trim($analisis['observacion'])) {
                    $this->tareas->anotar($ya, '🤖 '.$analisis['observacion'], 'nota');
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
                'aliado_id' => $analisis['aliado_id'],
                'tipo' => self::REPORTES[$caso['reporte']]['tipo'],
                'cedula' => $caso['documento'],
                'contrato_id' => $analisis['contrato_id'],
                'razon_social_id' => $analisis['razon_social_id'],
                'entidad' => self::ENTIDAD,
                'tarea' => $analisis['tarea'],
                'observacion' => $analisis['observacion'],
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
            'nuevas' => $nuevas,
            'cerradas' => $this->cerrarResueltas($nit, $vistas, $simular, $detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * Qué hay que hacer con este caso, según lo que BryNex sepa del trabajador.
     */
    private function analizar(string $nit, array $caso): array
    {
        $meses = collect($caso['periodos'])->map(fn ($p) => CruceAportes::mesEnLetras($p))->implode(', ');
        $plata = CruceAportes::plata($caso['valor']);
        $contrato = CruceAportes::contratoDe($nit, $caso['documento']);
        $planillas = $caso['planillas'] ? ' Planilla(s): '.implode(', ', $caso['planillas']).'.' : '';
        $estado = $caso['estado_eps'] ? " La EPS lo reporta como «{$caso['estado_eps']}»." : '';

        $base = [
            'reporte' => $caso['reporte'],
            'documento' => $caso['documento'],
            'nombre' => $caso['nombre'],
            'periodos' => $caso['periodos'],
            'valor' => $caso['valor'],
            'aliado_id' => $contrato?->aliado_id ?: CruceAportes::aliadoDe($nit),
            'contrato_id' => $contrato?->id,
            'razon_social_id' => $contrato?->razon_social_id ?: CruceAportes::razonSocialDe($nit),
        ];

        // Se pagó por alguien que la EPS ya no tenía: eso no se paga, se reclama.
        if ($caso['reporte'] !== 'sin_pago') {
            $causal = str_contains(mb_strtolower((string) $caso['estado_eps']), 'traslado')
                ? 'Pago realizado con novedad de retiro a otra EPS'
                : 'Cierre de contrato - Retiro no reportado y/o retiro extemporáneo';

            return $base + [
                'causa' => $caso['reporte'],
                'tarea' => "Reclamar a Salud Total {$plata} pagados de más ({$meses}): cobró por alguien que ya no tenía afiliado.",
                'observacion' => 'Salud Total '.self::REPORTES[$caso['reporte']]['titulo']." en {$meses}, por {$plata}.{$estado}{$planillas} "
                    ."Reclamar por Cartera → Reporte novedades, causal «{$causal}».",
            ];
        }

        // Mora: lo mismo que en Nueva EPS, la planilla manda.
        $planos = CruceAportes::planosDe($caso['documento'], $caso['periodos']);
        $aqui = $planos->firstWhere('nit', $nit);
        $fuera = $planos->first(fn ($p) => $p->nit !== $nit);

        if ($aqui) {
            $pago = CruceAportes::pagoDe($aqui->numero_planilla);

            return $base + ($pago
                ? [
                    'causa' => 'planilla_pagada',
                    'tarea' => "Enviar a Salud Total el soporte de pago: cobra mora de {$meses} ({$plata}) y esa planilla ya está pagada.",
                    'observacion' => "Salud Total reporta sin pago {$meses}, por {$plata}. En BryNex la planilla {$aqui->numero_planilla} se pagó el {$pago}. "
                        .'Enviar el soporte para que retiren el cobro.',
                ]
                : [
                    'causa' => 'planilla_sin_pago',
                    'tarea' => "Confirmar el pago de la planilla {$aqui->numero_planilla}: Salud Total reporta mora de {$meses} ({$plata}).",
                    'observacion' => "Salud Total reporta sin pago {$meses}, por {$plata}. La planilla {$aqui->numero_planilla} está en BryNex pero sin pago "
                        .'registrado: confirmar si se pagó y enviar el soporte, o pagarla.',
                ]);
        }

        if ($fuera) {
            return $base + [
                'causa' => 'otra_empresa',
                'tarea' => "Revisar la afiliación en Salud Total: reporta mora de {$meses} ({$plata}) aquí, pero cotizó por {$fuera->razon_social}.",
                'observacion' => "Salud Total reporta sin pago {$meses}, por {$plata}, en el NIT {$nit}, pero las planillas de esos meses salieron por "
                    ."{$fuera->razon_social} (NIT {$fuera->nit}). Revisar en cuál empresa debe estar afiliado.",
            ];
        }

        if (! $contrato) {
            return $base + [
                'causa' => 'sin_contrato',
                'tarea' => "Revisar la afiliación en Salud Total: reporta mora de {$meses} ({$plata}) de alguien sin contrato en esta empresa.",
                'observacion' => "Salud Total reporta sin pago {$meses}, por {$plata}, en el NIT {$nit}, pero en BryNex esta persona no tiene contrato ahí.",
            ];
        }

        if ($contrato->fecha_retiro) {
            $retiro = Carbon::parse($contrato->fecha_retiro)->format('d/m/Y');

            return $base + [
                'causa' => 'retiro_no_reportado',
                'tarea' => "Reportar a Salud Total el retiro del {$retiro}: cobra mora de {$meses} ({$plata}) de alguien ya retirado.",
                'observacion' => "Salud Total reporta sin pago {$meses}, por {$plata}. En BryNex el contrato está retirado desde el {$retiro}. "
                    .'Reportar por Cartera → Reporte novedades, causal «Cierre de contrato - Retiro no reportado y/o retiro extemporáneo».',
            ];
        }

        return $base + [
            'causa' => 'falta_pagar',
            'tarea' => "Revisar y pagar el aporte: Salud Total reporta mora de {$meses} ({$plata}) y no hay planilla en BryNex.",
            'observacion' => "Salud Total reporta sin pago {$meses}, por {$plata}. El contrato sigue vigente y no hay planilla de esos meses en BryNex.",
        ];
    }

    /** Los meses a mirar, del anterior hacia atrás. */
    private function periodos(int $meses): array
    {
        $salida = [];

        for ($i = 1; $i <= max(1, $meses); $i++) {
            $salida[] = now()->startOfMonth()->subMonths($i)->format('Y-m');
        }

        return $salida;
    }

    /** '2026-07' → '7/2026', como lo pide el portal. */
    private static function periodoPortal(string $periodo): string
    {
        [$anio, $mes] = explode('-', $periodo);

        return ((int) $mes).'/'.$anio;
    }

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

            if ($this->tareas->cerrar($tarea, 'Salud Total ya no lo reporta en sus informes de cartera el '.now()->format('d/m/Y').'.')) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerrada', 'tarea_id' => $tarea->id];
            }
        }

        return $cerradas;
    }
}
