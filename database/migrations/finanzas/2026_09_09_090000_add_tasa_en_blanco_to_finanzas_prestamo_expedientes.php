<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite imprimir los documentos con la tasa en blanco, para escribirla a
 * mano al momento de la firma. Cuando está activo, ninguna cifra del paquete
 * puede delatar la tasa: van en blanco también el interés mensual en pesos del
 * contrato, la tasa del pagaré, la de la carta de instrucciones y la del recibo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamo_expedientes', function (Blueprint $table) {
            $table->boolean('tasa_en_blanco')->default(false)->after('tasa_interes_mensual');
        });
    }

    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamo_expedientes', function (Blueprint $table) {
            $table->dropColumn('tasa_en_blanco');
        });
    }
};
