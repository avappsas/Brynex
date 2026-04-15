<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestiones de incapacidad: cada acción que hace el trabajador para
 * gestionar una incapacidad (llamadas, correos, radicados, etc.).
 *
 * Puede aplicar a una incapacidad específica o a toda la familia
 * (padre + prórrogas) según el flag aplica_a_familia.
 *
 * El campo estado_resultado determina el nuevo estado de la incapacidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestiones_incapacidad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incapacidad_id')->index();
            $table->unsignedBigInteger('user_id');

            // ¿Esta gestión aplica a toda la familia (padre + prórrogas)?
            $table->boolean('aplica_a_familia')->default(false);

            // Tipo de gestión realizada
            // llamada | correo | whatsapp | portal | radico | tutela
            // transcripcion_ips | respuesta_entidad | autorizacion
            // liquidacion | pago_afiliado | otro
            $table->string('tipo', 30);

            // Qué hizo el trabajador (gestión realizada)
            $table->text('tramite');

            // Resultado o respuesta obtenida
            $table->text('respuesta')->nullable();

            // Estado que queda la incapacidad tras esta gestión
            // recibido | radicado | en_tramite | autorizado | liquidado
            // pagado_afiliado | rechazado | cerrado
            $table->string('estado_resultado', 25)->nullable();

            // Fecha en que se debe volver a hacer seguimiento
            $table->date('fecha_recordar')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // ── Claves foráneas ──────────────────────────────────────────────
            $table->foreign('incapacidad_id')->references('id')->on('incapacidades')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestiones_incapacidad');
    }
};
