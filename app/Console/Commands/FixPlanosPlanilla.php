<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPlanosPlanilla extends Command
{
    protected $signature   = 'planos:fix-planilla
                                {--dry-run : Solo muestra qué se actualizaría}';
    protected $description = 'Rellena numero_planilla en todos los planos cruzando con las BDs legacy';

    private array $dbs = [
        'Brygar_BD'       => 'brygar',
        'GiMave_Integral' => 'gimave',
        'Grupo_Fecop'     => 'fecop',
        'LuisLopez'       => 'luislopez',
        'Mave_Anderson'   => 'mave',
        'SS_Faga'         => 'faga',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info($dryRun ? '🔍 Modo DRY-RUN' : '⚙️  Ejecutando fix numero_planilla...');

        // ── 1. Cargar TODOS los planos de BryNex sin numero_planilla ─────────────
        $sinPlanilla = DB::table('planos')
            ->where(function($q) {
                $q->whereNull('numero_planilla')
                  ->orWhere('numero_planilla', '');
            })
            ->select('id', 'no_identifi', 'mes_plano', 'anio_plano', 'n_plano',
                     'razon_social_id', 'aliado_id', 'id_legacy')
            ->get();

        $this->line("Planos sin numero_planilla en BryNex: {$sinPlanilla->count()}");
        if ($sinPlanilla->isEmpty()) {
            $this->info('✅ Nada que corregir.');
            return 0;
        }

        // Indexar por id_legacy para match rápido
        $porIdLegacy = $sinPlanilla->whereNotNull('id_legacy')->keyBy('id_legacy');

        // Indexar por clave compuesta para fallback
        // clave: "cedula|mes|anio|n_plano|razon_social_id"  (razon_social_id = NIT en BryNex)
        $porClave = [];
        foreach ($sinPlanilla as $p) {
            $clave = "{$p->no_identifi}|{$p->mes_plano}|{$p->anio_plano}|{$p->n_plano}|{$p->razon_social_id}";
            $porClave[$clave][] = $p->id;
        }

        $fixes   = [];   // plano_id => numero_planilla
        $noMatch = [];   // planos sin match

        // ── 2. Por cada BD legacy, cargar PLANOS y cruzar ────────────────────────
        foreach ($this->dbs as $db => $key) {
            $this->line("\n<fg=cyan>── Legacy: {$db} ──</>");

            $legCount = 0; $offset = 0; $chunk = 5000;
            $this->line("  Cargando...");
            $legRows = 0;
            while (true) {
                try {
                    $rows = DB::connection('sqlsrv_legacy')
                        ->select("SELECT * FROM [{$db}].dbo.PLANOS
                                  ORDER BY Id
                                  OFFSET {$offset} ROWS FETCH NEXT {$chunk} ROWS ONLY");
                } catch (\Exception $e) {
                    $this->error("  Error: " . $e->getMessage());
                    break;
                }
                if (empty($rows)) break;

                foreach ($rows as $row) {
                    $row  = (array)$row;
                    $planillaRaw = $row['Planilla'] ?? $row['PLANILLA'] ?? $row['planilla'] ?? null;
                    if (!$planillaRaw || !is_numeric($planillaRaw)) continue;

                    $numPlan = (string)(int)((float)$planillaRaw);
                    $idLeg   = $row['Id']   ?? $row['ID']  ?? null;
                    $cedula  = $row['NO_IDENTIFI']  ?? $row['No_Identifi'] ?? null;
                    $mes     = $row['MES_PLANO']    ?? $row['Mes']         ?? null;
                    $anio    = $row['AÑO_PLANO']    ?? $row['Año']         ?? $row['Anio'] ?? null;
                    $nPlano  = $row['N_PLANO']      ?? $row['N_Plano']     ?? null;
                    // NIT de la empresa: Brygar usa RAZON_SOCIAL, otros usan Nit_Empresa o NIT
                    $nit     = $row['Nit_Empresa']  ?? $row['NIT']         ?? $row['Nit']
                             ?? $row['RAZON_SOCIAL'] ?? $row['Razon_Social'] ?? null;
                    $nitClean = is_numeric($nit) ? (string)(int)((float)$nit) : $nit;

                    // Match por id_legacy (más exacto)
                    if ($idLeg && isset($porIdLegacy[$idLeg])) {
                        $p = $porIdLegacy[$idLeg];
                        if (!isset($fixes[$p->id])) {
                            $fixes[$p->id] = $numPlan;
                            $legCount++;
                        }
                        continue;
                    }

                    // Match por clave compuesta
                    $clave = "{$cedula}|{$mes}|{$anio}|{$nPlano}|{$nitClean}";
                    if (isset($porClave[$clave])) {
                        foreach ($porClave[$clave] as $pid) {
                            if (!isset($fixes[$pid])) {
                                $fixes[$pid] = $numPlan;
                                $legCount++;
                            }
                        }
                    }
                }
                $legRows += count($rows);
                $offset  += $chunk;
                if ($legRows % 20000 === 0) {
                    $this->line("  ... {$legRows} filas legacy procesadas");
                }
                if (count($rows) < $chunk) break;
            }
            $this->line("  Matches encontrados en {$db}: {$legCount}");
        }

        // ── 3. Fallback gastos para planos BryNex-native (sin id_legacy) ─────────
        $sinMatchIds = $sinPlanilla->pluck('id')->diff(array_keys($fixes))->values();
        if ($sinMatchIds->isNotEmpty()) {
            $this->line("\n<fg=yellow>Buscando en gastos para {$sinMatchIds->count()} planos sin match legacy...</>");

            $gastosMap = DB::table('gastos')
                ->where('tipo', 'pago_planilla')
                ->whereNotNull('numero_planilla')
                ->whereNotNull('razon_social_id')
                ->select('aliado_id', 'razon_social_id', 'numero_planilla',
                    DB::raw('MONTH(fecha) AS mes_g'), DB::raw('YEAR(fecha) AS anio_g'))
                ->get()
                ->keyBy(fn($g) => "{$g->aliado_id}|{$g->razon_social_id}|{$g->mes_g}|{$g->anio_g}");

            $gastosCount = 0;
            foreach ($sinMatchIds as $pid) {
                $p    = $sinPlanilla->firstWhere('id', $pid);
                $gKey = "{$p->aliado_id}|{$p->razon_social_id}|{$p->mes_plano}|{$p->anio_plano}";
                if (isset($gastosMap[$gKey])) {
                    $fixes[$pid] = $gastosMap[$gKey]->numero_planilla;
                    $gastosCount++;
                } else {
                    $noMatch[] = $p;
                }
            }
            $this->line("  Matches por gastos: {$gastosCount}");
        }

        // ── 4. Aplicar ────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info("Total con match: " . count($fixes) . " | Sin match: " . count($noMatch));

        if (!$dryRun && !empty($fixes)) {
            $chunks = array_chunk($fixes, 500, true);
            $bar = $this->output->createProgressBar(count($fixes));
            $bar->start();
            foreach ($chunks as $chunk) {
                // Una sola query por lote usando CASE WHEN
                $ids   = array_keys($chunk);
                $cases = '';
                foreach ($chunk as $pid => $planilla) {
                    $safe  = addslashes($planilla);
                    $cases .= " WHEN {$pid} THEN '{$safe}'";
                }
                $idList = implode(',', $ids);
                DB::statement("UPDATE planos SET numero_planilla = CASE id {$cases} END WHERE id IN ({$idList})");
                $bar->advance(count($chunk));
            }
            $bar->finish();
            $this->newLine();
            $this->info('✅ Actualizados: ' . count($fixes));
        }

        if (!empty($noMatch)) {
            $this->warn('⚠️  Sin match (' . count($noMatch) . '):');
            $rows = collect($noMatch)->map(fn($p) => [
                $p->id, $p->aliado_id, $p->no_identifi,
                $p->mes_plano, $p->anio_plano, $p->n_plano, $p->razon_social_id
            ])->toArray();
            $this->table(['ID','Aliado','Cedula','Mes','Año','N_Plano','RS_ID'], $rows);
        }

        return 0;
    }
}
