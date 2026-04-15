<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de radicados: historial completo del proceso de afiliación
 * por contrato. Un contrato genera automáticamente registros pendiente
 * según los servicios que incluye su plan.
 *
 * Estados del radicado:
 *   pendiente   → contrato creado, aún no se ha iniciado trámite
 *   en_tramite  → se inició el proceso con la entidad
 *   confirmado  → la entidad confirmó la afiliación
 *   rechazado   → la entidad rechazó el trámite
 *
 * Canal de envío (cómo se hizo el trámite):
 *   web | correo | asesor | presencial | otro
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radicados', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('contrato_id');        // FK → contratos
            $table->unsignedBigInteger('aliado_id')->nullable();

            // Tipo de entidad
            $table->string('tipo', 20);  // eps | arl | caja | pension

            // Número de radicado asignado por la entidad
            $table->string('numero_radicado', 80)->nullable();

            // Estado del proceso
            $table->string('estado', 20)->default('pendiente');
            // pendiente | en_tramite | confirmado | rechazado

            // Canal por el que se realizó el trámite
            $table->string('canal_envio', 30)->nullable();
            // web | correo | asesor | presencial | otro

            // ─ Envío al cliente ─
            $table->boolean('enviado_al_cliente')->default(false);
            $table->string('canal_envio_cliente', 30)->nullable(); // correo | whatsapp | fisica | otro
            $table->datetime('fecha_envio_cliente')->nullable();

            // ─ Fechas de seguimiento ─
            $table->datetime('fecha_inicio_tramite')->nullable(); // Cuando pasó a en_tramite
            $table->datetime('fecha_confirmacion')->nullable();   // Cuando se confirmó

            // ─ Auditoría ─
            $table->unsignedBigInteger('user_id')->nullable();   // FK → users (quien registró el cambio)
            $table->text('observacion')->nullable();

            $table->timestamps();

            // ─ Foreign Keys ─
            $table->foreign('contrato_id')->references('id')->on('contratos')->onDelete('cascade');
            $table->foreign('aliado_id')->references('id')->on('aliados')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->noActionOnDelete();

            // Índices
            $table->index(['contrato_id', 'tipo']);
            $table->index(['estado']);
            $table->index(['enviado_al_cliente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radicados');
    }
};
