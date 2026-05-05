<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambia gastos.valor de integer (32-bit, máx ~2.1B)
     * a bigint (64-bit, máx ~9.2 trillones).
     *
     * Necesario para registrar ajustes de apertura bancaria
     * con saldos históricos migrados que superan 2.147.483.647.
     */
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->bigInteger('valor')->change();
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->integer('valor')->change();
        });
    }
};
