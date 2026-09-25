<?php

namespace App\Listeners;

use App\Models\CorridaProgramada;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as TareaProgramada;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anota en `corridas_programadas` cómo salió cada comando de la agenda.
 *
 * Va por los eventos del planificador y no por cada comando: son treinta y
 * subiendo, y pedirle a cada uno que se anote sería pedir que nadie se olvide.
 *
 * Los comandos que corren en segundo plano avisan dos veces: `Finished` salta en
 * cuanto se lanzan —todavía sin resultado— y el resultado de verdad llega en
 * `ScheduledBackgroundTaskFinished`, cuando `schedule:finish` lo recoge. Por eso
 * el primero se ignora para ellos; si no, todo saldría "bien" en un segundo.
 *
 * Nada de esto puede tumbar una corrida: si el registro falla, se deja en el log
 * y el comando sigue su camino.
 */
class RegistrarCorridaProgramada
{
    /** Cuánto se guarda de lo que imprimió el comando. */
    private const MAX_SALIDA = 4000;

    public function empezando(ScheduledTaskStarting $evento): void
    {
        $this->seguro(function () use ($evento) {
            $corrida = CorridaProgramada::create([
                'nombre' => $this->nombre($evento->task),
                'comando' => mb_substr((string) $evento->task->command, 0, 400),
                'inicio' => now(),
            ]);

            // La corrida y su cierre son dos procesos distintos: el id viaja por
            // caché, con la llave del mutex, que es lo único que comparten. Con
            // él va el tamaño que ya tenía el log, para después leer solo lo que
            // escribió esta corrida y no la cola de las anteriores.
            Cache::put($this->llave($evento->task), [
                'id' => $corrida->id,
                'desde' => $this->tamanoDelLog($evento->task),
            ], now()->addHours(12));
        });
    }

    public function termino(ScheduledTaskFinished $evento): void
    {
        // En segundo plano esto salta al lanzarlo, no al terminar.
        if ($evento->task->runInBackground) {
            return;
        }

        $this->cerrar($evento->task, null);
    }

    public function terminoEnSegundoPlano(ScheduledBackgroundTaskFinished $evento): void
    {
        $this->cerrar($evento->task, null);
    }

    public function fallo(ScheduledTaskFailed $evento): void
    {
        $this->cerrar($evento->task, mb_substr((string) $evento->exception->getMessage(), 0, 400));
    }

    private function cerrar(TareaProgramada $tarea, ?string $motivo): void
    {
        $this->seguro(function () use ($tarea, $motivo) {
            $guardado = Cache::pull($this->llave($tarea));
            $corrida = ! empty($guardado['id']) ? CorridaProgramada::find($guardado['id']) : null;

            // Sin la fila de inicio (caché limpiada, o el proceso murió y
            // reapareció) se anota igual: media verdad es mejor que ninguna.
            $corrida ??= CorridaProgramada::create([
                'nombre' => $this->nombre($tarea),
                'comando' => mb_substr((string) $tarea->command, 0, 400),
                'inicio' => now(),
            ]);

            $codigo = $motivo !== null ? ($tarea->exitCode ?? 1) : $tarea->exitCode;

            $corrida->update([
                'fin' => now(),
                'segundos' => (int) $corrida->inicio->diffInSeconds(now()),
                'exit_code' => $codigo,
                'exitosa' => $motivo === null && (int) $codigo === 0,
                'motivo' => $motivo,
                'salida' => $this->cola($tarea, (int) ($guardado['desde'] ?? 0)),
            ]);
        });
    }

    /**
     * Con qué nombre queda anotada.
     *
     * El `->name()` de la agenda cuando lo tiene. Si no, el nombre del comando
     * de artisan, no la línea entera: `getSummaryForDisplay()` devuelve el
     * envoltorio con la ruta de PHP, y eso es lo que acabaría en el WhatsApp.
     */
    private function nombre(TareaProgramada $tarea): string
    {
        if ($tarea->description) {
            return mb_substr($tarea->description, 0, 120);
        }

        $comando = (string) $tarea->command;

        return mb_substr(preg_match("/artisan'?\\s+'?([a-z0-9:_-]+)/i", $comando, $m)
            ? $m[1]
            : $tarea->getSummaryForDisplay(), 0, 120);
    }

    private function llave(TareaProgramada $tarea): string
    {
        return 'corrida_programada:'.sha1($tarea->mutexName());
    }

    /**
     * Lo que imprimió esta corrida, para no tener que abrir el log.
     *
     * Solo cuando el comando escribe a un archivo propio (`appendOutputTo`), y
     * desde donde estaba el archivo al empezar: con `appendOutputTo` el log se
     * acumula, y leer la cola a secas mezclaría corridas de otros días. De lo
     * que escribió se guarda el final, que es donde está el resumen.
     */
    private function cola(TareaProgramada $tarea, int $desde): ?string
    {
        $ruta = (string) $tarea->output;

        if ($ruta === '' || $ruta === '/dev/null' || ! is_file($ruta)) {
            return null;
        }

        $tamano = (int) filesize($ruta);
        // El log pudo rotarse o truncarse mientras corría: entonces vale todo.
        $arranque = $desde > 0 && $desde <= $tamano ? $desde : 0;
        $manejador = @fopen($ruta, 'rb');

        if (! $manejador) {
            return null;
        }

        if ($tamano - $arranque > self::MAX_SALIDA) {
            $arranque = $tamano - self::MAX_SALIDA;
        }

        fseek($manejador, $arranque);
        $texto = (string) stream_get_contents($manejador);
        fclose($manejador);

        return trim($texto) ?: null;
    }

    private function tamanoDelLog(TareaProgramada $tarea): int
    {
        $ruta = (string) $tarea->output;

        return $ruta !== '' && $ruta !== '/dev/null' && is_file($ruta) ? (int) filesize($ruta) : 0;
    }

    /** Un fallo al anotar nunca debe tumbar la corrida que estaba anotando. */
    private function seguro(callable $que): void
    {
        try {
            $que();
        } catch (Throwable $e) {
            Log::warning('No se pudo anotar la corrida de la agenda', ['error' => $e->getMessage()]);
        }
    }
}
