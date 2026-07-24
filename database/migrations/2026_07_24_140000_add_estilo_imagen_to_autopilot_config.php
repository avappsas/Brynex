<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            // ilustracion | fotorrealista | alternar (elige al azar cada día entre las dos)
            $table->string('estilo_imagen', 20)->default('ilustracion')->after('modo');
        });
    }

    public function down(): void
    {
        Schema::table('autopilot_config', function (Blueprint $table) {
            $table->dropColumn('estilo_imagen');
        });
    }
};
