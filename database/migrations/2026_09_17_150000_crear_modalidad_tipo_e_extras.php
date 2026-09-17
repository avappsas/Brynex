<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Tipo E - Extras" (-12): una sola modalidad para todo lo que se vende suelto.
 *
 * Hasta hoy cada combinación era su propia modalidad —Caja 30 (-5), Caja 14
 * (-10), Pensión 30 (-11)—, así que abrir "solo salud" habría sido una fila
 * más, y otra para "salud y riesgos". No escala: lo que cambia entre ellas es
 * QUÉ se vende, y eso ya lo dice el plan del contrato.
 *
 * Desde aquí la modalidad dice CÓMO se paga (dos planillas encadenadas) y el
 * plan dice QUÉ se paga. Los cinco planes de la modalidad:
 *
 *   SOLO_EPS      salud del mes                (corrección por el portal)
 *   EPS_ARL       salud y riesgos del mes      (corrección por el portal)
 *   SOLO_CCF_14   caja de 14 días              (corrección por API)
 *   SOLO_CCF_30   caja del mes                 (corrección por API)
 *   SOLO_AFP      pensión del mes              (corrección por API)
 *
 * Los días de caja dejan de vivir en `tipo_modalidad.dias_caja` y pasan a ser
 * dos planes distintos, que es como el usuario los pide.
 *
 * Las tres modalidades viejas quedan INACTIVAS, no se borran: los contratos y
 * los planos que ya existen las siguen usando, y el tarifario conserva sus
 * celdas. Ver PilaCotizanteDosPasos.
 */
return new class extends Migration
{
    private const EXTRAS = -12;

    private const VIEJAS = [-5, -10, -11];

    private const FAMILIA = 'Extraordinarias';

    /** Planes de la modalidad, en el orden en que se muestran. */
    private const PLANES = [
        'SOLO_EPS',
        'EPS_ARL',
        'SOLO_CCF_14',
        'SOLO_CCF_30',
        'SOLO_AFP',
    ];

    public function up(): void
    {
        // ── 1. Los dos planes de caja, uno por cantidad de días ──────────
        $nuevos = [
            'SOLO_CCF_14' => ['Solo CCF 14 días', 'Solo caja de compensación por 14 días.'],
            'SOLO_CCF_30' => ['Solo CCF 30 días', 'Solo caja de compensación por el mes completo.'],
        ];

        foreach ($nuevos as $codigo => [$nombre, $descripcion]) {
            if (DB::table('planes_contrato')->where('codigo', $codigo)->exists()) {
                continue;
            }

            DB::table('planes_contrato')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'incluye_eps' => 0,
                'incluye_arl' => 0,
                'incluye_pension' => 0,
                'incluye_caja' => 1,
                'activo' => 1,
                'descripcion' => $descripcion
                    .' Se paga en dos planillas encadenadas: una planilla E de un día y una '
                    .'corrección que anexa la caja (ver PilaCotizanteDosPasos).',
            ]);
        }

        // El SOLO_CCF sin días queda fuera de venta: lo reemplazan los dos de
        // arriba. No se borra porque los contratos de Caja 30 y Caja 14 lo usan.
        DB::table('planes_contrato')->where('codigo', 'SOLO_CCF')->update(['activo' => 0]);

        // ── 2. La modalidad ──────────────────────────────────────────────
        // El id es negativo y manual: `tipo_modalidad` no es IDENTITY (ver
        // TipoModalidad), y los negativos son las modalidades que agregó BryNex
        // sobre el catálogo heredado.
        if (! DB::table('tipo_modalidad')->where('id', self::EXTRAS)->exists()) {
            DB::table('tipo_modalidad')->insert([
                'id' => self::EXTRAS,
                'tipo_modalidad' => 'EXTRAS',
                'observacion' => 'Tipo E - Extras',
                'modalidad' => self::FAMILIA,
                'orden' => 22,
                'activo' => 1,
                'es_tiempo_parcial' => 0,
                'tipo_cot' => 1,
                'sub_tipo_cot' => 0,
                // Los días ya no salen del catálogo: los dice el plan.
                'dias_arl' => null,
                'dias_afp' => null,
                'dias_caja' => null,
                'descripcion' => 'Tipo E - Extras — el cliente compra un solo subsistema (salud, '
                    .'salud y riesgos, caja de 14 o 30 días, o pensión) y el plan dice cuál. Se paga '
                    .'en dos planillas encadenadas: una planilla E con un día de pensión, y una '
                    .'corrección N que anexa lo vendido. Solo dependientes.',
            ]);
        }

        // ── 3. Los cinco planes de la modalidad ──────────────────────────
        $ids = DB::table('planes_contrato')
            ->whereIn('codigo', self::PLANES)
            ->pluck('id', 'codigo');

        foreach (self::PLANES as $codigo) {
            $planId = $ids[$codigo] ?? null;

            if (! $planId) {
                continue;
            }

            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', self::EXTRAS)
                ->where('plan_id', $planId)
                ->exists();

            if (! $existe) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => self::EXTRAS,
                    'plan_id' => $planId,
                    'solo_ia' => 0,
                ]);
            }
        }

        // ── 4. Se apagan las tres viejas ─────────────────────────────────
        DB::table('tipo_modalidad')->whereIn('id', self::VIEJAS)->update(['activo' => 0]);
    }

    public function down(): void
    {
        DB::table('modalidad_planes')->where('tipo_modalidad_id', self::EXTRAS)->delete();
        DB::table('tipo_modalidad')->where('id', self::EXTRAS)->update(['activo' => 0]);
        DB::table('tipo_modalidad')->whereIn('id', self::VIEJAS)->update(['activo' => 1]);
        DB::table('planes_contrato')->where('codigo', 'SOLO_CCF')->update(['activo' => 1]);

        // Los planes nuevos quedan: borrarlos rompería los contratos que ya los usen.
    }
};
