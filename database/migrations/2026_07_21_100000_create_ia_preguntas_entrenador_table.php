<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_preguntas_entrenador', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->nullable();
            $table->unsignedBigInteger('conversacion_id')->nullable();
            $table->text('pregunta');
            $table->text('respuesta')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente|respondida|descartada
            $table->unsignedBigInteger('respondido_por')->nullable();
            $table->timestamp('respondido_at')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('conversacion_id')->references('id')->on('ia_conversaciones')->nullOnDelete();
            $table->foreign('respondido_por')->references('id')->on('users');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_preguntas_entrenador');
    }
};
