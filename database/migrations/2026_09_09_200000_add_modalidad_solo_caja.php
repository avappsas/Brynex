<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modalidad "Solo Caja" (id -5): pagar el mes completo de caja de compensación
 * sin salud, sin riesgos y sin pensión, en dos planillas encadenadas.
 *
 * Es una fila de catálogo, no un cambio de estructura: `tipo_modalidad` no es
 * auto-incremental y los ids negativos son las modalidades especiales, así que
 * el -5 se escribe a mano igual que el -4 (E-1). El cálculo vive en
 * PilaCotizanteSoloCaja y el flujo de dos pasos en PlanillaSoloCajaService.
 */
return new class extends Migration
{
    private const ID = -5;

    public function up(): void
    {
        if (DB::table('tipo_modalidad')->where('id', self::ID)->exists()) {
            return;
        }

        DB::table('tipo_modalidad')->insert([
            'id' => self::ID,
            'tipo_modalidad' => 'CCF',
            'observacion' => 'Solo Caja',
            'orden' => 20,
            'modalidad' => 'Solo Caja',
            'activo' => 1,
            'tipo_cot' => '1',
            'sub_tipo_cot' => '0',
            'es_tiempo_parcial' => 0,
            'descripcion' => 'Solo Caja — el cliente compra únicamente la caja de compensación: '
                .'sin salud, sin riesgos y sin pensión. Se paga en dos planillas encadenadas '
                .'(una planilla E con un día de pensión y una corrección N que sube la caja al mes '
                .'completo), porque el operador no acepta pagar la caja sola en un solo archivo. '
                .'Solo dependientes, planilla E.',
        ]);
    }

    public function down(): void
    {
        DB::table('tipo_modalidad')->where('id', self::ID)->delete();
    }
};
