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
    public function liquidarPeriodo(
        Prestamo $prestamo,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        array $mesesExcluidos = [],
        bool $soloMesesCompletos = false
    ): float {
        $fechaCorte = $fechaHasta ? Carbon::parse($fechaHasta) : Carbon::now();
        $ultimoCorte = $fechaDesde ? Carbon::parse($fechaDesde) : ($prestamo->ultimo_corte ? Carbon::parse($prestamo->ultimo_corte) : Carbon::parse($prestamo->fecha_desembolso));

        $diasPeriodo = $ultimoCorte->diffInDays($fechaCorte, false);

        if ($diasPeriodo <= 0) {
            return 0.00;
        }

        // Tasa mensual (ej. 3.5% es 3.5 / 100 = 0.035)
        $tasaDecimal = $prestamo->tasa_interes_mensual / 100;
        $totalInteresAcumulado = 0.00;

        DB::transaction(function () use ($prestamo, $fechaCorte, $ultimoCorte, $tasaDecimal, $mesesExcluidos, $soloMesesCompletos, &$totalInteresAcumulado) {
            $ultimoCorteIter = $ultimoCorte->copy();

            // Iterar mes a mes calendario para mantener el mismo día del mes de cobro
            while ($ultimoCorteIter->copy()->addMonth()->startOfDay()->lte($fechaCorte->copy()->startOfDay())) {
                $ultimoCorteIterBefore = $ultimoCorteIter->copy();
                $ultimoCorteIter = $ultimoCorteIter->addMonth();
                $diasPeriodo = $ultimoCorteIterBefore->diffInDays($ultimoCorteIter);
                
                $fechaCorteMes = $ultimoCorteIter->toDateString();

                // Si este mes calendario completo está excluido, se omite el cobro pero el corte avanza
                if (in_array($fechaCorteMes, $mesesExcluidos)) {
                    continue;
                }

                $saldoAntes = $prestamo->saldo_actual;
                $interesCiclo = round($saldoAntes * $tasaDecimal, 2);

                if ($interesCiclo <= 0) {
                    continue;
                }

                $saldoDespues = $saldoAntes + $interesCiclo;

                // Registrar movimiento de liquidación parcial de un mes completo
                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'interes_mensual',
                    'fecha' => $fechaCorteMes,
                    'monto' => $interesCiclo,
                    'saldo_antes' => $saldoAntes,
                    'saldo_despues' => $saldoDespues,
                    'dias_periodo' => $diasPeriodo,
                    'observacion' => "Interés compuesto liquidado por mes calendario completo ({$diasPeriodo} días).",
                ]);

                // Actualizar saldo temporal en la instancia para el siguiente ciclo
                $prestamo->saldo_actual = $saldoDespues;
                $totalInteresAcumulado += $interesCiclo;
            }

            // Liquidar la fracción restante de días (menor a un mes completo) sólo si no se requiere únicamente meses completos
            if (!$soloMesesCompletos) {
                $diasRestantes = $ultimoCorteIter->diffInDays($fechaCorte, false);
                if ($diasRestantes > 0) {
                    $fechaCorteFraccion = $fechaCorte->toDateString();
                    
                    // Si la fracción restante está excluida, se omite el cobro pero el corte avanza
                    if (!in_array($fechaCorteFraccion, $mesesExcluidos)) {
                        $saldoAntes = $prestamo->saldo_actual;
                        $interesProporcional = round($saldoAntes * $tasaDecimal * ($diasRestantes / 30), 2);

                        if ($interesProporcional > 0) {
                            $saldoDespues = $saldoAntes + $interesProporcional;

                            // Registrar movimiento de liquidación proporcional final
                            PrestamoMovimiento::create([
                                'prestamo_id' => $prestamo->id,
                                'tipo' => 'interes_mensual',
                                'fecha' => $fechaCorteFraccion,
                                'monto' => $interesProporcional,
                                'saldo_antes' => $saldoAntes,
                                'saldo_despues' => $saldoDespues,
                                'dias_periodo' => $diasRestantes,
                                'observacion' => "Interés proporcional liquidado por fracción de {$diasRestantes} días.",
                            ]);

                            $prestamo->saldo_actual = $saldoDespues;
                            $totalInteresAcumulado += $interesProporcional;
                        }
                    }
                    $ultimoCorteIter = $fechaCorte;
                }
            }

            // Guardar el estado definitivo en la base de datos utilizando la fecha del último corte calculado real
            $prestamo->update([
                'saldo_actual' => $prestamo->saldo_actual,
                'ultimo_corte' => $ultimoCorteIter->toDateString(),
                'estado' => $this->evaluarEstado($prestamo, $fechaCorte),
            ]);
        });

        return $totalInteresAcumulado;
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
    public function registrarPago(Prestamo $prestamo, float $monto, ?string $fecha = null, ?string $observacion = null, ?string $soportePath = null, ?int $cuentaId = null): array
    {
        $fechaPago = $fecha ? Carbon::parse($fecha) : Carbon::now();

        if ($monto <= 0) {
            return ['success' => false, 'message' => 'El monto del pago debe ser mayor a cero.'];
        }

        return DB::transaction(function () use ($prestamo, $monto, $fechaPago, $observacion, $soportePath, $cuentaId) {
            // Liquidar únicamente intereses de meses completos a la fecha antes de aplicar el abono
            $interesPrevio = $this->liquidarPeriodo($prestamo, null, $fechaPago->toDateString(), [], true);

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
                    'soporte_path' => $soportePath,
                    'cuenta_id' => $cuentaId,
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
                    'soporte_path' => $soportePath,
                    'cuenta_id' => $cuentaId,
                ]);
            }

            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagado' : $this->evaluarEstado($prestamo, $fechaPago);

            $prestamo->update([
                'monto_original' => $nuevoMontoOriginal,
                'saldo_actual' => $nuevoSaldo,
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
     * Registra un desembolso adicional (anexa valor al préstamo).
     */
    public function registrarDesembolsoAdicional(Prestamo $prestamo, float $monto, string $fecha, ?string $observacion = null, ?string $soportePath = null): array
    {
        if ($monto <= 0) {
            return ['success' => false, 'message' => 'El monto debe ser mayor a cero.'];
        }

        return DB::transaction(function () use ($prestamo, $monto, $fecha, $observacion, $soportePath) {
            $saldoAntes = $prestamo->saldo_actual;
            $nuevoSaldo = $saldoAntes + $monto;
            $nuevoMontoOriginal = $prestamo->monto_original + $monto;

            // Registrar movimiento de desembolso (suma saldo)
            PrestamoMovimiento::create([
                'prestamo_id'  => $prestamo->id,
                'tipo'         => 'desembolso',
                'fecha'        => $fecha,
                'monto'        => $monto,
                'saldo_antes'  => $saldoAntes,
                'saldo_despues'=> $nuevoSaldo,
                'observacion'  => $observacion ?: "Desembolso adicional de capital.",
                'soporte_path' => $soportePath,
            ]);

            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagado' : $this->evaluarEstado($prestamo, Carbon::parse($fecha));

            $prestamo->update([
                'monto_original'=> $nuevoMontoOriginal,
                'saldo_actual'  => $nuevoSaldo,
                'estado'        => $nuevoEstado,
            ]);

            return [
                'success' => true,
                'saldo_anterior' => $saldoAntes,
                'nuevo_saldo' => $nuevoSaldo,
            ];
        });
    }

    /**
     * Evalúa si un préstamo debe pasar a estado 'mora' en base a la fecha de vencimiento
     */
    private function evaluarEstado(Prestamo $prestamo, Carbon $fechaReferencia): string
    {
        if ($prestamo->saldo_actual <= 0) {
            return 'pagado';
        }

        // Buscar el último movimiento de tipo abono o pago total
        $ultimoAbono = $prestamo->movimientos()
            ->whereIn('tipo', ['abono_capital', 'abono_interes', 'pago_total'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $referencia = $ultimoAbono ? Carbon::parse($ultimoAbono->fecha) : Carbon::parse($prestamo->fecha_desembolso);
        $diasTranscurridos = $referencia->diffInDays($fechaReferencia, false);

        if ($diasTranscurridos >= $prestamo->dias_mora_alerta) {
            return 'mora';
        }

        return 'activo';
    }
}
