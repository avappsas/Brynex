<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columna anticipo_aplicado a facturas.
 *
 * Esta columna almacena el total aplicado desde anticipos previos.
 * Es SEPARADA de valor_efectivo y valor_consignado para evitar doble
 * conteo en el cuadre diario:
 *
 *   valor_efectivo   = solo el pago nuevo del mes
 *   valor_consignado = solo el pago nuevo del mes
 *   anticipo_aplicado = dinero de anticipos aplicados (ya contado en mes anterior)
 *
 * El cuadre de mayo suma SOLO valor_efectivo + valor_consignado,
 * nunca anticipo_aplicado (ese ya fue contado cuando se registró en abril).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('anticipo_aplicado', 14, 0)
                  ->default(0)
                  ->after('saldo_proximo')
                  ->comment('Total aplicado desde anticipos previos. No suma al cuadre del mes de la factura.');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('anticipo_aplicado');
        });
    }
};
