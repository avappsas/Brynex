<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Ingreso-Retiro (12) y el plan EPS + ARL + AFP + CCF. */
    private const MODALIDAD_IR = 12;

    private const PLAN_EPS_ARL_AFP_CCF = 6;

    /**
     * Vuelve a habilitar EPS+ARL+AFP+CCF en Ingreso-Retiro, pero solo por mostrador.
     *
     * La migración 2026_08_07_150000 dejó la modalidad con un único plan (EPS+ARL+AFP) porque
     * de 2.191 contratos solo 28 usaban los otros tres. El aliado sigue necesitando afiliar con
     * caja en esta modalidad, así que la relación vuelve marcada solo_ia = true: eso la hace
     * visible en el selector de planes del formulario de contrato (ContratoController::datosFormulario
     * no filtra por solo_ia) sin devolverla al cotizador público ni al tarifario del aliado
     * (TarifaAsesorService::combinaciones y ConfiguracionAliadoController sí filtran solo_ia = false).
     *
     * El cargo fijo sin-CCF de $100 (Contrato::aplicaCargoSinCcf) no se ve afectado: ya depende de
     * que el plan no incluya caja, y este sí la incluye.
     */
    public function up(): void
    {
        $existe = DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', self::MODALIDAD_IR)
            ->where('plan_id', self::PLAN_EPS_ARL_AFP_CCF)
            ->exists();

        if (! $existe) {
            DB::table('modalidad_planes')->insert([
                'tipo_modalidad_id' => self::MODALIDAD_IR,
                'plan_id' => self::PLAN_EPS_ARL_AFP_CCF,
                'solo_ia' => true,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('modalidad_planes')
            ->where('tipo_modalidad_id', self::MODALIDAD_IR)
            ->where('plan_id', self::PLAN_EPS_ARL_AFP_CCF)
            ->delete();
    }
};
