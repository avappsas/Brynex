<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Factura;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrección puntual: un lote donde los "otros" del modal no se repartieron
 * completos porque las facturas de retiro facturable se creaban con otros=0
 * (y además pesaban como un mes entero en el reparto del pago).
 *
 * Reparte de nuevo, sobre el costo real ya guardado en cada factura:
 *   peso_i   = total_i - otros_i - otros_admon_i
 *   otros_i  = proporción de --otros / --otros-admon sobre ese peso
 *   total_i  = peso_i + otros_i + otros_admon_i
 *   pago_i   = proporción de lo efectivamente pagado en el lote
 *   saldo_i  = pago_i - total_i
 *
 * No mueve plata: reparte la misma que ya está registrada en el lote.
 */
class FacturasRecalcularLoteOtros extends Command
{
    protected $signature = 'facturas:recalcular-lote-otros
                            {aliado : ID del aliado}
                            {numero : numero_factura del lote}
                            {--otros=0        : Valor de "Otros planilla" del lote (el del modal)}
                            {--otros-admon=0  : Valor de "Otros admón" del lote}
                            {--dry-run        : Mostrar el recálculo sin escribir}
                            {--force          : Aplicar sin preguntar (para correrlo sin terminal interactiva)}';

    protected $description = 'Reparte de nuevo los "otros" y el pago de un lote de facturas cuyo reparto quedó incompleto por facturas de retiro.';

    public function handle(): int
    {
        $aliadoId = (int) $this->argument('aliado');
        $numero = (int) $this->argument('numero');
        $otrosLote = (int) $this->option('otros');
        $otrosAdmonLote = (int) $this->option('otros-admon');
        $dry = (bool) $this->option('dry-run');

        $facturas = Factura::where('aliado_id', $aliadoId)
            ->where('numero_factura', $numero)
            ->whereNull('deleted_at')
            ->where('estado', '!=', 'anulada')
            ->orderBy('id')
            ->get();

        if ($facturas->isEmpty()) {
            $this->error("No hay facturas vivas con numero_factura={$numero} en el aliado {$aliadoId}.");

            return Command::FAILURE;
        }

        // Costo propio de cada factura: lo que vale sin los "otros" del lote.
        $pesos = [];
        foreach ($facturas as $f) {
            $pesos[$f->id] = max(0, (int) $f->total - (int) $f->otros - (int) $f->otros_admon);
        }
        $base = array_sum($pesos);

        $otrosNuevos = $this->repartir($otrosLote, $pesos, $base);
        $otrosAdmonNuevos = $this->repartir($otrosAdmonLote, $pesos, $base);

        $totalesNuevos = [];
        foreach ($pesos as $id => $peso) {
            $totalesNuevos[$id] = $peso + $otrosNuevos[$id] + $otrosAdmonNuevos[$id];
        }
        $baseTotal = array_sum($totalesNuevos);

        // El dinero del lote es el que ya está registrado: solo se reparte distinto.
        $pagoConsig = (int) $facturas->sum('valor_consignado');
        $pagoEfectivo = (int) $facturas->sum('valor_efectivo');
        $pagoPrestamo = (int) $facturas->sum('valor_prestamo');
        $pagoAnticipo = (int) $facturas->sum('anticipo_aplicado');

        $consigNuevo = $this->repartir($pagoConsig, $totalesNuevos, $baseTotal);
        $efectivoNuevo = $this->repartir($pagoEfectivo, $totalesNuevos, $baseTotal);
        $prestamoNuevo = $this->repartir($pagoPrestamo, $totalesNuevos, $baseTotal);
        $anticipoNuevo = $this->repartir($pagoAnticipo, $totalesNuevos, $baseTotal);

        $this->info($dry ? '🔍 DRY-RUN — no se escribe nada' : '⚙️  Escribiendo en producción');
        $this->line("Lote #{$numero} (aliado {$aliadoId}) — {$facturas->count()} facturas");
        $this->newLine();

        $filas = [];
        foreach ($facturas as $f) {
            $id = $f->id;
            $pagado = $consigNuevo[$id] + $efectivoNuevo[$id] + $prestamoNuevo[$id] + $anticipoNuevo[$id];
            $filas[] = [
                $id,
                $f->cedula,
                (int) $f->otros.' → '.$otrosNuevos[$id],
                (int) $f->total.' → '.$totalesNuevos[$id],
                (int) $f->valor_consignado.' → '.$consigNuevo[$id],
                (int) $f->saldo_proximo.' → '.($pagado - $totalesNuevos[$id]),
            ];
        }
        $this->table(['factura', 'cédula', 'otros', 'total', 'consignado', 'saldo'], $filas);

        $this->line('Total lote:   '.$facturas->sum('total').' → '.$baseTotal);
        $this->line('Otros lote:   '.($facturas->sum('otros') + $facturas->sum('otros_admon')).' → '.($otrosLote + $otrosAdmonLote));
        $this->line('Pagado lote:  '.($pagoConsig + $pagoEfectivo + $pagoPrestamo + $pagoAnticipo).' (sin cambio)');
        $this->line('Saldo lote:   '.$facturas->sum('saldo_proximo').' → '.($pagoConsig + $pagoEfectivo + $pagoPrestamo + $pagoAnticipo - $baseTotal));

        if ($dry) {
            return Command::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Aplicar estos cambios sobre la base de producción?', false)) {
            $this->warn('Cancelado.');

            return Command::SUCCESS;
        }

        $antes = $facturas->map(fn ($f) => [
            'id' => $f->id,
            'otros' => (int) $f->otros,
            'otros_admon' => (int) $f->otros_admon,
            'total' => (int) $f->total,
            'valor_consignado' => (int) $f->valor_consignado,
            'valor_efectivo' => (int) $f->valor_efectivo,
            'valor_prestamo' => (int) $f->valor_prestamo,
            'anticipo_aplicado' => (int) $f->anticipo_aplicado,
            'saldo_proximo' => (int) $f->saldo_proximo,
        ])->all();

        DB::transaction(function () use ($facturas, $otrosNuevos, $otrosAdmonNuevos, $totalesNuevos, $consigNuevo, $efectivoNuevo, $prestamoNuevo, $anticipoNuevo) {
            foreach ($facturas as $f) {
                $id = $f->id;
                $pagado = $consigNuevo[$id] + $efectivoNuevo[$id] + $prestamoNuevo[$id] + $anticipoNuevo[$id];
                $f->update([
                    'otros' => $otrosNuevos[$id],
                    'otros_admon' => $otrosAdmonNuevos[$id],
                    'total' => $totalesNuevos[$id],
                    'valor_consignado' => $consigNuevo[$id],
                    'valor_efectivo' => $efectivoNuevo[$id],
                    'valor_prestamo' => $prestamoNuevo[$id],
                    'anticipo_aplicado' => $anticipoNuevo[$id],
                    'saldo_proximo' => $pagado - $totalesNuevos[$id],
                ]);
            }
        });

        Bitacora::registrar(
            accion: 'updated',
            modelo: 'Factura',
            registroId: $facturas->first()->id,
            descripcion: "Lote #{$this->argument('numero')} recalculado: reparto de \"otros\" y del pago corregido (facturas de retiro que quedaron en otros=0).",
            detalle: ['antes' => $antes],
            alidoId: (int) $this->argument('aliado')
        );

        $this->info('✅ Lote recalculado.');

        return Command::SUCCESS;
    }

    /**
     * Mismo criterio que _repartirProporcional() de FacturacionController:
     * round por parte y el último se lleva el residuo, para que la suma cuadre.
     */
    private function repartir(int $monto, array $pesos, int $base): array
    {
        $claves = array_keys($pesos);
        $partes = array_fill_keys($claves, 0);
        if ($monto === 0 || $claves === []) {
            return $partes;
        }
        $n = count($claves);
        $acum = 0;
        foreach ($claves as $i => $k) {
            if ($i === $n - 1) {
                $partes[$k] = $monto - $acum;
                break;
            }
            $parte = $base > 0 ? (int) round($monto * $pesos[$k] / $base) : intdiv($monto, $n);
            $partes[$k] = $parte;
            $acum += $parte;
        }

        return $partes;
    }
}
