<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPlanillasGastos extends Command
{
    protected $signature   = 'gastos:fix-planillas {--dry-run : Solo muestra qué se actualizaría sin hacer cambios} {--aliado= : ID del aliado (omitir = todos)}';
    protected $description = 'Extrae el numero_planilla de la descripción de gastos pago_planilla y lo guarda en el campo dedicado';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Aliados a procesar
        $aliadoOpt = $this->option('aliado');
        $aliados   = $aliadoOpt
            ? collect([(object)['id' => (int)$aliadoOpt]])
            : DB::table('aliados')->orderBy('id')->get(['id']);

        if ($aliados->isEmpty()) {
            $this->error('No se encontraron aliados.');
            return 1;
        }

        $this->info($dryRun ? '🔍 Modo DRY-RUN (sin cambios)' : '⚙️  Ejecutando actualización...');
        $this->newLine();

        foreach ($aliados as $aliado) {
            $aid = $aliado->id;
            $this->line("<fg=cyan>── Aliado ID: {$aid} ──</>");
            $this->procesarAliado($aid, $dryRun);
            $this->newLine();
        }

        return 0;
    }

    private function procesarAliado(int $aid, bool $dryRun): void
    {
        $format1 = $this->fixFormato1($aid, $dryRun);
        $format2 = $this->fixFormato2($aid, $dryRun);

        $sinResolver = DB::table('gastos')
            ->where('aliado_id', $aid)
            ->where('tipo', 'pago_planilla')
            ->whereNull('numero_planilla')
            ->select('id', 'descripcion', 'fecha', 'valor')
            ->get();

        $this->table(['Formato', 'Registros actualizados'], [
            ['Nuevo  (... | Planilla: {num})',       $format1],
            ['Legacy (Pago Planillas: {num} / ...)', $format2],
        ]);

        if ($sinResolver->isNotEmpty()) {
            $this->warn("⚠️  {$sinResolver->count()} registro(s) sin resolver:");
            $rows = $sinResolver->map(fn($g) => [
                $g->id,
                substr($g->descripcion ?? '', 0, 70),
                $g->fecha,
                number_format($g->valor),
            ])->toArray();
            $this->table(['ID', 'Descripción', 'Fecha', 'Valor'], $rows);
        } else {
            $this->info('✅ Todos los registros tienen numero_planilla asignado.');
        }
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
