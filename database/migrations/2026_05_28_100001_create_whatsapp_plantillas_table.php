<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantillas de WhatsApp aprobadas por Meta.
     *
     * Las plantillas pueden:
     *  - Crearse desde el sistema (via API de Meta) → meta_template_id se guarda
     *  - Importarse desde Meta (sincronización) → igual se guarda meta_template_id
     *
     * Botones (JSON): [
     *   {"tipo": "QUICK_REPLY", "texto": "Sí, ya pagué"},
     *   {"tipo": "URL",         "texto": "Ver deuda", "url": "https://..."},
     *   {"tipo": "PHONE_NUMBER","texto": "Llamar",    "telefono": "+57..."}
     * ]
     *
     * Variables mapa (JSON): mapea {{1}}→campo del sistema para auto-rellenar en masivos
     * Ejemplo: {"1": "cliente.nombre", "2": "factura.total", "3": "factura.fecha_vencimiento"}
     */
    public function up(): void
    {
        Schema::create('whatsapp_plantillas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');

            // Identificación
            $table->string('nombre', 150);              // nombre en Meta (snake_case)
            $table->string('nombre_display', 200);      // nombre amigable para el sistema
            $table->string('categoria', 30)->default('UTILITY'); // MARKETING, UTILITY, AUTHENTICATION
            $table->string('idioma', 10)->default('es_CO');

            // Estado (sincronizado con Meta)
            $table->string('estado', 30)->default('pending'); // pending, approved, rejected, paused, disabled
            $table->string('meta_template_id', 100)->nullable();
            $table->boolean('creado_en_meta')->default(false);

            // Contenido del mensaje
            $table->string('header_tipo', 20)->nullable(); // TEXT, IMAGE, DOCUMENT, VIDEO
            $table->text('header_valor')->nullable();      // texto del header o URL del media

            $table->text('cuerpo');           // Cuerpo con {{1}}, {{2}} etc.
            $table->string('footer', 500)->nullable();

            // Botones y variables (JSON)
            $table->longText('botones')->nullable();         // JSON de botones
            $table->longText('variables_mapa')->nullable();  // JSON mapeo variables

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('aliado_id')->references('id')->on('aliados')->onDelete('cascade');
            $table->unique(['aliado_id', 'nombre'], 'wa_plantilla_aliado_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_plantillas');
    }
};
