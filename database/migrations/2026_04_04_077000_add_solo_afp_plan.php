<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar plan "Solo AFP" si no existe
        if (!DB::table('planes_contrato')->where('codigo', 'SOLO_AFP')->exists()) {
            DB::table('planes_contrato')->insert([
                'codigo'           => 'SOLO_AFP',
                'nombre'           => 'Solo AFP',
                'incluye_eps'      => 0,
                'incluye_arl'      => 0,
                'incluye_pension'  => 1,
                'incluye_caja'     => 0,
                'activo'           => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('planes_contrato')->where('codigo', 'SOLO_AFP')->delete();
    }
};
