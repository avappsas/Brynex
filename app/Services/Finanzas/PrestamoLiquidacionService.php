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
        $ultimoCorte = $fechaDesde ? Carbon::parse($fechaDesde) : $this->corteVigente($prestamo);

        if ($ultimoCorte->diffInDays($fechaCorte, false) <= 0) {
            return 0.00;
        }

        $plan = $this->proyectarCiclos($prestamo, $ultimoCorte, $fechaCorte, $mesesExcluidos, $soloMesesCompletos);

        if (empty($plan['ciclos']) && $plan['corte_final']->eq($ultimoCorte)) {
            return 0.00;
        }

        DB::connection('finanzas')->transaction(function () use ($prestamo, $fechaCorte, $plan) {
            foreach ($plan['ciclos'] as $ciclo) {
                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'interes_mensual',
                    'fecha' => $ciclo['fecha'],
                    'monto' => $ciclo['monto'],
                    'saldo_antes' => $ciclo['saldo_antes'],
                    'saldo_despues' => $ciclo['saldo_despues'],
                    'dias_periodo' => $ciclo['dias'],
                    'observacion' => $ciclo['observacion'],
                ]);
            }

            $prestamo->saldo_actual = $plan['saldo_final'];

            // Guardar el estado definitivo en la base de datos utilizando la fecha del último corte calculado real
            $prestamo->update([
                'saldo_actual' => $plan['saldo_final'],
                'ultimo_corte' => $plan['corte_final']->toDateString(),
                'estado' => $this->evaluarEstado($prestamo, $fechaCorte),
            ]);
        });

        return $plan['total'];
    }

    /**
     * Proyecta (sin escribir nada) los ciclos de interés entre dos fechas.
     * Se usa tanto para liquidar de verdad como para simular el cierre de un préstamo.
     */
    private function proyectarCiclos(
        Prestamo $prestamo,
        Carbon $ultimoCorte,
        Carbon $fechaCorte,
        array $mesesExcluidos = [],
        bool $soloMesesCompletos = false
    ): array {
        // Tasa mensual (ej. 3.5% es 3.5 / 100 = 0.035)
        $tasaDecimal = $prestamo->tasa_interes_mensual / 100;
        $saldo = (float) $prestamo->saldo_actual;
        $total = 0.00;
        $ciclos = [];
        $corteIter = $ultimoCorte->copy();
        $diaCobro = (int) Carbon::parse($prestamo->fecha_desembolso)->day;

        // Iterar mes a mes calendario para mantener el mismo día del mes de cobro
        while ($this->siguienteCorte($corteIter, $diaCobro)->startOfDay()->lte($fechaCorte->copy()->startOfDay())) {
            $corteAnterior = $corteIter->copy();
            $corteIter = $this->siguienteCorte($corteIter, $diaCobro);
            $diasPeriodo = $corteAnterior->diffInDays($corteIter);

            $fechaCorteMes = $corteIter->toDateString();

            // Si este mes calendario completo está excluido, se omite el cobro pero el corte avanza
            if (in_array($fechaCorteMes, $mesesExcluidos)) {
                continue;
            }

            $interesCiclo = round($saldo * $tasaDecimal, 2);

            if ($interesCiclo <= 0) {
                continue;
            }

            $ciclos[] = [
                'fecha' => $fechaCorteMes,
                'monto' => $interesCiclo,
                'saldo_antes' => $saldo,
                'saldo_despues' => $saldo + $interesCiclo,
                'dias' => $diasPeriodo,
                'observacion' => "Interés compuesto liquidado por mes calendario completo ({$diasPeriodo} días).",
            ];

            $saldo += $interesCiclo;
            $total += $interesCiclo;
        }

        // Liquidar la fracción restante de días (menor a un mes completo) sólo si no se requiere únicamente meses completos
        if (!$soloMesesCompletos) {
            $diasRestantes = $corteIter->diffInDays($fechaCorte, false);
            if ($diasRestantes > 0) {
                $fechaCorteFraccion = $fechaCorte->toDateString();

                // Si la fracción restante está excluida, se omite el cobro pero el corte avanza
                if (!in_array($fechaCorteFraccion, $mesesExcluidos)) {
                    $interesProporcional = round($saldo * $tasaDecimal * ($diasRestantes / 30), 2);

                    if ($interesProporcional > 0) {
                        $ciclos[] = [
                            'fecha' => $fechaCorteFraccion,
                            'monto' => $interesProporcional,
                            'saldo_antes' => $saldo,
                            'saldo_despues' => $saldo + $interesProporcional,
                            'dias' => $diasRestantes,
                            'observacion' => "Interés proporcional liquidado por fracción de {$diasRestantes} días.",
                        ];

                        $saldo += $interesProporcional;
                        $total += $interesProporcional;
                    }
                }
                $corteIter = $fechaCorte->copy();
            }
        }

        return [
            'ciclos' => $ciclos,
            'saldo_final' => $saldo,
            'corte_final' => $corteIter,
            'total' => $total,
        ];
    }

    /**
     * Registra un abono/pago a un préstamo.
     *
     * El pago se aplica en tres capas, en este orden:
     *   1. Intereses ya liquidados y pendientes (cortes de meses completos).
     *   2. Interés causado por los días corridos del ciclo abierto sobre el capital que se retira.
     *      Sin esto, abonar a mitad de ciclo borraba hacia atrás el interés de esos días: el capital
     *      bajaba el día del abono pero el interés sólo se causaba al cumplir el mes completo.
     *   3. Capital.
     *
     * La fecha de corte mensual NO se mueve por el abono: el ciclo siguiente se sigue cobrando
     * el mismo día del mes, ahora sobre el capital ya reducido.
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

        return DB::connection('finanzas')->transaction(function () use ($prestamo, $monto, $fechaPago, $observacion, $soportePath, $cuentaId) {
            // Liquidar únicamente intereses de meses completos a la fecha antes de aplicar el abono
            $this->liquidarPeriodo($prestamo, null, $fechaPago->toDateString(), [], true);

            $saldoAntes = (float) $prestamo->saldo_actual;
            $capitalVigente = max(0.00, (float) $prestamo->monto_original);
            $interesesPendientes = max(0.00, $saldoAntes - $capitalVigente);

            $abonoInteres = 0.00;
            $abonoCapital = 0.00;
            $interesFraccion = 0.00;
            $excedente = 0.00;

            if ($monto <= $interesesPendientes) {
                // El abono no alcanza a cubrir los intereses ya liquidados: no toca capital
                $abonoInteres = $monto;
            } else {
                $abonoInteres = $interesesPendientes;
                $disponible = $monto - $interesesPendientes;

                // Interés causado por los días corridos desde el último corte sobre el capital que sale
                $factor = $this->factorFraccion($prestamo, $fechaPago);

                // El disponible se reparte entre el capital que se retira y el interés que ese capital causó:
                //   disponible = capital + capital * factor  =>  capital = disponible / (1 + factor)
                $capitalRetirado = $factor > 0 ? round($disponible / (1 + $factor), 2) : $disponible;

                if ($capitalRetirado >= $capitalVigente) {
                    // El abono alcanza para cerrar el capital completo: se cobra la fracción sobre todo él
                    $capitalRetirado = $capitalVigente;
                    $interesFraccion = round($capitalVigente * $factor, 2);
                    // Lo que sobre por encima de capital + interés causado es rendimiento, no saldo a favor
                    $excedente = round($disponible - $capitalRetirado - $interesFraccion, 2);
                    if ($excedente < 0) {
                        $excedente = 0.00;
                    }
                    $interesFraccion += $excedente;
                } else {
                    $interesFraccion = round($disponible - $capitalRetirado, 2);
                }

                $abonoCapital = $capitalRetirado;
                $abonoInteres += $interesFraccion;
            }

            $saldoCorriente = $saldoAntes;

            // Causar el interés de la fracción (sube el saldo) antes de aplicarle el abono encima.
            // Se usa un tipo propio y no 'interes_mensual' para no correr la fecha de corte del ciclo.
            if ($interesFraccion > 0) {
                $dias = $this->diasFraccion($prestamo, $fechaPago);
                $detalle = $excedente > 0
                    ? "Interés causado por {$dias} días corridos sobre el capital pagado, más excedente del abono."
                    : "Interés causado por {$dias} días corridos sobre el capital abonado.";

                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'interes_proporcional',
                    'fecha' => $fechaPago->toDateString(),
                    'monto' => $interesFraccion,
                    'saldo_antes' => $saldoCorriente,
                    'saldo_despues' => $saldoCorriente + $interesFraccion,
                    'dias_periodo' => $dias,
                    'observacion' => $detalle,
                ]);

                $saldoCorriente += $interesFraccion;
            }

            // Registrar movimiento de abono a intereses
            if ($abonoInteres > 0) {
                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'abono_interes',
                    'fecha' => $fechaPago->toDateString(),
                    'monto' => $abonoInteres,
                    'saldo_antes' => $saldoCorriente,
                    'saldo_despues' => $saldoCorriente - $abonoInteres,
                    'observacion' => $observacion ?: "Abono a intereses acumulados.",
                    'soporte_path' => $soportePath,
                    'cuenta_id' => $cuentaId,
                ]);

                $saldoCorriente -= $abonoInteres;
            }

            // Registrar movimiento de abono a capital
            if ($abonoCapital > 0) {
                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'abono_capital',
                    'fecha' => $fechaPago->toDateString(),
                    'monto' => $abonoCapital,
                    'saldo_antes' => $saldoCorriente,
                    'saldo_despues' => $saldoCorriente - $abonoCapital,
                    'observacion' => $observacion ?: "Abono a capital.",
                    'soporte_path' => $soportePath,
                    'cuenta_id' => $cuentaId,
                ]);

                $saldoCorriente -= $abonoCapital;
            }

            $nuevoSaldo = max(0.00, round($saldoCorriente, 2));
            $nuevoMontoOriginal = max(0.00, round($capitalVigente - $abonoCapital, 2));

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
                'interes_fraccion' => $interesFraccion,
                'excedente' => $excedente,
            ];
        });
    }

    /**
     * Calcula, sin escribir nada, cuánto hay que pagar para dejar el préstamo en cero a una fecha.
     * Incluye los cortes mensuales que estén pendientes más el interés de los días sueltos.
     */
    public function calcularCierre(Prestamo $prestamo, ?string $fechaHasta = null): array
    {
        $fechaCorte = $fechaHasta ? Carbon::parse($fechaHasta) : Carbon::now();
        $ultimoCorte = $this->corteVigente($prestamo);
        $capital = max(0.00, (float) $prestamo->monto_original);

        // Cortes mensuales pendientes (capitalizan igual que en la liquidación real)
        $plan = $this->proyectarCiclos($prestamo, $ultimoCorte, $fechaCorte, [], true);

        $saldoTrasMeses = $plan['saldo_final'];
        $interesesPendientes = max(0.00, $saldoTrasMeses - $capital);

        // Días sueltos del ciclo abierto, cobrados sobre el capital
        $dias = max(0, (int) $plan['corte_final']->diffInDays($fechaCorte, false));
        $interesFraccion = round($capital * ($prestamo->tasa_interes_mensual / 100) * ($dias / 30), 2);

        return [
            'fecha' => $fechaCorte->toDateString(),
            'capital' => $capital,
            'intereses_pendientes' => round($interesesPendientes, 2),
            'interes_meses' => round($plan['total'], 2),
            'interes_fraccion' => $interesFraccion,
            'dias_fraccion' => $dias,
            'total' => round($capital + $interesesPendientes + $interesFraccion, 2),
        ];
    }

    /**
     * Fecha del siguiente corte mensual.
     *
     * El corte cae el mismo día de cada mes. Cuando ese día no existe en el mes destino
     * (un préstamo del 31 en un mes de 30), cae en el último día del mes y no se desborda
     * al día 1 del mes siguiente; al mes siguiente recupera su día original.
     */
    private function siguienteCorte(Carbon $desde, int $diaCobro): Carbon
    {
        $dia = $desde->day;
        $mesBase = $desde->copy();

        if ($dia === $desde->daysInMonth && $diaCobro > $dia) {
            // El corte anterior quedó en el último día de su mes porque el día de cobro no cabía
            $dia = $diaCobro;
        } elseif ($dia <= 3 && $diaCobro >= 29) {
            // Corte heredado del cálculo viejo, que en vez de caer en el último día del mes
            // desbordaba a los primeros días del mes siguiente (al 1 desde un mes de 30, al 2 o 3
            // saliendo de febrero). Se devuelve a su mes y se recupera el día de cobro.
            $mesBase = $desde->copy()->subMonthNoOverflow();
            $dia = $diaCobro;
        }

        $siguiente = $mesBase->startOfMonth()->addMonth();

        return $siguiente->day(min($dia, $siguiente->daysInMonth));
    }

    /**
     * Fecha desde la que corre el ciclo de interés vigente.
     */
    private function corteVigente(Prestamo $prestamo): Carbon
    {
        return $prestamo->ultimo_corte
            ? Carbon::parse($prestamo->ultimo_corte)
            : Carbon::parse($prestamo->fecha_desembolso);
    }

    /**
     * Días corridos del ciclo abierto hasta la fecha dada.
     */
    private function diasFraccion(Prestamo $prestamo, Carbon $fecha): int
    {
        return max(0, (int) $this->corteVigente($prestamo)->diffInDays($fecha, false));
    }

    /**
     * Proporción de la tasa mensual causada por los días corridos del ciclo abierto.
     */
    private function factorFraccion(Prestamo $prestamo, Carbon $fecha): float
    {
        if ($prestamo->tasa_interes_mensual <= 0) {
            return 0.00;
        }

        return ($prestamo->tasa_interes_mensual / 100) * ($this->diasFraccion($prestamo, $fecha) / 30);
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

        return DB::connection('finanzas')->transaction(function () use ($prestamo, $monto, $fecha, $observacion, $soportePath) {
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
