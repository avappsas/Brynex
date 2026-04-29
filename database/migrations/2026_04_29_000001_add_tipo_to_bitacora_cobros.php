<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora_cobros', function (Blueprint $table) {
            // Distingue si la gestión es de cobro normal de cartera mensual
            // o de un préstamo pendiente de períodos anteriores.
            $table->string('tipo', 20)->default('cobro')->after('observacion');
            // 'cobro'    → gestión del cobro mensual normal (módulo Cobros)
            // 'prestamo' → gestión de cobro de cartera/préstamo (módulo Préstamos)

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_cobros', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
