<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            // Número de planilla expedido por el operador al confirmar el pago
            $table->string('numero_planilla', 80)->nullable()->after('n_plano')
                  ->comment('Número de planilla del operador (ej: SOI, APL) al confirmar pago');
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('numero_planilla');
        });
    }
};
