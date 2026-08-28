<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Día del mes en que corta el préstamo. Nace con el día del desembolso y se
     * actualiza cuando un pago que cubre todo el interés re-ancla el ciclo.
     * Guardarlo aparte permite que un corte que cayó en el último día de un mes
     * corto (30-sep para un préstamo del 31) recupere su día real al mes siguiente.
     */
    public function up(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamos', function (Blueprint $table) {
            $table->tinyInteger('dia_cobro')->nullable()->after('ultimo_corte');
        });

        DB::connection('finanzas')->statement(
            'UPDATE finanzas_prestamos SET dia_cobro = DAY(fecha_desembolso) WHERE dia_cobro IS NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_prestamos', function (Blueprint $table) {
            $table->dropColumn('dia_cobro');
        });
    }
};
