<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos para configurar que BryNex gestione las afiliaciones
 * de un aliado específico, con un usuarioBryNex asignado por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            // Indica si BryNex gestiona las afiliaciones de este aliado
            $table->boolean('afiliaciones_brynex')->default(false)->after('activo');
            // Usuario BryNex asignado por defecto como encargado de afiliación
            $table->unsignedBigInteger('encargado_afil_id')->nullable()->after('afiliaciones_brynex');

            $table->foreign('encargado_afil_id')
                  ->references('id')->on('users')
                  ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropForeign(['encargado_afil_id']);
            $table->dropColumn(['afiliaciones_brynex', 'encargado_afil_id']);
        });
    }
};
