<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el id que Meta asigna a cada Custom Audience creada, por segmento.
 *
 * Sin esto, cada sincronización crearía una audiencia nueva en la cuenta publicitaria en vez
 * de reemplazar los datos de la existente: se acumularían duplicados y las campañas quedarían
 * apuntando a una audiencia vieja que ya nadie actualiza.
 *
 * Forma: {"ventana_dorada": "23851...", "ex_clientes": "23852..."}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->json('audiencias')->nullable()->after('ad_account_id');
            $table->timestamp('audiencias_sync_at')->nullable()->after('audiencias');
        });
    }

    public function down(): void
    {
        Schema::table('pauta_config', function (Blueprint $table) {
            $table->dropColumn(['audiencias', 'audiencias_sync_at']);
        });
    }
};
