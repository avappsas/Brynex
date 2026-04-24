<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Insertar en modalidad_planes los planes para todas las
 * modalidades de Tiempo Parcial que quedaron sin asignación.
 *
 * La migración anterior (seed_modalidad_planes_tiempo_parcial) falló
 * silenciosamente porque la condición `$yaExiste` impidió los inserts
 * al detectar cualquier registro previo para esos IDs en la tabla.
 *
 * Esta migración usa INSERT directo ignorando duplicados con MERGE.
 *
 * IDs TP conocidos: 1(TP7), 2(TP14), 3(TP21), 4(TP30), -6(TP7-14),
 *                   -7(TP7-21), -8(TP14-21)
 *
 * Planes TP:
 *   id=11 → ARL + AFP + CCF (con pensión)
 *   id=13 → ARL + CCF       (APTP, sin pensión)
 */
return new class extends Migration
{
    // Modalidades TP y sus planes
    private const IDS_TP        = [1, 2, 3, 4, -6, -7, -8];
    private const PLAN_ARL_AFP_CCF = 11;  // ARL + AFP + CCF
    private const PLAN_ARL_CCF     = 13;  // ARL + CCF (APTP)

    public function up(): void
    {
        $inserts = [];

        foreach (self::IDS_TP as $modId) {
            // Verificar que la modalidad existe
            if (!DB::table('tipo_modalidad')->where('id', $modId)->exists()) {
                continue;
            }

            // Por cada plan TP, insertar SOLO si no existe ya ese par (mod, plan)
            foreach ([self::PLAN_ARL_AFP_CCF, self::PLAN_ARL_CCF] as $planId) {
                // Verificar que el plan existe
                if (!DB::table('planes_contrato')->where('id', $planId)->exists()) {
                    continue;
                }

                $existe = DB::table('modalidad_planes')
                    ->where('tipo_modalidad_id', $modId)
                    ->where('plan_id', $planId)
                    ->exists();

                if (!$existe) {
                    $inserts[] = [
                        'tipo_modalidad_id' => $modId,
                        'plan_id'           => $planId,
                    ];
                }
            }
        }

        // UPC (id=13) → Solo EPS (id=1)
        if (DB::table('tipo_modalidad')->where('id', 13)->exists()) {
            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', 13)
                ->where('plan_id', 1)
                ->exists();
            if (!$existe) {
                $inserts[] = ['tipo_modalidad_id' => 13, 'plan_id' => 1];
            }
        }

        // Ing-Ret (id=12) → Agregar plan 12 (ARL+AFP+CCF) si falta
        // (ya tenía planes 3,4,5,6 — verificar si el 12 más aplica o se deja igual)

        if (!empty($inserts)) {
            DB::table('modalidad_planes')->insert($inserts);
        }

        // Reportar lo insertado
        $total = count($inserts);
        echo "  Insertados: {$total} registros en modalidad_planes\n";
    }

    public function down(): void
    {
        // Eliminar sólo los TP + UPC insertados por esta migración
        DB::table('modalidad_planes')
            ->whereIn('tipo_modalidad_id', array_merge(self::IDS_TP, [13]))
            ->whereIn('plan_id', [self::PLAN_ARL_AFP_CCF, self::PLAN_ARL_CCF, 1])
            ->delete();
    }
};
