<?php

namespace App\Services\RedesSociales;

use App\Models\Publicacion;
use App\Models\PublicacionMetrica;
use App\Models\RedSocialConfig;
use Illuminate\Support\Facades\Http;

/**
 * Lee las métricas orgánicas de cada pieza ya publicada usando los MISMOS tokens de
 * RedSocialConfig con los que se publicó (solo lectura). Verificado contra la API real:
 * - Facebook: likes/comentarios/compartidos por post. Meta retiró el alcance por post
 *   de la API de Páginas (error #100 "not a valid insights metric"), así que ahí va null.
 * - Instagram: like_count/comments_count + alcance real vía /insights?metric=reach.
 */
class MetricasRedesService
{
    private const BASE_URL = 'https://graph.facebook.com/v23.0';

    /** Actualiza (upsert) las métricas de todas las redes donde la pieza se publicó con éxito. */
    public static function actualizarPara(Publicacion $publicacion): array
    {
        $resultado = $publicacion->resultado_publicacion ?: [];
        $actualizadas = [];

        foreach (['facebook', 'instagram'] as $red) {
            $idPost = $resultado[$red]['id'] ?? null;
            if (!$idPost || !($resultado[$red]['ok'] ?? false)) {
                continue;
            }

            $config = RedSocialConfig::paraAliado($publicacion->aliado_id, $red);
            if (!$config->credencialesCompletas()) {
                continue;
            }

            $metricas = $red === 'facebook'
                ? self::leerFacebook($idPost, $config->access_token)
                : self::leerInstagram($idPost, $config->access_token);

            if ($metricas === null) {
                continue;
            }

            PublicacionMetrica::updateOrCreate(
                ['publicacion_id' => $publicacion->id, 'red' => $red],
                $metricas + ['medido_at' => now()]
            );
            $actualizadas[] = $red;
        }

        return $actualizadas;
    }

    private static function leerFacebook(string $idPost, string $token): ?array
    {
        $resp = Http::timeout(15)->get(self::BASE_URL . "/{$idPost}", [
            'fields'       => 'likes.summary(true),comments.summary(true),shares',
            'access_token' => $token,
        ]);

        if (!$resp->successful()) {
            return null;
        }

        return [
            'alcance'     => null, // no disponible por post en la API de Páginas
            'me_gusta'    => (int) $resp->json('likes.summary.total_count', 0),
            'comentarios' => (int) $resp->json('comments.summary.total_count', 0),
            'compartidos' => (int) $resp->json('shares.count', 0),
        ];
    }

    private static function leerInstagram(string $idPost, string $token): ?array
    {
        $basico = Http::timeout(15)->get(self::BASE_URL . "/{$idPost}", [
            'fields'       => 'like_count,comments_count',
            'access_token' => $token,
        ]);

        if (!$basico->successful()) {
            return null;
        }

        $alcance = null;
        $insights = Http::timeout(15)->get(self::BASE_URL . "/{$idPost}/insights", [
            'metric'       => 'reach',
            'access_token' => $token,
        ]);
        if ($insights->successful()) {
            $alcance = (int) data_get($insights->json(), 'data.0.values.0.value', 0);
        }

        return [
            'alcance'     => $alcance,
            'me_gusta'    => (int) $basico->json('like_count', 0),
            'comentarios' => (int) $basico->json('comments_count', 0),
            'compartidos' => 0, // Instagram no expone compartidos por la API
        ];
    }
}
