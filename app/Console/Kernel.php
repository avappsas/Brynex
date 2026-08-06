<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * TODAS las tareas llevan ->user('www-data'). No es opcional ni cosmético:
     * el cron del servidor es de root, y `withoutOverlapping()` guarda su
     * mutex en la CACHÉ (`framework/schedule-<hash>`). Con CACHE_DRIVER=file
     * eso significa que cada corrida escribe en storage/framework/cache — así
     * que cualquier tarea de root, haga lo que haga, va dejando directorios
     * con dueño root ahí dentro. Cuando a Apache (www-data) le toca una llave
     * de caché que cae en uno de esos directorios, no puede crear el archivo
     * y la petición revienta con un 500.
     *
     * Eso fue lo que rompió la consulta de cédula del modal de clientes el
     * 2026-08-04 (ver commit 49c0454): 7 de 118 shards habían quedado de root.
     *
     * Al agregar una tarea nueva, ponerle ->user('www-data') también.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ── Retención de accesos y bitácora ───────────────────────────
        // El día 2 de cada mes a las 02:30: borra accesos con más de 2 años y
        // bitácora con más de 5, que son los plazos que el aviso de tratamiento
        // de datos le promete al usuario. Si se cambian los plazos aquí, hay
        // que cambiarlos también en docs/clausulas-y-aviso-datos.md.
        // Ejecución manual: php artisan retencion:limpiar (sin --ejecutar simula)
        $schedule->command('retencion:limpiar --ejecutar')
            ->monthlyOn(2, '02:30')
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/retencion.log'));

        // ── Retención de las entregas de datos a aliados ──────────────
        // Cada día a las 03:00 borra los ZIP que pasaron de la ventana de
        // config/exportacion (7 días). Son datos personales de miles de
        // personas: prometer que se borran y dejarlos ahí es peor que no
        // prometer nada. La pantalla también purga al entrar, pero eso depende
        // de que alguien entre.
        $schedule->call(fn () => app(\App\Services\Exportacion\ExportAliadoService::class)->purgarVencidas())
            ->dailyAt('03:00')
            ->timezone('America/Bogota')
            ->name('exportaciones-purgar')
            ->withoutOverlapping();

        // ── Reset mensual de n_plano ──────────────────────────────────
        // El día 1 de cada mes a las 00:01 (hora Colombia) resetea n_plano=1
        // y avanza mes_pagos/anio_pagos en todas las razones sociales.
        // Ejecución manual: php artisan planos:reset-mensual
        $schedule->command('planos:reset-mensual')
            ->monthlyOn(1, '00:01')
            ->timezone('America/Bogota')
            ->user('www-data')
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
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/finanzas-liquidacion.log'));

        // ── Seguimiento comercial del Asistente IA (WhatsApp) ────────────
        // Cada 15 min, en horario comercial: revisa conversaciones que la IA
        // dejó sin respuesta del cliente hace 3h+ y, SOLO si quedó una afiliación
        // pendiente (lo evalúa SeguimientoEvaluador), envía un único mensaje
        // de seguimiento (no repite hasta que el cliente vuelva a escribir).
        // Ejecución manual: php artisan whatsapp:seguimiento-ia
        $schedule->command('whatsapp:seguimiento-ia')
            ->everyFifteenMinutes()
            ->between('07:00', '21:00')
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/whatsapp-seguimiento-ia.log'));

        // ── Despacho de publicidad programada (Fase 4 página web pública) ────
        // Cada 5 min: publica las piezas aprobadas cuya fecha programada ya llegó.
        // Ejecución manual: php artisan publicaciones:despachar
        $schedule->command('publicaciones:despachar')
            ->everyFiveMinutes()
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/publicaciones-despacho.log'));

        // ── Procesamiento de video IA (Veo + overlay FFmpeg) ─────────────────
        // Cada minuto: consulta el estado de los videos en generación en Veo (1-3 min
        // típico), y cuando terminan les monta el overlay de texto+logo y los marca listos.
        // Ejecución manual: php artisan videos:procesar
        $schedule->command('videos:procesar')
            ->everyMinute()
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/videos-procesar.log'));

        // ── Métricas de redes de las piezas publicadas ───────────────────────
        // Diario a las 21:30: lee likes/comentarios/compartidos/alcance de cada
        // pieza de los últimos 30 días — alimenta el aprendizaje del piloto.
        // Ejecución manual: php artisan marketing:metricas
        $schedule->command('marketing:metricas')
            ->dailyAt('21:30')
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-metricas.log'));

        // ── Sincronización de pauta pagada (gasto real + tope de seguridad) ──
        // Cada hora: lee el gasto real de Meta y pausa automáticamente cualquier
        // pauta que se pase de su presupuesto o del tope mensual del aliado.
        // Solo APAGA gasto, nunca lo prende ni lo sube — eso es siempre manual.
        // Ejecución manual: php artisan marketing:pauta-sync
        $schedule->command('marketing:pauta-sync')
            ->hourly()
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-pauta-sync.log'));

        // ── Piloto automático de marketing (community manager IA) ────────────
        // Cada 30 min en horario diurno: genera la pieza publicitaria del día de
        // cada aliado con piloto activo (una por día, desde la hora configurada).
        // Ejecución manual: php artisan marketing:autopilot [--aliado=slug --force]
        $schedule->command('marketing:autopilot')
            ->everyThirtyMinutes()
            ->between('05:00', '21:00')
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-autopilot.log'));

        // ── Completar EPS/pensión/nombres desde el registro oficial ──────────
        // Una tanda por hora hasta terminar los ~31.000 clientes. Se reparte en
        // tandas a propósito: 31.000 consultas seguidas contra el operador se
        // verían como abuso y pueden costar el bloqueo de la cuenta. Con 1.000
        // por hora y 250 ms entre consultas son ~4 minutos de trabajo por hora,
        // y el barrido completo toma poco más de un día.
        //
        // El orden lo decide el comando: primero los clientes VIGENTES a los
        // que les falta información, de últimos los retirados que ya están
        // completos. Cuando no queden pendientes la corrida no hace nada, así
        // que la tarea puede quedarse programada sin efecto.
        //
        // Solo rellena huecos: nunca sobrescribe un dato que el cliente ya
        // tiene. Las diferencias van a `clientes:informe-ruaf`.
        // Ejecución manual: php artisan clientes:completar-ruaf --limite=100
        $schedule->command('clientes:completar-ruaf --limite=1000 --pausa=250 --aplicar')
            ->hourly()
            ->timezone('America/Bogota')
            ->user('www-data')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/completar-ruaf.log'));
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
