<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Ingreso-Retiro (12) y el único plan que debe manejar: EPS + ARL + AFP. */
    private const MODALIDAD_IR = 12;

    private const PLAN_EPS_ARL_AFP = 5;

    /**
     * Ingreso-Retiro solo se vende con EPS + ARL + AFP.
     *
     * Estaba habilitada también con EPS+ARL (3), EPS+ARL+CCF (4) y EPS+ARL+AFP+CCF (6), pero de
     * los 2.191 contratos de la modalidad, 2.163 son del plan 5 y solo 28 quedaron en los otros
     * tres. Quitar las relaciones saca esos planes del selector del formulario de contrato y
     * del cotizador público, que es de donde salen los tres a la vez (ver
     * TarifaAsesorService::combinaciones y ContratoController::datosFormulario).
     *
     * Los 28 contratos existentes NO se tocan: se siguen facturando con su plan actual. Lo que
     * cambia es que ya no se pueden crear nuevos con esa combinación.
     */
    public function up(): void
    {
        DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', self::MODALIDAD_IR)
            ->where('plan_id', '!=', self::PLAN_EPS_ARL_AFP)
            ->delete();
    }

    public function down(): void
    {
        foreach ([3, 4, 6] as $planId) {
            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', self::MODALIDAD_IR)
                ->where('plan_id', $planId)
                ->exists();

            if (! $existe) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => self::MODALIDAD_IR,
                    'plan_id' => $planId,
                    'solo_ia' => false,
                ]);
            }
        }
    }
};
