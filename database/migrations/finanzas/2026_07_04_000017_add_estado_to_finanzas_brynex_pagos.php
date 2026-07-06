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
            $table->string('estado', 50)->default('completo')->after('monto');
            $table->decimal('saldo_pendiente', 18, 2)->default(0)->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_brynex_pagos', function (Blueprint $table) {
            $table->dropColumn(['estado', 'saldo_pendiente']);
        });
    }
};
