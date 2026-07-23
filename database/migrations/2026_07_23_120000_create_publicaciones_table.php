<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('titulo', 150);
            $table->text('copy')->nullable();
            $table->string('imagen_path', 255);
            $table->string('origen', 20)->default('plantilla'); // plantilla|ia|subida
            $table->string('plantilla_usada', 60)->nullable();
            $table->string('estado', 20)->default('borrador');  // borrador|pendiente|aprobada|rechazada|publicada
            $table->json('destinos')->nullable();                // ["web","facebook","instagram"]
            $table->timestamp('programada_at')->nullable();
            $table->timestamp('publicada_at')->nullable();
            $table->json('resultado_publicacion')->nullable();   // {facebook:{ok,mensaje,id}, instagram:{...}}
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
            // noActionOnDelete (no nullOnDelete): SQL Server rechaza "multiple cascade paths" si
            // dos FKs distintas (aliado_id cascade + creado_por/aprobado_por hacia users) pueden
            // llegar a la misma fila por rutas distintas — mismo patrón ya usado en
            // configuracion_aliado.encargado_default_id.
            $table->foreign('creado_por')->references('id')->on('users')->noActionOnDelete();
            $table->foreign('aprobado_por')->references('id')->on('users')->noActionOnDelete();
            $table->index(['aliado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicaciones');
    }
};
