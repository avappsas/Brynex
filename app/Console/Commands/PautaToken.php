<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\PautaConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Guarda el token de usuario con el que se crean los anuncios.
 *
 * Se pide oculto y nunca se imprime: un token de larga duración con `ads_management` puede
 * gastar dinero real, así que no debe quedar en el historial del shell ni en los logs.
 */
class PautaToken extends Command
{
    protected $signature = 'pauta:token {aliado : Slug del aliado} {--quitar : Borra el token y vuelve al de la página}';

    protected $description = 'Guarda (oculto) el token de usuario para crear anuncios en Meta';

    public function handle(): int
    {
        $aliado = Aliado::where('slug', $this->argument('aliado'))->first();
        if (!$aliado) {
            $this->error('No existe ese aliado.');
            return self::FAILURE;
        }

        $config = PautaConfig::paraAliado($aliado->id);

        if ($this->option('quitar')) {
            $config->update(['access_token_ads' => null]);
            $this->info('Token de pauta borrado. Se vuelve a usar el token de la página.');
            return self::SUCCESS;
        }

        $token = $this->secret('Pega el token de usuario (no se va a mostrar)');
        if (!$token) {
            $this->error('No se recibió ningún token.');
            return self::FAILURE;
        }

        // Verificar ANTES de guardar: un token inválido guardado en silencio se descubriría
        // recién cuando falle la primera creación de anuncio, y ahí el error sería confuso.
        $d = Http::get('https://graph.facebook.com/v23.0/debug_token', [
            'input_token'  => $token,
            'access_token' => $token,
        ])->json('data');

        if (empty($d['is_valid'])) {
            $this->error('Meta dice que ese token no es válido. No se guardó.');
            return self::FAILURE;
        }
        if (!in_array('ads_management', $d['scopes'] ?? [], true)) {
            $this->error('Al token le falta el permiso ads_management. No se guardó.');
            return self::FAILURE;
        }

        $quien = Http::get('https://graph.facebook.com/v23.0/me', [
            'fields'       => 'id,name',
            'access_token' => $token,
        ])->json();

        $config->update(['access_token_ads' => $token]);

        $this->info('Token guardado y cifrado.');
        $this->line('  Tipo:    ' . ($d['type'] ?? '?'));
        $this->line('  Usuario: ' . ($quien['name'] ?? '?') . ' (' . ($quien['id'] ?? '?') . ')');
        $this->line('  Expira:  ' . (($d['expires_at'] ?? 0) ? date('Y-m-d', $d['expires_at']) : 'nunca'));

        if (($d['type'] ?? '') !== 'USER') {
            $this->warn('  Ojo: no es un token de USUARIO. Si es de página o de usuario del sistema,');
            $this->warn('  Meta va a volver a pedir la certificación de no discriminación.');
        }

        return self::SUCCESS;
    }
}
