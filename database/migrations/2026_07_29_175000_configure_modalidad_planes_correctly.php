<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpiar y reconfigurar modalidad_planes para que cada plan solo tenga sus modalidades válidas.
        // Esto está conectado con la nueva feature "afiliación configurable por plan+modalidad+nivel de riesgo ARL".
        DB::table('modalidad_planes')->delete();

        $configs = [
            // Solo ARL: Gestion ARL (15), Estudiante K (-1), ARL Tipo Y (8)
            2 => [15, -1, 8],

            // EPS + ARL: Dependiente E (0), Independientes (10), Ingreso-Retiro (12)
            3 => [0, 10, 12],

            // EPS + ARL + CCF: Dependiente E (0), Independientes (10), Ingreso-Retiro (12)
            4 => [0, 10, 12],

            // EPS + ARL + AFP: Dependiente E (0), Independientes (10), Ingreso-Retiro (12), TipoE (-4)
            5 => [0, 10, 12, -4],

            // EPS + ARL + AFP + CCF: Dependiente E (0), Independientes (10), Ingreso-Retiro (12), TipoE (-4)
            6 => [0, 10, 12, -4],

            // ARL + AFP + CCF (Tiempo Parcial): 7, 14, 21, 30 días + variantes (7-14, 7-21, 14-21, 30-14)
            11 => [1, 2, 3, 4, -6, -7, -8, -9],

            // ARL + CCF (Tiempo Parcial): 7, 14, 21, 30 días + variantes (7-14, 7-21, 14-21, 30-14)
            13 => [1, 2, 3, 4, -6, -7, -8, -9],
        ];

        foreach ($configs as $planId => $modalidadIds) {
            foreach ($modalidadIds as $modalidadId) {
                DB::table('modalidad_planes')->insert([
                    'plan_id' => $planId,
                    'tipo_modalidad_id' => $modalidadId,
                    'solo_ia' => false,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: dejar solo_ia sin cambios, pero vaciar modalidades específicas de ARL planes.
        // Es destructivo, pero acceptable para rollback en dev/staging.
        DB::table('modalidad_planes')->delete();
    }
};
