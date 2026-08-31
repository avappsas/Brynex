<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de movimientos de cada contrato ante ARL Sura.
 *
 * Va en tabla propia y no en columnas de `contratos` porque la modalidad 15 es un
 * ciclo mensual —afiliar, retirar, volver a afiliar— y unas columnas sueltas
 * sobrescribirían el mes anterior. Aquí cada movimiento queda con su
 * `codigo_transaccion`, que es lo que Sura pide cuando hay que reclamar algo.
 *
 * `payload` y `respuesta` se guardan para poder reconstruir qué se envió el día
 * que una afiliación salga mal: el API no tiene ambiente de pruebas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arl_afiliaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedInteger('contrato_id'); // contratos.id es int (legacy)
            $table->unsignedInteger('razon_social_id'); // razones_sociales.id es int (legacy)
            $table->string('cedula', 20);

            $table->string('operacion', 20);          // afiliacion | retiro | anulacion
            $table->string('estado', 20);             // exitosa | fallida | anulada
            $table->string('poliza', 20);
            $table->string('tipo_afiliado', 1)->nullable();      // D | E | I
            $table->string('tipo_cotizante', 2)->nullable();     // 01, 51, 59...
            $table->string('codigo_centro', 20)->nullable();
            $table->unsignedTinyInteger('nivel_riesgo')->nullable();

            $table->date('fecha_inicio_cobertura')->nullable();
            $table->date('fecha_fin_cobertura')->nullable();

            $table->string('codigo_transaccion', 30)->nullable();
            $table->timestamp('fecha_proceso')->nullable();

            $table->text('payload')->nullable();      // JSON enviado
            $table->text('respuesta')->nullable();    // JSON recibido
            $table->string('mensaje_error', 500)->nullable();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('contrato_id')->references('id')->on('contratos');
            $table->foreign('razon_social_id')->references('id')->on('razones_sociales');

            $table->index(['contrato_id', 'operacion'], 'ix_arl_afi_contrato_op');
            $table->index(['aliado_id', 'estado'], 'ix_arl_afi_aliado_estado');
            $table->index('cedula', 'ix_arl_afi_cedula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arl_afiliaciones');
    }
};
