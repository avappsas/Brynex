<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\PrestamoMovimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PrestamoLiquidacionService
{
    /**
     * Realiza la liquidación mensual (corte) de intereses de un préstamo.
     * Si han pasado 30 o más días desde el último corte (o desembolso), se liquida la tasa mensual completa.
     * Si es menos, se calcula proporcionalmente.
     * Si no se paga, los intereses liquidados se capitalizan (interés compuesto).
     *
     * @param Prestamo $prestamo
     * @param string|null $fecha
     * @return float Interés liquidado
     */
    public function liquidarPeriodo(Prestamo $prestamo, ?string $fecha = null): float
    {
        $fechaCorte = $fecha ? Carbon::parse($fecha) : Carbon::now();
        $ultimoCorte = $prestamo->ultimo_corte ? Carbon::parse($prestamo->ultimo_corte) : Carbon::parse($prestamo->fecha_desembolso);

        $diasPeriodo = $ultimoCorte->diffInDays($fechaCorte, false);

        if ($diasPeriodo <= 0) {
            return 0.00;
        }

        // Tasa mensual (ej. 3.5% es 3.5 / 100 = 0.035)
        $tasaDecimal = $prestamo->tasa_interes_mensual / 100;
        $interesLiquidado = 0.00;

        if ($diasPeriodo >= 30) {
            // Un mes completo
            $interesLiquidado = $prestamo->saldo_actual * $tasaDecimal;
        } else {
            // Proporcional por días
            $interesLiquidado = $prestamo->saldo_actual * $tasaDecimal * ($diasPeriodo / 30);
        }

        $interesLiquidado = round($interesLiquidado, 2);

        if ($interesLiquidado > 0) {
            DB::transaction(function () use ($prestamo, $interesLiquidado, $fechaCorte, $diasPeriodo) {
                $saldoAntes = $prestamo->saldo_actual;
                $saldoDespues = $saldoAntes + $interesLiquidado;

                // Registrar movimiento de liquidación
                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'interes_mensual',
                    'fecha' => $fechaCorte->toDateString(),
                    'monto' => $interesLiquidado,
                    'saldo_antes' => $saldoAntes,
                    'saldo_despues' => $saldoDespues,
                    'dias_periodo' => $diasPeriodo,
                    'observacion' => "Interés liquidado por {$diasPeriodo} días del período.",
                ]);

                // Actualizar préstamo (capitalización automática / interés compuesto)
                $prestamo->update([
                    'saldo_actual' => $saldoDespues,
                    'ultimo_corte' => $fechaCorte->toDateString(),
                    'estado' => $this->evaluarEstado($prestamo, $fechaCorte),
                ]);
            });
        }

        return $interesLiquidado;
    }

    /**
     * Registra un abono/pago a un préstamo.
     * El pago se aplica primero a los intereses pendientes y luego al capital original.
     *
     * @param Prestamo $prestamo
     * @param float $monto
     * @param string|null $fecha
     * @param string|null $observacion
     * @return array
     */
    public function registrarPago(Prestamo $prestamo, float $monto, ?string $fecha = null, ?string $observacion = null): array
    {
        $fechaPago = $fecha ? Carbon::parse($fecha) : Carbon::now();

        if ($monto <= 0) {
            return ['success' => false, 'message' => 'El monto del pago debe ser mayor a cero.'];
        }

        return DB::transaction(function () use ($prestamo, $monto, $fechaPago, $observacion) {
            // Liquidar intereses proporcionales a la fecha antes de aplicar el abono
            $interesPrevio = $this->liquidarPeriodo($prestamo, $fechaPago->toDateString());

            $saldoAntes = $prestamo->saldo_actual;
            $interesesPendientes = $prestamo->intereses_acumulados; // saldo_actual - monto_original

            $abonoInteres = 0.00;
            $abonoCapital = 0.00;

            if ($monto >= $interesesPendientes) {
                // El abono cubre todos los intereses y el excedente va a capital
                $abonoInteres = $interesesPendientes;
                $abonoCapital = $monto - $interesesPendientes;
            } else {
                // El abono solo cubre parte de los intereses
                $abonoInteres = $monto;
                $abonoCapital = 0.00;
            }

            $nuevoSaldo = $saldoAntes - $monto;
            $nuevoMontoOriginal = $prestamo->monto_original - $abonoCapital;

            // Evitar saldos negativos
            if ($nuevoSaldo < 0) {
                $nuevoSaldo = 0.00;
            }
            if ($nuevoMontoOriginal < 0) {
                $nuevoMontoOriginal = 0.00;
            }

            // Registrar movimiento de abono a intereses
            if ($abonoInteres > 0) {
                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'abono_interes',
                    'fecha' => $fechaPago->toDateString(),
                    'monto' => $abonoInteres,
                    'saldo_antes' => $saldoAntes,
                    'saldo_despues' => $saldoAntes - $abonoInteres,
                    'observacion' => $observacion ?: "Abono a intereses acumulados.",
                ]);
            }

            // Registrar movimiento de abono a capital
            if ($abonoCapital > 0) {
                $saldoIntermedio = $saldoAntes - $abonoInteres;
                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'abono_capital',
                    'fecha' => $fechaPago->toDateString(),
                    'monto' => $abonoCapital,
                    'saldo_antes' => $saldoIntermedio,
                    'saldo_despues' => $nuevoSaldo,
                    'observacion' => $observacion ?: "Abono a capital.",
                ]);
            }

            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagado' : $this->evaluarEstado($prestamo, $fechaPago);

            $prestamo->update([
                'monto_original' => $nuevoMontoOriginal,
                'saldo_actual' => $nuevoSaldo,
                'ultimo_corte' => $fechaPago->toDateString(),
                'estado' => $nuevoEstado,
            ]);

            return [
                'success' => true,
                'saldo_anterior' => $saldoAntes,
                'nuevo_saldo' => $nuevoSaldo,
                'abono_interes' => $abonoInteres,
                'abono_capital' => $abonoCapital,
            ];
        });
    }

    /**
     * Registra el desembolso inicial de un préstamo.
     */
    public function registrarDesembolso(Prestamo $prestamo): void
    {
        PrestamoMovimiento::create([
            'prestamo_id' => $prestamo->id,
            'tipo' => 'desembolso',
            'fecha' => $prestamo->fecha_desembolso,
            'monto' => $prestamo->monto_original,
            'saldo_antes' => 0.00,
            'saldo_despues' => $prestamo->monto_original,
            'observacion' => $prestamo->descripcion ?: "Desembolso de préstamo inicial.",
        ]);
    }

    /**
     * Evalúa si un préstamo debe pasar a estado 'mora' en base a la fecha de vencimiento
     */
    private function evaluarEstado(Prestamo $prestamo, Carbon $fechaReferencia): string
    {
        if ($prestamo->saldo_actual <= 0) {
            return 'pagado';
        }

        $ultimoCorte = $prestamo->ultimo_corte ? Carbon::parse($prestamo->ultimo_corte) : Carbon::parse($prestamo->fecha_desembolso);
        $diasTranscurridos = $ultimoCorte->diffInDays($fechaReferencia, false);

        if ($diasTranscurridos >= $prestamo->dias_mora_alerta) {
            return 'mora';
        }

        return 'activo';
    }
}
