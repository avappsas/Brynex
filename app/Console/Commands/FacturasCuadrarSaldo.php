<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pone el saldo de la factura a decir lo mismo que sus propios números:
 * lo recibido menos lo que vale.
 *
 * No toca el total, ni los "Otros", ni un peso de lo pagado: solo reescribe
 * `saldo_proximo`. Y solo en las facturas donde el saldo le cobra al cliente
 * MÁS de lo que muestran sus cifras, porque en la otra dirección suele ser un
 * crédito de la empresa consumiéndose, que el sistema graba así queriendo (ver
 * FacturacionController::facturar, saldoEmpresaAplicar).
 *
 * Dos guardas, aprendidas a golpes el 10-sep-2026:
 *   • el lote no puede tener facturas en préstamo — ahí `valor_prestamo` es
 *     deuda, no plata recibida, y el saldo se lleva por otra vía;
 *   • la consignación registrada en el banco tiene que coincidir con lo que
 *     dicen las facturas del lote. Si no coinciden, el pago también puede estar
 *     mal y recalcular el saldo sobre él solo consolida el error.
 */
class FacturasCuadrarSaldo extends Command
{
    protected $signature = 'facturas:cuadrar-saldo
                            {--dias=120  : Ventana hacia atrás, en días}
                            {--aliado=   : Limitar a un aliado}
                            {--dry-run   : Mostrar qué se corregiría sin escribir}
                            {--force     : Aplicar sin preguntar}';

    protected $description = 'Corrige el saldo de las facturas cuyo saldo guardado le cobra al cliente más de lo que dicen sus propios números, cuando el banco confirma el pago.';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $aliado = $this->option('aliado') ? (int) $this->option('aliado') : null;
        $desde = now()->subDays($dias)->startOfDay();

        $candidatas = DB::select("
            SELECT f.id, f.aliado_id, f.numero_factura, f.cedula, f.mes, f.anio, f.total,
                   f.valor_consignado + f.valor_efectivo + f.anticipo_aplicado AS recibido,
                   f.saldo_proximo
            FROM facturas f
            WHERE f.deleted_at IS NULL AND f.estado <> 'anulada' AND f.created_at >= ?
              AND f.saldo_proximo < (f.valor_consignado + f.valor_efectivo + f.anticipo_aplicado - f.total)
              ".($aliado ? 'AND f.aliado_id = '.$aliado : '')."
            ORDER BY f.aliado_id, f.numero_factura
        ", [$desde]);

        if (! $candidatas) {
            $this->info('No hay facturas por cuadrar.');

            return self::SUCCESS;
        }

        // Se decide por lote: el banco confirma el lote entero, no una factura suelta.
        $porLote = [];
        foreach ($candidatas as $c) {
            $porLote[$c->aliado_id.':'.$c->numero_factura][] = $c;
        }

        $aplicar = [];
        $descartes = [];
        foreach ($porLote as $clave => $facturas) {
            [$al, $nf] = explode(':', $clave);

            $lote = DB::table('facturas')->where('aliado_id', $al)->where('numero_factura', $nf)
                ->whereNull('deleted_at')->where('estado', '<>', 'anulada')
                ->get(['id', 'estado', 'valor_consignado', 'valor_prestamo']);

            if ($lote->contains(fn ($f) => $f->estado === 'prestamo' || (int) $f->valor_prestamo > 0)) {
                $descartes[] = "#{$nf} (aliado {$al}): tiene préstamo";

                continue;
            }

            $banco = (int) DB::table('consignaciones')->whereIn('factura_id', $lote->pluck('id'))
                ->whereNull('deleted_at')->sum('valor');
            $registrado = (int) $lote->sum('valor_consignado');

            if ($banco === 0) {
                $descartes[] = "#{$nf} (aliado {$al}): sin consignación con qué cruzar";

                continue;
            }
            if ($banco !== $registrado) {
                $descartes[] = "#{$nf} (aliado {$al}): el banco dice \$".number_format($banco, 0, ',', '.')
                    .' y las facturas \$'.number_format($registrado, 0, ',', '.');

                continue;
            }

            foreach ($facturas as $f) {
                $aplicar[] = $f;
            }
        }

        $this->info($this->option('dry-run') ? '🔍 DRY-RUN — no se escribe nada' : '⚙️  Escribiendo en producción');
        $this->line('Facturas por cuadrar: '.count($aplicar).' (de '.count($candidatas).' candidatas, en '.count($porLote).' lotes)');
        $this->newLine();

        $filas = [];
        $movido = 0;
        foreach ($aplicar as $f) {
            $nuevo = (int) $f->recibido - (int) $f->total;
            $movido += abs($nuevo - (int) $f->saldo_proximo);
            $filas[] = [$f->id, $f->aliado_id, $f->numero_factura, $f->cedula,
                (int) $f->total, (int) $f->recibido, (int) $f->saldo_proximo.' → '.$nuevo];
        }
        $this->table(['factura', 'aliado', 'recibo', 'cédula', 'total', 'recibido', 'saldo'], array_slice($filas, 0, 25));
        if (count($filas) > 25) {
            $this->line('... y '.(count($filas) - 25).' más');
        }
        $this->line('Saldo corregido en total: $'.number_format($movido, 0, ',', '.'));

        if ($descartes) {
            $this->newLine();
            $this->warn('Lotes que NO se tocan ('.count($descartes).'):');
            foreach (array_slice($descartes, 0, 15) as $d) {
                $this->line('  '.$d);
            }
            if (count($descartes) > 15) {
                $this->line('  ... y '.(count($descartes) - 15).' más');
            }
        }

        if ($this->option('dry-run') || ! $aplicar) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Aplicar sobre la base de producción?', false)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $antes = array_map(fn ($f) => [
            'id' => $f->id, 'saldo_proximo' => (int) $f->saldo_proximo,
            'recibido' => (int) $f->recibido, 'total' => (int) $f->total,
        ], $aplicar);

        DB::transaction(function () use ($aplicar) {
            foreach ($aplicar as $f) {
                DB::table('facturas')->where('id', $f->id)->update([
                    'saldo_proximo' => (int) $f->recibido - (int) $f->total,
                    'updated_at' => now(),
                ]);
            }
        });

        Bitacora::registrar(
            accion: 'updated',
            modelo: 'Factura',
            registroId: (int) $aplicar[0]->id,
            descripcion: 'Saldo cuadrado con lo recibido menos el total en '.count($aplicar)
                .' factura(s), en lotes donde la consignación del banco confirma el pago. No se tocó ningún total ni pago.',
            detalle: ['antes' => $antes],
            alidoId: (int) $aplicar[0]->aliado_id
        );

        $this->info('✅ '.count($aplicar).' factura(s) cuadradas.');

        return self::SUCCESS;
    }
}
