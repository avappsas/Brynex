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
                            {--otros-fuera    : Los "otros" ya guardados en la factura NO están dentro del total: se suman tal cual, sin repartir}
                            {--solo-pago      : No toca totales ni "otros": solo reparte de nuevo el pago del lote entre las facturas}
                            {--sin-mora       : Quita la mora del lote: la asume el aliado y deja de ser deuda del cliente}
                            {--con-prestamo   : Procesar aunque el lote tenga facturas en préstamo (por defecto se rechaza)}
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

        // Un lote con prestamo tiene su propia contabilidad: `valor_prestamo` no
        // es plata recibida, es lo que el cliente queda debiendo, y el saldo se
        // calcula sin contarlo (ver CuadreDiarioController::convertirEnPrestamo).
        // Repartirlo como si fuera un pago le borra la deuda al cliente, que es
        // justo lo que paso el 10-sep-2026 en 5 lotes (142 facturas, revertidas).
        $conPrestamo = $facturas->filter(fn ($f) => $f->estado === 'prestamo' || (int) $f->valor_prestamo > 0);
        if ($conPrestamo->isNotEmpty() && ! $this->option('con-prestamo')) {
            $this->error("El lote #{$numero} tiene {$conPrestamo->count()} factura(s) en prestamo: ahi el saldo no se recalcula desde el pago.");
            $this->line('Revisar a mano, o forzar con --con-prestamo si se sabe lo que se hace.');

            return Command::FAILURE;
        }

        // Costo propio de cada factura: lo que vale sin los "otros" del lote.
        // Con --otros-fuera el total guardado YA es ese costo limpio, porque los
        // "otros" nunca se le sumaron (factura de Alfredo Martinez, ago-2026:
        // $99.000 cobrados al cliente que quedaron fuera del total y volvieron
        // como saldo a favor). Ahí no hay nada que repartir: cada factura
        // conserva los suyos y se le suman al total.
        $otrosFuera = (bool) $this->option('otros-fuera');
        $soloPago = (bool) $this->option('solo-pago');
        // Quitar la mora es una decision del aliado, no un error de calculo: si
        // no se le cobra al cliente, la asume el aliado y no puede quedar como
        // deuda arrastrada. La mora que si se cobra y no se paga se factura como
        // prestamo, que es el camino por el que alguien la cobra.
        $sinMora = (bool) $this->option('sin-mora');
        $pesos = [];
        foreach ($facturas as $f) {
            $peso = ($otrosFuera || $soloPago)
                ? (int) $f->total
                : (int) $f->total - (int) $f->otros - (int) $f->otros_admon;
            $pesos[$f->id] = max(0, $peso - ($sinMora ? (int) $f->mora : 0));
        }
        $base = array_sum($pesos);

        if ($soloPago) {
            // El lote se cobró bien, pero el pago se repartió mal: una factura de
            // retiro pesaba como un mes entero y se llevó plata que era de las
            // demás, dejando saldos a favor y deudas que no existen. Aquí los
            // totales no se tocan — solo se reparte el dinero como corresponde.
            $otrosNuevos = $facturas->pluck('otros', 'id')->map(fn ($v) => (int) $v)->all();
            $otrosAdmonNuevos = $facturas->pluck('otros_admon', 'id')->map(fn ($v) => (int) $v)->all();
            $totalesNuevos = $pesos;   // el peso ya es el total (menos la mora, si se quita)
        } elseif ($otrosFuera && ($otrosLote > 0 || $otrosAdmonLote > 0)) {
            // Los "otros" quedaron copiados enteros en cada factura del lote: el
            // valor del lote se cobra UNA vez, repartido sobre los totales
            // actuales (que no lo incluyen). Sumar lo guardado factura por
            // factura multiplicaria el cobro por el numero de facturas.
            $otrosNuevos = $this->repartir($otrosLote, $pesos, $base);
            $otrosAdmonNuevos = $this->repartir($otrosAdmonLote, $pesos, $base);
        } elseif ($otrosFuera) {
            $otrosNuevos = $facturas->pluck('otros', 'id')->map(fn ($v) => (int) $v)->all();
            $otrosAdmonNuevos = $facturas->pluck('otros_admon', 'id')->map(fn ($v) => (int) $v)->all();
        } else {
            $otrosNuevos = $this->repartir($otrosLote, $pesos, $base);
            $otrosAdmonNuevos = $this->repartir($otrosAdmonLote, $pesos, $base);
        }

        if (! $soloPago) {
            $totalesNuevos = [];
            foreach ($pesos as $id => $peso) {
                $totalesNuevos[$id] = $peso + $otrosNuevos[$id] + $otrosAdmonNuevos[$id];
            }
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
            // El prestamo no entra: es deuda, no plata recibida.
            $pagado = $consigNuevo[$id] + $efectivoNuevo[$id] + $anticipoNuevo[$id];
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
        $otrosDespues = ($otrosFuera || $soloPago)
            ? array_sum($otrosNuevos) + array_sum($otrosAdmonNuevos)
            : $otrosLote + $otrosAdmonLote;
        $this->line('Otros lote:   '.($facturas->sum('otros') + $facturas->sum('otros_admon')).' → '.$otrosDespues
            .($soloPago ? '  (sin cambio)' : ($otrosFuera ? '  (los mismos, ahora dentro del total)' : '')));
        if ($sinMora) {
            $this->line('Mora lote:    '.$facturas->sum('mora').' → 0  (la asume el aliado)');
        }
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
            'mora' => (int) $f->mora,
            'total' => (int) $f->total,
            'valor_consignado' => (int) $f->valor_consignado,
            'valor_efectivo' => (int) $f->valor_efectivo,
            'valor_prestamo' => (int) $f->valor_prestamo,
            'anticipo_aplicado' => (int) $f->anticipo_aplicado,
            'saldo_proximo' => (int) $f->saldo_proximo,
        ])->all();

        DB::transaction(function () use ($facturas, $otrosNuevos, $otrosAdmonNuevos, $totalesNuevos, $consigNuevo, $efectivoNuevo, $prestamoNuevo, $anticipoNuevo, $sinMora) {
            foreach ($facturas as $f) {
                $id = $f->id;
                $pagado = $consigNuevo[$id] + $efectivoNuevo[$id] + $anticipoNuevo[$id];
                $f->update([
                    'mora' => $sinMora ? 0 : (int) $f->mora,
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
