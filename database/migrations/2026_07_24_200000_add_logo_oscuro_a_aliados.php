<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segunda variante del logo, para fondos oscuros (versión clara/blanca del logo completo,
 * con nombre y eslogan integrados) — `logo` sigue siendo la versión oscura/de color, para
 * fondos claros. LogoWatermarker elige automáticamente cuál usar según qué tan oscura sea
 * la esquina real de cada foto generada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->string('logo_oscuro')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('logo_oscuro');
        });
    }
};
