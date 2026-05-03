<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `mora` a la tabla facturas.
 *
 * Este campo registra la mora cobrada AL CLIENTE por pago tardío de su
 * factura de Seguridad Social. NO es un ingreso del aliado — es un
 * cobro de recuperación que se reporta separado en el cuadro de SS.
 *
 * Se suma al `total` de la factura para efecto de cobro, pero se excluye
 * de los cálculos de utilidad (c_utilidad) e ingresos operacionales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (!Schema::hasColumn('facturas', 'mora')) {
                $table->decimal('mora', 14, 0)
                      ->default(0)
                      ->after('otros')
                      ->comment('Mora cobrada al cliente (no es ingreso del aliado)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (Schema::hasColumn('facturas', 'mora')) {
                $table->dropColumn('mora');
            }
        });
    }
};
