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

        // Obtener contratos cuya Razón Social tiene es_independiente = true
        $contratos = DB::table('contratos as c')
            ->join('planes_contrato as p',    'p.id',  '=', 'c.plan_id')
            ->join('razones_sociales as rs',  'rs.id', '=', 'c.razon_social_id')
            ->where('rs.es_independiente', true)
            ->whereNotNull('c.plan_id')
            ->get([
                'c.id', 'c.cedula', 'c.tipo_modalidad_id', 'c.plan_id',
                'c.eps_id', 'c.arl_id', 'c.pension_id', 'c.caja_id',
                'rs.razon_social',
                'p.nombre as plan_nombre',
                'p.incluye_eps', 'p.incluye_arl', 'p.incluye_pension', 'p.incluye_caja',
            ]);

        $this->info("   Contratos con RS independiente y plan asignado: {$contratos->count()}");

        $actualizados   = 0;
        $sinCambio      = 0;
        $sinPlanMatch   = 0;
        $reporteFilas   = [];

        foreach ($contratos as $c) {
            $planActualTieneArl = (bool)$c->incluye_arl;

            // Si el plan ya incluye ARL → no hay nada que hacer
            if ($planActualTieneArl) {
                $sinCambio++;
                continue;
            }

            // El plan actual NO tiene ARL.
            // Buscar el plan equivalente CON ARL (mismo eps/pen/caja pero incluye_arl=1)
            $keyConArl = ((bool)$c->incluye_eps     ? '1' : '0') . '|1|' .
                         ((bool)$c->incluye_pension  ? '1' : '0') . '|' .
                         ((bool)$c->incluye_caja     ? '1' : '0');

            $planCorrecto = $planPorCombinacion[$keyConArl] ?? null;

            if (!$planCorrecto) {
                $sinPlanMatch++;
                $reporteFilas[] = [
                    $c->id, $c->cedula, $c->razon_social, $c->plan_nombre,
                    '❌ Sin plan con ARL para: EPS='.((bool)$c->incluye_eps?'1':'0')
                        .' ARL=1 AFP='.((bool)$c->incluye_pension?'1':'0')
                        .' CCF='.((bool)$c->incluye_caja?'1':'0'),
                    '—',
                ];
                continue;
            }

            $reporteFilas[] = [
                $c->id,
                $c->cedula,
                $c->razon_social,
                $c->plan_nombre . ' → ' . $planCorrecto->nombre,
                'EPS='.((bool)$c->incluye_eps?'1':'0').' ARL=1 AFP='.((bool)$c->incluye_pension?'1':'0').' CCF='.((bool)$c->incluye_caja?'1':'0'),
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
                ['Contrato ID', 'Cédula', 'Razón Social', 'Plan', 'Combinación Real', 'Estado'],
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

        $this->newLine();

        // ─────────────────────────────────────────────────────────────────
        // PASO 3: Sincronizar arl_id y n_arl desde la BD legacy
        //         Solo para contratos cuya RS tiene es_independiente = true
        // ─────────────────────────────────────────────────────────────────
        $this->info('【3】 Sincronizando arl_id / n_arl desde legacy para contratos independientes…');

        // Mapa aliado_nombre → bd_legacy (igual que MigrateLegacy)
        $dbsMap = [
            'Brygar_BD'       => 'brygar',
            'GiMave_Integral' => 'gimave',
            'Grupo_Fecop'     => 'fecop',
            'LuisLopez'       => 'luislopez',
            'Mave_Anderson'   => 'mave',
            'SS_Faga'         => 'faga',
        ];

        // Cargar IDs de aliados
        $aliados = DB::table('aliados')->get(['id', 'nombre', 'nit']);
        $aliadoIds = [];
        foreach ($aliados as $a) {
            if ($a->nit === '901918923')          $aliadoIds['brygar']    = $a->id;
            if ($a->nombre === 'GiMave Integral') $aliadoIds['gimave']    = $a->id;
            if ($a->nombre === 'Grupo Fecop')     $aliadoIds['fecop']     = $a->id;
            if ($a->nombre === 'Luis Lopez')      $aliadoIds['luislopez'] = $a->id;
            if ($a->nombre === 'Mave Anderson')   $aliadoIds['mave']      = $a->id;
            if ($a->nombre === 'SS Faga')         $aliadoIds['faga']      = $a->id;
        }
        $this->line('   Aliados: ' . json_encode($aliadoIds));

        $totalActualizados = 0;
        $totalSinLegacy    = 0;
        $totalSinArl       = 0;
        $totalYaTenia      = 0;

        foreach ($dbsMap as $db => $key) {
            $aliadoId = $aliadoIds[$key] ?? null;
            if (!$aliadoId) {
                $this->warn("   ⚠ Aliado '$key' no encontrado, se omite.");
                continue;
            }

            // Contratos del aliado con RS independiente y sin arl_id (o con n_arl vacío)
            $contratos = DB::table('contratos as c')
                ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
                ->where('c.aliado_id', $aliadoId)
                ->where('rs.es_independiente', true)
                ->whereNotNull('c.id_legacy')
                ->where(function ($q) {
                    $q->whereNull('c.arl_id')
                      ->orWhereNull('c.n_arl');
                })
                ->get(['c.id', 'c.id_legacy', 'c.arl_id', 'c.n_arl']);

            if ($contratos->isEmpty()) {
                $this->line("   ✅ $db: sin contratos pendientes.");
                continue;
            }

            $this->line("   $db: {$contratos->count()} contratos independientes sin ARL completo.");

            // Cargar datos ARL desde el legacy para todos esos id_legacy de una sola vez
            $legacyIds  = $contratos->pluck('id_legacy')->filter()->values()->toArray();
            $idList     = implode(',', $legacyIds);

            try {
                $legacyRows = DB::connection('sqlsrv_legacy')
                    ->select("SELECT Id, ARL, N_ARL FROM [$db].dbo.Contratos WHERE Id IN ($idList)");
            } catch (\Exception $e) {
                $this->error("   ❌ Error consultando legacy $db: " . $e->getMessage());
                continue;
            }

            // Indexar por Id legacy
            $legacyMap = [];
            foreach ($legacyRows as $lr) {
                $legacyMap[$lr->Id] = $lr;
            }

            foreach ($contratos as $c) {
                $lr = $legacyMap[$c->id_legacy] ?? null;
                if (!$lr) {
                    $totalSinLegacy++;
                    continue;
                }

                // Resolver arl_id por NIT
                $arlNit = $lr->ARL ?? null;
                $newArlId = (is_numeric($arlNit) && (float)$arlNit > 1)
                    ? DB::table('arls')->where('nit', (int)(float)$arlNit)->value('id')
                    : null;

                // Resolver n_arl
                $newNarl = (is_numeric($lr->N_ARL) && $lr->N_ARL >= 1 && $lr->N_ARL <= 5)
                    ? (int)$lr->N_ARL
                    : null;

                // Si no hay ARL en el legacy tampoco, registrar y continuar
                if ($newArlId === null && $newNarl === null) {
                    $totalSinArl++;
                    continue;
                }

                // Si ya tiene los mismos valores, no tocar
                if ($c->arl_id == $newArlId && $c->n_arl == $newNarl) {
                    $totalYaTenia++;
                    continue;
                }

                if ($ejecutar) {
                    DB::table('contratos')->where('id', $c->id)->update(array_filter([
                        'arl_id' => $newArlId,
                        'n_arl'  => $newNarl ?? 1,
                    ], fn($v) => $v !== null));
                }
                $totalActualizados++;
            }

            $this->line("   $db: procesados {$contratos->count()}.");
        }

        $this->newLine();
        $this->info("   Sin datos legacy   : {$totalSinLegacy}");
        $this->info("   Sin ARL en legacy  : {$totalSinArl}");
        $this->info("   Ya tenían ARL OK   : {$totalYaTenia}");
        if ($ejecutar) {
            $this->info("   ✅ Actualizados    : {$totalActualizados}");
        } else {
            $this->line("   [DRY-RUN] Actualizarían : {$totalActualizados}");
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
