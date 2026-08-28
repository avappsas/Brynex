<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo par de columnas que ya tiene `eps` (ver
     * 2026_04_09_182640_add_formulario_to_eps_table): el editor visual de
     * mapeo es el mismo, solo cambia la entidad dueña del PDF.
     * Nace por el formulario de afiliación de COLPENSIONES, que no es una EPS
     * y no tenía dónde vivir.
     */
    public function up(): void
    {
        Schema::table('pensiones', function (Blueprint $table) {
            // Nombre del archivo dentro de storage/app/formularios/pensiones
            $table->string('formulario_pdf')->nullable();
            // JSON con las coordenadas de cada campo para superponer con FPDI
            $table->json('formulario_campos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pensiones', function (Blueprint $table) {
            $table->dropColumn(['formulario_pdf', 'formulario_campos']);
        });
    }
};
