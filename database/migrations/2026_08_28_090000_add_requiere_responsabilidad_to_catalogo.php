<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué responsabilidad del RUT exige una obligación para aplicar.
 *
 * La retención en la fuente estaba atada al régimen ordinario, y está mal: un
 * contribuyente del régimen simple también retiene si el RUT lo marca como
 * agente retenedor. Con los RUT de BRYGAR cargados aparecieron cinco razones
 * sociales simples con la responsabilidad 07 y cero declaraciones de retención
 * en su checklist — 60 declaraciones al año que nadie estaba vigilando.
 *
 * Lo mismo con la exógena: la informa quien tenga la responsabilidad 14, no
 * todo el mundo.
 *
 * Acepta varias separadas por coma ('07,09'): basta con tener una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brynex_obligaciones_catalogo', function (Blueprint $table) {
            if (! Schema::hasColumn('brynex_obligaciones_catalogo', 'requiere_responsabilidad')) {
                $table->string('requiere_responsabilidad', 40)->nullable()->after('periodicidad_iva_requerida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brynex_obligaciones_catalogo', function (Blueprint $table) {
            $table->dropColumn('requiere_responsabilidad');
        });
    }
};
