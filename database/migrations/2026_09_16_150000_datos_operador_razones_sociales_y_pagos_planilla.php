<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el operador de planilla sabe de la empresa y del pago, guardado para
 * dejar de inventarlo.
 *
 * El soporte que arma BryNex rellenaba con datos fijos lo que la razón social
 * no tenía —dirección, teléfono y representante de Brygar para todas las
 * empresas, de todos los aliados—. El API de Enlace (ARUS y Simple) devuelve la
 * ficha del aportante tal como quedó registrada allá.
 *
 * `razones_sociales`: columnas para lo que no existía (departamento y municipio
 * DANE, tipo de persona, tipo de documento del representante, exoneración) y
 * `datos_operador` con la ficha completa. El PDF lee de esa copia para salir
 * igual al del operador, sin pisar `direccion` ni `telefonos`, que los usan los
 * portales de ARL y pueden tener un dato mejor que el "5555555" de allá.
 *
 * `planillas_pago_operador`: la fecha y hora exactas del pago, una fila por
 * planilla. El API no la trae; sale del informe individual del operador, que es
 * el único que la imprime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->string('rep_tipo_doc', 4)->nullable();
            $table->string('cod_departamento', 2)->nullable();   // DANE
            $table->string('cod_municipio', 8)->nullable();      // DANE como lo da el operador: 76001000
            $table->string('tipo_persona', 1)->nullable();       // N | J
            $table->boolean('exonerado_parafiscales')->nullable();
            $table->text('datos_operador')->nullable();          // JSON de la ficha del aportante
            $table->timestamp('datos_operador_at')->nullable();
        });

        Schema::create('planillas_pago_operador', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedInteger('razon_social_id')->nullable(); // razones_sociales.id es int (legacy)
            $table->unsignedBigInteger('operador_planilla_id');
            $table->string('numero_planilla', 20);
            $table->dateTime('fecha_pago');
            $table->string('tipo_planilla', 2)->nullable();
            $table->string('periodo_cotizacion', 6)->nullable();
            $table->string('periodo_servicio', 6)->nullable();
            $table->timestamps();

            $table->unique(['aliado_id', 'numero_planilla'], 'ux_planillas_pago_op_planilla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planillas_pago_operador');

        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->dropColumn([
                'rep_tipo_doc', 'cod_departamento', 'cod_municipio', 'tipo_persona',
                'exonerado_parafiscales', 'datos_operador', 'datos_operador_at',
            ]);
        });
    }
};
