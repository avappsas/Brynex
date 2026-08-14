<?php

namespace App\Console\Commands;

use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\PrestamoMovimiento;
use App\Services\Finanzas\PrestamoLiquidacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruye el historial de un préstamo re-aplicando sus desembolsos y abonos
 * con la liquidación de intereses corregida.
 *
 * Existe porque hasta ago-2026 un abono a mitad de ciclo borraba hacia atrás el interés
 * de los días ya corridos: el capital bajaba el día del abono, pero el interés sólo se
 * causaba al cumplir el mes calendario completo.
 *
 * Sólo actúa sobre préstamos cuyo historial son puros desembolsos y abonos. Si el préstamo
 * tiene cortes de interés ya registrados (que pudieron ajustarse a mano, con meses excluidos),
 * se salta: reconstruirlo perdería esos ajustes.
 */
class RecalcularAbonosPrestamos extends Command
{
    protected $signature = 'finanzas:recalcular-abonos
                            {ids* : IDs de los préstamos a reconstruir}
                            {--apply : Persiste los cambios. Sin esta bandera sólo simula y revierte}';

    protected $description = 'Reconstruye préstamos re-aplicando sus abonos con el interés proporcional corregido';

    public function handle(PrestamoLiquidacionService $liquidacion): int
    {
        $ids = $this->argument('ids');
        $aplicar = (bool) $this->option('apply');

        if (! $aplicar) {
            $this->warn('Modo simulación: se calcula el resultado y se revierte. Use --apply para persistir.');
        }

        foreach ($ids as $id) {
            $this->newLine();
            $this->line("═══ Préstamo {$id} ═══");

            $prestamo = Prestamo::find($id);

            if (! $prestamo) {
                $this->error('  No existe.');

                continue;
            }

            $movimientos = $prestamo->movimientos()->orderBy('fecha')->orderBy('id')->get();

            $cortes = $movimientos->whereIn('tipo', ['interes_mensual', 'interes_proporcional', 'capitalizacion']);
            if ($cortes->isNotEmpty()) {
                $this->error("  {$prestamo->nombre_deudor}: tiene {$cortes->count()} movimiento(s) de interés ya registrados. Se omite para no perder ajustes manuales.");

                continue;
            }

            $this->line("  Deudor: {$prestamo->nombre_deudor} · tasa {$prestamo->tasa_interes_mensual}% · desembolso {$prestamo->fecha_desembolso}");
            $this->line('  ANTES:   capital $'.number_format($prestamo->monto_original, 2).' · saldo $'.number_format($prestamo->saldo_actual, 2)." · estado {$prestamo->estado}");

            $conn = DB::connection('finanzas');
            $conn->beginTransaction();

            try {
                $this->reconstruir($prestamo, $movimientos, $liquidacion);

                $prestamo->refresh();
                $this->info('  DESPUÉS: capital $'.number_format($prestamo->monto_original, 2).' · saldo $'.number_format($prestamo->saldo_actual, 2)." · estado {$prestamo->estado}");

                $this->newLine();
                $this->line('  Historial reconstruido:');
                foreach ($prestamo->movimientos()->orderBy('fecha')->orderBy('id')->get() as $m) {
                    $this->line(sprintf(
                        '    %s  %-21s $%14s   saldo %s → %s',
                        $m->fecha,
                        $m->tipo,
                        number_format($m->monto, 2),
                        number_format($m->saldo_antes, 2),
                        number_format($m->saldo_despues, 2)
                    ));
                }

                if ($aplicar) {
                    $conn->commit();
                    $this->info('  ✔ Aplicado.');
                } else {
                    $conn->rollBack();
                    $this->comment('  ↩ Revertido (simulación).');
                }
            } catch (\Throwable $e) {
                $conn->rollBack();
                $this->error('  Falló, se revirtió: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    /**
     * Borra el historial y lo vuelve a construir movimiento por movimiento en orden cronológico.
     */
    private function reconstruir(Prestamo $prestamo, $movimientos, PrestamoLiquidacionService $liquidacion): void
    {
        PrestamoMovimiento::where('prestamo_id', $prestamo->id)->delete();

        $prestamo->update([
            'monto_original' => 0,
            'saldo_actual' => 0,
            'ultimo_corte' => $prestamo->fecha_desembolso,
            'estado' => 'activo',
        ]);

        foreach ($this->agruparPagos($movimientos) as $paso) {
            if ($paso['tipo'] === 'desembolso') {
                $liquidacion->registrarDesembolsoAdicional(
                    $prestamo,
                    $paso['monto'],
                    $paso['fecha'],
                    $paso['observacion'],
                    $paso['soporte_path']
                );

                $prestamo->refresh();

                continue;
            }

            $res = $liquidacion->registrarPago(
                $prestamo,
                $paso['monto'],
                $paso['fecha'],
                $paso['observacion'],
                $paso['soporte_path'],
                $paso['cuenta_id']
            );

            if (! ($res['success'] ?? false)) {
                throw new \RuntimeException("No se pudo re-aplicar el abono del {$paso['fecha']}: ".($res['message'] ?? 'error desconocido'));
            }

            $prestamo->refresh();
        }
    }

    /**
     * Un mismo pago quedó partido en dos filas (abono_interes + abono_capital). Se vuelven a unir
     * para re-aplicarlo como el pago único que fue; si no, la reconstrucción lo trataría como dos
     * abonos distintos del mismo día y el reparto interés/capital saldría diferente.
     */
    private function agruparPagos($movimientos): array
    {
        $pasos = [];

        foreach ($movimientos as $mov) {
            $esDesembolso = $mov->tipo === 'desembolso';
            $clave = implode('|', [
                $esDesembolso ? 'desembolso' : 'pago',
                $mov->fecha,
                $mov->observacion,
                $mov->soporte_path,
                $mov->cuenta_id,
            ]);

            if (isset($pasos[$clave])) {
                $pasos[$clave]['monto'] += (float) $mov->monto;

                continue;
            }

            $pasos[$clave] = [
                'tipo' => $esDesembolso ? 'desembolso' : 'pago',
                'fecha' => $mov->fecha,
                'monto' => (float) $mov->monto,
                'observacion' => $mov->observacion,
                'soporte_path' => $mov->soporte_path,
                'cuenta_id' => $mov->cuenta_id,
            ];
        }

        return array_values($pasos);
    }
}
