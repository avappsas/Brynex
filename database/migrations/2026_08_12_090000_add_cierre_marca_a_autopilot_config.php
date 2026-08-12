<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos que aparecen —y se dicen en voz alta— en el cierre de marca de cada Reel.
 *
 * Son columnas y no constantes porque van en publicidad: los años de trayectoria y la ciudad
 * son afirmaciones sobre el negocio, y cada aliado tiene los suyos. Dejarlos fijos en el
 * código haría que un aliado nuevo saliera anunciando la experiencia de otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->boolean('cierre_activo')->default(true)->after('video_duracion');
            $table->unsignedSmallInteger('cierre_anios')->nullable()->after('cierre_activo');
            $table->string('cierre_ciudad', 60)->nullable()->after('cierre_anios');
        });
    }

    public function down(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->dropColumn(['cierre_activo', 'cierre_anios', 'cierre_ciudad']);
        });
    }
};
