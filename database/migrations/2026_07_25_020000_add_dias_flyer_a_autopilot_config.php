<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días de la semana en que el piloto automático publica un FLYER promocional de un plan
 * (con foto propia, precio real y botón de WhatsApp) en vez del post educativo del día.
 * Vacío = nunca. Lo típico es un par de días por semana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->string('dias_flyer', 40)->nullable()->after('dias');
        });
    }

    public function down(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->dropColumn('dias_flyer');
        });
    }
};
