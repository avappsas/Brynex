<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPlanillasGastos extends Command
{
    protected $signature   = 'gastos:fix-planillas {--dry-run : Solo muestra qué se actualizaría sin hacer cambios}';
    protected $description = 'Extrae el numero_planilla de la descripción de gastos pago_planilla y lo guarda en el campo dedicado';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $aid    = DB::table('aliados')->orderBy('id')->value('id');

        if (!$aid) {
            $this->error('No se encontró ningún aliado.');
            return 1;
        }

        $this->info("Aliado ID: {$aid}");
        $this->info($dryRun ? '🔍 Modo DRY-RUN (sin cambios)' : '⚙️  Ejecutando actualización...');
        $this->newLine();

        // ── Paso 1: Formato NUEVO  "... | Planilla: {numero}" ─────────────
        // Ya cubierto por la migración inicial, pero ejecutamos por si quedaron
        $format1 = $this->fixFormato1($aid, $dryRun);

        // ── Paso 2: Formato LEGACY "Pago Planillas: {numero} / {empresa}" ─
        $format2 = $this->fixFormato2($aid, $dryRun);

        // ── Paso 3: Preview de los que quedaron sin resolver ───────────────
        $sinResolver = DB::table('gastos')
            ->where('aliado_id', $aid)
            ->where('tipo', 'pago_planilla')
            ->whereNull('numero_planilla')
            ->select('id', 'descripcion', 'fecha', 'valor')
            ->get();

        $this->newLine();
        $this->table(['Formato', 'Registros actualizados'], [
            ['Nuevo  (... | Planilla: {num})',         $format1],
            ['Legacy (Pago Planillas: {num} / ...)',   $format2],
        ]);

        if ($sinResolver->isNotEmpty()) {
            $this->newLine();
            $this->warn("⚠️  {$sinResolver->count()} registro(s) NO resueltos (descripción no reconocida):");
            $rows = $sinResolver->map(fn($g) => [
                $g->id,
                substr($g->descripcion, 0, 80),
                $g->fecha,
                number_format($g->valor),
            ])->toArray();
            $this->table(['ID', 'Descripción', 'Fecha', 'Valor'], $rows);
        } else {
            $this->info('✅ Todos los registros tienen numero_planilla asignado.');
        }

        return 0;
    }

    // ── Formato nuevo: "... | Planilla: {numero}" ─────────────────────────
    private function fixFormato1(int $aid, bool $dryRun): int
    {
        $registros = DB::table('gastos')
            ->where('aliado_id', $aid)
            ->where('tipo', 'pago_planilla')
            ->whereNull('numero_planilla')
            ->where('descripcion', 'LIKE', '%Planilla: %')
            ->get(['id', 'descripcion']);

        $actualizados = 0;

        foreach ($registros as $g) {
            if (preg_match('/Planilla:\s*(\S+)\s*$/', $g->descripcion, $m)) {
                $num = trim($m[1]);
                if (!$dryRun) {
                    DB::table('gastos')->where('id', $g->id)->update(['numero_planilla' => $num]);
                } else {
                    $this->line("  [F1] ID {$g->id} → planilla: <info>{$num}</info>");
                }
                $actualizados++;
            }
        }

        return $actualizados;
    }

    // ── Formato legacy: "Pago Planillas: {numero} / {empresa...}" ─────────
    private function fixFormato2(int $aid, bool $dryRun): int
    {
        $registros = DB::table('gastos')
            ->where('aliado_id', $aid)
            ->where('tipo', 'pago_planilla')
            ->whereNull('numero_planilla')
            ->where('descripcion', 'LIKE', 'Pago Planillas%')
            ->get(['id', 'descripcion']);

        $actualizados = 0;

        foreach ($registros as $g) {
            // Patron: "Pago Planillas: {num} / ..." o "Pago Planillas: {num}\n..."
            if (preg_match('/Pago Planillas[:\s]+(\d+)/i', $g->descripcion, $m)) {
                $num = trim($m[1]);
                if (!$dryRun) {
                    DB::table('gastos')->where('id', $g->id)->update(['numero_planilla' => $num]);
                } else {
                    $this->line("  [F2] ID {$g->id} → planilla: <info>{$num}</info>");
                }
                $actualizados++;
            }
        }

        return $actualizados;
    }
}
