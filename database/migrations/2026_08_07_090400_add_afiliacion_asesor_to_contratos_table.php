<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que le toca al asesor del costo de afiliación DE ESTE contrato, congelado al crearlo
     * desde la matriz del asesor. Ver docs/plan-tarifario-asesores.md.
     *
     * Es el gemelo de admon_asesor (que ya existía y hace lo mismo con la admon mensual): el
     * valor vive en el contrato, no se recalcula al facturar, y cambiar el nivel del asesor
     * mañana no altera lo que se paga por contratos ya creados.
     *
     * NULLABLE a propósito, y ese null es semántico:
     *   null → contrato anterior a esta función ⇒ la facturación sigue el camino de siempre
     *          (ConfiguracionAliado::calcularDistribucion con la comisión plana del asesor).
     *   0+   → contrato nuevo ⇒ la factura de afiliación copia este valor a dist_asesor.
     * Por eso NO lleva default 0: eso convertiría los ~cientos de miles de contratos viejos
     * en "contratos nuevos con comisión 0".
     */
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->decimal('afiliacion_asesor', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('afiliacion_asesor');
        });
    }
};
