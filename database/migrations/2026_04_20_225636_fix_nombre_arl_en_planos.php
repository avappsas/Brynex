<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de datos: nombre_arl en tabla planos.
 *
 * Bug: el campo nombre_arl se guardaba tomando el arl del contrato individual,
 * ignorando que los contratos dependientes usan la ARL de la razón social (arl_nit).
 *
 * Solución:
 *  1. Para planos cuya razón social (razones_sociales) tiene arl_nit → usar nombre_arl de la tabla arls.
 *  2. Fallback: si la RS no tiene arl_nit, dejar el nombre_arl actual (ya viene del contrato individual).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Actualizar nombre_arl en planos donde la RS tiene arl_nit
        // y el nombre guardado NO coincide con el nombre_arl de la tabla arls.
        //
        // Lógica SQL:
        //   JOIN planos → razones_sociales (por razon_social_id)
        //   JOIN razones_sociales → arls (por arl_nit = arls.nit)
        //   WHERE planos.nombre_arl IS DISTINCT FROM arls.nombre_arl
        //   SET planos.nombre_arl = arls.nombre_arl

        DB::statement("
            UPDATE planos
            SET nombre_arl = arls.nombre_arl
            FROM planos
            JOIN razones_sociales ON razones_sociales.id = planos.razon_social_id
            JOIN arls ON arls.nit = razones_sociales.arl_nit
            WHERE planos.deleted_at IS NULL
              AND razones_sociales.arl_nit IS NOT NULL
              AND razones_sociales.arl_nit <> ''
              AND (planos.nombre_arl IS NULL
                   OR planos.nombre_arl <> arls.nombre_arl)
        ");
    }

    public function down(): void
    {
        // No se puede revertir automáticamente (datos sobrescritos).
        // Para revertir manualmente: restore desde backup o re-generar los planos afectados.
    }
};
