<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modalidad "Pensión 30 días" (id -11): el cliente compra únicamente el aporte
 * a pensión.
 *
 * Mismo esquema de dos planillas que Solo Caja —el paso 1 es literalmente el
 * mismo archivo— y los días salen de `dias_afp`, así que una variante de menos
 * días es otra fila, no otra línea de código. El cálculo vive en
 * PilaCotizanteDosPasos.
 */
return new class extends Migration
{
    private const ID = -11;

    public function up(): void
    {
        if (DB::table('tipo_modalidad')->where('id', self::ID)->exists()) {
            return;
        }

        DB::table('tipo_modalidad')->insert([
            'id' => self::ID,
            'tipo_modalidad' => 'PEN30',
            'observacion' => 'Pensión 30 días',
            'modalidad' => 'Solo Pensión',
            'orden' => 22,
            'activo' => 1,
            'tipo_cot' => '1',
            'sub_tipo_cot' => '0',
            'es_tiempo_parcial' => 0,
            'dias_afp' => 30,
            'descripcion' => 'Pensión 30 días — el cliente compra únicamente el aporte a pensión: '
                .'sin salud, sin caja y sin riesgos. Se paga en dos planillas encadenadas (una '
                .'planilla E con un día de pensión y una corrección N que la sube al mes completo, '
                .'donde el operador cobra solo la diferencia). Los riesgos suben con la pensión '
                .'porque el operador los amarra, pero con tarifa cero no cuestan; la corrección '
                .'lleva además un día de caja obligatorio. Solo dependientes, planilla E.',
        ]);
    }

    public function down(): void
    {
        DB::table('tipo_modalidad')->where('id', self::ID)->delete();
    }
};
