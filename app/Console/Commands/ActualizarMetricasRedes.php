<?php

namespace App\Console\Commands;

use App\Models\Publicacion;
use App\Services\RedesSociales\MetricasRedesService;
use Illuminate\Console\Command;

/**
 * Actualiza las métricas orgánicas (likes, comentarios, compartidos, alcance IG) de las
 * piezas publicadas en los últimos 30 días. Registrado a diario en Kernel::schedule() —
 * las métricas de Meta son acumulativas, así que basta con la última foto de cada día.
 * Este historial alimenta el "aprendizaje" del piloto automático (temas/estilos que más
 * atraen) y el ranking del panel.
 */
class ActualizarMetricasRedes extends Command
{
    protected $signature = 'marketing:metricas';

    protected $description = 'Actualiza las métricas de redes de las piezas publicadas en los últimos 30 días';

    public function handle(): int
    {
        $piezas = Publicacion::publicadas()
            ->where('publicada_at', '>=', now()->subDays(30))
            ->get();

        if ($piezas->isEmpty()) {
            $this->info('No hay piezas publicadas en los últimos 30 días.');
            return self::SUCCESS;
        }

        foreach ($piezas as $pieza) {
            $redes = MetricasRedesService::actualizarPara($pieza);
            $this->info("Pieza #{$pieza->id}: " . ($redes ? implode(', ', $redes) : 'sin redes que medir'));
        }

        return self::SUCCESS;
    }
}
