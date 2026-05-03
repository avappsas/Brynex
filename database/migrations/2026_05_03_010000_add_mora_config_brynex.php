<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega la tasa de mora PILA a configuracion_brynex.
 * Esta tasa la define BryNex y aplica para todos los aliados
 * al calcular la mora real = total_ss × tasa / 365 × días_mora.
 *
 * Fuente: Art. 635 Estatuto Tributario | Superfinanciera mayo-2026:
 *   Tasa usura consumo: 28.17% E.A. → mora tributaria: 28.17% - 2% = 26.17% E.A.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Insertar o actualizar — idempotente
        DB::table('configuracion_brynex')->updateOrInsert(
            ['clave' => 'tasa_mora_pila'],
            [
                'valor'       => '26.17',
                'descripcion' => 'Tasa de mora PILA (% efectivo anual). '
                    . 'Art. 635 ET: tasa usura consumo Superfinanciera menos 2 pp. '
                    . 'Actualizar cada vez que la Superfinanciera modifique la tasa de usura.',
            ]
        );
    }

    public function down(): void
    {
        DB::table('configuracion_brynex')->where('clave', 'tasa_mora_pila')->delete();
    }
};
