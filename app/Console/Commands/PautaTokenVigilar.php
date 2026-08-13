<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\PautaConfig;
use App\Services\AlertaOperativaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Avisa antes de que se venza el token con el que se crean los anuncios.
 *
 * Meta no emite tokens de usuario perpetuos: el largo dura unos 60 días y se acabó. Cuando
 * vence, `marketing:pauta-creatividades` empieza a fallar y no lo hace a gritos — deja de
 * meter piezas al conjunto y ya. El piloto sigue generando y publicando como si nada, así que
 * lo único que se nota es que el gasto se queda quieto, y eso se descubre tarde.
 *
 * Por eso el aviso sale con una semana de anticipación y se repite acercándose a la fecha, en
 * vez de una sola vez que se puede pasar por alto.
 *
 * Ejecución manual: php artisan pauta:token-vigilar
 */
class PautaTokenVigilar extends Command
{
    protected $signature = 'pauta:token-vigilar {--dias=7 : Con cuántos días de anticipación avisar}';

    protected $description = 'Revisa cuánto le queda al token de pauta y avisa por WhatsApp antes de que venza';

    /**
     * Días exactos en los que se avisa. Avisar los 7 seguidos convierte la alerta en ruido.
     *
     * El 0 va incluido: "quedan 0 días" es que vence HOY, en unas horas — el momento en que
     * más urge. Sin él la primera corrida real avisaba a un día y después se callaba justo
     * cuando se estaba rompiendo.
     */
    private const HITOS = [7, 3, 1, 0];

    public function handle(AlertaOperativaService $alertas): int
    {
        $configs = PautaConfig::where('activo', true)->whereNotNull('access_token_ads')->get();

        if ($configs->isEmpty()) {
            $this->line('Ningún aliado tiene token de pauta propio configurado.');
            return self::SUCCESS;
        }

        foreach ($configs as $config) {
            $this->revisar($config, $alertas);
        }

        return self::SUCCESS;
    }

    private function revisar(PautaConfig $config, AlertaOperativaService $alertas): void
    {
        $nombre = Aliado::find($config->aliado_id)?->nombre ?? "aliado {$config->aliado_id}";

        $datos = Http::get('https://graph.facebook.com/v23.0/debug_token', [
            'input_token'  => $config->access_token_ads,
            'access_token' => $config->access_token_ads,
        ])->json('data');

        // Sin respuesta utilizable no se puede concluir nada: puede ser un corte de red, y
        // gritar "token vencido" por eso sería peor que callarse.
        if (!is_array($datos) || !array_key_exists('is_valid', $datos)) {
            $this->warn("{$nombre}: no se pudo consultar el token (¿sin red?). No se avisa.");
            return;
        }

        if (!$datos['is_valid']) {
            $this->error("{$nombre}: el token de pauta YA NO SIRVE.");
            $alertas->enviarUnaVez(
                "pauta_token_invalido_{$config->aliado_id}",
                'Pauta ' . $nombre,
                'El token de anuncios ya no sirve: no se están creando anuncios nuevos. Hay que regenerarlo con pauta:token.',
                60 * 12
            );
            return;
        }

        $expira = (int) ($datos['expires_at'] ?? 0);

        // 0 = no vence (usuario de sistema). Es justo lo que queremos que pase algún día.
        if ($expira === 0) {
            $this->info("{$nombre}: el token no vence. Nada que vigilar.");
            return;
        }

        $dias = (int) floor(($expira - time()) / 86400);
        $fecha = date('Y-m-d', $expira);

        if ($dias < 0) {
            $this->error("{$nombre}: token vencido el {$fecha}.");
            $alertas->enviarUnaVez(
                "pauta_token_vencido_{$config->aliado_id}",
                'Pauta ' . $nombre,
                "El token de anuncios venció el {$fecha}. El piloto sigue publicando pero ya no crea anuncios. Regeneralo con pauta:token.",
                60 * 12
            );
            return;
        }

        $this->line("{$nombre}: al token le quedan {$dias} día(s) — vence el {$fecha}.");

        $umbral = (int) $this->option('dias');
        if ($dias > $umbral || !in_array($dias, self::HITOS, true)) {
            return;
        }

        $enviada = $alertas->enviarUnaVez(
            "pauta_token_por_vencer_{$config->aliado_id}_{$dias}",
            'Pauta ' . $nombre,
            "El token de anuncios vence el {$fecha}, en {$dias} día(s). Cuando venza dejan de crearse anuncios. Renovalo con pauta:token.",
            60 * 20
        );

        $this->line($enviada
            ? "  → aviso enviado a {$alertas->numeroDestino()}"
            : '  → sin aviso: ya se avisó de este hito hace poco.');
    }
}
