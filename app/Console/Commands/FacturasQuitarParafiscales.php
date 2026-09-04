<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Contrato;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve a su valor las facturas a las que se les cobró SENA e ICBF sin que
 * el aportante los debiera.
 *
 * `Contrato::pagaParafiscales()` leía `exonerado_parafiscales` filtrando por el
 * aliado del contrato y convertía el resultado a bool de una vez. El cliente
 * que arrastra un `cod_empresa` apuntando a una empresa de otro aliado —la 1,
 * sobre todo— no devolvía fila: `null` se volvía `false` y el contrato quedaba
 * tratado como NO exonerado. Entre el 3-sep-2026 y el arreglo salieron así 19
 * facturas en cuatro aliados, con $1.517.800 de más.
 *
 * Nadie pagó ese exceso: el cobrador cobró el valor real del plan. El daño es
 * que la factura quedó registrada por un total mayor y el cliente arrastra una
 * deuda que no tiene — y que el recibo del mes siguiente le muestra como saldo
 * anterior pendiente.
 *
 * Qué toca cada factura:
 *   v_parafiscales → 0
 *   total_ss, total → menos lo que valían los parafiscales
 *   saldo_proximo → se le SUMA ese mismo valor, en vez de recalcularlo de cero:
 *                   así la deuda falsa desaparece sin borrar la real que pueda
 *                   traer la factura por otro motivo.
 *
 * No toca el pago (ya está bien registrado), ni el estado, ni los planos: el
 * plano PILA no guarda parafiscales en ninguna columna.
 *
 * A quién NO toca: la factura cuyo contrato SIGUE debiendo parafiscales hoy
 * —el aportante realmente no exonerado, art. 114-1 ET—. El criterio no es una
 * consulta paralela sino `pagaParafiscales()` ya corregido, que es la fuente de
 * verdad; si mañana cambia la regla, este comando la respeta sola.
 *
 * Sin `--ejecutar` solo reporta.
 */
class FacturasQuitarParafiscales extends Command
{
    protected $signature = 'facturas:quitar-parafiscales
        {--aliado= : Solo las facturas de este aliado}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Quita de las facturas los parafiscales que se cobraron por no encontrar la empresa del cliente';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $ejecutar = (bool) $this->option('ejecutar');

        $facturas = DB::table('facturas')
            ->whereNull('deleted_at')
            ->where('v_parafiscales', '>', 0)
            ->when($aliadoId, fn ($q) => $q->where('aliado_id', $aliadoId))
            ->orderBy('aliado_id')
            ->orderBy('numero_factura')
            ->get([
                'id', 'aliado_id', 'numero_factura', 'cedula', 'contrato_id',
                'mes', 'anio', 'total', 'total_ss', 'v_parafiscales', 'estado',
                'valor_efectivo', 'valor_consignado', 'anticipo_aplicado', 'saldo_proximo',
            ]);

        $porCorregir = [];
        $legitimas = [];
        $sinContrato = [];

        foreach ($facturas as $f) {
            $contrato = $f->contrato_id ? Contrato::find($f->contrato_id) : null;

            // Sin contrato no hay a quién preguntarle si el aportante estaba
            // exonerado: se reporta y se deja quieta.
            if (! $contrato) {
                $sinContrato[] = $f;

                continue;
            }

            if ($contrato->pagaParafiscales()) {
                $legitimas[] = $f;

                continue;
            }

            $paraf = (int) $f->v_parafiscales;
            $f->nuevo_total = (int) $f->total - $paraf;
            $f->nuevo_total_ss = (int) $f->total_ss - $paraf;
            $f->nuevo_saldo = (int) $f->saldo_proximo + $paraf;
            $f->pagado = (int) $f->valor_efectivo + (int) $f->valor_consignado + (int) $f->anticipo_aplicado;
            $porCorregir[] = $f;
        }

        $this->info('Facturas con parafiscales liquidados: '.$facturas->count());
        $this->info('Por corregir (el aportante sí estaba exonerado): '.count($porCorregir));

        if ($legitimas) {
            $this->info('Correctas, se dejan como están: '.count($legitimas)
                .' ('.implode(', ', array_map(fn ($f) => '#'.$f->numero_factura, $legitimas)).')');
        }

        if ($sinContrato) {
            $this->warn('Sin contrato asociado, hay que mirarlas a mano: '
                .implode(', ', array_map(fn ($f) => '#'.$f->numero_factura, $sinContrato)));
        }

        if (! $porCorregir) {
            $this->info('Nada que corregir.');

            return self::SUCCESS;
        }

        $fmt = fn ($v) => number_format((int) $v, 0, ',', '.');

        $this->table(
            ['Factura', 'Aliado', 'Cédula', 'Período', 'Total', 'Sobra', 'Total SS', 'Pagado', 'Saldo'],
            array_map(fn ($f) => [
                $f->numero_factura,
                $f->aliado_id,
                $f->cedula,
                sprintf('%02d/%d', $f->mes, $f->anio),
                $fmt($f->total).' → '.$fmt($f->nuevo_total),
                $fmt($f->v_parafiscales),
                $fmt($f->total_ss).' → '.$fmt($f->nuevo_total_ss),
                $fmt($f->pagado),
                $fmt($f->saldo_proximo).' → '.$fmt($f->nuevo_saldo),
            ], $porCorregir)
        );

        $exceso = array_sum(array_map(fn ($f) => (int) $f->v_parafiscales, $porCorregir));
        $this->info('Se quitan '.$fmt($exceso).' repartidos en '.count($porCorregir).' facturas.');

        // Al día = lo pagado cubre el total corregido. Si alguna queda debiendo
        // después del ajuste, es una deuda real que este comando no inventa ni
        // tapa, y conviene mirarla aparte.
        $quedanDebiendo = array_filter($porCorregir, fn ($f) => $f->nuevo_saldo < 0);
        if ($quedanDebiendo) {
            $this->warn('Quedan con saldo pendiente propio (no era de los parafiscales): '
                .implode(', ', array_map(fn ($f) => '#'.$f->numero_factura.' '.$fmt($f->nuevo_saldo), $quedanDebiendo)));
        }

        if (! $ejecutar) {
            $this->comment('Simulación. Con --ejecutar se escriben los cambios.');

            return self::SUCCESS;
        }

        $corregidas = 0;

        DB::transaction(function () use ($porCorregir, &$corregidas) {
            foreach ($porCorregir as $f) {
                DB::table('facturas')->where('id', $f->id)->update([
                    'v_parafiscales' => 0,
                    'total_ss' => $f->nuevo_total_ss,
                    'total' => $f->nuevo_total,
                    'saldo_proximo' => $f->nuevo_saldo,
                    'updated_at' => now(),
                ]);

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Factura',
                    registroId: (int) $f->id,
                    descripcion: "Factura #{$f->numero_factura}: se quitaron los parafiscales "
                        ."cobrados por no encontrar la empresa del cliente bajo su aliado.",
                    detalle: [
                        'v_parafiscales' => ['antes' => (int) $f->v_parafiscales, 'despues' => 0],
                        'total' => ['antes' => (int) $f->total, 'despues' => $f->nuevo_total],
                        'total_ss' => ['antes' => (int) $f->total_ss, 'despues' => $f->nuevo_total_ss],
                        'saldo_proximo' => ['antes' => (int) $f->saldo_proximo, 'despues' => $f->nuevo_saldo],
                    ],
                    alidoId: (int) $f->aliado_id
                );

                $corregidas++;
            }
        });

        $this->info("Listo: {$corregidas} facturas corregidas.");

        return self::SUCCESS;
    }
}
