<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modalidad E-1: pagar salud (y ARL/caja) sin pensión, en dos planillas.
 *
 * El operador rechaza una planilla E ordinaria con 30 días de salud y cero de
 * pensión, así que el pago se parte en dos:
 *
 *   Paso 1 — planilla E: 1 día de pensión + 1 día de caja, salud en cero.
 *   Paso 2 — planilla N (corrección de la anterior, ya pagada): la línea C
 *            sube la salud, la ARL y la caja a los días reales con IBC
 *            completo y pone subtipo 4 (requisitos cumplidos para pensión).
 *
 * La modalidad ya existía como `-4` ("SimpleP" / "TipoE- (1 dia pension)"),
 * heredada del legacy: 8.184 de sus 8.195 planos vienen de Brygar y ningún
 * contrato activo la usa. Se reactiva en vez de crear una nueva porque es
 * literalmente el mismo plan, y porque los 11 planos nativos que tiene son
 * gente del aliado 7 haciendo esto a mano (poniendo num_dias = 1, que deja la
 * salud también en un día).
 */
return new class extends Migration
{
    private const MODALIDAD_E1 = -4;

    /** EPS+ARL (3) y EPS+ARL+CCF (4) — los planes sin pensión que admite. */
    private const PLANES_E1 = [3, 4];

    public function up(): void
    {
        DB::table('tipo_modalidad')
            ->where('id', self::MODALIDAD_E1)
            ->update([
                'activo'      => 1,
                'observacion' => 'E-1 (1 día pensión + corrección)',
            ]);

        // Estaba amarrada a los planes CON pensión (5 y 6), que es justo lo que
        // esta modalidad no paga. Se reemplazan por los dos sin pensión.
        DB::table('modalidad_planes')->where('tipo_modalidad_id', self::MODALIDAD_E1)->delete();

        foreach (self::PLANES_E1 as $planId) {
            DB::table('modalidad_planes')->insert([
                'tipo_modalidad_id' => self::MODALIDAD_E1,
                'plan_id'           => $planId,
                'solo_ia'           => 0,
            ]);
        }

        Schema::table('operador_planillas_api', function (Blueprint $table) {
            // 1 = planilla E de un día, 2 = corrección N. El resto de las
            // liquidaciones quedan en 1, que es lo que siempre han sido.
            $table->unsignedTinyInteger('paso')->default(1)->after('n_plano');
            // Campos 9 y 10 del registro tipo 1 de la corrección: sin ellos el
            // operador no sabe qué planilla se está corrigiendo.
            $table->string('planilla_asociada_numero', 80)->nullable()->after('numero_planilla');
            $table->date('planilla_asociada_fecha_pago')->nullable()->after('planilla_asociada_numero');
        });
    }

    public function down(): void
    {
        Schema::table('operador_planillas_api', function (Blueprint $table) {
            $table->dropColumn(['paso', 'planilla_asociada_numero', 'planilla_asociada_fecha_pago']);
        });

        DB::table('modalidad_planes')->where('tipo_modalidad_id', self::MODALIDAD_E1)->delete();

        foreach ([5, 6] as $planId) {
            DB::table('modalidad_planes')->insert([
                'tipo_modalidad_id' => self::MODALIDAD_E1,
                'plan_id'           => $planId,
                'solo_ia'           => 0,
            ]);
        }

        DB::table('tipo_modalidad')
            ->where('id', self::MODALIDAD_E1)
            ->update(['activo' => 0, 'observacion' => 'TipoE- (1 dia pension)']);
    }
};
