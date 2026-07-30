<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            // Para piezas de más de una escena (16s/24s): array de
            // {orden, prompt, operation_name, estado, video_bruto_path}. Null para piezas de
            // una sola escena (4/6/8s), que siguen usando operation_name/video_path directo.
            $table->json('escenas')->nullable()->after('operation_name');
        });
    }

    public function down(): void
    {
        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            $table->dropColumn('escenas');
        });
    }
};
