<?php

namespace App\Console\Commands;

use App\Models\CorridaProgramada;
use App\Services\AlertaOperativaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Cómo salió la agenda de anoche, y un aviso si algo se rompió.
 *
 * Los treinta comandos de la agenda escriben su propio log y nadie los abre: un
 * comando que empieza a fallar de madrugada puede pasar semanas así. Esto lo
 * mira una vez por la mañana y **solo avisa cuando hay algo que contar**, que es
 * lo que hace que el aviso se siga leyendo.
 *
 * Lo que cuenta como "algo": un comando que salió con error, uno que nunca
 * terminó —se quedó colgado o lo mataron, y ese es el peor caso porque no deja
 * rastro en ninguna parte— y la madrugada entera en silencio, que significa que
 * el cron no corrió.
 */
class CorridasResumen extends Command
{
    protected $signature = 'corridas:resumen
                            {--horas=12 : Cuántas horas hacia atrás mirar}
                            {--siempre : Avisa aunque todo haya salido bien}
                            {--simular : Muestra el aviso pero no lo manda}';

    protected $description = 'Revisa cómo salieron los comandos de la agenda y avisa por WhatsApp si algo falló';

    /**
     * Cuánto historial se guarda.
     *
     * La agenda deja unas doscientas corridas al día —hay comandos cada media
     * hora— y cada una puede traer hasta 4 KB de lo que imprimió. Un mes es de
     * sobra para entender una racha de fallos, y el SQL Server es Express, con
     * techo de 10 GB: ver [[sql-server-express-limites]].
     */
    private const DIAS_DE_HISTORIAL = 30;

    public function handle(AlertaOperativaService $alertas): int
    {
        $hasta = now();
        $desde = $hasta->copy()->subHours(max(1, (int) $this->option('horas')));

        $corridas = CorridaProgramada::entre($desde, $hasta);

        // Se limpia después de leer la ventana, nunca antes: el historial viejo
        // no estorba al resumen de hoy y así el barrido no necesita su propia
        // entrada en la agenda.
        if ($viejas = CorridaProgramada::where('inicio', '<', now()->subDays(self::DIAS_DE_HISTORIAL))->limit(5000)->delete()) {
            $this->line("Se borraron {$viejas} corrida(s) de hace más de ".self::DIAS_DE_HISTORIAL.' días.');
        }

        $this->info("Agenda entre {$desde->format('d/m H:i')} y {$hasta->format('d/m H:i')}: {$corridas->count()} corrida(s).");
        $this->line('');

        foreach ($corridas as $c) {
            $marca = match (true) {
                $c->fin === null => '⏳',
                (bool) $c->exitosa => '✅',
                default => '❌',
            };
            $this->line("  {$marca} ".$c->inicio->format('H:i').'  '.str_pad(mb_substr($c->nombre, 0, 34), 35)
                .str_pad($c->duracion(), 8).($c->fin === null ? 'sin terminar' : ('salida '.$c->exit_code))
                .($c->motivo ? "  — {$c->motivo}" : ''));
        }

        $fallaron = $corridas->filter(fn ($c) => $c->fin !== null && ! $c->exitosa);
        $colgadas = $corridas->filter(fn ($c) => $c->fin === null && $c->inicio->lt(now()->subHours(3)));

        $aviso = $this->aviso($corridas, $fallaron, $colgadas, $desde);

        if (! $aviso && ! $this->option('siempre')) {
            $this->line('');
            $this->info($corridas->isEmpty()
                ? 'Todavía no hay historial de la agenda: nada que comparar.'
                : 'Todo salió bien: no hay nada que avisar.');

            return self::SUCCESS;
        }

        $aviso ??= "Agenda de la noche sin novedades: {$corridas->count()} comandos, todos bien.";

        $this->line('');
        $this->line($aviso);

        if ($this->option('simular')) {
            $this->info('(simulación: no se envió)');

            return self::SUCCESS;
        }

        $alertas->enviar('Agenda BryNex', $aviso)
            ? $this->info('Avisado al '.$alertas->numeroDestino().'.')
            : $this->warn('El aviso de WhatsApp no salió (queda en el log).');

        return self::SUCCESS;
    }

    /**
     * El aviso, o null si no hay nada que contar.
     *
     * Sin saltos de línea: Meta rechaza la variable de la plantilla si los trae.
     */
    private function aviso($corridas, $fallaron, $colgadas, Carbon $desde): ?string
    {
        // La madrugada en silencio no es buena noticia: es que el cron no corrió.
        //
        // Salvo que nunca se haya anotado nada: entonces el silencio es que esto
        // acaba de instalarse y todavía no ha visto una noche. Sin este freno, la
        // primera mañana después de cada despliegue avisaría que el cron está
        // caído, que es exactamente el aviso que enseña a no creerle a los avisos.
        if ($corridas->isEmpty()) {
            return CorridaProgramada::where('inicio', '<', $desde)->exists()
                ? 'La agenda de BryNex no corrió nada desde el '.$desde->format('d/m H:i')
                    .'. Revisar el cron de schedule:run en el servidor.'
                : null;
        }

        if ($fallaron->isEmpty() && $colgadas->isEmpty()) {
            return null;
        }

        $partes = [];

        if ($fallaron->isNotEmpty()) {
            $partes[] = ($fallaron->count() === 1 ? '1 falló: ' : $fallaron->count().' fallaron: ').$fallaron
                ->map(fn ($c) => $c->nombre.' ('.($c->motivo ?: 'salida '.$c->exit_code).')')
                ->implode('; ');
        }

        if ($colgadas->isNotEmpty()) {
            $partes[] = $colgadas->count().' sin terminar: '.$colgadas->map(fn ($c) => $c->nombre)->implode(', ');
        }

        return 'Agenda de la noche: de '.$corridas->count().' comandos, '.implode('. ', $partes)
            .'. Ver con: php artisan corridas:resumen';
    }
}
