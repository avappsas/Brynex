<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapta la tabla consignaciones para soportar anticipos.
 *
 * Cambios:
 * 1. factura_id pasa a nullable (un anticipo no tiene factura aún)
 * 2. Se agrega anticipo_id nullable (vínculo con la tabla anticipos)
 *
 * Así una fila en consignaciones puede ser:
 *   - Pago de factura: factura_id=X, anticipo_id=null
 *   - Pago anticipado: factura_id=null, anticipo_id=Y
 *   - Traslado/banco:  factura_id=null, anticipo_id=null, tipo='traslado_efectivo'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            // 1. Hacer nullable factura_id (puede ser anticipo sin factura)
            $table->unsignedBigInteger('factura_id')->nullable()->change();

            // 2. Agregar vínculo con anticipo
            $table->unsignedBigInteger('anticipo_id')
                  ->nullable()
                  ->after('factura_id')
                  ->index()
                  ->comment('Si la consignación es de un anticipo, referencia al anticipo');

            $table->foreign('anticipo_id')
                  ->references('id')
                  ->on('anticipos')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('consignaciones', function (Blueprint $table) {
            $table->dropForeign(['anticipo_id']);
            $table->dropColumn('anticipo_id');
            $table->unsignedBigInteger('factura_id')->nullable(false)->change();
        });
    }
};
