<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // ID del operador de planilla asignado al cliente (para RS independientes).
            // Referencia lógica a operadores_planilla.id (sin FK formal por compatibilidad).
            $table->unsignedInteger('operador_planilla_id')->nullable()->after('pension_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('operador_planilla_id');
        });
    }
};
