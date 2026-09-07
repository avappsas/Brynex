<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué un movimiento queda fuera del cuadre, y qué cuentas son mixtas.
 *
 * Varias cuentas del aliado son personales pero por ellas pasa la operación:
 * la cuenta Bancolombia personal de Brayan lleva 36.978 consignaciones del
 * negocio, más que la de BRYGAR SAS. En esas cuentas el extracto trae también
 * su mercado y su gasolina, y sin distinguirlos el descuadre nunca baja.
 *
 * `clasificacion` guarda el motivo de haber sacado un movimiento del cuadre —el
 * banco lo cobró, o es plata personal—, que hasta ahora se perdía: todo caía
 * en «ignorado» sin decir por qué. `uso_personal` marca la cuenta entera, para
 * que la pantalla descuente lo personal en vez de mostrar un descuadre que no
 * es un error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banco_movimientos', function (Blueprint $table) {
            // costo_banco | personal | null (aún sin clasificar)
            $table->string('clasificacion', 20)->nullable()->after('estado_conciliacion');
        });

        Schema::table('banco_cuentas', function (Blueprint $table) {
            $table->boolean('uso_personal')->default(false)->after('incapacidad');
        });
    }

    public function down(): void
    {
        Schema::table('banco_movimientos', function (Blueprint $table) {
            $table->dropColumn('clasificacion');
        });

        Schema::table('banco_cuentas', function (Blueprint $table) {
            $table->dropColumn('uso_personal');
        });
    }
};
