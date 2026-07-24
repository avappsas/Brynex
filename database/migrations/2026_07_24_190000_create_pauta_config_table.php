<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de pauta pagada (Meta Marketing API) por aliado: cuenta publicitaria y
 * tope de gasto mensual — el tope es un límite DURO validado en el servidor antes de crear
 * o activar cualquier campaña, nunca decidido por la IA. Empieza inactivo (activo=false):
 * activarlo es una decisión explícita del humano, no un default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pauta_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->unique();
            $table->boolean('activo')->default(false);
            $table->string('ad_account_id', 30)->nullable();
            $table->decimal('limite_mensual_cop', 12, 2)->nullable();
            $table->decimal('presupuesto_diario_default_cop', 12, 2)->default(15000);
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
        });

        Schema::table('publicaciones', function (Blueprint $table) {
            // borrador (creada en Meta pero PAUSED, $0 gastado) | activa (gastando real) |
            // pausada (el usuario la detuvo) | finalizada (alcanzó su presupuesto/fecha)
            $table->string('pauta_estado', 20)->nullable();
            $table->decimal('pauta_presupuesto_diario_cop', 12, 2)->nullable();
            $table->decimal('pauta_gasto_total_cop', 12, 2)->default(0);
            $table->string('meta_campana_id', 40)->nullable();
            $table->string('meta_adset_id', 40)->nullable();
            $table->string('meta_ad_id', 40)->nullable();
            $table->timestamp('pauta_activada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn([
                'pauta_estado', 'pauta_presupuesto_diario_cop', 'pauta_gasto_total_cop',
                'meta_campana_id', 'meta_adset_id', 'meta_ad_id', 'pauta_activada_at',
            ]);
        });
        Schema::dropIfExists('pauta_config');
    }
};
