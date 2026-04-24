<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina las columnas saldo_a_favor y saldo_pendiente de la tabla facturas.
 *
 * Estas columnas eran redundantes: el sistema siempre ha calculado el saldo
 * acumulativo dinámicamente desde saldo_proximo. Solo saldo_proximo se usa
 * en todos los cálculos reales del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['saldo_a_favor', 'saldo_pendiente']);
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->integer('saldo_a_favor')->default(0)->after('saldo_proximo');
            $table->integer('saldo_pendiente')->default(0)->after('saldo_a_favor');
        });
    }
};
