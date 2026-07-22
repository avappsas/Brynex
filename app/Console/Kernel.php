<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ── Reset mensual de n_plano ──────────────────────────────────
        // El día 1 de cada mes a las 00:01 (hora Colombia) resetea n_plano=1
        // y avanza mes_pagos/anio_pagos en todas las razones sociales.
        // Ejecución manual: php artisan planos:reset-mensual
        $schedule->command('planos:reset-mensual')
            ->monthlyOn(1, '00:01')
            ->timezone('America/Bogota')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/reset-n-plano.log'));

        // ── Liquidación automática de intereses de préstamos (Finanzas) ──
        // Diario a la 1:00 AM Colombia: liquida los meses calendario completos
        // vencidos de cada préstamo activo/mora con tasa > 0.
        // Ejecución manual: php artisan finanzas:liquidar-intereses
        $schedule->command('finanzas:liquidar-intereses')
            ->dailyAt('01:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/finanzas-liquidacion.log'));

        // ── Seguimiento comercial del Asistente IA (WhatsApp) ────────────
        // Cada 15 min, en horario comercial: revisa conversaciones que la IA
        // dejó sin respuesta del cliente hace 2h+ y envía un único mensaje
        // de seguimiento (no repite hasta que el cliente vuelva a escribir).
        // Ejecución manual: php artisan whatsapp:seguimiento-ia
        $schedule->command('whatsapp:seguimiento-ia')
            ->everyFifteenMinutes()
            ->between('07:00', '21:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/whatsapp-seguimiento-ia.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
