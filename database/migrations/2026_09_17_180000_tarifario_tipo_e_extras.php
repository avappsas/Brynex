<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Precios de Tipo E - Extras (-12) para BRYGAR.
 *
 * Sin celda en `afiliacion_arl_modalidad`, la combinación (plan, modalidad,
 * nivel) cae al respaldo por plan de `configuracion_aliado`, que es ciego a la
 * modalidad: cobraría lo mismo que un dependiente normal y la administración
 * saldría de la fila global. Estas modalidades son trabajo doble —dos planillas
 * y dos pagos por persona cada mes—, así que llevan su propia celda.
 *
 * Decisión del dueño, 17-sep-2026:
 *   • administración $46.000 en los cinco planes (la del aliado);
 *   • afiliación igual a la del plan normal: Solo EPS $99.900 y EPS+ARL
 *     $110.000. Caja y pensión no cobran afiliación.
 *
 * Aparte de esto, el cliente paga la planilla del operador, que en estos planes
 * trae un sobrecosto fijo de $12.200: el día de pensión, el de ARL y el de caja
 * del paso 1. Ver PilaCotizanteDosPasos.
 */
return new class extends Migration
{
    private const ALIADO = 2;          // BRYGAR

    private const MODALIDAD = -12;     // Tipo E - Extras

    private const ADMINISTRACION = 46000;

    /**
     * Qué cobra cada plan y en qué niveles de riesgo se ofrece.
     *
     * Solo "EPS y ARL" vende riesgos, así que es el único con los cinco
     * niveles; los demás van en el 1, que es como están cargados los otros
     * planes sin ARL del aliado.
     */
    private const PRECIOS = [
        'SOLO_EPS' => ['afiliacion' => 99900, 'niveles' => [1]],
        'EPS_ARL' => ['afiliacion' => 110000, 'niveles' => [1, 2, 3, 4, 5]],
        'SOLO_CCF_14' => ['afiliacion' => 0, 'niveles' => [1]],
        'SOLO_CCF_30' => ['afiliacion' => 0, 'niveles' => [1]],
        'SOLO_AFP' => ['afiliacion' => 0, 'niveles' => [1]],
    ];

    public function up(): void
    {
        $planes = DB::table('planes_contrato')
            ->whereIn('codigo', array_keys(self::PRECIOS))
            ->pluck('id', 'codigo');

        foreach (self::PRECIOS as $codigo => $precio) {
            $planId = $planes[$codigo] ?? null;

            if (! $planId) {
                continue;
            }

            foreach ($precio['niveles'] as $nivel) {
                $existe = DB::table('afiliacion_arl_modalidad')
                    ->where('aliado_id', self::ALIADO)
                    ->where('plan_id', $planId)
                    ->where('tipo_modalidad_id', self::MODALIDAD)
                    ->where('nivel_arl', $nivel)
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::table('afiliacion_arl_modalidad')->insert([
                    'aliado_id' => self::ALIADO,
                    'plan_id' => $planId,
                    'tipo_modalidad_id' => self::MODALIDAD,
                    'nivel_arl' => $nivel,
                    'costo_afiliacion' => $precio['afiliacion'],
                    'administracion' => self::ADMINISTRACION,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('afiliacion_arl_modalidad')
            ->where('aliado_id', self::ALIADO)
            ->where('tipo_modalidad_id', self::MODALIDAD)
            ->delete();
    }
};
