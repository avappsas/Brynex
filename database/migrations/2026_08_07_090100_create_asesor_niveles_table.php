<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Niveles de asesor por aliado (ver docs/plan-tarifario-asesores.md).
     *
     * Un nivel es una PLANTILLA de comisiones: agrupa asesores por tamaño de cartera
     * ("Nivel 1: 1 a 10 contratos vigentes") y guarda cuánto gana un asesor de ese nivel.
     * Al asignarle el nivel a un asesor, sus tarifas se COPIAN a asesor_tarifas y desde ahí
     * son libres — el nivel no es un piso ni se re-aplica solo.
     *
     * admon_asesor vive aquí y no en la matriz: en la práctica es el mismo valor en todos los
     * planes, así que se pide una sola vez por nivel (queda ajustable por asesor después).
     *
     * contratos_min/max son solo para SUGERIR el nivel en pantalla; nada se reasigna solo.
     */
    public function up(): void
    {
        Schema::create('asesor_niveles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);

            // Rango de contratos vigentes que sugiere este nivel. max null = "sin tope".
            $table->unsignedInteger('contratos_min')->default(0);
            $table->unsignedInteger('contratos_max')->nullable();

            // Comisión mensual de administración del asesor: un solo valor para todos los planes.
            $table->decimal('admon_asesor', 12, 2)->default(0);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->index(['aliado_id', 'orden'], 'asesor_niveles_aliado_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesor_niveles');
    }
};
