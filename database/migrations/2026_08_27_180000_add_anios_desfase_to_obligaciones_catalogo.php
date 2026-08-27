<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuántos años después del año gravable se presenta la obligación.
 *
 * La ficha ordena el checklist por plazo, no por año gravable. Cuando hay
 * `fecha_vencimiento` el plazo sale de ahí, pero los años sin calendario
 * cargado caían al año gravable, y eso pone la declaración anual del 2026 en
 * la pestaña del 2026 cuando en realidad se presenta en abril de 2027.
 *
 * No basta con mirar la periodicidad: la renovación de matrícula mercantil es
 * anual y se hace DENTRO del mismo año que cubre (hasta el 31 de marzo), no al
 * siguiente. Por eso el desfase es un dato por obligación y no una regla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brynex_obligaciones_catalogo', function (Blueprint $table) {
            if (! Schema::hasColumn('brynex_obligaciones_catalogo', 'anios_desfase')) {
                $table->integer('anios_desfase')->default(0)->after('periodicidad_iva_requerida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brynex_obligaciones_catalogo', function (Blueprint $table) {
            $table->dropColumn('anios_desfase');
        });
    }
};
