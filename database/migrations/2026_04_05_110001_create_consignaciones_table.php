<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una consignación bancaria puede vincular varias facturas
        Schema::create('consignaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aliado_id')->index();
            $table->date('fecha');
            $table->unsignedBigInteger('banco_cuenta_id')->nullable();   // cta primaria
            $table->unsignedBigInteger('banco_cuenta2_id')->nullable();  // cta secundaria (mixto)
            $table->decimal('valor_total', 14, 0);
            $table->decimal('valor_banco1', 14, 0)->default(0);
            $table->decimal('valor_banco2', 14, 0)->default(0);
            $table->string('referencia', 80)->nullable();                 // nro. comprobante
            $table->text('observacion')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();
        });

        // Pivot: una consignación puede pagar varias facturas
        Schema::create('consignacion_factura', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consignacion_id');
            $table->unsignedBigInteger('factura_id');
            $table->decimal('valor_aplicado', 14, 0);      // cuánto de la consignación va a esta factura
            $table->timestamps();

            $table->foreign('consignacion_id')->references('id')->on('consignaciones');
            $table->foreign('factura_id')->references('id')->on('facturas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignacion_factura');
        Schema::dropIfExists('consignaciones');
    }
};
