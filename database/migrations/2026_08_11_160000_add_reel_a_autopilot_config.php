<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El piloto automático pasa a producir Reels en vez de imágenes estáticas.
 *
 * El motivo es medición propia, no moda: sobre las piezas publicadas de BRYGAR, las 15
 * imágenes promediaron 5,2 de alcance en Instagram (máx. 9) y los 2 videos promediaron
 * 159,5 (máx. 186). Meta empuja Reels a no-seguidores; una imagen no sale ni a los propios.
 *
 * `video_nivel` existe porque el costo cambia dos órdenes de magnitud: Veo Lite cuesta
 * USD 0,05/seg (~USD 0,40 por clip de 8s) y Standard USD 0,40/seg (~USD 3,20). Un Reel
 * diario en Standard costaría más que el presupuesto de pauta del aliado, así que el
 * default es lite y subirlo es una decisión explícita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            // 'reel' | 'imagen' — qué formato tiene el post educativo del día. Los días de
            // flyer promocional no se tocan: ese sigue siendo una pieza gráfica con precios.
            $table->string('formato', 10)->default('reel')->after('estilo_imagen');
            // 'lite' | 'standard'
            $table->string('video_nivel', 10)->default('lite')->after('formato');
            // Segundos del clip (8 = una escena; 16/24 se arman por concatenación).
            $table->unsignedSmallInteger('video_duracion')->default(8)->after('video_nivel');
        });

        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            // Concepto y destino que el piloto ya decidió al lanzar el video. Como Veo es
            // asíncrono (1-3 min), la Publicacion no se puede crear en ese momento: se crea
            // cuando `videos:procesar` termina el clip, leyendo esto. Si viene null, el video
            // es de generación manual desde el panel y no se publica solo.
            $table->json('autopilot_payload')->nullable()->after('error_mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->dropColumn(['formato', 'video_nivel', 'video_duracion']);
        });

        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            $table->dropColumn('autopilot_payload');
        });
    }
};
