<?php

namespace App\Console\Commands;

use App\Models\PautaConfig;
use App\Models\Publicacion;
use App\Services\RedesSociales\MetaAdsService;
use Illuminate\Console\Command;

/**
 * Sincroniza el gasto real de Meta en las piezas con pauta activa/pausada, y pausa
 * automáticamente cualquiera que se pase de su propio presupuesto o del tope mensual del
 * aliado. Es la única acción "automática" con dinero real permitida: apagar gasto, nunca
 * prenderlo ni subirlo — eso siempre requiere el clic explícito del usuario.
 */
class SincronizarPauta extends Command
{
    protected $signature = 'marketing:pauta-sync';

    protected $description = 'Sincroniza el gasto real de las pautas activas y aplica los topes de seguridad';

    public function handle(): int
    {
        $piezas = Publicacion::whereIn('pauta_estado', ['activa', 'pausada', 'borrador'])
            ->whereNotNull('meta_adset_id')
            ->get();

        if ($piezas->isEmpty()) {
            $this->info('No hay piezas con pauta para sincronizar.');
            return self::SUCCESS;
        }

        foreach ($piezas as $pieza) {
            MetaAdsService::sincronizarGasto($pieza);
            $pieza->refresh();

            if ($pieza->pauta_estado !== 'activa') {
                continue;
            }

            $config = PautaConfig::paraAliado($pieza->aliado_id);
            $superoTope = $config->gastadoEsteMes() > (float) $config->limite_mensual_cop;

            if ($superoTope) {
                MetaAdsService::pausar($pieza);
                $this->warn("Pieza #{$pieza->id}: pausada — superó el tope mensual del aliado.");
            } else {
                $this->info("Pieza #{$pieza->id}: gasto real \${$pieza->pauta_gasto_total_cop} COP");
            }
        }

        return self::SUCCESS;
    }
}
