<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos de afiliación enviados a los asesores de las EPS (plan B cuando el
 * portal no deja, e independientes).
 *
 * Cada correo guarda su Message-ID: el agente del buzón (fase 2) reconoce la
 * respuesta por In-Reply-To/References y la pega al radicado. `vence_at` es
 * hasta cuándo se espera respuesta antes de avisar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correos_afiliacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedInteger('contrato_id');             // contratos.id es int (legacy)
            $table->unsignedBigInteger('radicado_id')->nullable();
            $table->string('entidad', 30);                      // sos
            $table->string('motivo', 30);                       // portal_rechazo | independiente | manual
            $table->string('buzon', 120);                       // cuenta que envía
            $table->string('para', 500);
            $table->string('cc', 500)->nullable();
            $table->string('asunto', 300);
            $table->text('cuerpo');
            $table->text('adjuntos')->nullable();               // JSON [{nombre, ruta, tipo}]
            $table->string('message_id', 255);
            $table->string('estado', 20)->default('enviado');   // enviado | respondido | observaciones | sin_respuesta | fallido
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('vence_at')->nullable();
            $table->timestamp('respondido_at')->nullable();
            $table->string('respuesta_de', 120)->nullable();
            $table->text('respuesta_resumen')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('contrato_id')->references('id')->on('contratos');
            $table->index(['estado', 'vence_at'], 'ix_correos_afi_estado');
            $table->index('message_id', 'ix_correos_afi_msgid');
            $table->index(['contrato_id', 'entidad'], 'ix_correos_afi_contrato');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correos_afiliacion');
    }
};
