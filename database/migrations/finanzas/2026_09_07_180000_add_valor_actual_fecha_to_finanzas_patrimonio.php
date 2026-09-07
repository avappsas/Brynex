<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha en que se supo el `valor_actual` de un bien.
     *
     * Sin ella no se puede devaluar solo: un avalúo de $96.000.000 no dice nada
     * si no se sabe de cuándo es. Con la fecha, el valor estimado de hoy sale de
     * aplicar la tasa de la categoría desde ese día, y escribir un valor nuevo
     * reinicia el conteo.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->table('finanzas_patrimonio', function (Blueprint $table) {
            $table->date('valor_actual_fecha')->nullable()->after('valor_actual');
        });

        // Lo ya cargado con un valor a mano se toma como sabido el día de la
        // adquisición; sin eso la devaluación arrancaría hoy y se perdería el
        // tiempo que el bien ya lleva encima.
        DB::connection('finanzas')->statement(
            'UPDATE finanzas_patrimonio SET valor_actual_fecha = fecha_adquisicion
             WHERE valor_actual_fecha IS NULL AND valor_actual IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_patrimonio', function (Blueprint $table) {
            $table->dropColumn('valor_actual_fecha');
        });
    }
};
