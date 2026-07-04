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
        Schema::connection('finanzas')->create('finanzas_entradas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('fuente_id');
            $table->integer('anio');
            $table->integer('mes'); // 1 a 12
            $table->decimal('monto', 18, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->timestamps();

            // Clave foránea local dentro de la base de datos de finanzas
            $table->foreign('fuente_id')->references('id')->on('finanzas_fuentes_ingreso')->onDelete('cascade');

            // Índice único para evitar registros duplicados por mes/año y fuente
            $table->unique(['fuente_id', 'anio', 'mes'], 'uq_entrada_fuente_mes');
            $table->index(['user_id', 'anio', 'mes'], 'ix_entrada_user_periodo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_entradas');
    }
};
