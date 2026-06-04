<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimización de índices del módulo WhatsApp.
     *
     * Problema 1 — Query principal del sidebar:
     *   WHERE aliado_id = ? AND estado IN ('abierta', 'asignada')
     *   ORDER BY ultimo_mensaje_at DESC
     *   Los índices existentes son separados: [aliado_id, estado] y [ultimo_mensaje_at].
     *   La BD debe hacer un merge + sort. El índice compuesto resuelve todo en un solo paso.
     *
     * Problema 2 — Webhook entrante (por cada mensaje del cliente):
     *   WHERE phone_number_id = ? AND activo = 1
     *   Sin índice → full scan de la tabla whatsapp_configuracion_aliado.
     */
    public function up(): void
    {
        // Índice compuesto para la query del sidebar (lista de conversaciones activas)
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->index(
                ['aliado_id', 'estado', 'ultimo_mensaje_at'],
                'wa_conv_aliado_estado_ultimo_msg_idx'
            );
        });

        // Índice en phone_number_id para el webhook de Meta
        Schema::table('whatsapp_configuracion_aliado', function (Blueprint $table) {
            $table->index(
                ['phone_number_id', 'activo'],
                'wa_config_phone_activo_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversaciones', function (Blueprint $table) {
            $table->dropIndex('wa_conv_aliado_estado_ultimo_msg_idx');
        });

        Schema::table('whatsapp_configuracion_aliado', function (Blueprint $table) {
            $table->dropIndex('wa_config_phone_activo_idx');
        });
    }
};
