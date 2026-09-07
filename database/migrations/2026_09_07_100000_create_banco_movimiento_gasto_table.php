<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre las salidas del extracto y los gastos del libro.
 *
 * Gemela de `banco_movimiento_consignacion`, para el otro lado del cuadre: las
 * consignaciones explican la plata que entra, los gastos la que sale. Se
 * mantienen separadas porque un movimiento nunca es las dos cosas y mezclarlas
 * obligaría a preguntar el tipo en cada consulta.
 *
 * También es muchos-a-muchos: un pago de planilla grande sale partido en
 * varias transferencias, y una sola transferencia puede cubrir dos gastos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banco_movimiento_gasto', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();
            $table->unsignedBigInteger('banco_movimiento_id');
            $table->unsignedBigInteger('gasto_id');

            $table->decimal('valor_aplicado', 18, 2);
            $table->string('regla', 30);
            $table->integer('dias_diferencia')->default(0);
            $table->unsignedInteger('usuario_id')->nullable();   // null = automático
            $table->timestamps();

            $table->foreign('banco_movimiento_id')->references('id')->on('banco_movimientos');

            $table->unique(['banco_movimiento_id', 'gasto_id'], 'ux_bmg_movimiento_gasto');
            $table->index('gasto_id', 'ix_bmg_gasto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_movimiento_gasto');
    }
};
