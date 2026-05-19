<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_asesores', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id');
            $table->unsignedBigInteger('asesor_id');
            $table->integer('valor');                        // monto pagado
            $table->date('fecha');                           // cuándo se pagó
            $table->string('tipo', 20);                      // 'efectivo' | 'banco'
            $table->unsignedBigInteger('banco_cuenta_id')->nullable(); // solo si tipo=banco
            $table->unsignedSmallInteger('periodo_mes');     // período al que aplica
            $table->unsignedSmallInteger('periodo_anio');
            $table->string('observacion', 500)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable(); // quién registró

            $table->timestamps();

            $table->foreign('asesor_id')->references('id')->on('asesores');
            $table->foreign('banco_cuenta_id')->references('id')->on('banco_cuentas');
            $table->foreign('usuario_id')->references('id')->on('users');

            $table->index(['aliado_id', 'asesor_id']);
            $table->index(['aliado_id', 'periodo_anio', 'periodo_mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_asesores');
    }
};
