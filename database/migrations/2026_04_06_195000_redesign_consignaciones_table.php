<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar el pivot viejo (ya no se necesita)
        Schema::dropIfExists('consignacion_factura');

        // 2. Rediseñar la tabla consignaciones
        //    Una fila = una transferencia bancaria vinculada a UNA factura
        Schema::dropIfExists('consignaciones');
        Schema::create('consignaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();
            $table->unsignedBigInteger('factura_id')->index();
            $table->unsignedBigInteger('banco_cuenta_id');       // qué cuenta recibe el dinero
            $table->date('fecha');                                // fecha de la transferencia
            $table->decimal('valor', 14, 0);                     // monto consignado
            $table->string('referencia', 100)->nullable();        // nro. comprobante / soporte
            $table->boolean('confirmado')->default(false);        // para arqueo bancario
            $table->text('observacion')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();    // quién registró
            $table->timestamps();

            $table->foreign('factura_id')
                  ->references('id')->on('facturas')
                  ->onDelete('cascade');
            $table->foreign('banco_cuenta_id')
                  ->references('id')->on('banco_cuentas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignaciones');
        // Recrear pivot básico para rollback (sin datos)
        Schema::create('consignacion_factura', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consignacion_id');
            $table->unsignedBigInteger('factura_id');
            $table->decimal('valor_aplicado', 14, 0);
            $table->timestamps();
        });
    }
};
