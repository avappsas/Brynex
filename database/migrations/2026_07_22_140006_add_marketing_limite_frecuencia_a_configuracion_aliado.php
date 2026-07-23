<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Límite de frecuencia de campañas de marketing por número, configurable por aliado
     * (además de la regla fija: la misma campaña nunca se repite al mismo número). Protege
     * la reputación del número de WhatsApp — Meta puede degradar/suspender números con
     * tasas altas de bloqueo por spam. NULL en marketing_max_campanas = sin límite.
     */
    public function up(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->unsignedInteger('marketing_max_campanas')->nullable()->after('dia_ingreso_ir');
            $table->unsignedInteger('marketing_dias_periodo')->nullable()->default(30)->after('marketing_max_campanas');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $table->dropColumn(['marketing_max_campanas', 'marketing_dias_periodo']);
        });
    }
};
