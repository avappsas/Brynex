<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pone el desglose de los abonos del lado que dice su forma de pago.
 *
 * El modal de abonos pedía el valor y además el desglose, y dejaba la casilla
 * de efectivo con el saldo aunque la ocultara: una consignación viajaba con
 * `valor_efectivo` = valor y `valor_consignado` = 0. Ese desglose lo leen el
 * cuadre diario, los informes y la facturación electrónica, así que la plata
 * que entró al banco figuraba como efectivo en caja.
 *
 * La regla es la misma que aplica hoy el formulario: en efectivo el valor va
 * todo a efectivo, en consignación todo a consignado, y el mixto es el único
 * que trae el desglose escrito.
 *
 * Los mixtos que no cuadran NO se tocan: ahí sí hay dos cifras reales y cuál
 * está mal no se puede adivinar. Se reportan para revisarlos a mano.
 *
 * Lo que este comando no puede reponer es a qué cuenta entró cada consignación
 * (`banco_cuenta_id`): eso no está en ninguna parte del registro y se completa
 * desde la ficha del abono.
 *
 * Sin `--ejecutar` solo reporta.
 */
class AbonosNormalizarDesglose extends Command
{
    protected $signature = 'abonos:normalizar-desglose
        {--aliado= : Solo los abonos de las facturas de este aliado}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Cuadra valor_efectivo y valor_consignado con la forma de pago del abono';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $ejecutar = (bool) $this->option('ejecutar');

        $abonos = DB::table('abonos as a')
            ->join('facturas as f', 'f.id', '=', 'a.factura_id')
            ->when($aliadoId, fn ($q) => $q->where('f.aliado_id', $aliadoId))
            ->select(
                'a.id', 'a.factura_id', 'a.forma_pago', 'a.fecha',
                'a.valor', 'a.valor_efectivo', 'a.valor_consignado',
                'a.banco_cuenta_id', 'f.aliado_id'
            )
            ->orderBy('a.id')
            ->get();

        $porCorregir = [];
        $mixtosDescuadrados = [];

        foreach ($abonos as $ab) {
            $valor = (int) $ab->valor;
            $efectivo = (int) $ab->valor_efectivo;
            $consignado = (int) $ab->valor_consignado;

            if ($ab->forma_pago === 'mixto') {
                if ($efectivo + $consignado !== $valor) {
                    $mixtosDescuadrados[] = $ab;
                }

                continue;
            }

            $nuevoEfectivo = $ab->forma_pago === 'efectivo' ? $valor : 0;
            $nuevoConsignado = $ab->forma_pago === 'consignacion' ? $valor : 0;

            if ($efectivo !== $nuevoEfectivo || $consignado !== $nuevoConsignado) {
                $ab->nuevo_efectivo = $nuevoEfectivo;
                $ab->nuevo_consignado = $nuevoConsignado;
                $porCorregir[] = $ab;
            }
        }

        $this->info('Abonos revisados: '.$abonos->count());
        $this->info('Con el desglose al revés: '.count($porCorregir));

        if ($mixtosDescuadrados) {
            $this->warn('Mixtos que no suman el valor del abono (se dejan como están): '
                .implode(', ', array_map(fn ($a) => '#'.$a->id, $mixtosDescuadrados)));
        }

        if (! $porCorregir) {
            $this->info('Nada que corregir.');

            return self::SUCCESS;
        }

        $this->table(
            ['Abono', 'Aliado', 'Fecha', 'Forma', 'Valor', 'Efectivo', 'Consignado', 'Cuenta'],
            array_map(fn ($a) => [
                $a->id,
                $a->aliado_id,
                $a->fecha,
                $a->forma_pago,
                number_format((int) $a->valor, 0, ',', '.'),
                (int) $a->valor_efectivo.' → '.$a->nuevo_efectivo,
                (int) $a->valor_consignado.' → '.$a->nuevo_consignado,
                $a->banco_cuenta_id ?: '— sin cuenta —',
            ], $porCorregir)
        );

        $sinCuenta = collect($porCorregir)
            ->filter(fn ($a) => $a->forma_pago === 'consignacion' && ! $a->banco_cuenta_id)
            ->count();

        if ($sinCuenta) {
            $this->warn("De esas, {$sinCuenta} consignaciones no tienen cuenta registrada. "
                .'Ese dato no está en ninguna parte: hay que ponerlo a mano desde la ficha del abono.');
        }

        if (! $ejecutar) {
            $this->comment('Simulación. Con --ejecutar se escriben los cambios.');

            return self::SUCCESS;
        }

        $corregidos = 0;

        DB::transaction(function () use ($porCorregir, &$corregidos) {
            foreach ($porCorregir as $ab) {
                DB::table('abonos')->where('id', $ab->id)->update([
                    'valor_efectivo' => $ab->nuevo_efectivo,
                    'valor_consignado' => $ab->nuevo_consignado,
                    'updated_at' => now(),
                ]);

                Bitacora::registrar(
                    accion: 'updated',
                    modelo: 'Abono',
                    registroId: (int) $ab->id,
                    descripcion: "Desglose del abono #{$ab->id} cuadrado con su forma de pago ({$ab->forma_pago}).",
                    detalle: [
                        'valor_efectivo' => ['antes' => (int) $ab->valor_efectivo, 'despues' => $ab->nuevo_efectivo],
                        'valor_consignado' => ['antes' => (int) $ab->valor_consignado, 'despues' => $ab->nuevo_consignado],
                    ],
                    alidoId: (int) $ab->aliado_id
                );

                $corregidos++;
            }
        });

        $this->info("Abonos corregidos: {$corregidos}.");

        return self::SUCCESS;
    }
}
