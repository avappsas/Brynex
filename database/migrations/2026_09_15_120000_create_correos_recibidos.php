<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agente del buzón de afiliaciones (fase 2).
 *
 * - `correos_recibidos`: cada correo que llega de una entidad al buzón del
 *   aliado, lo que el agente entendió (respuesta del asesor, vacaciones, otra
 *   entidad…) y lo que hizo o dejó para revisar. El Message-ID evita procesar
 *   dos veces el mismo correo.
 * - `correos_afiliacion.avisado_vencido_at`: para avisar una sola vez cuando el
 *   asesor no respondió a tiempo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correos_recibidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('buzon', 120);
            $table->string('message_id', 255);
            $table->unsignedBigInteger('uid')->nullable();
            $table->string('in_reply_to', 500)->nullable();
            $table->text('referencias')->nullable();
            $table->string('de', 200);
            $table->string('de_nombre', 200)->nullable();
            $table->string('asunto', 500)->nullable();
            $table->timestamp('recibido_at')->nullable();
            $table->text('texto')->nullable();                   // sin lo citado, recortado
            $table->text('adjuntos')->nullable();                // JSON [{nombre, ruta, tamano}]
            $table->string('clasificacion', 30);                 // respuesta_asesor | vacaciones | radicado_entidad | otra_entidad | otro
            $table->string('entidad', 30)->nullable();
            $table->unsignedBigInteger('correo_afiliacion_id')->nullable();
            $table->unsignedInteger('contrato_id')->nullable();  // contratos.id es int (legacy)
            $table->unsignedBigInteger('radicado_id')->nullable();
            $table->string('estado', 20);                        // aplicado | por_revisar | informativo | ignorado
            $table->string('accion', 500)->nullable();
            $table->unsignedBigInteger('revisado_por')->nullable();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->index(['buzon', 'message_id'], 'ix_correos_rec_msgid');
            $table->index(['aliado_id', 'estado', 'recibido_at'], 'ix_correos_rec_estado');
            $table->index('correo_afiliacion_id', 'ix_correos_rec_enviado');
        });

        Schema::table('correos_afiliacion', function (Blueprint $table) {
            $table->timestamp('avisado_vencido_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('correos_afiliacion', function (Blueprint $table) {
            $table->dropColumn('avisado_vencido_at');
        });
        Schema::dropIfExists('correos_recibidos');
    }
};
