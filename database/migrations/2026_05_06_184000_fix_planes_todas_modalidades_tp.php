<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración correctiva: Asignar planes ARL+AFP+CCF y ARL+CCF a TODAS las
 * modalidades que tengan es_tiempo_parcial = 1 en la BD.
 *
 * Problema:  Las migraciones anteriores usaban IDs hardcodeados [1,2,3,4,-6,-7,-8].
 *            Si existe alguna modalidad TP con un ID diferente (ej. una creada
 *            manualmente con es_tiempo_parcial = 1), no tiene planes asignados
 *            y el select de planes queda vacío al elegirla.
 *
 * Reglas de negocio para Tiempo Parcial:
 *   - Plan principal: ARL + AFP + CCF  (sin EPS) — obligatorio salvo exención AFP
 *   - Plan alternativo: ARL + CCF       (sin EPS, sin AFP) — solo para clientes exentos AFP
 *   - EPS nunca aplica en TP (el JS ya filtra planes con EPS)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Obtener ID del plan ARL+AFP+CCF (sin EPS) ────────────────────
        $planArlAfpCcf = DB::table('planes_contrato')
            ->where('incluye_eps',     false)
            ->where('incluye_arl',     true)
            ->where('incluye_pension', true)
            ->where('incluye_caja',    true)
            ->where('activo',          true)
            ->first();

        // ── Obtener ID del plan ARL+CCF (sin EPS, sin AFP) ───────────────
        $planArlCcf = DB::table('planes_contrato')
            ->where('incluye_eps',     false)
            ->where('incluye_arl',     true)
            ->where('incluye_pension', false)
            ->where('incluye_caja',    true)
            ->where('activo',          true)
            ->first();

        if (!$planArlAfpCcf) {
            echo "  ⚠️  No se encontró plan ARL+AFP+CCF — abortando\n";
            return;
        }

        // ── Obtener TODOS los IDs de modalidades TP ───────────────────────
        $modalidadesTP = DB::table('tipo_modalidad')
            ->where('es_tiempo_parcial', true)
            ->pluck('id');

        if ($modalidadesTP->isEmpty()) {
            echo "  ⚠️  No hay modalidades con es_tiempo_parcial=1 — nada que hacer\n";
            return;
        }

        $inserts   = [];
        $omitidos  = 0;
        $insertados = 0;

        foreach ($modalidadesTP as $modId) {
            // Plan ARL+AFP+CCF — siempre debe estar
            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modId)
                ->where('plan_id', $planArlAfpCcf->id)
                ->exists();

            if (!$existe) {
                $inserts[] = [
                    'tipo_modalidad_id' => $modId,
                    'plan_id'           => $planArlAfpCcf->id,
                ];
                $insertados++;
            } else {
                $omitidos++;
            }

            // Plan ARL+CCF — solo si existe el plan en BD
            if ($planArlCcf) {
                $existeAlt = DB::table('modalidad_planes')
                    ->where('tipo_modalidad_id', $modId)
                    ->where('plan_id', $planArlCcf->id)
                    ->exists();

                if (!$existeAlt) {
                    $inserts[] = [
                        'tipo_modalidad_id' => $modId,
                        'plan_id'           => $planArlCcf->id,
                    ];
                    $insertados++;
                }
            }
        }

        if (!empty($inserts)) {
            DB::table('modalidad_planes')->insert($inserts);
        }

        echo "  ✅ Modalidades TP procesadas: {$modalidadesTP->count()}\n";
        echo "  ✅ Pares (modalidad, plan) insertados: {$insertados}\n";
        echo "  ℹ️  Pares ya existentes (omitidos): {$omitidos}\n";
    }

    public function down(): void
    {
        // Obtener IDs de planes TP (sin EPS)
        $planIds = DB::table('planes_contrato')
            ->where('incluye_eps', false)
            ->where('incluye_arl', true)
            ->where('incluye_caja', true)
            ->whereIn('incluye_pension', [true, false])
            ->pluck('id');

        // Eliminar de modalidad_planes solo para modalidades TP
        $modalidadesTP = DB::table('tipo_modalidad')
            ->where('es_tiempo_parcial', true)
            ->pluck('id');

        DB::table('modalidad_planes')
            ->whereIn('tipo_modalidad_id', $modalidadesTP)
            ->whereIn('plan_id', $planIds)
            ->delete();
    }
};
