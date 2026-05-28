<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Control de envíos masivos de WhatsApp.
     *
     * whatsapp_envios_masivos: cabecera del lote (1 por acción del usuario)
     * whatsapp_envios_masivos_detalle: 1 fila por destinatario del lote
     *
     * Restricción anti-spam: no se puede enviar la misma plantilla (plantilla_id)
     *   al mismo destinatario en el mismo día calendario.
     *   → Se valida con SELECT en detalle WHERE wa_numero=X AND plantilla_id=Y AND DATE(created_at)=HOY
     */
    public function up(): void
    {
        // ── Cabecera del envío masivo ──────────────────────────────────
        Schema::create('whatsapp_envios_masivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('plantilla_id');
            $table->unsignedBigInteger('usuario_id'); // quien lanzó el envío

            // Período de cobro al que corresponde
            $table->tinyInteger('mes')->unsigned();
            $table->smallInteger('anio')->unsigned();

            // Tipo: individuales o empresas
            $table->string('tipo_envio', 20)->default('individual'); // individual | empresa

            // Contadores
            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('total_enviados')->default(0);
            $table->unsignedInteger('total_fallidos')->default(0);
            $table->unsignedInteger('total_omitidos')->default(0); // ya enviado hoy

            // Estado del proceso
            $table->string('estado', 20)->default('pendiente'); // pendiente, procesando, completado, fallido

            // Parámetros usados (snapshot para auditoría)
            $table->longText('parametros_json')->nullable();

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('plantilla_id')->references('id')->on('whatsapp_plantillas')->noActionOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->noActionOnDelete();

            $table->index(['aliado_id', 'estado']);
            $table->index(['aliado_id', 'mes', 'anio']);
        });

        // ── Detalle por destinatario ───────────────────────────────────
        Schema::create('whatsapp_envios_masivos_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('envio_id');
            $table->unsignedInteger('contrato_id')->nullable();   // int para coincidir con contratos.id
            $table->unsignedInteger('empresa_id')->nullable();    // int para coincidir con empresas.id

            $table->string('wa_numero', 25);          // número al que se envió
            $table->string('nombre_destinatario', 200);
            $table->string('estado', 20)->default('pendiente'); // pendiente, enviado, fallido, omitido

            $table->string('wa_message_id', 100)->nullable(); // ID de Meta si fue enviado
            $table->string('error', 500)->nullable();

            $table->timestamps();

            $table->foreign('envio_id')->references('id')->on('whatsapp_envios_masivos')->noActionOnDelete();

            // Índice para validación anti-spam: wa_numero + envio.plantilla_id
            $table->index(['wa_numero', 'estado']);
            $table->index('envio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_envios_masivos_detalle');
        Schema::dropIfExists('whatsapp_envios_masivos');
    }
};
