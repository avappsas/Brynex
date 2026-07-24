<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\AutopilotConfig;
use App\Models\Publicacion;
use App\Services\Publicidad\AutopilotGenerator;
use Illuminate\Console\Command;

/**
 * Piloto automático de marketing: para cada aliado con el piloto activo genera la pieza
 * del día (una sola por día, a partir de la hora configurada). Registrado cada 30 min
 * en Kernel::schedule(). Con --aliado= y --force se puede probar manualmente sin
 * esperar la hora ni el límite diario.
 */
class MarketingAutopilot extends Command
{
    protected $signature = 'marketing:autopilot {--aliado= : slug del aliado (solo ese)} {--force : ignora hora, día y límite diario}';

    protected $description = 'Genera la pieza publicitaria diaria de los aliados con piloto automático activo';

    public function handle(): int
    {
        $configs = AutopilotConfig::where('activo', true)->with('aliado')->get();

        if ($slug = $this->option('aliado')) {
            $aliado = Aliado::where('slug', $slug)->first();
            if (!$aliado) {
                $this->error("No existe un aliado con slug '{$slug}'.");
                return self::FAILURE;
            }
            $configs = collect([AutopilotConfig::paraAliado($aliado->id)->load('aliado')]);
        }

        if ($configs->isEmpty()) {
            $this->info('Ningún aliado tiene el piloto automático activo.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        foreach ($configs as $config) {
            $aliado = $config->aliado;

            if (!$force) {
                if (!$config->tocaHoy() || !$config->horaLlego()) {
                    continue;
                }
                $yaGenerada = Publicacion::where('aliado_id', $aliado->id)
                    ->where('origen', 'ia_auto')
                    ->where('created_at', '>=', now('America/Bogota')->startOfDay())
                    ->exists();
                if ($yaGenerada) {
                    continue;
                }
            }

            $this->info("Generando pieza del día para {$aliado->nombre}...");
            $resultado = AutopilotGenerator::generarPiezaDelDia($aliado, $config);

            if ($resultado['ok']) {
                $p = $resultado['publicacion'];
                $this->info("✅ Pieza #{$p->id} — [{$p->tema}] {$p->titulo} ({$p->etiquetaEstado()})");
            } else {
                $this->error("❌ {$aliado->nombre}: {$resultado['error']}");
            }
        }

        return self::SUCCESS;
    }
}
