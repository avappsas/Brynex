<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costo de afiliación configurable por (aliado, plan, modalidad, nivel de riesgo ARL) — una
     * tercera dimensión sobre lo que ya existía (configuracion_aliado.costo_afiliacion, un solo
     * valor por plan). Ejemplo real: "Solo ARL" cobra afiliación distinta según el riesgo (nivel 1
     * vs nivel 5), y el MISMO plan "EPS+ARL+AFP" cobra afiliación distinta si es Independiente vs
     * Dependiente. Sin fila configurada para una combinación exacta, se sigue usando el
     * costo_afiliacion general del plan (fallback, ver CotizacionPublicaService::cotizar()) — no
     * rompe nada mientras no se termine de configurar todo. La MENSUALIDAD sigue siendo siempre
     * calculada (CotizadorService + tarifas ARL), esta tabla es solo para el pago de afiliación.
     */
    public function up(): void
    {
        Schema::create('afiliacion_arl_modalidad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedTinyInteger('plan_id');
            $table->integer('tipo_modalidad_id');
            $table->unsignedTinyInteger('nivel_arl');
            $table->decimal('costo_afiliacion', 12, 2);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('planes_contrato')->cascadeOnDelete();

            $table->unique(['aliado_id', 'plan_id', 'tipo_modalidad_id', 'nivel_arl'], 'afiliacion_arl_modalidad_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliacion_arl_modalidad');
    }
};
