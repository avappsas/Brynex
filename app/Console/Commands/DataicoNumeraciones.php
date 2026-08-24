<?php

namespace App\Console\Commands;

use App\Models\DataicoConfiguracion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Lista las numeraciones (resoluciones DIAN) de la cuenta de Dataico.
 *
 * El `numbering_range_id` no aparece en ninguna pantalla del portal: se
 * consulta por API. Como la ruta exacta no está en la documentación pública,
 * el comando prueba las candidatas y reporta cuál respondió, para no dejar al
 * usuario adivinando.
 *
 *   php artisan dataico:numeraciones --aliado=2
 */
class DataicoNumeraciones extends Command
{
    protected $signature = 'dataico:numeraciones {--aliado= : Aliado cuya configuración se usa}';

    protected $description = 'Lista las numeraciones DIAN de la cuenta de Dataico y su numbering_range_id';

    /** Rutas candidatas, en orden de probabilidad. */
    private const CANDIDATAS = [
        '/direct/dataico_api/v2/numbering_ranges',
        '/direct/dataico_api/v2/numberings',
        '/direct/dataico_api/v2/resolutions',
        '/direct/dataico_api/v1/numbering_ranges',
    ];

    public function handle(): int
    {
        $aliadoId = (int) ($this->option('aliado') ?: session('aliado_id_activo'));

        $cfg = DataicoConfiguracion::where('aliado_id', $aliadoId)->first();

        if (! $cfg || blank($cfg->dataico_account_id) || blank($cfg->auth_token)) {
            $this->error('No hay credenciales de Dataico guardadas para el aliado '.$aliadoId.'.');
            $this->line('Guárdalas primero en Configuración → Dataico por API.');

            return self::FAILURE;
        }

        $base = rtrim(config('dataico.base_url'), '/');

        foreach (self::CANDIDATAS as $ruta) {
            $this->line("· probando {$ruta} …");

            try {
                $r = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Auth-token' => $cfg->auth_token,
                ])
                    ->timeout(config('dataico.timeout'))
                    ->get($base.$ruta, ['dataico_account_id' => $cfg->dataico_account_id]);
            } catch (\Throwable $e) {
                $this->warn('  falla de red: '.$e->getMessage());

                continue;
            }

            if ($r->status() === 404) {
                $this->line('  404 — esa ruta no existe');

                continue;
            }

            $this->newLine();
            $this->info("Respondió {$ruta} con HTTP {$r->status()}:");
            $this->line(json_encode(
                $r->json() ?? $r->body(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return $r->successful() ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->warn('Ninguna ruta respondió. No pasa nada: el campo es opcional.');
        $this->line('Deja «Numbering range ID» vacío y llena «Prefijo» y «Resolución»');
        $this->line('con los datos de tu resolución DIAN — el payload los manda como');
        $this->line('alternativa y Dataico resuelve la numeración con ese par.');

        return self::FAILURE;
    }
}
