<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logo completo (ícono + nombre + eslogan) para fondos CLAROS, exclusivo del watermark de
 * marketing — separado de `logo` (el ícono cuadrado que ya se usa en el layout, facturación,
 * selector de aliado, etc.) para no tener que tocar ese campo. Junto con `logo_oscuro`
 * (fondos oscuros, ya existente) forma el par que usa LogoWatermarker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->string('logo_marca_claro')->nullable()->after('logo_oscuro');
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('logo_marca_claro');
        });
    }
};
