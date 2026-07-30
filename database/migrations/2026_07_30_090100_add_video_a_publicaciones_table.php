<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            // 'imagen'|'video' — para video, imagen_path guarda el poster/primer frame,
            // así ningún listado/thumbnail existente necesita cambios.
            $table->string('tipo_pieza', 10)->default('imagen')->after('origen');
            $table->string('video_path', 255)->nullable()->after('imagen_path');
            $table->string('video_modelo', 40)->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn(['tipo_pieza', 'video_path', 'video_modelo']);
        });
    }
};
