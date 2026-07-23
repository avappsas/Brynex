<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eventos mínimos de analítica propia (sin Google Analytics): visitas, clics a WhatsApp,
     * cotizaciones completadas y leads capturados. Log de eventos simple, agregado bajo
     * demanda en el dashboard del admin (MetricaService::resumen).
     */
    public function up(): void
    {
        Schema::create('pagina_metricas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('tipo', 30); // visita|clic_whatsapp|cotizacion_completada|lead_capturado
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
            $table->index(['aliado_id', 'tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagina_metricas');
    }
};
