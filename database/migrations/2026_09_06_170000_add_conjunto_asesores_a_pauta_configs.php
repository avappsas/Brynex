<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conjunto de pauta propio para las piezas de reclutamiento de asesores.
 *
 * Hasta ahora todas las piezas competían dentro del mismo conjunto, y ahí se mezclaban dos
 * públicos que no tienen nada que ver: independientes que buscan afiliarse y asesores con
 * cartera propia. Meta reparte según su propia señal —que no ve las conversaciones de
 * WhatsApp— y el 6-sep-2026 le había dado el 85% del presupuesto a la pieza que costaba
 * $12.595 por conversación, dejando en $1.825 a la que costaba $913.
 *
 * Separarlos también permite saber cuánto cuesta reclutar un asesor, dato que hoy queda
 * diluido entre el gasto de captar clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pauta_configs', function (Blueprint $table) {
            $table->string('meta_campana_asesores_id')->nullable()->after('exterior_interes_nombre');
            $table->string('meta_adset_asesores_id')->nullable()->after('meta_campana_asesores_id');
            $table->decimal('asesores_presupuesto_diario_cop', 12, 2)->nullable()->after('meta_adset_asesores_id');
        });
    }

    public function down(): void
    {
        Schema::table('pauta_configs', function (Blueprint $table) {
            $table->dropColumn([
                'meta_campana_asesores_id',
                'meta_adset_asesores_id',
                'asesores_presupuesto_diario_cop',
            ]);
        });
    }
};
