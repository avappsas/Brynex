<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_id')->index();
            $table->decimal('valor', 14, 0);
            $table->string('forma_pago', 20);           // efectivo|consignacion|mixto
            $table->decimal('valor_efectivo',   14, 0)->default(0);
            $table->decimal('valor_consignado', 14, 0)->default(0);
            $table->unsignedInteger('banco_cuenta_id')->nullable();
            $table->date('fecha');
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->foreign('factura_id')->references('id')->on('facturas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos');
    }
};
