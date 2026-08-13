<?php

namespace App\Services\RedesSociales;

use Illuminate\Support\Facades\Http;

/**
 * Convierte un token de Meta de corta duración en uno largo (~60 días).
 *
 * Es exactamente lo que hace el botón "Extend Access Token" del depurador, pero por API. Se
 * escribió porque esa pantalla es difícil de encontrar —el botón solo aparece DESPUÉS de
 * depurar, al final de una tabla larga— y equivocarse es fácil: el token que hay que copiar es
 * el que sale abajo, no el que uno pegó arriba. Un token corto guardado por error dura dos
 * horas, y lo que se rompe después no dice que la causa fue esa.
 *
 * Sirve también para RENOVAR: canjear un token largo antes de que venza devuelve otro con el
 * reloj en cero, así que la renovación puede ser automática (ver pauta:token-vigilar).
 *
 * Necesita la clave secreta de la app (META_APP_SECRET). Sin ella no hay canje posible y el
 * token se guarda tal como venga — degradado, no roto.
 */
class MetaTokenLargo
{
    private const BASE_URL = 'https://graph.facebook.com/v23.0';

    /**
     * @param  string  $appId  Id de la app que generó el token (sale de debug_token).
     * @return array{ok: bool, token: ?string, expira_en: ?int, error: ?string}
     *         `expira_en` son segundos; 0 significa que no vence.
     */
    public static function canjear(string $token, string $appId): array
    {
        $secreto = config('services.meta.app_secret');

        if (!$secreto) {
            return ['ok' => false, 'token' => null, 'expira_en' => null,
                'error' => 'Falta META_APP_SECRET en el .env — sin la clave secreta de la app no se puede alargar el token.'];
        }

        $resp = Http::get(self::BASE_URL . '/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $appId,
            'client_secret'     => $secreto,
            'fb_exchange_token' => $token,
        ]);

        if (!$resp->successful()) {
            $mensaje = $resp->json('error.message') ?? "HTTP {$resp->status()}";

            return ['ok' => false, 'token' => null, 'expira_en' => null, 'error' => "Meta rechazó el canje: {$mensaje}"];
        }

        $nuevo = $resp->json('access_token');
        if (!$nuevo) {
            return ['ok' => false, 'token' => null, 'expira_en' => null, 'error' => 'Meta no devolvió un token en el canje.'];
        }

        return [
            'ok'        => true,
            'token'     => $nuevo,
            // Sin `expires_in` el token no vence (pasa con los de usuario de sistema).
            'expira_en' => (int) ($resp->json('expires_in') ?? 0),
            'error'     => null,
        ];
    }

    /** ¿Está configurada la clave secreta? Para poder avisar antes de intentarlo. */
    public static function hayClaveSecreta(): bool
    {
        return (bool) config('services.meta.app_secret');
    }
}
