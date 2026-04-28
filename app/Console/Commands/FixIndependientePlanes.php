<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Correcciones para contratos de modalidades independientes:
 *
 *  1. Vincula planes con ARL a las modalidades independientes en modalidad_planes.
 *  2. Detecta contratos independientes cuyo plan_id no coincide con sus
 *     entidades reales (eps_id, arl_id, pension_id, caja_id) y los corrige
 *     asignando el plan más apropiado disponible en planes_contrato.
 *
 * Nota: el campo es_independiente de razones_sociales es responsabilidad
 * de cada aliado — se gestiona desde /admin/configuracion/modalidades.
 *
 * Uso:
 *   php artisan brynex:fix-independiente-planes             → modo DRY-RUN (solo reporta)
 *   php artisan brynex:fix-independiente-planes --ejecutar  → aplica los cambios
 */
class FixIndependientePlanes extends Command
{
    protected $signature   = 'brynex:fix-independiente-planes {--ejecutar : Aplica los cambios (sin esta flag solo reporta)}';
    protected $description = 'Vincula planes ARL a modalidades independientes y corrige plan_id en contratos independientes existentes';

    /** IDs de modalidades independientes */
    const MODS_INDEP = [10, 11, 14];

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        if (!$ejecutar) {
            $this->warn('⚠️  MODO DRY-RUN — no se aplicará ningún cambio. Use --ejecutar para guardar.');
            $this->newLine();
        }

        // ─────────────────────────────────────────────────────────────────
        // PASO 1: Vincular planes con ARL a modalidades independientes
        // ─────────────────────────────────────────────────────────────────
        $this->info('【1】 Vinculando planes con ARL a modalidades independientes…');

        $planesConArl = DB::table('planes_contrato')
            ->where('activo', true)
            ->where('incluye_arl', true)
            ->get(['id', 'nombre', 'incluye_eps', 'incluye_arl', 'incluye_pension', 'incluye_caja']);

        if ($planesConArl->isEmpty()) {
            $this->warn('   No se encontraron planes con ARL activos.');
        } else {
            $existingLinks = DB::table('modalidad_planes')
                ->whereIn('tipo_modalidad_id', self::MODS_INDEP)
                ->get(['tipo_modalidad_id', 'plan_id'])
                ->groupBy('tipo_modalidad_id')
                ->map(fn($rows) => $rows->pluck('plan_id')->toArray());

            $nuevos = [];
            foreach (self::MODS_INDEP as $modId) {
                $yaExisten = $existingLinks->get($modId, []);
                foreach ($planesConArl as $plan) {
                    if (!in_array($plan->id, $yaExisten)) {
                        $nuevos[] = ['tipo_modalidad_id' => $modId, 'plan_id' => $plan->id];
                    }
                }
            }

            if (empty($nuevos)) {
                $this->info('   ✅ Todos los planes con ARL ya estaban vinculados.');
            } else {
                $this->table(
                    ['Modalidad ID', 'Plan ID', 'Plan Nombre'],
                    collect($nuevos)->map(fn($r) => [
                        $r['tipo_modalidad_id'],
                        $r['plan_id'],
                        $planesConArl->firstWhere('id', $r['plan_id'])?->nombre ?? '?',
                    ])->toArray()
                );
                if ($ejecutar) {
                    DB::table('modalidad_planes')->insert($nuevos);
                    $this->info('   ✅ ' . count($nuevos) . ' nuevas vinculaciones insertadas.');
                } else {
                    $this->line('   [DRY-RUN] Se insertarían ' . count($nuevos) . ' vinculaciones.');
                }
            }
        }

        $this->newLine();

        // ─────────────────────────────────────────────────────────────────
        // PASO 2: Corregir plan_id en contratos independientes existentes
        // ─────────────────────────────────────────────────────────────────
        $this->info('【2】 Corrigiendo plan_id en contratos independientes…');

        // Todos los planes activos indexados por combinación de entidades
        $todosPlanes = DB::table('planes_contrato')
            ->where('activo', true)
            ->get(['id', 'nombre', 'incluye_eps', 'incluye_arl', 'incluye_pension', 'incluye_caja']);

        // Crear mapa: "eps|arl|pen|caja" => plan
        $planPorCombinacion = [];
        foreach ($todosPlanes as $p) {
            $key = ((bool)$p->incluye_eps    ? '1' : '0') . '|' .
                   ((bool)$p->incluye_arl    ? '1' : '0') . '|' .
                   ((bool)$p->incluye_pension ? '1' : '0') . '|' .
                   ((bool)$p->incluye_caja   ? '1' : '0');
            // Guardar todos los planes por combinación (puede haber varios; tomamos el primero)
            if (!isset($planPorCombinacion[$key])) {
                $planPorCombinacion[$key] = $p;
            }
        }

        // Obtener contratos independientes con sus entidades
        $contratos = DB::table('contratos as c')
            ->join('planes_contrato as p', 'p.id', '=', 'c.plan_id')
            ->whereIn('c.tipo_modalidad_id', self::MODS_INDEP)
            ->whereNotNull('c.plan_id')
            ->get([
                'c.id', 'c.cedula', 'c.tipo_modalidad_id', 'c.plan_id',
                'c.eps_id', 'c.arl_id', 'c.pension_id', 'c.caja_id',
                'p.nombre as plan_nombre',
                'p.incluye_eps', 'p.incluye_arl', 'p.incluye_pension', 'p.incluye_caja',
            ]);

        $this->info("   Contratos independientes con plan: {$contratos->count()}");

        $actualizados   = 0;
        $sinCambio      = 0;
        $sinPlanMatch   = 0;
        $reporteFilas   = [];

        foreach ($contratos as $c) {
            // Determinar combinación real según entidades guardadas
            $tieneEps = !empty($c->eps_id)     ? '1' : '0';
            $tieneArl = !empty($c->arl_id)     ? '1' : '0';
            $tienePen = !empty($c->pension_id) ? '1' : '0';
            $tieneCaj = !empty($c->caja_id)    ? '1' : '0';

            $keyReal = "{$tieneEps}|{$tieneArl}|{$tienePen}|{$tieneCaj}";

            // Determinar combinación actual según plan asignado
            $keyActual = ((bool)$c->incluye_eps     ? '1' : '0') . '|' .
                         ((bool)$c->incluye_arl     ? '1' : '0') . '|' .
                         ((bool)$c->incluye_pension  ? '1' : '0') . '|' .
                         ((bool)$c->incluye_caja     ? '1' : '0');

            // Si ya coincide, no hacer nada
            if ($keyReal === $keyActual) {
                $sinCambio++;
                continue;
            }

            // Buscar plan correcto
            $planCorrecto = $planPorCombinacion[$keyReal] ?? null;

            if (!$planCorrecto) {
                $sinPlanMatch++;
                $reporteFilas[] = [
                    $c->id, $c->cedula, $c->plan_nombre,
                    '❌ Sin plan para: EPS='.$tieneEps.' ARL='.$tieneArl.' AFP='.$tienePen.' CCF='.$tieneCaj,
                    '—',
                ];
                continue;
            }

            $reporteFilas[] = [
                $c->id,
                $c->cedula,
                $c->plan_nombre . ' → ' . $planCorrecto->nombre,
                'EPS='.$tieneEps.' ARL='.$tieneArl.' AFP='.$tienePen.' CCF='.$tieneCaj,
                $ejecutar ? '✅ Actualizado' : '[DRY-RUN]',
            ];

            if ($ejecutar) {
                DB::table('contratos')
                    ->where('id', $c->id)
                    ->update(['plan_id' => $planCorrecto->id]);
                $actualizados++;
            }
        }

        if (!empty($reporteFilas)) {
            $this->table(
                ['Contrato ID', 'Cédula', 'Plan', 'Combinación Real', 'Estado'],
                $reporteFilas
            );
        }

        $this->newLine();
        $this->info("   Sin cambio necesario : {$sinCambio}");
        if ($ejecutar) {
            $this->info("   ✅ Actualizados       : {$actualizados}");
        } else {
            $actualizarPendientes = count(array_filter($reporteFilas, fn($r) => str_contains($r[4], 'DRY-RUN')));
            $this->line("   [DRY-RUN] Actualizarían : {$actualizarPendientes}");
        }
        if ($sinPlanMatch > 0) {
            $this->warn("   ⚠️  Sin plan matching    : {$sinPlanMatch} (revisar planes_contrato)");
        }

        $this->newLine();
        if (!$ejecutar) {
            $this->warn('Ejecute con --ejecutar para aplicar los cambios:');
            $this->line('  php artisan brynex:fix-independiente-planes --ejecutar');
        } else {
            $this->info('✅ Proceso completado.');
        }

        return self::SUCCESS;
    }
}
