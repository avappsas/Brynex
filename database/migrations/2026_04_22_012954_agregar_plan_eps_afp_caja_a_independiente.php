<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega el plan ID 8 (EPS + AFP + CCF) a las modalidades
     * independientes I Venc (10) e I Act (11).
     *
     * Plan ID 8: eps:1, arl:0, pension:1, caja:1
     */
    public function up(): void
    {
        $modalidades = [10, 11]; // I Venc, I Act
        $planId      = 8;        // EPS + AFP + CCF

        foreach ($modalidades as $modalId) {
            // Insertar solo si no existe ya
            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modalId)
                ->where('plan_id', $planId)
                ->exists();

            if (!$existe) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => $modalId,
                    'plan_id'           => $planId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('modalidad_planes')
            ->whereIn('tipo_modalidad_id', [10, 11])
            ->where('plan_id', 8)
            ->delete();
    }
};
