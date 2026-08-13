<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * NINGUNA tarea lleva ->user('www-data'), y es a propósito: el cron que
     * dispara `schedule:run` es de **www-data** (`crontab -u www-data`), así
     * que todo corre ya con el usuario correcto. Poner ->user() aquí sería
     * peor que redundante — Laravel lo implementa envolviendo el comando en
     * `sudo -u www-data`, y www-data NO está en sudoers: el subproceso muere
     * con "www-data is not in the sudoers file" mientras `schedule:run`
     * reporta DONE. Tareas caídas en silencio.
     *
     * Por qué el cron es de www-data y no de root: `withoutOverlapping()`
     * guarda su mutex en la CACHÉ (`framework/schedule-<hash>`), y con
     * CACHE_DRIVER=file eso escribe en storage/framework/cache en cada
     * corrida, haga lo que haga el comando. Peor: ese mutex lo crea el
     * proceso de `schedule:run` (ver Event::run() → shouldSkipDueToOverlapping()),
     * ANTES de que ->user() tenga efecto en start(). Con el cron de root eso
     * sembraba directorios de dueño root en la caché, y cuando a Apache le
     * tocaba una llave que caía en uno de ellos, la petición reventaba con 500.
     *
     * Rompió la consulta de cédula del modal de clientes el 2026-08-04 (commit
     * 49c0454) y el LOGIN completo el 2026-08-08 — el throttle del login
     * shardea por IP, así que dejó fuera solo a las IPs con mala suerte.
     * `storage/` quedó además con ACL por defecto para www-data como red de
     * seguridad, pero la causa se corta aquí: el cron corre como www-data.
     *
     * TODOS los withoutOverlapping() llevan minutos explícitos, nunca el
     * default. El default son 1440 minutos: si el proceso muere sin llegar a
     * `schedule:finish`, el mutex queda tomado y la tarea no vuelve a correr
     * **en 24 horas**, sin un solo error en ningún log. Eso ya pasó — el
     * 2026-08-07 siete tareas quedaron congeladas 17 horas por el problema de
     * permisos de la caché, y solo se notó al revisar los mutexes a mano
     * (`clientes:completar-ruaf` fue la única que siguió viva, justamente
     * porque era la única con expiración explícita).
     *
     * El criterio es por frecuencia, con holgura amplia sobre lo que tarda la
     * tarea de verdad: **15** para las que corren cada 1-5 min, **30** para las
     * de 15-60 min, **60** para las diarias y mensuales. El número solo tiene
     * que ser mayor que la duración real (si no, se solapa de verdad) y lo
     * bastante chico para que un atasco se cure solo.
     *
     * Para ver si hay mutexes atascados, recorrer los eventos y mirar
     * `$e->mutex->exists($e)`; se limpian con `schedule:clear-cache`.
     *
     * Al agregar una tarea nueva: NO le pongas ->user(), y SÍ dale minutos
     * explícitos a withoutOverlapping().
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
            ->withoutOverlapping(60)
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
            ->withoutOverlapping(30);

        // ── Reset mensual de n_plano ──────────────────────────────────
        // El día 1 de cada mes a las 00:01 (hora Colombia) resetea n_plano=1
        // y avanza mes_pagos/anio_pagos en todas las razones sociales.
        // Ejecución manual: php artisan planos:reset-mensual
        $schedule->command('planos:reset-mensual')
            ->monthlyOn(1, '00:01')
            ->timezone('America/Bogota')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/reset-n-plano.log'));

        // ── Liquidación automática de intereses de préstamos (Finanzas) ──
        // Diario a la 1:00 AM Colombia: liquida los meses calendario completos
        // vencidos de cada préstamo activo/mora con tasa > 0.
        // Ejecución manual: php artisan finanzas:liquidar-intereses
        $schedule->command('finanzas:liquidar-intereses')
            ->dailyAt('01:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping(60)
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
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/whatsapp-seguimiento-ia.log'));

        // ── Despacho de publicidad programada (Fase 4 página web pública) ────
        // Cada 5 min: publica las piezas aprobadas cuya fecha programada ya llegó.
        // Ejecución manual: php artisan publicaciones:despachar
        $schedule->command('publicaciones:despachar')
            ->everyFiveMinutes()
            ->timezone('America/Bogota')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/publicaciones-despacho.log'));

        // ── Procesamiento de video IA (Veo + overlay FFmpeg) ─────────────────
        // Cada minuto: consulta el estado de los videos en generación en Veo (1-3 min
        // típico), y cuando terminan les monta el overlay de texto+logo y los marca listos.
        // Ejecución manual: php artisan videos:procesar
        $schedule->command('videos:procesar')
            ->everyMinute()
            ->timezone('America/Bogota')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/videos-procesar.log'));

        // ── Métricas de redes de las piezas publicadas ───────────────────────
        // Diario a las 21:30: lee likes/comentarios/compartidos/alcance de cada
        // pieza de los últimos 30 días — alimenta el aprendizaje del piloto.
        // Ejecución manual: php artisan marketing:metricas
        $schedule->command('marketing:metricas')
            ->dailyAt('21:30')
            ->timezone('America/Bogota')
            ->withoutOverlapping(60)
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
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-pauta-sync.log'));

        // ── Vencimiento del token de pauta ───────────────────────────────────
        // Diario a las 08:00: el token de anuncios dura ~60 días y al vencer deja
        // de crear anuncios EN SILENCIO — el piloto sigue publicando, solo se
        // congela el gasto. Avisa a 7, 3 y 1 día, y cuando ya venció.
        // Ejecución manual: php artisan pauta:token-vigilar
        $schedule->command('pauta:token-vigilar')
            ->dailyAt('08:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/pauta-token-vigilar.log'));

        // ── Creatividades del conjunto permanente de pauta ───────────────────
        // Diario a las 12:00: mete la pieza publicada más reciente al conjunto
        // permanente (si queda cupo semanal) y pausa las que ya no compiten.
        // No enciende gasto: si el conjunto está en pausa, la creatividad entra
        // en pausa. Ejecución manual: php artisan marketing:pauta-creatividades
        $schedule->command('marketing:pauta-creatividades')
            ->dailyAt('12:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/marketing-pauta-creatividades.log'));

        // ── Piloto automático de marketing (community manager IA) ────────────
        // Cada 30 min en horario diurno: genera la pieza publicitaria del día de
        // cada aliado con piloto activo (una por día, desde la hora configurada).
        // Ejecución manual: php artisan marketing:autopilot [--aliado=slug --force]
        $schedule->command('marketing:autopilot')
            ->everyThirtyMinutes()
            ->between('05:00', '21:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping(30)
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
            ->withoutOverlapping(30)
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
