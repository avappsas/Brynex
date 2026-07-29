<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notas de corrección tomadas en vivo desde el simulador de conversación
     * (/brynex/ia/simulador): el entrenador marca una respuesta real de la IA como
     * incorrecta y anota qué debió decir en su lugar, para revisar después y ajustar
     * el system prompt o las reglas de una tool.
     */
    public function up(): void
    {
        Schema::create('ia_entrenamiento_notas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('creado_por')->nullable();

            $table->longText('mensaje_cliente');
            $table->longText('respuesta_ia');
            $table->longText('correccion');
            $table->longText('contexto')->nullable(); // transcripción previa, para no perder el hilo

            // pendiente | resuelta (ya se ajustó el prompt/tool por esto)
            $table->string('estado', 20)->default('pendiente');

            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->noActionOnDelete();
            $table->foreign('creado_por')->references('id')->on('users')->noActionOnDelete();

            $table->index(['aliado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_entrenamiento_notas');
    }
};
