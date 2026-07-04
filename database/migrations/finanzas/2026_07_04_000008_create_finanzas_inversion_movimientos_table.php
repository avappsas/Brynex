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
        Schema::connection('finanzas')->create('finanzas_inversion_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inversion_id');
            $table->string('tipo', 20); // compra | venta | ganancia | perdida
            $table->date('fecha');
            $table->decimal('monto_cop', 18, 2);
            $table->decimal('cantidad_tokens', 18, 8)->nullable();
            $table->decimal('precio_token_cop', 18, 4)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            // Clave foránea interna
            $table->foreign('inversion_id')->references('id')->on('finanzas_inversiones')->onDelete('cascade');

            $table->index(['inversion_id', 'fecha'], 'ix_inv_mov_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_inversion_movimientos');
    }
};
