<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('brynex_modulos')->where('codigo', 'marketing')->exists()) {
            DB::table('brynex_modulos')->insert([
                'codigo'      => 'marketing',
                'nombre'      => 'Marketing',
                'descripcion' => 'Envío masivo de campañas publicitarias por WhatsApp: listas de contactos, lista negra y métricas por campaña.',
                'activo'      => true,
                'orden'       => 95,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('brynex_modulos')->where('codigo', 'marketing')->delete();
    }
};
