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

        // La prueba que vale es usar el token contra la cuenta publicitaria de verdad. La
        // lista de `scopes` de debug_token NO sirve como criterio: cuando un token se depura
        // consigo mismo, Meta a veces devuelve la lista vacía aunque los permisos estén, y
        // rechazar por eso deja afuera tokens perfectamente buenos.
        if (!$config->ad_account_id) {
            $this->error('El aliado no tiene cuenta publicitaria configurada.');
            return self::FAILURE;
        }
        $cuenta = 'act_' . ltrim($config->ad_account_id, 'act_');
        $prueba = Http::get("https://graph.facebook.com/v23.0/{$cuenta}", [
            'fields'       => 'id,name,account_status',
            'access_token' => $token,
        ]);

        if (!$prueba->successful()) {
            $this->error('Ese token no puede leer la cuenta publicitaria. No se guardó.');
            $this->line('  Meta dijo: ' . (string) $prueba->json('error.message'));
            // Sin esto quedaría en adivinanza: el motivo más común de un (#200) con la cuenta
            // bien asignada es haber generado el token con OTRA app —la del Explorador por
            // defecto— que no tiene ads_management aprobado.
            $this->newLine();
            $this->line('  Token generado con la app: ' . ($d['app_id'] ?? '?'));
            $this->line('  Tipo: ' . ($d['type'] ?? '?') . '   Usuario: ' . ($d['user_id'] ?? '?'));
            $this->line('  Permisos que reporta Meta: ' . (implode(', ', $d['scopes'] ?? []) ?: '(ninguno)'));

            return self::FAILURE;
        }

        $scopes = $d['scopes'] ?? [];
        if ($scopes && !in_array('ads_management', $scopes, true)) {
            $this->warn('Aviso: entre los permisos declarados no aparece ads_management,');
            $this->warn('pero el token sí lee la cuenta publicitaria. Se guarda igual.');
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
