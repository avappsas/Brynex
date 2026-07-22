<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_conocimiento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id')->nullable(); // null = conocimiento global (todos los aliados)
            $table->string('titulo', 255);
            $table->longText('contenido');
            $table->string('categoria', 100)->nullable(); // seguridad_social|modulos|general
            $table->string('fuente', 20)->default('entrenador'); // entrenador|internet
            $table->string('estado', 20)->default('aprobado'); // aprobado|pendiente|rechazado
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados');
            $table->foreign('creado_por')->references('id')->on('users');
            $table->index(['estado', 'aliado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_conocimiento');
    }
};
