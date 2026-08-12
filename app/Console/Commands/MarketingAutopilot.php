<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\AutopilotConfig;
use App\Models\Publicacion;
use App\Models\PublicidadVideoIa;
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
                $inicioDia = now('America/Bogota')->startOfDay();

                $yaGenerada = Publicacion::where('aliado_id', $aliado->id)
                    ->where('origen', 'ia_auto')
                    ->where('created_at', '>=', $inicioDia)
                    ->exists();

                // Un Reel no tiene Publicacion hasta que Veo termina (1-3 min), así que
                // mirar solo `publicaciones` dejaría al comando creyendo que no ha hecho
                // nada: volvería a lanzar video cada 30 minutos hasta las 21:00. Se cuenta
                // también el video del día, en cualquier estado — si quedó en error, no se
                // reintenta hoy: sale en el log y se revisa, que es más barato que gastar
                // una generación de Veo por cada corrida.
                $videoDeHoy = PublicidadVideoIa::where('aliado_id', $aliado->id)
                    ->whereNull('creado_por')
                    ->where('created_at', '>=', $inicioDia)
                    ->exists();

                if ($yaGenerada || $videoDeHoy) {
                    continue;
                }
            }

            $this->info("Generando pieza del día para {$aliado->nombre}...");
            $resultado = AutopilotGenerator::generarPiezaDelDia($aliado, $config);

            if (!$resultado['ok']) {
                $this->error("❌ {$aliado->nombre}: {$resultado['error']}");
                continue;
            }

            // El Reel devuelve `publicacion => null` a propósito: la pieza todavía no existe
            // porque Veo sigue generando. La crea `videos:procesar` al terminar el clip.
            if ($p = $resultado['publicacion'] ?? null) {
                $this->info("✅ Pieza #{$p->id} — [{$p->tema}] {$p->titulo} ({$p->etiquetaEstado()})");
            } elseif ($v = $resultado['video'] ?? null) {
                $this->info("🎬 Reel #{$v->id} en generación ({$v->modelo}, {$v->duracion_seg}s, ~USD {$v->costo_estimado_usd}) — la pieza se creará al terminar.");
            }
        }

        return self::SUCCESS;
    }
}
