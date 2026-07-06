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
        Schema::connection('finanzas')->create('finanzas_app_lideres_recibos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('banco', 100);
            $table->date('fecha_pago');
            $table->decimal('monto_total', 18, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->string('soporte_path', 255)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::connection('finanzas')->create('finanzas_app_lideres_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('app_lider_aliado_id');
            $table->unsignedBigInteger('recibo_id')->nullable();
            $table->integer('anio');
            $table->integer('mes'); // 1 a 12
            $table->decimal('monto', 18, 2)->default(0);
            $table->string('estado', 30)->default('completo'); // completo | pendiente
            $table->decimal('saldo_pendiente', 18, 2)->default(0);
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->unique(['app_lider_aliado_id', 'anio', 'mes'], 'uq_app_lider_pago_aliado_mes');
            $table->index(['user_id', 'anio', 'mes'], 'ix_app_lider_pago_user_periodo');

            $table->foreign('app_lider_aliado_id')->references('id')->on('finanzas_app_lideres_aliados')->onDelete('cascade');
            $table->foreign('recibo_id')->references('id')->on('finanzas_app_lideres_recibos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_app_lideres_pagos');
        Schema::connection('finanzas')->dropIfExists('finanzas_app_lideres_recibos');
    }
};
