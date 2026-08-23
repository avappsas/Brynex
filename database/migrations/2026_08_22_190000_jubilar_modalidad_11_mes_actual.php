<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jubila la modalidad 11 ("Independientes Mes Actual").
 *
 * Lo que la 11 significaba —cotizar el mes en curso— ya vive en
 * `paga_mes_actual`, y desde la fase anterior ningún punto del código decide
 * el período mirando el id de la modalidad. Sus contratos y planos pasan a la
 * 10 conservando el flag, así que siguen cotizando el mes corriente.
 *
 * La 11 no se borra del catálogo: se desactiva. Los informes históricos que la
 * referencien por id siguen resolviendo el nombre.
 *
 * Orden importante: esta migración va DESPUÉS de que el código lea el flag. Al
 * revés, cualquier `= 11` que hubiera quedado sin migrar trataría a esta gente
 * como mes vencido y sacaría su planilla del mes equivocado.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Red de seguridad: si algo se creó con la 11 y sin el flag entre la
        // fase anterior y hoy, se marca antes de perder el rastro de la modalidad.
        DB::table('contratos')->where('tipo_modalidad_id', 11)->update(['paga_mes_actual' => true]);
        DB::table('planos')->where('tipo_modalidad_id', 11)->update(['paga_mes_actual' => true]);

        DB::table('contratos')->where('tipo_modalidad_id', 11)->update(['tipo_modalidad_id' => 10]);

        // `tipo_p` es la copia legacy de la modalidad en el plano: si se queda
        // en 11 el snapshot dice una cosa y la columna oficial otra.
        DB::table('planos')->where('tipo_modalidad_id', 11)->update([
            'tipo_modalidad_id' => 10,
            'tipo_p'            => 10,
        ]);

        DB::table('cotizaciones_prospectos')->where('modalidad_id', 11)->update(['modalidad_id' => 10]);

        // Los planes habilitados eran los mismos siete de la 10: sin modalidad
        // que los use, estas filas solo estorban.
        DB::table('modalidad_planes')->where('tipo_modalidad_id', 11)->delete();

        DB::table('tipo_modalidad')->where('id', 11)->update(['activo' => 0]);
    }

    public function down(): void
    {
        DB::table('tipo_modalidad')->where('id', 11)->update(['activo' => 1]);

        foreach ([1, 3, 4, 5, 6, 7, 8] as $planId) {
            DB::table('modalidad_planes')->insertOrIgnore([
                'tipo_modalidad_id' => 11,
                'plan_id'           => $planId,
                'solo_ia'           => 0,
            ]);
        }

        // Solo vuelven a la 11 los independientes que pagan mes actual: una Y o
        // un contrato del exterior con el mismo flag nunca fue modalidad 11.
        DB::table('contratos')->where('tipo_modalidad_id', 10)->where('paga_mes_actual', 1)
            ->update(['tipo_modalidad_id' => 11]);

        DB::table('planos')->where('tipo_modalidad_id', 10)->where('paga_mes_actual', 1)
            ->update(['tipo_modalidad_id' => 11, 'tipo_p' => 11]);
    }
};
