<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tres valores de negocio que hasta ahora estaban sin configurar (heredados del plan
     * normal, o fijos en el código) — un solo valor por aliado (fila global, plan_id null),
     * no por plan:
     * - ingreso_retiro_valor_mensual: reemplaza el rango aproximado "$100.000 a $150.000"
     *   que la IA daba para la estrategia Ingreso-Retiro por un valor exacto.
     * - tiempo_parcial_costo_afiliacion: afiliación específica (más económica) para Tiempo
     *   Parcial, en vez de heredar el costo_afiliacion completo del plan normal.
     * - arl_descuento_porcentaje: el 25% del plan B de ARL (antes fijo en el código, en
     *   CotizacionPublicaService::cotizarGestionArlConDescuento).
     */
    public function up(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->decimal('ingreso_retiro_valor_mensual', 12, 2)->nullable()->after('dia_ingreso_ir');
            $table->decimal('tiempo_parcial_costo_afiliacion', 12, 2)->nullable()->after('ingreso_retiro_valor_mensual');
            $table->unsignedTinyInteger('arl_descuento_porcentaje')->nullable()->after('tiempo_parcial_costo_afiliacion');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn(['ingreso_retiro_valor_mensual', 'tiempo_parcial_costo_afiliacion', 'arl_descuento_porcentaje']);
        });
    }
};
