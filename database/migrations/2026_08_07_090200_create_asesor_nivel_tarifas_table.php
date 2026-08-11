<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matriz de la plantilla: cuánto gana de AFILIACIÓN un asesor de este nivel, por
     * (plan, modalidad, nivel de riesgo ARL). Ver docs/plan-tarifario-asesores.md.
     *
     * Una sola cifra por celda a propósito: el precio público, el retiro y los "otros" se
     * heredan de afiliacion_arl_modalidad (Parámetros), y lo del aliado es el resto:
     *
     *   aliado = público − retiro − otros − afil_asesor      (mínimo 0)
     *
     * Así, si mañana sube el precio público, el asesor sigue ganando lo mismo y el aumento
     * se lo queda el aliado. La admon del asesor NO está aquí: es única por nivel.
     *
     * tipo_modalidad_id es integer con signo: los ids de tipo_modalidad no son IDENTITY y hay
     * negativos (-1 Estudiante K, -6..-9 Tiempo Parcial). Sin FK por lo mismo.
     */
    public function up(): void
    {
        Schema::create('asesor_nivel_tarifas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asesor_nivel_id');
            $table->unsignedTinyInteger('plan_id');
            $table->integer('tipo_modalidad_id');
            $table->unsignedTinyInteger('nivel_arl');

            $table->decimal('afil_asesor', 12, 2)->default(0);

            $table->timestamps();

            $table->foreign('asesor_nivel_id')->references('id')->on('asesor_niveles')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('planes_contrato');

            $table->unique(
                ['asesor_nivel_id', 'plan_id', 'tipo_modalidad_id', 'nivel_arl'],
                'asesor_nivel_tarifa_unica'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesor_nivel_tarifas');
    }
};
