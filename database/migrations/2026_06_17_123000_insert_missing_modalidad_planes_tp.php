<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Obtener el plan ARL+AFP+CCF (ID 11)
        $planArlAfpCcf = DB::table('planes_contrato')
            ->where('incluye_eps',     false)
            ->where('incluye_arl',     true)
            ->where('incluye_pension', true)
            ->where('incluye_caja',    true)
            ->where('activo',          true)
            ->first();

        // Obtener el plan ARL+CCF (ID 13)
        $planArlCcf = DB::table('planes_contrato')
            ->where('incluye_eps',     false)
            ->where('incluye_arl',     true)
            ->where('incluye_pension', false)
            ->where('incluye_caja',    true)
            ->where('activo',          true)
            ->first();

        if (!$planArlAfpCcf) {
            echo "  ⚠️ No se encontró el plan ARL+AFP+CCF en la base de datos. Saltando inserción.\n";
            return;
        }

        // Obtener todas las modalidades de Tiempo Parcial
        $modalidadesTP = DB::table('tipo_modalidad')
            ->where('es_tiempo_parcial', true)
            ->pluck('id');

        $inserts = [];

        foreach ($modalidadesTP as $modId) {
            // Plan ARL+AFP+CCF
            $existeAfp = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modId)
                ->where('plan_id', $planArlAfpCcf->id)
                ->exists();

            if (!$existeAfp) {
                $inserts[] = [
                    'tipo_modalidad_id' => $modId,
                    'plan_id'           => $planArlAfpCcf->id,
                ];
            }

            // Plan ARL+CCF
            if ($planArlCcf) {
                $existeCcf = DB::table('modalidad_planes')
                    ->where('tipo_modalidad_id', $modId)
                    ->where('plan_id', $planArlCcf->id)
                    ->exists();

                if (!$existeCcf) {
                    $inserts[] = [
                        'tipo_modalidad_id' => $modId,
                        'plan_id'           => $planArlCcf->id,
                    ];
                }
            }
        }

        if (!empty($inserts)) {
            DB::table('modalidad_planes')->insert($inserts);
            echo "  ✅ Insertadas " . count($inserts) . " relaciones de tiempo parcial en modalidad_planes.\n";
        } else {
            echo "  ℹ️ Todas las relaciones de tiempo parcial en modalidad_planes ya existen.\n";
        }
    }

    public function down(): void
    {
        // En reversa no removemos nada de forma masiva para evitar alterar registros de producción configurados manualmente.
    }
};
