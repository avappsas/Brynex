<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversaciones de WhatsApp por aliado.
     *
     * Una conversación es la relación entre el aliado y un cliente (wa_contact_id).
     * Puede estar asociada a un contrato (cliente individual) o a una empresa.
     *
     * Estados:
     *  - abierta:   en el inbox general, sin asignar
     *  - asignada:  tiene usuario responsable (asignado_a)
     *  - cerrada:   archivada, no aparece en inbox activo
     *
     * ventana_activa_hasta: Meta permite responder libremente hasta 24h después
     *   del último mensaje RECIBIDO del cliente. Fuera de esa ventana, solo plantillas.
     */
    public function up(): void
    {
        Schema::create('whatsapp_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');

            // Contacto de WhatsApp (número del cliente)
            $table->string('wa_contact_id', 25);    // Número E.164: +57300...
            $table->string('nombre_contacto', 200)->nullable();

            // Vínculos opcionales con el sistema
            $table->unsignedInteger('contrato_id')->nullable();  // cliente individual
            $table->unsignedInteger('empresa_id')->nullable();   // contacto empresa

            // Estado de la conversación
            $table->string('estado', 20)->default('abierta'); // abierta, asignada, cerrada
            $table->unsignedBigInteger('asignado_a')->nullable(); // user_id del agente

            // Métricas y control de ventana
            $table->dateTime('ultimo_mensaje_at')->nullable();
            $table->dateTime('ventana_activa_hasta')->nullable(); // now() + 24h en cada msg entrante
            $table->unsignedInteger('total_mensajes_no_leidos')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
            // FK a asignado_a sin cascada para evitar paths múltiples en SQL Server
            $table->foreign('asignado_a')->references('id')->on('users')->noActionOnDelete();
            // contrato_id y empresa_id no tienen FK de BD (compatibilidad SQL Server + tipos int)
            // La integridad se valida a nivel de aplicación

            // Un número solo puede tener una conversación activa por aliado
            $table->unique(['aliado_id', 'wa_contact_id'], 'wa_conv_aliado_contacto_unique');

            // Índices de rendimiento
            $table->index(['aliado_id', 'estado']);
            $table->index(['aliado_id', 'asignado_a']);
            $table->index('ultimo_mensaje_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversaciones');
    }
};
