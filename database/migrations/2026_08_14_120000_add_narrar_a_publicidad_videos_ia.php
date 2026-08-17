<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ¿Este video lleva narración en off?
 *
 * Los formatos sin diálogo —el oficio en acción, tensión y alivio, manos y detalle— quedan
 * mudos salvo por el sonido ambiente, y un Reel mudo se salta. La narración la pone el TTS
 * sobre el clip ya montado, pero eso ocurre minutos después, cuando Veo termina: para
 * entonces ya no se sabe con qué formato se pidió. Se decide al crear y se guarda aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            $table->boolean('narrar')->default(false)->after('frases_texto');
        });
    }

    public function down(): void
    {
        Schema::table('publicidad_videos_ia', function (Blueprint $table) {
            $table->dropColumn('narrar');
        });
    }
};
