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
        Schema::connection('finanzas')->create('finanzas_brynex_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('aliado_id'); // Id del aliado de la tabla principal
            $table->integer('anio');
            $table->integer('mes'); // 1 a 12
            $table->decimal('monto', 18, 2)->default(0);
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            // Evitar duplicados del mismo aliado, año y mes
            $table->unique(['aliado_id', 'anio', 'mes'], 'uq_brynex_pago_aliado_mes');
            $table->index(['user_id', 'anio', 'mes'], 'ix_brynex_pago_user_periodo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_brynex_pagos');
    }
};
