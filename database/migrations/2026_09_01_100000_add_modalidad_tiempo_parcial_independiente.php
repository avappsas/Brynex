<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tiempo Parcial para independientes — cotizante 76 (Resolución 1529 de 2026).
 *
 * Desde el 1-sep-2026 el independiente que trabaja por días o semanas y gana
 * menos de un SMMLV cotiza con tipo 76: pensión por semanas, ARL siempre sobre
 * el mínimo completo, caja voluntaria al 0,6% o 2% y sin salud. El cotizante 51
 * queda solo para los dependientes.
 *
 * Los días NO se modelan como modalidad (que es lo que produjo las ocho
 * TP(7), TP(14), TP(7-14)… de los dependientes) ni como plan (que responde qué
 * entidades lleva). Van como campo del contrato, con espejo en el plano, igual
 * que se hizo con paga_mes_actual al jubilar la modalidad 11.
 *
 * Los planos y contratos existentes quedan con los campos en NULL y siguen
 * leyendo los días de su modalidad: esta migración no cambia ninguna
 * liquidación anterior.
 */
return new class extends Migration
{
    /** Tiempo Parcial Independiente. El id de tipo_modalidad no es IDENTITY: se asigna a mano. */
    private const MODALIDAD_TP_IND = 18;

    public function up(): void
    {
        // ── 1. Días de tiempo parcial por contrato ───────────────────────
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_tp_afp')->nullable()->after('porcentaje_caja');
            $table->unsignedTinyInteger('dias_tp_caja')->nullable()->after('dias_tp_afp');
        });

        // ── 2. Espejo en el plano ────────────────────────────────────────
        // El plano es el snapshot del período: si el mes que viene el contrato
        // cotiza otros días, la planilla ya generada no puede cambiar.
        Schema::table('planos', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_tp_afp')->nullable();
            $table->unsignedTinyInteger('dias_tp_caja')->nullable();
        });

        // ── 3. Modalidad ─────────────────────────────────────────────────
        // dias_afp/dias_caja quedan en NULL a propósito: en esta modalidad los
        // días son del contrato, no del catálogo. dias_arl = 30 porque la ARL
        // del 76 siempre se paga por el mes completo.
        if (! DB::table('tipo_modalidad')->where('id', self::MODALIDAD_TP_IND)->exists()) {
            DB::table('tipo_modalidad')->insert([
                'id' => self::MODALIDAD_TP_IND,
                'tipo_modalidad' => 'TP Ind',
                'observacion' => 'Tiempo Parcial IND',
                'descripcion' => 'Independiente que trabaja por días o semanas y gana menos de un salario mínimo. '
                                     .'Cotiza pensión por semanas y ARL sobre el mínimo completo, sin salud. '
                                     .'Cotizante 76 (Resolución 1529 de 2026).',
                'orden' => 3,   // junto a Independientes (I Venc)
                'modalidad' => 'TP Independiente',
                'activo' => 1,
                // Columna informativa del catálogo: quién lee el tipo de cotizante
                // es PilaCotizanteCalculator, pero la tabla no puede decir 51 acá.
                'tipo_cot' => 76,
                'es_tiempo_parcial' => 1,
                'dias_arl' => 30,
                'dias_afp' => null,
                'dias_caja' => null,
            ]);
        }

        // ── 4. Plan ARL + AFP ────────────────────────────────────────────
        // El plan de cabecera del 76: pensión obligatoria, ARL obligatoria y
        // caja voluntaria que en este caso no se lleva. No existía.
        if (! DB::table('planes_contrato')->where('codigo', 'ARL_AFP')->exists()) {
            DB::table('planes_contrato')->insert([
                'codigo' => 'ARL_AFP',
                'nombre' => 'ARL + AFP',
                'descripcion' => 'Pensión y riesgos laborales, sin salud ni caja.',
                'incluye_eps' => 0,
                'incluye_arl' => 1,
                'incluye_pension' => 1,
                'incluye_caja' => 0,
                'activo' => 1,
            ]);
        }

        // ── 5. Planes permitidos en la modalidad ─────────────────────────
        // Solo los que llevan pensión: en el 76 el aporte a pensión es
        // obligatorio y es la razón de ser del esquema (sumar semanas).
        $planes = DB::table('planes_contrato')
            ->whereIn('codigo', ['ARL_AFP', 'ARL_AFP_CCF'])
            ->pluck('id', 'codigo');

        foreach ($planes as $planId) {
            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', self::MODALIDAD_TP_IND)
                ->where('plan_id', $planId)
                ->exists();

            if (! $existe) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => self::MODALIDAD_TP_IND,
                    'plan_id' => $planId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('modalidad_planes')->where('tipo_modalidad_id', self::MODALIDAD_TP_IND)->delete();

        // La modalidad solo se borra si nadie la usó todavía.
        if (! DB::table('contratos')->where('tipo_modalidad_id', self::MODALIDAD_TP_IND)->exists()) {
            DB::table('tipo_modalidad')->where('id', self::MODALIDAD_TP_IND)->delete();
        }

        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn(['dias_tp_afp', 'dias_tp_caja']);
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['dias_tp_afp', 'dias_tp_caja']);
        });
    }
};
