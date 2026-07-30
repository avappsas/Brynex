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
        $newConfigs = [
            // Plan 1 (Solo EPS): Independientes (10), Independientes Mes Actual (11), UPC (13), En el Exterior (14)
            1 => [10, 11, 13, 14],

            // Plan 3 (EPS + ARL): Independientes Mes Actual (11)
            3 => [11],

            // Plan 4 (EPS + ARL + CCF): Independientes Mes Actual (11)
            4 => [11],

            // Plan 5 (EPS + ARL + AFP): Independientes Mes Actual (11)
            5 => [11],

            // Plan 6 (EPS + ARL + AFP + CCF): Independientes Mes Actual (11)
            6 => [11],

            // Plan 7 (EPS + AFP): Independientes (10), Independientes Mes Actual (11), En el Exterior (14)
            7 => [10, 11, 14],

            // Plan 8 (EPS + AFP + CCF): Independientes (10), Independientes Mes Actual (11)
            8 => [10, 11],

            // Plan 9 (Solo AFP): En el Exterior (14)
            9 => [14],
        ];

        foreach ($newConfigs as $planId => $modalidadIds) {
            foreach ($modalidadIds as $modalidadId) {
                // Check if the relation already exists to prevent duplicate key errors
                $exists = DB::table('modalidad_planes')
                    ->where('plan_id', $planId)
                    ->where('tipo_modalidad_id', $modalidadId)
                    ->exists();

                if (!$exists) {
                    DB::table('modalidad_planes')->insert([
                        'plan_id' => $planId,
                        'tipo_modalidad_id' => $modalidadId,
                        'solo_ia' => false,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $newConfigs = [
            1 => [10, 11, 13, 14],
            3 => [11],
            4 => [11],
            5 => [11],
            6 => [11],
            7 => [10, 11, 14],
            8 => [10, 11],
            9 => [14],
        ];

        foreach ($newConfigs as $planId => $modalidadIds) {
            DB::table('modalidad_planes')
                ->where('plan_id', $planId)
                ->whereIn('tipo_modalidad_id', $modalidadIds)
                ->delete();
        }
    }
};
