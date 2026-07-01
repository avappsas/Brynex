<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // ID de la factura con numero_factura=0 que esta factura real reemplaza.
            // Permite reactivar la factura 0 si se anula la factura real del retiro.
            $table->unsignedBigInteger('factura_retiro_origen_id')
                  ->nullable()
                  ->after('contrato_id')
                  ->comment('ID de la factura de retiro (numero_factura=0) que esta factura reemplaza');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('factura_retiro_origen_id');
        });
    }
};
