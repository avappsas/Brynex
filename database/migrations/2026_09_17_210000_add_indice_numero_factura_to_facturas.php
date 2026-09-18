<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice para buscar las filas de un recibo por su número.
 *
 * Un recibo son todas las filas de `facturas` con el mismo `numero_factura`
 * (un lote de empresa, o una sola persona), y la app las busca por
 * `aliado_id` + `numero_factura` sin empresa: el recibo, anular, los abonos.
 * No había índice sobre `numero_factura`, así que SQL Server recorría la tabla
 * entera (288.000 filas) y cada búsqueda costaba ~83 ms, la mitad de lo que
 * tarda en abrir un recibo.
 *
 * `deleted_at` va DENTRO de la llave y no como índice filtrado
 * (`WHERE deleted_at IS NULL`): un índice filtrado exige QUOTED_IDENTIFIER ON
 * en toda sesión que escriba en la tabla, y si alguna conecta con la opción
 * apagada, los INSERT y UPDATE fallan. Ver la memoria de índices creados a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->index(['aliado_id', 'numero_factura', 'deleted_at'], 'IX_facturas_aliado_numero');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex('IX_facturas_aliado_numero');
        });
    }
};
