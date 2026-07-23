<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campaña de marketing: agrupa una plantilla con el contexto que la IA necesita para
     * atender a quien responda (qué se promociona, el objetivo, y qué significa cada botón)
     * sin tener que arrancar la conversación desde cero. Cada tanda de envío
     * (whatsapp_envios_masivos) se vincula a una campaña vía campana_id.
     */
    public function up(): void
    {
        Schema::create('marketing_campanas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('plantilla_id');

            $table->string('nombre', 150);
            $table->text('descripcion_ia'); // qué se promociona, para que la IA arranque enfocada
            $table->string('objetivo', 500)->nullable(); // ej. "cotizar y afiliar", "agendar llamada"
            $table->longText('guia_botones')->nullable(); // JSON {texto_boton: instruccion_para_la_ia}

            $table->boolean('incluir_clientes_vigentes')->default(false);
            $table->string('estado', 20)->default('activa'); // activa | pausada | finalizada

            $table->unsignedBigInteger('creado_por')->nullable();

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('plantilla_id')->references('id')->on('whatsapp_plantillas')->noActionOnDelete();
            $table->foreign('creado_por')->references('id')->on('users')->noActionOnDelete();

            $table->index(['aliado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campanas');
    }
};
