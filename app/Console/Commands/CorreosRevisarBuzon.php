<?php

namespace App\Console\Commands;

use App\Services\Correo\AgenteBuzonAfiliaciones;
use Illuminate\Console\Command;
use Throwable;

/**
 * Revisa el buzón de afiliaciones del aliado: respuestas de los asesores a los
 * correos enviados desde BryNex, radicados que llegan por correo y otros correos
 * de entidades (ver AgenteBuzonAfiliaciones). Corre cada 10 minutos.
 */
class CorreosRevisarBuzon extends Command
{
    protected $signature = 'correos:revisar-buzon
                            {--aliado=2 : Aliado dueño del buzón}
                            {--dias=2 : Cuántos días atrás leer}
                            {--simular : Muestra lo que haría sin guardar, cambiar radicados ni avisar}';

    protected $description = 'Lee el buzón de afiliaciones y aplica las respuestas de las entidades a los radicados';

    public function handle(AgenteBuzonAfiliaciones $agente): int
    {
        // Un correo con adjuntos grandes se parsea completo en memoria.
        ini_set('memory_limit', '512M');

        try {
            $r = $agente->revisar((int) $this->option('aliado'), max(1, (int) $this->option('dias')), (bool) $this->option('simular'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($r['detalle'] as $linea) {
            $this->line('  '.$linea);
        }
        $this->info(sprintf('%sLeídos %d · nuevos de entidades %d · aplicados %d · por revisar %d · informativos %d · vencidos %d',
            $this->option('simular') ? '[SIMULACIÓN] ' : '', $r['leidos'], $r['nuevos'], $r['aplicados'], $r['por_revisar'], $r['informativos'], $r['vencidos']));

        return self::SUCCESS;
    }
}
