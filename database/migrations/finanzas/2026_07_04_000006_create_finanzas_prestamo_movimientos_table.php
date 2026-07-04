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
        Schema::connection('finanzas')->create('finanzas_prestamo_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prestamo_id');
            $table->string('tipo', 30); // desembolso | interes_mensual | capitalizacion | abono_interes | abono_capital | pago_total
            $table->date('fecha');
            $table->decimal('monto', 18, 2);
            $table->decimal('saldo_antes', 18, 2);
            $table->decimal('saldo_despues', 18, 2);
            $table->integer('dias_periodo')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            // Clave foránea interna
            $table->foreign('prestamo_id')->references('id')->on('finanzas_prestamos')->onDelete('cascade');

            $table->index(['prestamo_id', 'fecha'], 'ix_prestamo_mov_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('finanzas')->dropIfExists('finanzas_prestamo_movimientos');
    }
};
