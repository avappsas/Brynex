<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            $table->unsignedTinyInteger('duracion_seg')->nullable()->after('modelo');
            $table->decimal('costo_estimado_usd', 6, 2)->nullable()->after('duracion_seg');
        });

        Schema::table('publicaciones', function (Blueprint $table) {
            $table->decimal('costo_estimado_usd', 6, 2)->nullable()->after('video_modelo');
        });
    }

    public function down(): void
    {
        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            $table->dropColumn(['duracion_seg', 'costo_estimado_usd']);
        });

        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn('costo_estimado_usd');
        });
    }
};
