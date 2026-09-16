<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si el número de planilla que se confirmó en BryNex cruza con el operador.
 *
 * Al bajar los soportes del operador salió que decenas de planillas
 * confirmadas no existen allá: "INFORMATIVO", "33", un NIT, números de quince
 * dígitos. Nadie se enteraba porque el soporte de BryNex se genera igual con
 * cualquier número. Aquí queda el resultado de preguntarle al operador, una
 * fila por planilla, para mostrarlo en planos y en el historial del cliente.
 *
 * Va aparte de `planillas_pago_operador`, que solo tiene las que sí cruzaron
 * (con su fecha de pago, que no puede ir vacía).
 *
 * estado: encontrada | no_encontrada | invalida | sin_acceso | verificando | error
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planillas_verificacion_operador', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('operador_planilla_id')->nullable();
            $table->string('numero_planilla', 80);
            $table->string('estado', 20);
            $table->string('mensaje', 500)->nullable();
            $table->timestamp('verificada_at')->nullable();
            $table->timestamps();

            $table->unique(['aliado_id', 'numero_planilla'], 'ux_planillas_verif_op_planilla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planillas_verificacion_operador');
    }
};
