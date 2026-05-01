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

            // ── A. Intentar con legacy (solo para planos migrados con id_legacy) ──────
            $legacyMap  = [];  // clave compuesta => planilla
            $legacyById = [];  // id_legacy => planilla

            $offset = 0; $chunk = 2000;
            $this->line("  Cargando legacy PLANOS...");
            while (true) {
                try {
                    // SELECT * para tolerar diferentes nombres de columnas entre aliados
                    $rows = DB::connection('sqlsrv_legacy')
                        ->select("SELECT * FROM [{$db}].dbo.PLANOS ORDER BY Id OFFSET {$offset} ROWS FETCH NEXT {$chunk} ROWS ONLY");
                } catch (\Exception $e) {
                    $this->error("  Error legacy: " . $e->getMessage());
                    break;
                }
                if (empty($rows)) break;

                foreach ($rows as $row) {
                    $row = (array)$row;
                    // Tolerancia a nombres de columna (uppercase/lowercase/alias)
                    $planillaRaw = $row['Planilla'] ?? $row['PLANILLA'] ?? $row['planilla'] ?? null;
                    $id          = $row['Id']       ?? $row['ID']       ?? $row['id']       ?? null;
                    $cedula      = $row['NO_IDENTIFI'] ?? $row['No_Identifi'] ?? $row['CEDULA'] ?? null;
                    $mes         = $row['MES_PLANO']   ?? $row['Mes']        ?? null;
                    $anio        = $row['AÑO_PLANO']   ?? $row['Año']        ?? $row['Anio']   ?? null;
                    $nPlano      = $row['N_PLANO']      ?? $row['N_Plano']   ?? null;
                    // En Brygar_BD el NIT está en la col RAZON_SOCIAL (confusamente nombrada)
                    $nit         = $row['Nit_Empresa'] ?? $row['NIT'] ?? $row['Nit']
                                ?? $row['RAZON_SOCIAL'] ?? $row['Razon_Social'] ?? null;

                    if (!$planillaRaw || !is_numeric($planillaRaw)) continue;
                    $numPlan = (string)(int)((float)$planillaRaw);

                    if ($id) $legacyById[$id] = $numPlan;

                    // Normalizar NIT (puede venir como float '901716074.0')
                    $nitClean = is_numeric($nit) ? (string)(int)((float)$nit) : $nit;
                    $clave = "{$cedula}|{$mes}|{$anio}|{$nPlano}|{$nitClean}";
                    $legacyMap[$clave] = $numPlan;
                }
                $offset += $chunk;
                if (count($rows) < $chunk) break;
            }
            $this->line("  Registros legacy cargados: " . count($legacyMap));

            // ── B. Para planos BryNex-native (sin id_legacy): buscar en gastos ──────
            // gastos pago_planilla agrupado por aliado+razon_social_id+mes+anio → numero_planilla
            $gastosMap = DB::table('gastos')
                ->where('aliado_id', $aliadoId)
                ->where('tipo', 'pago_planilla')
                ->whereNotNull('numero_planilla')
                ->select('razon_social_id', 'numero_planilla',
                    DB::raw('MONTH(fecha) AS mes_g'),
                    DB::raw('YEAR(fecha) AS anio_g'))
                ->get()
                ->keyBy(fn($g) => "{$g->razon_social_id}|{$g->mes_g}|{$g->anio_g}");

            $this->line("  Gastos planilla cargados: " . $gastosMap->count());

            // Cruzar y actualizar
            $fixedRows = []; $noMatch = [];
            foreach ($sinPlanilla as $p) {
                $numPlan = null;

                // 1. Por id_legacy (exacto)
                if ($p->id_legacy && isset($legacyById[$p->id_legacy])) {
                    $numPlan = $legacyById[$p->id_legacy];
                }

                // 2. Por clave compuesta (razon_social_id = NIT en BryNex)
                if (!$numPlan) {
                    $clave = "{$p->no_identifi}|{$p->mes_plano}|{$p->anio_plano}|{$p->n_plano}|{$p->razon_social_id}";
                    $numPlan = $legacyMap[$clave] ?? null;
                }

                // 3. Fallback gastos: planos BryNex-native sin legacy
                if (!$numPlan && $p->razon_social_id) {
                    $gKey = "{$p->razon_social_id}|{$p->mes_plano}|{$p->anio_plano}";
                    $numPlan = $gastosMap[$gKey]->numero_planilla ?? null;
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
