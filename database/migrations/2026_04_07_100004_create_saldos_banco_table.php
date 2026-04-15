<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_banco', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('banco_cuenta_id');
            $table->date('fecha');
            $table->string('tipo', 30); // entrada|salida|transferencia_entrada|transferencia_salida
            $table->string('descripcion', 500);
            $table->unsignedBigInteger('cuadre_id')->nullable();
            $table->unsignedBigInteger('gasto_id')->nullable();
            $table->unsignedBigInteger('factura_id')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->integer('valor');
            $table->integer('saldo_acumulado')->default(0);
            $table->timestamps();

            $table->index(['aliado_id', 'banco_cuenta_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_banco');
    }
};
