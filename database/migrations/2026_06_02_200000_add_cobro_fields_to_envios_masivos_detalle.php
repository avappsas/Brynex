<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega a whatsapp_envios_masivos_detalle:
     *  - valor_cobro     : valor pre-calculado del cobro del cliente en el lote.
     *  - contrato_ids_json: JSON con los IDs de contratos agrupados cuando se
     *                       unificaron múltiples contratos de planilla del mismo número.
     */
    public function up(): void
    {
        Schema::table('whatsapp_envios_masivos_detalle', function (Blueprint $table) {
            // Valor del cobro calculado al momento de crear el lote (para {{6}})
            $table->decimal('valor_cobro', 15, 2)->nullable()->after('nombre_destinatario');

            // JSON de IDs de contratos combinados ej: "[12, 35]" cuando se unifica planilla
            $table->string('contrato_ids_json', 500)->nullable()->after('valor_cobro');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_envios_masivos_detalle', function (Blueprint $table) {
            $table->dropColumn(['valor_cobro', 'contrato_ids_json']);
        });
    }
};
