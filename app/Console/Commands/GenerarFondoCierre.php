<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\IaConfiguracionAliado;
use App\Services\Publicidad\CierreMarcaVideo;
use App\Services\Publicidad\VeoVideoGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Genera con Veo el clip de fondo de una variante del cierre de marca.
 *
 * Los dos primeros fondos se hicieron a mano con scripts sueltos y quedaron como archivos
 * huérfanos: nadie podía volver a generarlos ni saber con qué prompt salieron. Esto lo deja
 * reproducible — el prompt vive en CierreMarcaVideo::promptFondo() junto a la variante.
 *
 * El clip se genera UNA vez y se cachea: el cierre es el mismo para todas las piezas, así que
 * no tiene sentido pagarlo en cada Reel.
 */
class GenerarFondoCierre extends Command
{
    protected $signature = 'cierres:fondo
        {--aliado= : ID o slug del aliado}
        {--variante= : Número de variante (sin esto, lista el estado de todas)}
        {--rehacer : Regenera el fondo aunque ya exista}';

    protected $description = 'Genera el clip de fondo de una variante del cierre de marca';

    public function handle(): int
    {
        $aliado = $this->resolverAliado();
        if (!$aliado) {
            return self::FAILURE;
        }

        $variantes = CierreMarcaVideo::variantes($aliado->id);

        if (!$this->option('variante')) {
            $this->table(
                ['#', 'Fondo', 'Textos', 'Dice'],
                collect($variantes)->map(fn ($v, $n) => [
                    $n,
                    $v['listo'] ? 'listo' : 'FALTA',
                    implode(' · ', $v['textos']),
                    \Illuminate\Support\Str::limit($v['dice'] ?? '', 60),
                ])->values()->all()
            );
            $this->line('Para generar uno: php artisan cierres:fondo --aliado=' . $aliado->id . ' --variante=3');

            return self::SUCCESS;
        }

        $variante = (int) $this->option('variante');
        if (!isset($variantes[$variante])) {
            $this->error("La variante {$variante} no existe. Declararla primero en CierreMarcaVideo::VARIANTES.");

            return self::FAILURE;
        }

        if ($variantes[$variante]['listo'] && !$this->option('rehacer')) {
            $this->warn("La variante {$variante} ya tiene fondo. Usar --rehacer para regenerarlo.");

            return self::SUCCESS;
        }

        $ia = IaConfiguracionAliado::paraAliado($aliado->id);
        if (!$ia->gemini_api_key) {
            $this->error('El aliado no tiene clave de Gemini configurada (ver Asistente Virtual).');

            return self::FAILURE;
        }

        $prompt = CierreMarcaVideo::promptFondo($aliado->nombre, $variante);
        $this->line("Dice: \"{$variantes[$variante]['dice']}\"");
        $this->line('Generando en Veo (~USD 0.4)...');

        $r = VeoVideoGenerator::iniciar($ia->gemini_api_key, $prompt, VeoVideoGenerator::MODELO_LITE, '9:16', '720p', 8);
        if (!$r['ok']) {
            $this->error($r['error']);

            return self::FAILURE;
        }

        // Veo tarda entre uno y tres minutos. Se espera aquí en vez de dejarlo en cola porque
        // esto se corre a mano, una vez por variante, y hay que ver el resultado.
        $uri = null;
        for ($i = 0; $i < 60; $i++) {
            sleep(10);
            $estado = VeoVideoGenerator::consultarEstado($ia->gemini_api_key, $r['operationName']);

            if (!$estado['ok']) {
                $this->error($estado['error']);

                return self::FAILURE;
            }
            if ($estado['done']) {
                $uri = $estado['videoUri'];
                break;
            }
            $this->line('  ... ' . (($i + 1) * 10) . 's');
        }

        if (!$uri) {
            $this->error('Veo no terminó a tiempo. El operationName sigue vivo: ' . $r['operationName']);

            return self::FAILURE;
        }

        $rel = $variantes[$variante]['fondo'];
        Storage::disk('public')->makeDirectory('publicidad/cierres');
        $abs = Storage::disk('public')->path($rel);

        if (!VeoVideoGenerator::descargar($ia->gemini_api_key, $uri, $abs)) {
            $this->error('No se pudo descargar el video de Veo.');

            return self::FAILURE;
        }

        // Los cierres ya construidos con esta variante quedan viejos: se borran para que el
        // proximo Reel los rearme con el fondo nuevo en vez de servir el cacheado.
        foreach (Storage::disk('public')->files('publicidad/cierres') as $f) {
            if (str_starts_with(basename($f), "cierre_{$aliado->id}_")) {
                Storage::disk('public')->delete($f);
            }
        }

        $this->info("Fondo de la variante {$variante} listo: {$rel}");
        $this->line('Revisar que el letrero se lea completo antes de dejarlo en rotación.');

        return self::SUCCESS;
    }

    private function resolverAliado(): ?Aliado
    {
        $ref = $this->option('aliado');
        if (!$ref) {
            $this->error('Falta --aliado.');

            return null;
        }

        $aliado = is_numeric($ref)
            ? Aliado::find((int) $ref)
            : Aliado::whereRaw('LOWER(nombre) = ?', [mb_strtolower($ref)])->first();

        if (!$aliado) {
            $this->error("No se encontró el aliado \"{$ref}\".");

            return null;
        }

        return $aliado;
    }
}
