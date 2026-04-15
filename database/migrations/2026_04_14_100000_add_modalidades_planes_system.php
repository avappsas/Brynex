<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Sistema de filtrado inteligente: planes permitidos por tipo de modalidad.
 *
 * Cambios:
 *  1. Nuevo plan: SOLO_AFP en planes_contrato
 *  2. Nueva modalidad: 'En el Exterior' en tipo_modalidad
 *  3. Tabla pivote modalidad_planes (configurable desde UI)
 *  4. Campo es_independiente en razones_sociales
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Plan SOLO_AFP ─────────────────────────────────────────────
        // Solo insertar si no existe
        if (!DB::table('planes_contrato')->where('codigo', 'SOLO_AFP')->exists()) {
            DB::table('planes_contrato')->insert([
                'codigo'           => 'SOLO_AFP',
                'nombre'           => 'Solo AFP',
                'incluye_eps'      => 0,
                'incluye_arl'      => 0,
                'incluye_pension'  => 1,
                'incluye_caja'     => 0,
                'activo'           => 1,
            ]);
        }

        // ── 2. Modalidad 'En el Exterior' ────────────────────────────────
        if (!DB::table('tipo_modalidad')->where('tipo_modalidad', 'Ext')->exists()) {
            DB::table('tipo_modalidad')->insert([
                'id'            => 14,
                'tipo_modalidad' => 'Ext',
                'observacion'   => 'En el Exterior',
                'orden'         => 4,        // entre I Act (3) e I Venc (3) → con orden 4 queda junto
                'modalidad'     => 'Exterior',
                'activo'        => 1,
            ]);
        }

        // ── 3. Tabla pivote modalidad_planes ─────────────────────────────
        Schema::create('modalidad_planes', function (Blueprint $table) {
            $table->smallInteger('tipo_modalidad_id');
            $table->unsignedTinyInteger('plan_id');
            $table->primary(['tipo_modalidad_id', 'plan_id']);
            $table->foreign('tipo_modalidad_id')
                  ->references('id')->on('tipo_modalidad')
                  ->cascadeOnDelete();
            $table->foreign('plan_id')
                  ->references('id')->on('planes_contrato')
                  ->cascadeOnDelete();
        });

        // Obtener IDs reales de planes por código
        $planes = DB::table('planes_contrato')
            ->pluck('id', 'codigo')
            ->toArray();

        // Mapeo: tipo_modalidad_id → [codigos_planes_permitidos]
        $mapeo = [
            //  Dependiente E (0) → EPS+ARL, EPS+ARL+CCF, EPS+ARL+AFP, EPS+ARL+AFP+CCF
            0  => ['EPS_ARL', 'EPS_ARL_CCF', 'EPS_ARL_AFP', 'EPS_ARL_AFP_CCF'],

            // Independiente Mes Actual (11) → todos los independientes
            11 => ['SOLO_EPS', 'SOLO_ARL', 'EPS_ARL', 'EPS_ARL_CCF', 'EPS_ARL_AFP', 'EPS_ARL_AFP_CCF', 'EPS_AFP', 'SOLO_AFP'],

            // Independiente Mes Vencido (10) → igual que I Act
            10 => ['SOLO_EPS', 'SOLO_ARL', 'EPS_ARL', 'EPS_ARL_CCF', 'EPS_ARL_AFP', 'EPS_ARL_AFP_CCF', 'EPS_AFP', 'SOLO_AFP'],

            // En el Exterior (14) → Solo AFP
            14 => ['SOLO_AFP'],

            // ARL Tipo Y (8) → Solo ARL
            8  => ['SOLO_ARL'],

            // Solo EPS (6) → Solo EPS
            6  => ['SOLO_EPS'],

            // Solo EPS y Planilla K (7) → EPS+ARL
            7  => ['EPS_ARL'],

            // Estudiante K (-1) → Solo ARL
            -1 => ['SOLO_ARL'],

            // Tiempo Parcial: 7, 14, 21, 30 días (IDs: 1,2,3,4)
            1  => ['EPS_ARL_CCF', 'EPS_ARL_AFP_CCF'],
            2  => ['EPS_ARL_CCF', 'EPS_ARL_AFP_CCF'],
            3  => ['EPS_ARL_CCF', 'EPS_ARL_AFP_CCF'],
            4  => ['EPS_ARL_CCF', 'EPS_ARL_AFP_CCF'],

            // TP dobles (-6,-7,-8)
            -6 => ['EPS_ARL_CCF', 'EPS_ARL_AFP_CCF'],
            -7 => ['EPS_ARL_CCF', 'EPS_ARL_AFP_CCF'],
            -8 => ['EPS_ARL_CCF', 'EPS_ARL_AFP_CCF'],

            // SimpleP (-4) → EPS+ARL+AFP
            -4 => ['EPS_ARL_AFP'],

            // Contribución Solidaria (5) → Solo EPS
            5  => ['SOLO_EPS'],

            // Ingreso-Retiro (12) → igual que Dependiente E
            12 => ['EPS_ARL', 'EPS_ARL_CCF', 'EPS_ARL_AFP', 'EPS_ARL_AFP_CCF'],

            // UPC (13) → Solo EPS
            13 => ['SOLO_EPS'],
        ];

        $rows = [];
        foreach ($mapeo as $modalidadId => $codigosPlanes) {
            foreach ($codigosPlanes as $codigo) {
                if (!isset($planes[$codigo])) continue;
                $rows[] = [
                    'tipo_modalidad_id' => $modalidadId,
                    'plan_id'           => $planes[$codigo],
                ];
            }
        }

        if (!empty($rows)) {
            DB::table('modalidad_planes')->insert($rows);
        }

        // ── 4. Campo es_independiente en razones_sociales ────────────────
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->boolean('es_independiente')->default(false)->after('observacion')
                  ->comment('Si true, solo puede usar modalidades independientes (I Act, I Venc, Ext)');
        });
    }

    public function down(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->dropColumn('es_independiente');
        });

        Schema::dropIfExists('modalidad_planes');

        DB::table('tipo_modalidad')->where('tipo_modalidad', 'Ext')->delete();
        DB::table('planes_contrato')->where('codigo', 'SOLO_AFP')->delete();
    }
};
