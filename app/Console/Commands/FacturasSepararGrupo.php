<?php

namespace App\Console\Commands;

use App\Models\Factura;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Saca una factura del `numero_factura` que comparte con otras y le da uno
 * propio.
 *
 * Para qué: la factura electrónica se emite por `numero_factura`, y un grupo
 * tiene que tener un solo adquiriente —si no, se le cobraría a una empresa
 * plata que no es suya—. Cuando dos clientes distintos comparten número, la
 * selección retiene el grupo entero y nadie factura nada.
 *
 * De dónde salen esos choques: la numeración del sistema viejo y la de BryNex
 * corrieron en paralelo y las dos llegaron a los mismos números. La migración
 * trajo la factura del legacy conservando su `Factura` como `numero_factura`, y
 * cayó encima de una que BryNex ya había creado con ese número para otro
 * cliente. En 2026 pasó dos veces: los grupos 12506 y 12508.
 *
 * El número nuevo lo entrega `factura_secuencias`, la misma fuente que usa la
 * facturación en lote. Tomarlo de `MAX(numero_factura) + 1` habría chocado con
 * la siguiente factura que alguien creara.
 *
 * Con `--consignacion` se le liga además su pago. Hace falta cuando la plata de
 * esa factura está registrada pero suelta —`factura_id` en null—, que es lo
 * normal en lo que vino del legacy: sin ese vínculo la factura queda separada
 * pero sin pago que la respalde, y la emisión no la ve.
 *
 * Sin `--ejecutar` solo reporta.
 */
class FacturasSepararGrupo extends Command
{
    protected $signature = 'facturas:separar-grupo
        {--aliado= : Aliado dueño de la factura}
        {--factura= : Id de la factura que se saca del grupo}
        {--consignacion=* : Id de consignación que se le liga (puede repetirse)}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Le da numero_factura propio a una factura que lo comparte con otro adquiriente';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $facturaId = (int) $this->option('factura');

        if (! $aliadoId || ! $facturaId) {
            $this->error('Faltan --aliado y --factura.');

            return self::FAILURE;
        }

        $f = DB::table('facturas')->where('id', $facturaId)->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')->first();

        if (! $f) {
            $this->error("La factura {$facturaId} no existe en el aliado {$aliadoId}.");

            return self::FAILURE;
        }

        $hermanas = DB::table('facturas')->where('aliado_id', $aliadoId)
            ->where('numero_factura', $f->numero_factura)->whereNull('deleted_at')
            ->where('id', '!=', $facturaId)
            ->get(['id', 'cedula', 'empresa_id', 'admon', 'afiliacion', 'fecha_pago']);

        if ($hermanas->isEmpty()) {
            $this->warn("La factura {$facturaId} ya tiene el número {$f->numero_factura} para ella sola.");

            return self::SUCCESS;
        }

        // Si el grupo ya se emitió, separarlo permitiría facturar dos veces la
        // misma plata: una en la electrónica vieja y otra en la nueva.
        $emitido = DB::table('dataico_envios')->where('aliado_id', $aliadoId)
            ->where('numero_factura', $f->numero_factura)->where('estado', 'enviado')->exists();

        if ($emitido) {
            $this->error("El grupo {$f->numero_factura} ya tiene factura electrónica emitida. Separarlo ahora");
            $this->error('emitiría dos veces la misma plata. Hay que anular la electrónica primero.');

            return self::FAILURE;
        }

        $base = fn ($x) => (int) $x->admon + (int) $x->afiliacion;

        $this->line("grupo {$f->numero_factura}:");
        $this->line(sprintf('  SE SACA   factura %-7s cédula %-12s empresa %-5s base %s  pago %s',
            $f->id, $f->cedula, $f->empresa_id ?: '—', number_format($base($f)), substr((string) $f->fecha_pago, 0, 10)));

        foreach ($hermanas as $h) {
            $this->line(sprintf('  se queda  factura %-7s cédula %-12s empresa %-5s base %s  pago %s',
                $h->id, $h->cedula, $h->empresa_id ?: '—', number_format($base($h)), substr((string) $h->fecha_pago, 0, 10)));
        }

        $consignaciones = collect($this->option('consignacion'))->map(fn ($x) => (int) $x)->filter();
        $aLigar = collect();

        foreach ($consignaciones as $id) {
            $c = DB::table('consignaciones')->where('id', $id)->where('aliado_id', $aliadoId)
                ->whereNull('deleted_at')->first();

            if (! $c) {
                $this->error("  consignación {$id}: no existe en este aliado.");

                return self::FAILURE;
            }

            if ($c->factura_id !== null) {
                $this->error("  consignación {$id}: ya está ligada a la factura {$c->factura_id}.");

                return self::FAILURE;
            }

            $this->line(sprintf('  se liga   consig  #%-6s %-11s cuenta %-5s ref %s',
                $c->id, number_format($c->valor), $c->banco_cuenta_id ?? '—', $c->referencia));
            $aLigar->push($c->id);
        }

        if (! $this->option('ejecutar')) {
            $this->warn('SIMULACIÓN — no se escribió nada. Agregue --ejecutar.');

            return self::SUCCESS;
        }

        $nuevo = DB::transaction(function () use ($aliadoId, $facturaId, $aLigar) {
            $nuevo = Factura::siguienteNumero($aliadoId);

            DB::table('facturas')->where('id', $facturaId)->update(['numero_factura' => $nuevo]);

            if ($aLigar->isNotEmpty()) {
                DB::table('consignaciones')->whereIn('id', $aLigar->all())
                    ->update(['factura_id' => $facturaId]);
            }

            return $nuevo;
        });

        $this->info("factura {$facturaId}: número {$f->numero_factura} → {$nuevo}"
                   .($aLigar->isNotEmpty() ? '  ('.$aLigar->count().' consignación/es ligadas)' : ''));

        return self::SUCCESS;
    }
}
