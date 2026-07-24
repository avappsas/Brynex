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

        // ── Despacho de publicidad programada (Fase 4 página web pública) ────
        // Cada 5 min: publica las piezas aprobadas cuya fecha programada ya llegó.
        // Ejecución manual: php artisan publicaciones:despachar
        $schedule->command('publicaciones:despachar')
            ->everyFiveMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/publicaciones-despacho.log'));

        // ── Métricas de redes de las piezas publicadas ───────────────────────
        // Diario a las 21:30: lee likes/comentarios/compartidos/alcance de cada
        // pieza de los últimos 30 días — alimenta el aprendizaje del piloto.
        // Ejecución manual: php artisan marketing:metricas
        $schedule->command('marketing:metricas')
            ->dailyAt('21:30')
            ->timezone('America/Bogota')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-metricas.log'));

        // ── Piloto automático de marketing (community manager IA) ────────────
        // Cada 30 min en horario diurno: genera la pieza publicitaria del día de
        // cada aliado con piloto activo (una por día, desde la hora configurada).
        // Ejecución manual: php artisan marketing:autopilot [--aliado=slug --force]
        $schedule->command('marketing:autopilot')
            ->everyThirtyMinutes()
            ->between('05:00', '21:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-autopilot.log'));
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
