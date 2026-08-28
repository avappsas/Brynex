<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\PrestamoMovimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PrestamoLiquidacionService
{
    /**
     * Prefijo de la observación del movimiento que liquida el ciclo completo en un pago.
     * `recalcularSaldos` lo usa para saber que en esa fecha el ciclo se reinició.
     */
    public const MARCA_REANCLA = 'Liquidación del ciclo al pago';

    /**
     * Realiza la liquidación mensual (corte) de intereses de un préstamo.
     * Si han pasado 30 o más días desde el último corte (o desembolso), se liquida la tasa mensual completa.
     * Si es menos, se calcula proporcionalmente.
     * Si no se paga, los intereses liquidados se capitalizan (interés compuesto).
     *
     * @param  string|null  $fecha
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

        // Un trabajo de cuenta corriente no cobra fracciones de mes: o se cumplió
        // el ciclo completo y se liquida, o el trabajo simplemente se pagó.
        if ($prestamo->es_cuenta_corriente) {
            $soloMesesCompletos = true;
        }

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
        $tasaDecimal = $this->tasaVigente($prestamo) / 100;
        $saldo = (float) $prestamo->saldo_actual;
        $total = 0.00;
        $ciclos = [];
        $corteIter = $ultimoCorte->copy();
        $diaCobro = $this->diaCobro($prestamo);

        // Capital que entró después del último corte: no debe pagar el mes entero, sólo los
        // días que alcanzó a estar dentro del ciclo en que cayó.
        $desembolsosDelTramo = PrestamoMovimiento::where('prestamo_id', $prestamo->id)
            ->where('tipo', 'desembolso')
            ->where('fecha', '>', $ultimoCorte->toDateString())
            ->get(['fecha', 'monto']);

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

            $noDevengado = $this->interesNoDevengado($desembolsosDelTramo, $corteAnterior, $corteIter, $tasaDecimal);
            $interesCiclo = round($saldo * $tasaDecimal - $noDevengado, 2);

            if ($interesCiclo <= 0) {
                continue;
            }

            $detalle = "Interés compuesto liquidado por mes calendario completo ({$diasPeriodo} días).";
            if ($noDevengado > 0) {
                $detalle .= ' Descuenta $'.number_format($noDevengado, 2).' del capital que entró dentro del ciclo.';
            }

            $ciclos[] = [
                'fecha' => $fechaCorteMes,
                'monto' => $interesCiclo,
                'saldo_antes' => $saldo,
                'saldo_despues' => $saldo + $interesCiclo,
                'dias' => $diasPeriodo,
                'observacion' => $detalle,
            ];

            $saldo += $interesCiclo;
            $total += $interesCiclo;
        }

        // Liquidar la fracción restante de días (menor a un mes completo) sólo si no se requiere únicamente meses completos
        if (! $soloMesesCompletos) {
            $diasRestantes = $corteIter->diffInDays($fechaCorte, false);
            if ($diasRestantes > 0) {
                $fechaCorteFraccion = $fechaCorte->toDateString();

                // Si la fracción restante está excluida, se omite el cobro pero el corte avanza
                if (! in_array($fechaCorteFraccion, $mesesExcluidos)) {
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
     * Registra un abono/pago a un préstamo. Modelo de saldo insoluto por días:
     * el interés corre día a día sobre el saldo, y cada pago liquida primero
     * todo el interés corrido hasta ese día.
     *
     * - Si el pago cubre TODO el interés (cortes pendientes + días corridos del ciclo
     *   abierto), el resto baja capital y el ciclo SE REINICIA en la fecha del pago:
     *   el próximo corte es un mes después de pagar, sobre el capital que quedó.
     * - Si no lo cubre, paga primero los intereses ya liquidados; un eventual resto
     *   baja capital pagando los días corridos de ese capital, y la fecha NO se mueve
     *   (no se premia pagar menos).
     * - En cuenta corriente no hay fracciones ni re-anclaje: el interés nace solo al
     *   cumplirse el mes.
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
            $reanclado = false;

            // Interés corrido del ciclo abierto sobre todo el saldo (compuesto dentro del ciclo)
            $fraccionCiclo = round($saldoAntes * $this->factorFraccion($prestamo, $fechaPago), 2);
            $interesTotal = $interesesPendientes + $fraccionCiclo;

            if (! $prestamo->es_cuenta_corriente && $monto >= $interesTotal && $interesTotal > 0) {
                // El pago liquida todo el interés corrido: el ciclo se reinicia hoy
                $reanclado = true;
                $interesFraccion = $fraccionCiclo;
                $abonoInteres = $interesTotal;

                $disponible = $monto - $interesTotal;
                $abonoCapital = min($disponible, $capitalVigente);

                // Lo que sobre por encima de toda la deuda es rendimiento, no saldo a favor
                $excedente = round($disponible - $abonoCapital, 2);
                if ($excedente > 0) {
                    $interesFraccion += $excedente;
                    $abonoInteres += $excedente;
                }
            } elseif ($monto <= $interesesPendientes) {
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

                // Caso límite: si el reparto pretende retirar más capital del que existe
                // (mucho interés capitalizado encima), se cierra el capital y el resto
                // del disponible queda como abono al interés.
                $capitalRetirado = min($capitalRetirado, $capitalVigente);
                $interesFraccion = round($disponible - $capitalRetirado, 2);

                $abonoCapital = $capitalRetirado;
                $abonoInteres += $interesFraccion;
            }

            $saldoCorriente = $saldoAntes;

            // Causar el interés de la fracción (sube el saldo) antes de aplicarle el abono encima.
            // Se usa un tipo propio y no 'interes_mensual' para no correr la fecha de corte del ciclo.
            if ($interesFraccion > 0) {
                $dias = $this->diasFraccion($prestamo, $fechaPago);
                if ($reanclado) {
                    $detalle = self::MARCA_REANCLA." ({$dias} días corridos): el ciclo se reinicia en esta fecha.";
                    if ($excedente > 0) {
                        $detalle .= ' Incluye excedente del abono.';
                    }
                } else {
                    $detalle = $excedente > 0
                        ? "Interés causado por {$dias} días corridos sobre el capital pagado, más excedente del abono."
                        : "Interés causado por {$dias} días corridos sobre el capital abonado.";
                }

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
                    'observacion' => $observacion ?: 'Abono a intereses acumulados.',
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
                    'observacion' => $observacion ?: 'Abono a capital.',
                    'soporte_path' => $soportePath,
                    'cuenta_id' => $cuentaId,
                ]);

                $saldoCorriente -= $abonoCapital;
            }

            $nuevoSaldo = max(0.00, round($saldoCorriente, 2));
            $nuevoMontoOriginal = max(0.00, round($capitalVigente - $abonoCapital, 2));

            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagado' : $this->evaluarEstado($prestamo, $fechaPago);

            $cambios = [
                'monto_original' => $nuevoMontoOriginal,
                'saldo_actual' => $nuevoSaldo,
                'estado' => $nuevoEstado,
            ];

            if ($reanclado) {
                // El próximo corte es un mes después de este pago, sobre el capital que quedó
                $cambios['ultimo_corte'] = $fechaPago->toDateString();
                $cambios['dia_cobro'] = (int) $fechaPago->day;
            }

            $prestamo->update($cambios);

            return [
                'success' => true,
                'saldo_anterior' => $saldoAntes,
                'nuevo_saldo' => $nuevoSaldo,
                'abono_interes' => $abonoInteres,
                'abono_capital' => $abonoCapital,
                'interes_fraccion' => $interesFraccion,
                'excedente' => $excedente,
                'reanclado' => $reanclado,
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

        // Días sueltos del ciclo abierto, sobre todo el saldo (compuesto dentro del ciclo).
        // En cuenta corriente no se cobran: el interés solo aparece al cumplir el mes.
        $dias = max(0, (int) $plan['corte_final']->diffInDays($fechaCorte, false));
        $interesFraccion = $prestamo->es_cuenta_corriente
            ? 0.00
            : round($saldoTrasMeses * ($this->tasaVigente($prestamo) / 100) * ($dias / 30), 2);

        return [
            'fecha' => $fechaCorte->toDateString(),
            'capital' => $capital,
            'intereses_pendientes' => round($interesesPendientes, 2),
            'interes_meses' => round($plan['total'], 2),
            'interes_fraccion' => $interesFraccion,
            'dias_fraccion' => $dias,
            'total' => round($saldoTrasMeses + $interesFraccion, 2),
        ];
    }

    /**
     * Interés que el corte cobraría de más por el capital que entró con el ciclo ya empezado.
     *
     * El corte aplica la tasa mensual completa sobre el saldo, pero ese capital sólo estuvo
     * parte del ciclo; se descuenta la porción que no alcanzó a devengar. Es el simétrico del
     * interés proporcional que paga el capital cuando sale antes del corte.
     */
    private function interesNoDevengado($desembolsos, Carbon $corteAnterior, Carbon $corteActual, float $tasaDecimal): float
    {
        $ajuste = 0.00;

        foreach ($desembolsos as $d) {
            $fecha = Carbon::parse($d->fecha);

            if ($fecha->lte($corteAnterior) || $fecha->gt($corteActual)) {
                continue;
            }

            $diasDentro = $fecha->diffInDays($corteActual);
            $diasFuera = max(0, 30 - $diasDentro);

            if ($diasFuera > 0) {
                $ajuste += (float) $d->monto * $tasaDecimal * ($diasFuera / 30);
            }
        }

        return round($ajuste, 2);
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
     * Día del mes en que corta el préstamo. Nace con el desembolso y se actualiza
     * cuando un pago completo re-ancla el ciclo.
     */
    private function diaCobro(Prestamo $prestamo): int
    {
        return (int) ($prestamo->dia_cobro ?: Carbon::parse($prestamo->fecha_desembolso)->day);
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
     * Fecha en la que cae el próximo corte del préstamo: un ciclo después del
     * corte vigente, el mismo día del mes en que se desembolsó.
     *
     * Es la misma fecha que usa la liquidación automática para causar el interés,
     * así que los recordatorios de WhatsApp anuncian exactamente el día que se cobra.
     */
    public function proximoCorte(Prestamo $prestamo, ?Carbon $desde = null): Carbon
    {
        $diaCobro = $this->diaCobro($prestamo);
        $hoy = ($desde ?: Carbon::now())->copy()->startOfDay();
        $corte = $this->siguienteCorte($this->corteVigente($prestamo), $diaCobro);

        // Un préstamo sin tasa nunca genera cortes, así que su `ultimo_corte` se
        // queda en el desembolso y el siguiente corte quedaría en el pasado.
        // Se adelanta ciclo a ciclo hasta el primero que aún no llega.
        $vueltas = 0;
        while ($corte->copy()->startOfDay()->lt($hoy) && $vueltas < 600) {
            $corte = $this->siguienteCorte($corte, $diaCobro);
            $vueltas++;
        }

        return $corte;
    }

    /**
     * Fecha del corte vigente (el último ya causado), expuesta para los recordatorios.
     */
    public function corteAnterior(Prestamo $prestamo): Carbon
    {
        return $this->corteVigente($prestamo);
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
        // En cuenta corriente el interés solo nace al cumplirse el mes: pagar un
        // trabajo antes de su primer corte no debe causar nada por días corridos.
        if ($prestamo->es_cuenta_corriente || $this->tasaVigente($prestamo) <= 0) {
            return 0.00;
        }

        return ($this->tasaVigente($prestamo) / 100) * ($this->diasFraccion($prestamo, $fecha) / 30);
    }

    /**
     * Tasa que realmente se causa. Un trabajo marcado "sin interés" guarda su tasa
     * pero no la cobra, así se puede reactivar sin volver a digitarla.
     */
    private function tasaVigente(Prestamo $prestamo): float
    {
        return $prestamo->sin_interes ? 0.00 : (float) $prestamo->tasa_interes_mensual;
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
            'observacion' => $prestamo->descripcion ?: 'Desembolso de préstamo inicial.',
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
                'prestamo_id' => $prestamo->id,
                'tipo' => 'desembolso',
                'fecha' => $fecha,
                'monto' => $monto,
                'saldo_antes' => $saldoAntes,
                'saldo_despues' => $nuevoSaldo,
                'observacion' => $observacion ?: 'Desembolso adicional de capital.',
                'soporte_path' => $soportePath,
            ]);

            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagado' : $this->evaluarEstado($prestamo, Carbon::parse($fecha));

            $prestamo->update([
                'monto_original' => $nuevoMontoOriginal,
                'saldo_actual' => $nuevoSaldo,
                'estado' => $nuevoEstado,
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
