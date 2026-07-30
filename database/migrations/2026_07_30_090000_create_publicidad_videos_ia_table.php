<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicidad_videos_ia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->text('prompt_video');
            $table->json('frases_texto')->nullable();
            $table->string('modelo', 40); // veo-3.1-lite-generate-preview|veo-3.1-generate-preview
            $table->string('estado', 20)->default('generando'); // generando|lista|error
            $table->string('operation_name', 255)->nullable();
            $table->string('video_path', 255)->nullable();
            $table->string('imagen_poster_path', 255)->nullable();
            $table->text('error_mensaje')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();

            $table->foreign('aliado_id')->references('id')->on('aliados')->cascadeOnDelete();
            $table->foreign('creado_por')->references('id')->on('users')->noActionOnDelete();
            $table->index(['aliado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicidad_videos_ia');
    }
};
