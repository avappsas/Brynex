<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPlanosPlanilla extends Command
{
    protected $signature   = 'planos:fix-planilla
                                {--aliado=  : ID del aliado (omitir = todos)}
                                {--dry-run  : Solo muestra qué se actualizaría}';
    protected $description = 'Rellena numero_planilla en planos cruzando con la BD legacy (PLANOS.Planilla)';

    /** Mismo mapa que MigrateLegacy */
    private array $dbs = [
        'Brygar_BD'       => 'brygar',
        'GiMave_Integral' => 'gimave',
        'Grupo_Fecop'     => 'fecop',
        'LuisLopez'       => 'luislopez',
        'Mave_Anderson'   => 'mave',
        'SS_Faga'         => 'faga',
    ];

    /** slug → aliado_id */
    private array $ids = [];

    public function handle(): int
    {
        // Cargar aliados igual que MigrateLegacy::loadAliados
        $aliados = DB::table('aliados')->get(['id', 'nombre', 'nit']);
        foreach ($aliados as $a) {
            if ($a->nit   === '901918923')        $this->ids['brygar']    = $a->id;
            if ($a->nombre === 'GiMave Integral') $this->ids['gimave']    = $a->id;
            if ($a->nombre === 'Grupo Fecop')     $this->ids['fecop']     = $a->id;
            if ($a->nombre === 'Luis Lopez')      $this->ids['luislopez'] = $a->id;
            if ($a->nombre === 'Mave Anderson')   $this->ids['mave']      = $a->id;
            if ($a->nombre === 'SS Faga')         $this->ids['faga']      = $a->id;
        }

        $dryRun    = $this->option('dry-run');
        $aliadoOpt = $this->option('aliado') ? (int)$this->option('aliado') : null;

        $this->info($dryRun ? '🔍 Modo DRY-RUN' : '⚙️  Ejecutando fix numero_planilla en planos...');

        $totalFix  = 0;
        $totalSkip = 0;

        foreach ($this->dbs as $db => $key) {
            $aliadoId = $this->ids[$key] ?? null;
            if (!$aliadoId) {
                $this->warn("  ⚠ No se encontró aliado para key '{$key}', se omite.");
                continue;
            }
            if ($aliadoOpt && $aliadoOpt !== $aliadoId) continue;

            $this->line("\n<fg=cyan>── DB: {$db} | Aliado: {$aliadoId} ──</>");

            // Traer TODOS los planos de BryNex sin numero_planilla para este aliado
            $sinPlanilla = DB::table('planos')
                ->where('aliado_id', $aliadoId)
                ->whereNull('numero_planilla')
                ->select('id', 'no_identifi', 'mes_plano', 'anio_plano', 'n_plano', 'razon_social_id', 'id_legacy')
                ->get();

            $this->line("  Planos sin numero_planilla: {$sinPlanilla->count()}");
            if ($sinPlanilla->isEmpty()) {
                $this->info("  ✅ Nada que corregir.");
                continue;
            }

            // Cargar PLANOS del legacy en chunks para cruzar en memoria
            // Índice: "no_identifi|mes|anio|n_plano|nit" => numero_planilla
            $legacyMap = [];
            $legacyById = []; // id_legacy => numero_planilla

            $offset = 0; $chunk = 2000;
            $this->line("  Cargando legacy PLANOS...");
            while (true) {
                try {
                    $rows = DB::connection('sqlsrv_legacy')
                        ->select("SELECT Id, Planilla, NO_IDENTIFI, MES_PLANO, AÑO_PLANO, N_PLANO, Nit_Empresa
                                  FROM [{$db}].dbo.PLANOS
                                  ORDER BY Id OFFSET {$offset} ROWS FETCH NEXT {$chunk} ROWS ONLY");
                } catch (\Exception $e) {
                    $this->error("  Error legacy: " . $e->getMessage());
                    break;
                }
                if (empty($rows)) break;

                foreach ($rows as $row) {
                    $planilla = $row->Planilla ?? null;
                    if (!$planilla || !is_numeric($planilla)) { $offset++; continue; }
                    $numPlan = (string)(int)((float)$planilla);

                    // Por ID legacy (más exacto)
                    if ($row->Id) $legacyById[$row->Id] = $numPlan;

                    // Por clave compuesta (fallback)
                    $clave = "{$row->NO_IDENTIFI}|{$row->MES_PLANO}|{$row->AÑO_PLANO}|{$row->N_PLANO}|{$row->Nit_Empresa}";
                    $legacyMap[$clave] = $numPlan;
                }
                $offset += $chunk;
                if (count($rows) < $chunk) break;
            }
            $this->line("  Registros legacy cargados: " . count($legacyMap));

            // Cruzar y actualizar
            $fixedRows = []; $noMatch = [];
            foreach ($sinPlanilla as $p) {
                $numPlan = null;

                // 1. Por id_legacy (exacto)
                if ($p->id_legacy && isset($legacyById[$p->id_legacy])) {
                    $numPlan = $legacyById[$p->id_legacy];
                }

                // 2. Por clave compuesta
                if (!$numPlan) {
                    $clave = "{$p->no_identifi}|{$p->mes_plano}|{$p->anio_plano}|{$p->n_plano}|{$p->razon_social_id}";
                    $numPlan = $legacyMap[$clave] ?? null;
                }

                if ($numPlan) {
                    $fixedRows[] = ['id' => $p->id, 'planilla' => $numPlan];
                } else {
                    $noMatch[] = $p;
                }
            }

            $this->line("  ✔ Con match: " . count($fixedRows) . " | Sin match: " . count($noMatch));

            if (!$dryRun && !empty($fixedRows)) {
                foreach ($fixedRows as $fix) {
                    DB::table('planos')->where('id', $fix['id'])->update(['numero_planilla' => $fix['planilla']]);
                }
                $this->info("  ✅ Actualizados: " . count($fixedRows));
            }

            if (!empty($noMatch)) {
                $this->warn("  ⚠️  Sin match en legacy ({$db}):");
                $rows = collect($noMatch)->map(fn($p) => [
                    $p->id, $p->no_identifi, $p->mes_plano, $p->anio_plano, $p->n_plano, $p->razon_social_id
                ])->toArray();
                $this->table(['ID', 'Cedula', 'Mes', 'Año', 'N_Plano', 'RS_ID'], $rows);
            }

            $totalFix  += count($fixedRows);
            $totalSkip += count($noMatch);
        }

        $this->newLine();
        $this->info("Total actualizados: {$totalFix} | Sin match: {$totalSkip}");
        return 0;
    }
}
