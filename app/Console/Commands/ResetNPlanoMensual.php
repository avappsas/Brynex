<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetNPlanoMensual extends Command
{
    /**
     * Nombre del comando artisan.
     *
     * Uso manual:  php artisan planos:reset-mensual
     * Automático:  corre el día 1 de cada mes a las 00:01 (America/Bogota)
     *              via el Kernel del scheduler de Laravel.
     */
    protected $signature = 'planos:reset-mensual
                            {--dry-run : Muestra cuántas RS serían afectadas sin hacer cambios}';

    protected $description = 'Resetea n_plano=1 y avanza mes_pagos/anio_pagos en todas las razones sociales al inicio del mes.';

    public function handle(): int
    {
        $mesActual  = (int) now()->month;
        $anioActual = (int) now()->year;
        $dryRun     = $this->option('dry-run');

        // ── Contar cuántas RS se van a actualizar ──────────────────────
        $total = DB::table('razones_sociales')->count();

        if ($dryRun) {
            $this->info("DRY-RUN: Se actualizarían {$total} razones sociales.");
            $this->info("  → n_plano     = 1");
            $this->info("  → mes_pagos   = {$mesActual}");
            $this->info("  → anio_pagos  = {$anioActual}");
            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->warn('No se encontraron razones sociales. No se realizó ningún cambio.');
            Log::warning('planos:reset-mensual — sin razones sociales en BD.');
            return self::SUCCESS;
        }

        // ── Actualizar todas las RS ────────────────────────────────────
        $actualizadas = DB::table('razones_sociales')
            ->update([
                'n_plano'    => 1,
                'mes_pagos'  => $mesActual,
                'anio_pagos' => $anioActual,
            ]);

        // ── Log y output ───────────────────────────────────────────────
        $mensaje = "Reset mensual n_plano: {$actualizadas} razones sociales → n_plano=1, mes_pagos={$mesActual}, anio_pagos={$anioActual}";

        Log::info($mensaje);
        $this->info("✓ {$mensaje}");

        return self::SUCCESS;
    }
}
