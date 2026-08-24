<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suspende la modalidad E-1 hasta que haya forma de completarla.
 *
 * El paso 1 (planilla E de un día de pensión) liquida y se paga sin problema,
 * pero el paso 2 —la corrección que sube la salud a 30 días— no lo acepta
 * ningún operador: necesita el subtipo de cotizante 4, y ese subtipo lo valida
 * un registro de la UGPP al que los cotizantes no pertenecen. Ver
 * docs/modalidad-e1-salud-sin-pension.md para el detalle de lo que se probó.
 *
 * Dejarla activa permite vender un plan que después no se puede pagar, así que
 * la modalidad vuelve a `activo = 0` y sus planes a los que tenía antes.
 *
 * NO se tocan:
 *   - Los contratos y planos que ya la usan. La modalidad inactiva desaparece
 *     del selector pero los registros existentes siguen leyéndose igual, tal
 *     como pasaba antes de reactivarla.
 *   - Las columnas `paso`, `planilla_asociada_numero` y
 *     `planilla_asociada_fecha_pago` de `operador_planillas_api`. Son
 *     aditivas, nullable y no estorban; borrarlas costaría más que dejarlas, y
 *     hacen falta el día que se retome.
 */
return new class extends Migration
{
    private const MODALIDAD_E1 = -4;

    /** EPS+ARL (3) y EPS+ARL+CCF (4): los planes sin pensión que se le pusieron. */
    private const PLANES_E1 = [3, 4];

    /** EPS+ARL+AFP (5) y EPS+ARL+AFP+CCF (6): los que tenía heredados del legacy. */
    private const PLANES_ORIGINALES = [5, 6];

    public function up(): void
    {
        DB::table('tipo_modalidad')
            ->where('id', self::MODALIDAD_E1)
            ->update([
                'activo' => 0,
                'observacion' => 'TipoE- (1 dia pension)',
            ]);

        $this->reemplazarPlanes(self::PLANES_ORIGINALES);
    }

    public function down(): void
    {
        DB::table('tipo_modalidad')
            ->where('id', self::MODALIDAD_E1)
            ->update([
                'activo' => 1,
                'observacion' => 'E-1 (1 día pensión + corrección)',
            ]);

        $this->reemplazarPlanes(self::PLANES_E1);
    }

    private function reemplazarPlanes(array $planes): void
    {
        DB::table('modalidad_planes')->where('tipo_modalidad_id', self::MODALIDAD_E1)->delete();

        foreach ($planes as $planId) {
            DB::table('modalidad_planes')->insert([
                'tipo_modalidad_id' => self::MODALIDAD_E1,
                'plan_id' => $planId,
                'solo_ia' => 0,
            ]);
        }
    }
};
