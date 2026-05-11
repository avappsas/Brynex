<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

/**
 * Adapta la tabla consignaciones para soportar anticipos.
 *
 * Cambios:
 * 1. factura_id pasa a nullable (un anticipo no tiene factura aún)
 *    → Se usa ALTER TABLE nativo para evitar segfault con doctrine/dbal en SQL Server
 * 2. Se agrega anticipo_id nullable (vínculo con la tabla anticipos)
 *
 * Así una fila en consignaciones puede ser:
 *   - Pago de factura:  factura_id=X, anticipo_id=null
 *   - Pago anticipado:  factura_id=null, anticipo_id=Y
 *   - Traslado banco:   factura_id=null, anticipo_id=null, tipo='traslado_efectivo'
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Hacer factura_id nullable con SQL nativo (evita segfault de doctrine/dbal)
        //    SQL Server: primero hay que quitar la FK, luego alterar la columna, luego re-crear FK.
        DB::statement('ALTER TABLE consignaciones DROP CONSTRAINT IF EXISTS consignaciones_factura_id_foreign');
        DB::statement('ALTER TABLE consignaciones ALTER COLUMN factura_id BIGINT NULL');
        DB::statement('ALTER TABLE consignaciones ADD CONSTRAINT consignaciones_factura_id_foreign
                        FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE');

        // 2. Agregar anticipo_id (Blueprint solo para ADD COLUMN, que no usa doctrine)
        Schema::table('consignaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('anticipo_id')
                  ->nullable()
                  ->after('factura_id')
                  ->index();

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
        });

        // Restaurar factura_id como NOT NULL
        DB::statement('ALTER TABLE consignaciones DROP CONSTRAINT IF EXISTS consignaciones_factura_id_foreign');
        DB::statement('ALTER TABLE consignaciones ALTER COLUMN factura_id BIGINT NOT NULL');
        DB::statement('ALTER TABLE consignaciones ADD CONSTRAINT consignaciones_factura_id_foreign
                        FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE');
    }
};
