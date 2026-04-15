<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // NULL = facturación individual desde el contrato
            // valor = ID de la empresa que procesó el pago en lote
            $table->unsignedBigInteger('empresa_id')->nullable()->after('contrato_id')
                  ->comment('NULL=individual, valor=empresa que facturó en lote');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('empresa_id');
        });
    }
};
