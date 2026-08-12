<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de autorizaciones de tratamiento de datos para prospección comercial.
 *
 * Es un log de solo-agregar: cada otorgamiento y cada revocación es una fila nueva, nunca
 * se actualiza ni se borra la anterior. Eso es deliberado — la Ley 1581 (art. 9) y el
 * Decreto 1377 ponen la carga de la prueba en el responsable: hay que poder demostrar QUÉ
 * texto se le mostró al titular, CUÁNDO, POR QUÉ CANAL y con qué finalidad. Un flag
 * booleano en `clientes` no prueba nada.
 *
 * Una fila por (persona, canal), porque la Ley 2300 (art. 2) exige que el canal esté
 * autorizado expresamente: autorizar correo no autoriza WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos_datos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');

            // A quién. La cédula puede faltar (un prospecto que aún no es cliente), el
            // teléfono normalizado es la llave real para el canal de WhatsApp.
            $table->string('cedula', 20)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('nombre', 200)->nullable();

            // Qué autorizó. `otorgado=false` es una revocación (baja/STOP).
            $table->string('canal', 20);              // whatsapp | sms | email | llamada
            $table->string('finalidad', 40)->default('prospeccion_comercial');
            $table->boolean('otorgado')->default(true);

            // La prueba. `texto_mostrado` es literal lo que vio el titular.
            $table->text('texto_mostrado')->nullable();
            $table->string('origen', 40);             // cotizador_web | chat_ia | formulario_afiliacion | asesor | panel | mensaje_baja
            $table->json('evidencia')->nullable();    // ip, user_agent, url, usuario_id, wa_message_id...

            $table->timestamp('fecha_evento');
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');

            // El envío consulta por (aliado, teléfono, canal) en caliente: sin este índice
            // cada destinatario de un lote costaría un scan.
            $table->index(['aliado_id', 'telefono', 'canal'], 'idx_consent_tel_canal');
            $table->index(['aliado_id', 'cedula', 'canal'], 'idx_consent_ced_canal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos_datos');
    }
};
