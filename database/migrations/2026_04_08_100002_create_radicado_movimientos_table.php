<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de movimientos/bitácora general.
 * Registra cada cambio de estado en afiliaciones y, en el futuro,
 * otros tipos de trámite (incapacidades, tutelas, derechos de petición,
 * inclusión de beneficiarios, traslados de EPS, etc.).
 *
 * tipo_proceso:
 *   afiliacion | incapacidad | tutela | derecho_peticion
 *   inclusion_beneficiario | traslado_eps | otro
 *
 * entidad (cuando aplica):
 *   eps | arl | caja | pension | clinica | otro
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radicado_movimientos', function (Blueprint $table) {
            $table->id();

            // Radicado al que pertenece (null para trámites sin afiliación directa)
            $table->unsignedBigInteger('radicado_id')->nullable();

            // Contrato asociado (SIEMPRE requerido)
            $table->unsignedInteger('contrato_id');

            // Tipo de proceso
            $table->string('tipo_proceso', 40)->default('afiliacion');
            // afiliacion | incapacidad | tutela | derecho_peticion
            // inclusion_beneficiario | traslado_eps | otro

            // Entidad involucrada (eps, arl, caja, pension, clinica, etc.)
            $table->string('entidad', 40)->nullable();

            // Quién hizo el movimiento
            $table->unsignedBigInteger('user_id')->nullable();

            // Estados
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20)->nullable();

            // Observación del movimiento
            $table->text('observacion')->nullable();

            // Solo fecha de creación (el movimiento es inmutable)
            $table->timestamp('created_at')->useCurrent();

            // ── Foreign Keys ──
            $table->foreign('radicado_id')
                  ->references('id')->on('radicados')
                  ->noActionOnDelete();

            $table->foreign('contrato_id')
                  ->references('id')->on('contratos')
                  ->noActionOnDelete();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->noActionOnDelete();

            // Índices
            $table->index(['contrato_id', 'tipo_proceso']);
            $table->index(['radicado_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radicado_movimientos');
    }
};
