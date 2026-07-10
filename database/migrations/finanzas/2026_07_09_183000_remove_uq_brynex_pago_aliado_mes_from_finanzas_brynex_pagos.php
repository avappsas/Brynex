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
        Schema::connection('finanzas')->table('finanzas_brynex_pagos', function (Blueprint $table) {
            $table->dropUnique('uq_brynex_pago_aliado_mes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_brynex_pagos', function (Blueprint $table) {
            $table->unique(['aliado_id', 'anio', 'mes'], 'uq_brynex_pago_aliado_mes');
        });
    }
};
