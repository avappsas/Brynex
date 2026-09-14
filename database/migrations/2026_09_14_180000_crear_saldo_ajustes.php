<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de saldo a favor.
 *
 * Un saldo a favor no siempre es plata del cliente: varios nacieron de "Otros"
 * que se escribían y no se cobraban, y el sistema los arrastra como crédito.
 * Antes la única forma de quitarlos era tocar la factura vieja; ahora se
 * registra el ajuste aquí, con su motivo y su autor, y el saldo del cliente se
 * calcula descontándolo. La factura original queda intacta.
 *
 * Es por cliente (aliado + cédula), que es como se acumula y se consume el
 * saldo: la factura donde nació solo se guarda como referencia en `detalle`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_ajustes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->string('cedula', 20);
            // El saldo que se consume. Positivo siempre: es cuánto se le quita
            // al crédito del cliente.
            $table->integer('valor');
            $table->string('motivo', 255);
            $table->unsignedBigInteger('usuario_id')->nullable();
            // Las facturas de las que venía ese saldo, para poder rastrearlo.
            $table->text('detalle')->nullable();
            $table->timestamps();
            $table->softDeletes();   // deshacer un ajuste devuelve el saldo

            $table->index(['aliado_id', 'cedula'], 'ix_saldo_ajustes_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_ajustes');
    }
};
