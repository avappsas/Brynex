<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Planes permitidos para Independiente (I Venc=10, I Act=11):
     *
     *  ID 1  → Solo EPS            (si AFP no es obligatorio)
     *  ID 3  → EPS + ARL           (si AFP no es obligatorio)
     *  ID 4  → EPS + ARL + CCF     (si AFP no es obligatorio)
     *  ID 7  → EPS + AFP
     *  ID 6  → EPS + ARL + AFP + CCF
     *
     *  ID 9  → Solo AFP  — SOLO para Exterior (ID 14), NO para I Venc / I Act
     */
    public function up(): void
    {
        $modalidadesIndep = [10, 11]; // I Venc, I Act

        // Planes que deben quedar para ambas modalidades independientes
        $planesDeseados = [1, 3, 4, 6, 7];

        foreach ($modalidadesIndep as $modalId) {
            // Borrar todos los planes actuales de esta modalidad
            DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modalId)
                ->delete();

            // Insertar los planes correctos
            foreach ($planesDeseados as $planId) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => $modalId,
                    'plan_id'           => $planId,
                ]);
            }
        }

        // Asegurar que Ext (ID 14) solo tenga Solo AFP (ID 9)
        DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', 14)
            ->delete();

        DB::table('modalidad_planes')->insert([
            'tipo_modalidad_id' => 14,
            'plan_id'           => 9,
        ]);
    }

    public function down(): void
    {
        $modalidadesIndep = [10, 11];

        // Restaurar estado anterior: Solo EPS (1), EPS+AFP (7), Solo AFP (9)
        foreach ($modalidadesIndep as $modalId) {
            DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modalId)
                ->delete();

            foreach ([1, 7, 9] as $planId) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => $modalId,
                    'plan_id'           => $planId,
                ]);
            }
        }

        // Restaurar Ext (14) → Solo AFP (9)
        DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', 14)
            ->delete();

        DB::table('modalidad_planes')->insert([
            'tipo_modalidad_id' => 14,
            'plan_id'           => 9,
        ]);
    }
};
