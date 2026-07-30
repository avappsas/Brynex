<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Para la modalidad En el Exterior (14) solo debe estar disponible el plan Solo AFP (9)
        DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', 14)
            ->whereIn('plan_id', [1, 7])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-agregar en caso de rollback
        foreach ([1, 7] as $planId) {
            $exists = DB::table('modalidad_planes')
                ->where('plan_id', $planId)
                ->where('tipo_modalidad_id', 14)
                ->exists();

            if (!$exists) {
                DB::table('modalidad_planes')->insert([
                    'plan_id' => $planId,
                    'tipo_modalidad_id' => 14,
                    'solo_ia' => false,
                ]);
            }
        }
    }
};
