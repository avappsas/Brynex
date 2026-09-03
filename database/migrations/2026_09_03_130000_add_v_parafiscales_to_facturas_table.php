<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda aparte lo que la factura cobró de SENA e ICBF.
 *
 * Va en su propia columna y no sumado dentro de `v_eps` porque en la planilla
 * son aportes distintos, con su propia tarifa y su propio redondeo: mezclarlos
 * haría imposible cuadrar el recibo contra el archivo que recibe el operador.
 * Solo se llena para los aportantes marcados como no exonerados
 * (`empresas.exonerado_parafiscales = 0`); para el resto queda en cero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('v_parafiscales', 14, 0)->default(0)->after('v_caja');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('v_parafiscales');
        });
    }
};
