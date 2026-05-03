<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega configuración de mora al cliente en configuracion_aliado.
 *
 * mora_dia_habil_inicio: Si el aliado lo configura, TODOS sus clientes
 *   entran en mora después de ese día hábil del mes de pago, sin importar
 *   el NIT de su razón social. Si es NULL, se usa el día hábil por RS (NIT).
 *
 * mora_minimo:  Si mora_real < mora_minimo → cobrar mora_minimo
 * mora_segundo: Si mora_real < mora_segundo → cobrar mora_segundo
 *               Si mora_real >= mora_segundo → cobrar mora_real
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            // Día hábil a partir del cual se cobra mora a TODOS los clientes
            // NULL = usar día hábil por RS (tabla Decreto 1990/2016 + NIT)
            if (!Schema::hasColumn('configuracion_aliado', 'mora_dia_habil_inicio')) {
                $table->tinyInteger('mora_dia_habil_inicio')
                      ->unsigned()
                      ->nullable()
                      ->after('seguro_valor')
                      ->comment('Día hábil global de inicio de mora (2-16). NULL = usar NIT de RS');
            }

            // Tramo 1: mora mínima fija
            if (!Schema::hasColumn('configuracion_aliado', 'mora_minimo')) {
                $table->decimal('mora_minimo', 10, 0)
                      ->default(2000)
                      ->after('mora_dia_habil_inicio')
                      ->comment('Mora mínima a cobrar si mora_real < mora_minimo');
            }

            // Tramo 2: mora segundo nivel
            if (!Schema::hasColumn('configuracion_aliado', 'mora_segundo')) {
                $table->decimal('mora_segundo', 10, 0)
                      ->default(5000)
                      ->after('mora_minimo')
                      ->comment('Mora segundo tramo: cobrar si mora_real < mora_segundo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_aliado', function (Blueprint $table) {
            $cols = ['mora_dia_habil_inicio', 'mora_minimo', 'mora_segundo'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('configuracion_aliado', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
