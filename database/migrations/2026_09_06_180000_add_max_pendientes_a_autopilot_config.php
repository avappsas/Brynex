<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freno del piloto: cuántas piezas sin aprobar se toleran antes de dejar de generar.
 *
 * El piloto generaba una pieza diaria pase lo que pase, y al 6-sep-2026 había 32 esperando
 * aprobación —28 de ellas de hace más de una semana, algunas de julio—. Cada Reel cuesta
 * dinero en Veo, así que la cola no solo estorba: se paga.
 *
 * Es un número y no un booleano para poder empezar en 3 y bajarlo a 0 —"solo generar si no
 * hay nada pendiente"— sin volver a desplegar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_pendientes_sin_aprobar')->default(3)->after('cierre_ciudad');
        });
    }

    public function down(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->dropColumn('max_pendientes_sin_aprobar');
        });
    }
};
