<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fondo de Solidaridad — Programa de Subsidio al Aporte en Pensión (PSAP).
 *
 * El Estado subsidia parte de la pensión de quien está inscrito en el programa
 * (Decreto 543 de 2026) y la persona paga el resto según su grupo: 4 % el
 * independiente, 4,8 % el desempleado, 3,2 % la madre sustituta y 0,8 % la
 * persona con discapacidad, siempre sobre un salario mínimo. En la planilla es
 * el cotizante 33, en planilla I, y la salud va completa: probado contra ARUS
 * Enlace el 16-sep-2026, el operador no deja liquidar el 33 sin ella.
 *
 * El grupo NO se modela como modalidad ni como plan, sino como campo del
 * contrato con espejo en el plano, igual que los días del Tiempo Parcial
 * Independiente: decide la tarifa de pensión, no qué entidades lleva.
 */
return new class extends Migration
{
    /** El id de tipo_modalidad no es IDENTITY: se asigna a mano. */
    private const MODALIDAD_FONDO_SOLIDARIDAD = 19;

    public function up(): void
    {
        // ── 1. Grupo del PSAP por contrato ───────────────────────────────
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('grupo_fondo_solidaridad', 20)->nullable()->after('dias_tp_caja');
        });

        // ── 2. Espejo en el plano ────────────────────────────────────────
        // Si el contrato cambia de grupo, la planilla de un mes ya generado
        // no puede cambiar de tarifa.
        Schema::table('planos', function (Blueprint $table) {
            $table->string('grupo_fondo_solidaridad', 20)->nullable();
        });

        // ── 3. Modalidad ─────────────────────────────────────────────────
        if (! DB::table('tipo_modalidad')->where('id', self::MODALIDAD_FONDO_SOLIDARIDAD)->exists()) {
            DB::table('tipo_modalidad')->insert([
                'id' => self::MODALIDAD_FONDO_SOLIDARIDAD,
                'tipo_modalidad' => 'FSP',
                'observacion' => 'Fondo de Solidaridad',
                'descripcion' => 'Beneficiario del Programa de Subsidio al Aporte en Pensión: el Estado subsidia '
                                     .'parte de la pensión y la persona paga el resto según su grupo (4 % independiente, '
                                     .'4,8 % desempleado, 3,2 % madre sustituta, 0,8 % discapacidad), sobre un salario '
                                     .'mínimo y con la salud completa. Solo Colpensiones y solo si está inscrito en el '
                                     .'programa. Cotizante 33, planilla I.',
                'orden' => 4,   // junto a Independientes (I Venc) y TP Ind
                'modalidad' => 'Fondo de Solidaridad',
                'activo' => 1,
                // Informativa: el tipo de cotizante lo pone PilaCotizanteCalculator.
                'tipo_cot' => 33,
                'es_tiempo_parcial' => 0,
            ]);
        }

        // ── 4. Planes permitidos ─────────────────────────────────────────
        // EPS + AFP para todos. Solo AFP solo para el desempleado, y hoy Enlace
        // lo rechaza porque el 33 está obligado a cotizar salud — se deja por
        // decisión del negocio. Qué grupo ve cada plan lo filtra el formulario.
        $planes = DB::table('planes_contrato')
            ->whereIn('codigo', ['EPS_AFP', 'SOLO_AFP'])
            ->pluck('id');

        foreach ($planes as $planId) {
            $existe = DB::table('modalidad_planes')
                ->where('tipo_modalidad_id', self::MODALIDAD_FONDO_SOLIDARIDAD)
                ->where('plan_id', $planId)
                ->exists();

            if (! $existe) {
                DB::table('modalidad_planes')->insert([
                    'tipo_modalidad_id' => self::MODALIDAD_FONDO_SOLIDARIDAD,
                    'plan_id' => $planId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('modalidad_planes')->where('tipo_modalidad_id', self::MODALIDAD_FONDO_SOLIDARIDAD)->delete();

        // La modalidad solo se borra si nadie la usó todavía.
        if (! DB::table('contratos')->where('tipo_modalidad_id', self::MODALIDAD_FONDO_SOLIDARIDAD)->exists()) {
            DB::table('tipo_modalidad')->where('id', self::MODALIDAD_FONDO_SOLIDARIDAD)->delete();
        }

        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('grupo_fondo_solidaridad');
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('grupo_fondo_solidaridad');
        });
    }
};
