<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ¿A esta empresa se le factura electrónicamente a su nombre?
 *
 * Reemplaza a `documento_verificado`, que duró unas horas y resolvía lo mismo
 * de forma implícita. Un interruptor explícito en la ficha se entiende sin
 * explicación y lo maneja quien conoce al cliente, que es quien sabe si el
 * documento es suyo o prestado.
 *
 *   true  → la factura sale a su nombre y documento (el caso normal).
 *   false → sale a consumidor final. Se usa cuando el documento no es de la
 *           empresa —CHOMPAS y TORQUE son establecimientos, DARIO CRUZ tiene
 *           la cédula de otra persona— y facturar a ese número sería emitirle
 *           a un tercero.
 *
 * Arranca encendido en todas: nada cambia sin que alguien lo decida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('factura_electronica')->default(true)->after('nombre_legal');
        });

        if (Schema::hasColumn('empresas', 'documento_verificado')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn('documento_verificado');
            });
        }
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('factura_electronica');
            $table->boolean('documento_verificado')->nullable();
        });
    }
};
