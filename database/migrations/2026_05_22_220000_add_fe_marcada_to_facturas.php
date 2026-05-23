<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de Facturación Electrónica (FE) a la tabla facturas.
 *
 * fe_marcada     → bandera: ya se generó la FE en Dataico
 * fe_marcada_at  → fecha/hora en que se marcó
 * fe_marcada_por → usuario que realizó el marcado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('fe_marcada')
                  ->default(false)
                  ->after('obs_factura')
                  ->comment('Indica si la factura fue incluida en una FE de Dataico');

            $table->timestamp('fe_marcada_at')
                  ->nullable()
                  ->after('fe_marcada')
                  ->comment('Fecha y hora en que se marcó como FE');

            $table->unsignedBigInteger('fe_marcada_por')
                  ->nullable()
                  ->after('fe_marcada_at')
                  ->comment('ID del usuario que realizó el marcado de FE');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['fe_marcada', 'fe_marcada_at', 'fe_marcada_por']);
        });
    }
};
