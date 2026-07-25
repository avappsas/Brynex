<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recorte opcional del logo de marca para el watermark, cuando el lockup completo trae un
 * eslogan que se vuelve ilegible al achicarlo. Layout asumido: ícono a la izquierda (alto
 * completo) + texto a la derecha (nombre arriba, eslogan abajo) — se guardan como % del
 * ancho/alto de la imagen fuente (no píxeles fijos), para que sea independiente de la
 * resolución exacta del archivo que suba cada aliado.
 *
 * Si un aliado no configura esto, LogoWatermarker usa el logo completo tal cual (default
 * seguro) — este recorte es opt-in, no una suposición universal sobre cómo luce cualquier
 * logo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->json('logo_marca_recorte')->nullable()->after('logo_marca_claro');
        });
    }

    public function down(): void
    {
        Schema::table('aliados', function (Blueprint $table) {
            $table->dropColumn('logo_marca_recorte');
        });
    }
};
