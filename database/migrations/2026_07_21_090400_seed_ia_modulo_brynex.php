<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('brynex_modulos')->where('codigo', 'ia_asistente')->exists()) {
            DB::table('brynex_modulos')->insert([
                'codigo'      => 'ia_asistente',
                'nombre'      => 'Asistente IA',
                'descripcion' => 'Asistente virtual con IA: cotiza planes, responde preguntas de seguridad social y ayuda a navegar el sistema.',
                'activo'      => true,
                'orden'       => 90,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('brynex_modulos')->where('codigo', 'ia_asistente')->delete();
    }
};
