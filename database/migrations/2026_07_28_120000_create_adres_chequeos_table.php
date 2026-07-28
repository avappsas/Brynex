<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chequeo del estado de seguridad social de una persona contra ADRES.
     *
     * El flujo no es de un solo paso: se abre la consulta, ADRES pide un captcha,
     * ese captcha lo resuelve una persona (el propio titular por WhatsApp, o un
     * operador desde el panel), y solo entonces se obtiene el resultado. Entre un
     * paso y otro pueden pasar minutos, así que el estado vive aquí y la sesión
     * del navegador la sostiene el worker de Node.
     *
     * autorizado_at es requisito, no adorno: sin autorización del titular no se
     * consulta el dato de nadie.
     */
    public function up(): void
    {
        Schema::create('adres_chequeos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('conversacion_id')->nullable();
            $table->unsignedBigInteger('solicitado_por')->nullable();

            $table->string('cedula', 20);
            $table->string('tipo_documento', 40)->default('Cedula de Ciudadania');

            // Constancia de que el titular autorizó la consulta de su propio dato.
            $table->dateTime('autorizado_at')->nullable();
            $table->string('autorizacion_texto', 500)->nullable();

            // pendiente | esperando_captcha | consultando | listo | fallido
            $table->string('estado', 25)->default('pendiente');
            $table->string('sesion_id', 60)->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('captcha_enviado_at')->nullable();

            $table->longText('filas')->nullable();        // JSON: períodos compensados
            $table->longText('diagnostico')->nullable();  // JSON: hallazgos interpretados
            $table->unsignedSmallInteger('total_filas')->nullable();
            $table->boolean('completo')->default(false);  // el PDF trajo todo lo que declaró la web
            $table->string('pdf_path', 255)->nullable();

            $table->string('error', 500)->nullable();

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('conversacion_id')->references('id')->on('whatsapp_conversaciones')->noActionOnDelete();
            $table->foreign('solicitado_por')->references('id')->on('users')->noActionOnDelete();

            $table->index(['aliado_id', 'estado']);
            $table->index(['aliado_id', 'cedula']);
            $table->index('sesion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adres_chequeos');
    }
};
