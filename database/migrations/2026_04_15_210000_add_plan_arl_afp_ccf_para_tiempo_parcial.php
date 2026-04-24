<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 1. Crea el plan "ARL + AFP + CCF" (sin EPS) para Tiempo Parcial.
     * 2. Asocia todas las modalidades de Tiempo Parcial con ese plan
     *    en la tabla modalidad_planes.
     */
    public function up(): void
    {
        // ── 1. Crear plan si no existe ─────────────────────────────────
        $existePlan = DB::table('planes_contrato')
            ->where('incluye_eps',     false)
            ->where('incluye_arl',     true)
            ->where('incluye_pension', true)
            ->where('incluye_caja',    true)
            ->first();

        if ($existePlan) {
            $planId = $existePlan->id;
        } else {
            $planId = DB::table('planes_contrato')->insertGetId([
                'codigo'           => 'ARL_AFP_CCF',
                'nombre'           => 'ARL + AFP + CCF',
                'incluye_eps'      => false,
                'incluye_arl'      => true,
                'incluye_pension'  => true,
                'incluye_caja'     => true,
                'activo'           => true,
            ]);
        }

        // ── 2. Asociar todas las modalidades TP con ese plan ───────────
        $modsTP = DB::table('tipo_modalidad')
            ->where('es_tiempo_parcial', true)
            ->pluck('id');

        foreach ($modsTP as $modId) {
            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modId)
                ->where('plan_id', $planId)
                ->exists();

            if (!$existe) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => $modId,
                    'plan_id'           => $planId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Eliminar el plan ARL+AFP+CCF sin EPS y sus relaciones
        $plan = DB::table('planes_contrato')
            ->where('nombre', 'ARL + AFP + CCF')
            ->where('incluye_eps', false)
            ->where('incluye_arl', true)
            ->where('incluye_pension', true)
            ->where('incluye_caja', true)
            ->first();

        if ($plan) {
            DB::table('modalidad_planes')->where('plan_id', $plan->id)->delete();
            DB::table('planes_contrato')->where('id', $plan->id)->delete();
        }
    }
};
