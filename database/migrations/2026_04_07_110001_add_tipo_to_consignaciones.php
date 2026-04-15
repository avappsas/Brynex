<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            // Tipo de consignación: pago de cliente, traslado de efectivo al banco,
            // entrada por transferencia banco→banco, o pago de gasto desde banco
            $table->string('tipo', 30)->default('cliente')->after('valor');
            // factura_id puede ser null ahora (para traslados internos)
            $table->unsignedBigInteger('factura_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
