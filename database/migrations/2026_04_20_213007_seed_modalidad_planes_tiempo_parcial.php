<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Asignar planes a las modalidades de Tiempo Parcial en modalidad_planes.
 *
 * Problema: Las modalidades TP (ids 1,2,3,4,-6,-7,-8) y UPC (13) no tenían
 * ningún plan asignado en la tabla modalidad_planes, por lo que el select de
 * planes quedaba vacío al seleccionar cualquiera de estas modalidades.
 *
 * Reglas de negocio:
 *   - Tiempo Parcial (todas las variantes: TP7, TP14, TP21, TP30, TP7-14, TP7-21, TP14-21):
 *       Plan principal: ARL + AFP + CCF (id=11) — para contratos con pensión
 *       Plan APTP:      ARL + CCF          (id=12, nuevo) — para contratos sin pensión
 *   - UPC (id=13): Solo EPS (id=1)
 *   - Todos (id=-100): todos los planes disponibles (sin restricción)
 *
 * Plan nuevo creado aquí:
 *   id=12: "ARL + CCF" — incluye_arl=1, incluye_caja=1, incluye_pension=0, incluye_eps=0
 */
return new class extends Migration
{
    // IDs de modalidades Tiempo Parcial
    private const IDS_TP = [1, 2, 3, 4, -6, -7, -8];

    public function up(): void
    {
        // ── 1. Crear plan "ARL + CCF" si no existe (para APTP sin AFP) ─────
        $planArlCcf = DB::table('planes_contrato')->where('nombre', 'ARL + CCF')->first();
        if (!$planArlCcf) {
            // En SQL Server, el id es IDENTITY — dejamos que el motor lo asigne automáticamente
            DB::statement("SET IDENTITY_INSERT planes_contrato OFF");
            DB::table('planes_contrato')->insert([
                'codigo'           => 'ARL_CCF',
                'nombre'           => 'ARL + CCF',
                'incluye_eps'      => false,
                'incluye_arl'      => true,
                'incluye_pension'  => false,
                'incluye_caja'     => true,
                'activo'           => true,
            ]);
        }
        $idArlCcf = DB::table('planes_contrato')->where('nombre', 'ARL + CCF')->value('id');

        // ID del plan ARL + AFP + CCF (ya existente, id=11)
        $idArlAfpCcf = 11;

        // ── 2. Insertar registros en modalidad_planes para cada TP ──────────
        $inserts = [];
        foreach (self::IDS_TP as $modId) {
            // Verificar que la modalidad exista antes de insertar
            $exists = DB::table('tipo_modalidad')->where('id', $modId)->exists();
            if (!$exists) continue;

            // ¿Ya existen registros para esta modalidad?
            $yaExiste = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', $modId)
                ->exists();
            if ($yaExiste) continue;

            // Añadir ARL + AFP + CCF (plan principal con pensión)
            $inserts[] = ['tipo_modalidad_id' => $modId, 'plan_id' => $idArlAfpCcf];
            // Añadir ARL + CCF (APTP — sin pensión)
            $inserts[] = ['tipo_modalidad_id' => $modId, 'plan_id' => $idArlCcf];
        }

        // ── 3. UPC (id=13): Solo EPS ────────────────────────────────────────
        $upcExists = DB::table('tipo_modalidad')->where('id', 13)->exists();
        $upcYaAsig = DB::table('modalidad_planes')->where('tipo_modalidad_id', 13)->exists();
        if ($upcExists && !$upcYaAsig) {
            $inserts[] = ['tipo_modalidad_id' => 13, 'plan_id' => 1]; // Solo EPS
        }

        if (!empty($inserts)) {
            DB::table('modalidad_planes')->insert($inserts);
        }
    }

    public function down(): void
    {
        // Eliminar los registros TP y UPC insertados por esta migración
        DB::table('modalidad_planes')
            ->whereIn('tipo_modalidad_id', array_merge(self::IDS_TP, [13]))
            ->delete();

        // Eliminar el plan ARL + CCF si fue creado aquí
        // (solo si no tiene contratos asociados)
        $planId = DB::table('planes_contrato')->where('nombre', 'ARL + CCF')->value('id');
        if ($planId) {
            $enUso = DB::table('contratos')->where('plan_id', $planId)->exists();
            if (!$enUso) {
                DB::table('planes_contrato')->where('id', $planId)->delete();
            }
        }
    }
};
