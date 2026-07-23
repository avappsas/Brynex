<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clave de Gemini, independiente del `proveedor` del asistente conversacional (que solo
     * soporta claude|openai) — se usa exclusivamente para la generación de imágenes en el
     * módulo de publicidad (Fase 4), donde el aliado puede elegir "generar con IA".
     */
    public function up(): void
    {
        Schema::table('ia_configuracion_aliado', function (Blueprint $table) {
            $table->text('gemini_api_key')->nullable()->after('api_key'); // encriptado
        });
    }

    public function down(): void
    {
        Schema::table('ia_configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn('gemini_api_key');
        });
    }
};
