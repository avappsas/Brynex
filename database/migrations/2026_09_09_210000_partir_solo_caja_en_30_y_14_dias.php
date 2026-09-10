<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Solo Caja se vende en dos formas: el mes completo y media jornada del mes.
 *
 * Son la misma modalidad —mismo cálculo, mismo flujo de dos pasos— y lo único
 * que cambia es `dias_caja`, que es de donde PilaCotizanteSoloCaja saca los
 * días del aporte. Se parten en dos filas del catálogo para que quien vende no
 * tenga que acordarse de escribir los días en el contrato.
 *
 * El -5 ya existía como "Solo Caja" (migración anterior, del mismo día): se
 * renombra a "Caja 30 días" y se le fija el número.
 */
return new class extends Migration
{
    private const CAJA_30 = -5;

    private const CAJA_14 = -10;

    public function up(): void
    {
        DB::table('tipo_modalidad')->where('id', self::CAJA_30)->update([
            'tipo_modalidad' => 'CCF30',
            'observacion' => 'Caja 30 días',
            'modalidad' => 'Caja 30 días',
            'orden' => 20,
            'dias_caja' => 30,
            'descripcion' => $this->descripcion(30),
        ]);

        if (DB::table('tipo_modalidad')->where('id', self::CAJA_14)->exists()) {
            return;
        }

        DB::table('tipo_modalidad')->insert([
            'id' => self::CAJA_14,
            'tipo_modalidad' => 'CCF14',
            'observacion' => 'Caja 14 días',
            'modalidad' => 'Caja 14 días',
            'orden' => 21,
            'activo' => 1,
            'tipo_cot' => '1',
            'sub_tipo_cot' => '0',
            'es_tiempo_parcial' => 0,
            'dias_caja' => 14,
            'descripcion' => $this->descripcion(14),
        ]);
    }

    public function down(): void
    {
        DB::table('tipo_modalidad')->where('id', self::CAJA_14)->delete();

        DB::table('tipo_modalidad')->where('id', self::CAJA_30)->update([
            'tipo_modalidad' => 'CCF',
            'observacion' => 'Solo Caja',
            'modalidad' => 'Solo Caja',
            'dias_caja' => null,
        ]);
    }

    private function descripcion(int $dias): string
    {
        return "Caja {$dias} días — el cliente compra únicamente la caja de compensación, "
            ."por {$dias} días: sin salud, sin riesgos y sin pensión. Se paga en dos planillas "
            .'encadenadas (una planilla E con un día de pensión y una corrección N que anexa la '
            .'caja), porque el operador no acepta pagar la caja sola en un solo archivo. '
            .'Solo dependientes, planilla E.';
    }
};
