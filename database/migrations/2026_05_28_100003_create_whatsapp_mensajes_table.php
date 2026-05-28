<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mensajes individuales de WhatsApp.
     *
     * Soporta todos los tipos de media de Meta Cloud API:
     *  - text:     solo contenido texto
     *  - image:    imagen JPG/PNG — descargada y guardada en storage/whatsapp/
     *  - audio:    audio OGG/MP3 — descargado y guardado en storage/whatsapp/
     *  - document: PDF u otros — descargado y guardado en storage/whatsapp/
     *  - video:    video MP4 — descargado y guardado en storage/whatsapp/
     *  - template: mensaje de plantilla aprobada — guarda parametros en JSON
     *
     * Dirección:
     *  - entrante: el cliente envió el mensaje a nosotros
     *  - saliente: nosotros enviamos el mensaje al cliente
     *
     * Estado (solo para mensajes salientes):
     *  - enviado:    confirmado por Meta (wa_message_id existe)
     *  - entregado:  webhook de Meta confirma delivery
     *  - leido:      webhook de Meta confirma lectura (doble check azul)
     *  - fallido:    error al enviar
     */
    public function up(): void
    {
        Schema::create('whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversacion_id');
            $table->unsignedBigInteger('aliado_id');

            // Identificador de Meta (para webhooks de estado)
            $table->string('wa_message_id', 100)->nullable();

            // Tipo y dirección
            $table->string('direccion', 10);  // entrante | saliente
            $table->string('tipo', 20);       // text, image, audio, document, video, template

            // Contenido de texto
            $table->longText('contenido')->nullable();

            // Media
            $table->string('media_url', 500)->nullable();      // path relativo en storage
            $table->string('media_mime_type', 100)->nullable();
            $table->string('media_nombre', 300)->nullable();    // nombre original del archivo
            $table->string('media_wa_id', 100)->nullable();     // ID de Meta para descargar

            // Plantilla (si tipo = template)
            $table->unsignedBigInteger('plantilla_id')->nullable();
            $table->longText('plantilla_parametros')->nullable(); // JSON de parámetros

            // Estado del mensaje (solo salientes)
            $table->string('estado', 20)->nullable(); // enviado, entregado, leido, fallido
            $table->dateTime('estado_at')->nullable();

            // Usuario que envió (null si fue el cliente)
            $table->unsignedBigInteger('usuario_id')->nullable();

            // Errores
            $table->text('error_detalle')->nullable();

            $table->timestamps();

            $table->foreign('conversacion_id')->references('id')->on('whatsapp_conversaciones')->noActionOnDelete();
            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('plantilla_id')->references('id')->on('whatsapp_plantillas')->noActionOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->noActionOnDelete();

            // Índice para búsqueda por wa_message_id (webhooks de estado)
            $table->index('wa_message_id');
            $table->index(['conversacion_id', 'created_at']);
            $table->index(['aliado_id', 'direccion', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensajes');
    }
};
