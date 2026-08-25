<?php

namespace App\Console\Commands\Finanzas;

use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\PrestamoMovimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalcularCapitalPrestamos extends Command
{
    /**
     * Reconstruye el capital vigente (`monto_original`) y la clasificación de los
     * abonos de un préstamo a partir de sus movimientos.
     *
     * El desfase viene de los movimientos `capitalizacion` que creó la importación
     * del Excel: suben el saldo pero nunca subieron `monto_original`. En el préstamo
     * de Fabio Arroyave eso dejó $258M de capital contados como interés, y como
     * `registrarPago` reparte contra `saldo - monto_original`, sus abonos a capital
     * se estaban registrando como pagos de intereses.
     *
     * El criterio de reconstrucción es el mismo que usa la liquidación: el interés
     * de cada corte se cubre con los abonos que le siguen, y lo que sobra es capital.
     * Un abono que cubre interés y capital a la vez se parte en dos movimientos.
     *
     * Sin --apply solo simula. Ejecución: php artisan finanzas:recalcular-capital-prestamos 20282 --apply
     */
    protected $signature = 'finanzas:recalcular-capital-prestamos
                            {ids?* : Ids de préstamo; sin ids, todos los que tengan capitalizaciones}
                            {--todos : Revisa todos los préstamos vivos, no solo los que tienen capitalizaciones}
                            {--apply : Escribe los cambios; sin esta opción solo simula}';

    protected $description = 'Reconstruye el capital vigente y la clasificación de abonos de los préstamos con capitalizaciones';

    /** Diferencia en pesos por debajo de la cual un descuadre es redondeo, no un pago. */
    private const RESIDUO = 1000;

    public function handle(): int
    {
        $ids = $this->argument('ids');

        if (! empty($ids)) {
            $prestamos = Prestamo::whereIn('id', $ids)->orderBy('id')->get();
        } elseif ($this->option('todos')) {
            $prestamos = Prestamo::where('saldo_actual', '>', 0)->orderBy('id')->get();
        } else {
            $ids = DB::connection('finanzas')
                ->table('finanzas_prestamo_movimientos')
                ->where('tipo', 'capitalizacion')
                ->distinct()
                ->pluck('prestamo_id')
                ->all();

            // Sin ids explícitos solo se tocan los vivos: un préstamo ya pagado
            // cerró en cero y reclasificar su historia no cambia ningún saldo.
            $prestamos = Prestamo::whereIn('id', $ids)->where('saldo_actual', '>', 0)->orderBy('id')->get();
        }

        if ($prestamos->isEmpty()) {
            $this->warn('No se encontró ningún préstamo con esos ids.');

            return self::SUCCESS;
        }

        $aplicar = $this->option('apply');
        $intactos = 0;

        foreach ($prestamos as $prestamo) {
            $plan = $this->planificar($prestamo);

            // Un préstamo ya correcto no aporta nada al informe: se cuenta y se salta,
            // que si no la revisión completa entierra los casos que sí hay que mirar.
            if ($this->sinCambios($prestamo, $plan)) {
                $intactos++;

                continue;
            }

            $this->line('');
            $this->info("#{$prestamo->id} {$prestamo->nombre_deudor}");
            $this->table(
                ['', 'antes', 'después'],
                [
                    ['capital vigente', $this->pesos($prestamo->monto_original), $this->pesos($plan['capital_vigente'])],
                    ['interés pendiente', $this->pesos($prestamo->intereses_acumulados), $this->pesos($plan['interes_pendiente'])],
                    ['saldo', $this->pesos($prestamo->saldo_actual), $this->pesos($plan['saldo'])],
                ]
            );

            // La guarda es contra un descuadre de verdad, no contra los centavos que deja
            // el redondeo de las liquidaciones: por debajo de un peso se sigue de largo.
            if (abs($plan['saldo'] - (float) $prestamo->saldo_actual) >= 1) {
                $this->error('  El saldo reconstruido no coincide con el guardado. Se omite este préstamo.');

                continue;
            }

            foreach ($plan['reclasificar'] as $r) {
                $this->line("  mov #{$r['id']} {$r['fecha']} {$this->pesos($r['monto'])}: {$r['de']} → {$r['a']}");
            }

            foreach ($plan['partir'] as $p) {
                $this->line("  mov #{$p['id']} {$p['fecha']} {$this->pesos($p['monto_total'])} se parte en "
                    ."{$this->pesos($p['capital'])} capital + {$this->pesos($p['interes'])} interés");
            }

            foreach ($plan['notas'] as $n) {
                $this->line("  mov #{$n['id']}: la nota automática pasa a «{$n['nota']}»");
            }

            if ($plan['saldos_rotos']) {
                $this->line('  se reescribe la columna de saldos de la ficha');
            }

            if (! $aplicar) {
                $this->comment('  [simulación] nada escrito. Agrega --apply para aplicar.');

                continue;
            }

            $this->aplicar($prestamo, $plan);
            $this->info('  ✔ aplicado');
        }

        $this->line('');
        $this->info("Revisados: {$prestamos->count()}. Ya correctos: {$intactos}.");

        if (! $aplicar) {
            $this->comment('Simulación: no se escribió nada. Repite con --apply para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * ¿El préstamo ya está como debería?
     */
    private function sinCambios(Prestamo $prestamo, array $plan): bool
    {
        return empty($plan['reclasificar'])
            && empty($plan['partir'])
            && empty($plan['notas'])
            && ! $plan['saldos_rotos']
            && abs($plan['capital_vigente'] - (float) $prestamo->monto_original) < self::RESIDUO;
    }

    /**
     * ¿La columna de saldos de la ficha encadena? Tras partir un abono, el movimiento
     * nuevo entra sin saldos y parte la cadena hasta que se reescriba.
     */
    private function saldosRotos(Prestamo $prestamo): bool
    {
        $saldo = 0.0;

        $movimientos = PrestamoMovimiento::where('prestamo_id', $prestamo->id)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        foreach ($movimientos as $m) {
            if (abs((float) $m->saldo_antes - $saldo) >= 1) {
                return true;
            }

            $suma = in_array($m->tipo, ['desembolso', 'capitalizacion', 'interes_mensual', 'interes_proporcional']);
            $saldo = $suma ? $saldo + (float) $m->monto : $saldo - (float) $m->monto;
        }

        return false;
    }

    /**
     * Recorre los movimientos en orden y decide qué parte de cada abono fue interés
     * y qué parte capital, sin escribir nada.
     */
    private function planificar(Prestamo $prestamo): array
    {
        $movimientos = $this->enOrden($prestamo);

        $entregado = 0.0;      // desembolsos + capitalizaciones
        $pagadoCapital = 0.0;
        $interesPendiente = 0.0;
        $saldo = 0.0;
        $reclasificar = [];
        $partir = [];

        foreach ($movimientos as $m) {
            $monto = (float) $m->monto;

            if (in_array($m->tipo, ['desembolso', 'capitalizacion'])) {
                $entregado += $monto;
                $saldo += $monto;

                continue;
            }

            if (in_array($m->tipo, ['interes_mensual', 'interes_proporcional'])) {
                $interesPendiente += $monto;
                $saldo += $monto;

                continue;
            }

            // Abono: cubre primero el interés pendiente, el resto baja capital.
            $aInteres = min($monto, $interesPendiente);
            $aCapital = $monto - $aInteres;

            // Los decimales de las liquidaciones dejan restos de pocos pesos. Partir un
            // abono por medio peso ensucia la ficha, así que el resto va al lado mayor.
            if ($aCapital > 0 && $aCapital < self::RESIDUO) {
                $aInteres = $monto;
                $aCapital = 0.0;
            } elseif ($aInteres > 0 && $aInteres < self::RESIDUO) {
                $aCapital = $monto;
                $aInteres = 0.0;
            }

            $interesPendiente = max(0.0, $interesPendiente - $aInteres);
            $pagadoCapital += $aCapital;
            $saldo -= $monto;

            if ($m->tipo === 'pago_total') {
                continue; // cierra el préstamo completo, no se reclasifica
            }

            if ($aInteres > 0 && $aCapital > 0) {
                $partir[] = [
                    'id' => $m->id,
                    'fecha' => substr((string) $m->fecha, 0, 10),
                    'monto_total' => $monto,
                    'capital' => $aCapital,
                    'interes' => $aInteres,
                ];

                continue;
            }

            $destino = $aInteres > 0 ? 'abono_interes' : 'abono_capital';

            // Un abono de unos pocos pesos es el arrastre de un redondeo, no un pago:
            // reetiquetarlo no cambia nada y llena la ficha de ruido.
            if ($m->tipo !== $destino && $monto >= self::RESIDUO) {
                $reclasificar[] = [
                    'id' => $m->id,
                    'fecha' => substr((string) $m->fecha, 0, 10),
                    'monto' => $monto,
                    'de' => $m->tipo,
                    'a' => $destino,
                ];
            }
        }

        return [
            'capital_vigente' => round($entregado - $pagadoCapital, 2),
            'interes_pendiente' => round($interesPendiente, 2),
            'saldo' => round($saldo, 2),
            'reclasificar' => $reclasificar,
            'partir' => $partir,
            'notas' => $this->notasContradictorias($movimientos, $reclasificar),
            'saldos_rotos' => $this->saldosRotos($prestamo),
        ];
    }

    /**
     * Escribe el plan: reclasifica, parte los abonos mixtos y fija el capital vigente.
     * El movimiento original conserva su id y su soporte con la parte de capital;
     * la parte de interés sale como movimiento nuevo.
     */
    private function aplicar(Prestamo $prestamo, array $plan): void
    {
        DB::connection('finanzas')->transaction(function () use ($prestamo, $plan) {
            foreach ($plan['reclasificar'] as $r) {
                PrestamoMovimiento::where('id', $r['id'])->update(['tipo' => $r['a']]);
            }

            foreach ($plan['partir'] as $p) {
                $original = PrestamoMovimiento::find($p['id']);

                $original->update([
                    'tipo' => 'abono_capital',
                    'monto' => $p['capital'],
                    'observacion' => trim(($original->observacion ?: '').' [Reclasificado: la parte de intereses salió a un movimiento aparte.]'),
                ]);

                PrestamoMovimiento::create([
                    'prestamo_id' => $prestamo->id,
                    'tipo' => 'abono_interes',
                    'fecha' => $original->fecha,
                    'monto' => $p['interes'],
                    'saldo_antes' => 0,
                    'saldo_despues' => 0,
                    'observacion' => 'Parte de intereses del abono del '.$p['fecha'].' (reclasificación de capital).',
                ]);
            }

            foreach ($plan['notas'] as $n) {
                PrestamoMovimiento::where('id', $n['id'])->update(['observacion' => $n['nota']]);
            }

            $prestamo->update(['monto_original' => $plan['capital_vigente']]);

            $this->recalcularSaldos($prestamo);
        });
    }

    /**
     * Reescribe `saldo_antes` / `saldo_despues` de todos los movimientos en orden,
     * para que la ficha del préstamo cuadre tras partir y reclasificar.
     *
     * Va por (fecha, id) a propósito, no en orden contable: `PrestamoController::show`
     * recalcula los saldos con ese orden cada vez que se abre la ficha, y si aquí se
     * escribiera otro los dos se estarían pisando. El orden contable es solo para
     * decidir qué parte de cada abono fue interés.
     */
    private function recalcularSaldos(Prestamo $prestamo): void
    {
        $saldo = 0.0;

        $movimientos = PrestamoMovimiento::where('prestamo_id', $prestamo->id)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        foreach ($movimientos as $m) {
            $suma = in_array($m->tipo, ['desembolso', 'capitalizacion', 'interes_mensual', 'interes_proporcional']);

            $m->saldo_antes = $saldo;
            $saldo = $suma ? $saldo + (float) $m->monto : $saldo - (float) $m->monto;
            $m->saldo_despues = $saldo;
            $m->save();
        }

        $prestamo->update(['saldo_actual' => round($saldo, 2)]);
    }

    /**
     * Notas automáticas que contradicen el tipo del movimiento.
     *
     * `registrarPago` escribe "Abono a intereses acumulados." o "Abono a capital."
     * según cómo repartió en su momento; al reclasificar, esa frase queda mintiendo en
     * la ficha. Solo se tocan esas dos frases exactas: una nota escrita a mano por el
     * usuario ("Cruce cuentas congreso") no se pisa nunca.
     */
    private function notasContradictorias($movimientos, array $reclasificar): array
    {
        $generica = [
            'abono_interes' => 'Abono a intereses acumulados.',
            'abono_capital' => 'Abono a capital.',
        ];

        $tipoFinal = collect($reclasificar)->pluck('a', 'id');
        $notas = [];

        foreach ($movimientos as $m) {
            $tipo = $tipoFinal[$m->id] ?? $m->tipo;

            if (! isset($generica[$tipo])) {
                continue;
            }

            $actual = trim((string) $m->observacion);
            $contraria = $tipo === 'abono_interes' ? $generica['abono_capital'] : $generica['abono_interes'];

            if ($actual === $contraria) {
                $notas[] = ['id' => $m->id, 'nota' => $generica[$tipo]];
            }
        }

        return $notas;
    }

    /**
     * Movimientos en orden contable: por fecha, y dentro del mismo día lo que suma
     * antes de lo que resta, y el abono a interés antes que el abono a capital.
     *
     * Sin este orden el comando no es idempotente: al partir un abono, la parte de
     * interés nace con un id mayor que la de capital, así que en una segunda pasada
     * la de capital absorbería el interés y el comando volvería a partir el mismo
     * movimiento una y otra vez.
     */
    private function enOrden(Prestamo $prestamo)
    {
        $rango = [
            'desembolso' => 0,
            'capitalizacion' => 0,
            'interes_mensual' => 1,
            'interes_proporcional' => 1,
            'abono_interes' => 2,
            'abono_capital' => 3,
            'pago_total' => 4,
        ];

        return PrestamoMovimiento::where('prestamo_id', $prestamo->id)
            ->get()
            ->sortBy([
                fn ($a, $b) => strcmp(substr((string) $a->fecha, 0, 10), substr((string) $b->fecha, 0, 10)),
                fn ($a, $b) => ($rango[$a->tipo] ?? 9) <=> ($rango[$b->tipo] ?? 9),
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->values();
    }

    private function pesos(float|int|string $v): string
    {
        return '$'.number_format((float) $v, 0, ',', '.');
    }
}
