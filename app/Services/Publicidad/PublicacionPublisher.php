<?php

namespace App\Services\Publicidad;

use App\Http\Controllers\Publico\PaginaAliadoController;
use App\Models\Publicacion;
use App\Models\RedSocialConfig;
use App\Services\RedesSociales\RedesFactory;

/**
 * Publica una pieza aprobada en todos sus destinos. 'web' no necesita llamada externa — la
 * página pública lee `publicaciones` directamente (por eso solo hace falta invalidar su
 * cache). facebook/instagram usan las credenciales configuradas en Fase 3 vía RedesFactory.
 * Si una red falla, las demás NO se revierten: el estado queda `publicada` de todas formas y
 * el error queda visible en `resultado_publicacion` para poder reintentar esa red sola.
 */
class PublicacionPublisher
{
    public static function publicar(Publicacion $publicacion): void
    {
        $destinos  = $publicacion->destinos ?: ['web'];
        $resultado = $publicacion->resultado_publicacion ?: [];

        foreach ($destinos as $destino) {
            $resultado[$destino] = self::publicarEnDestino($publicacion, $destino);
        }

        $publicacion->update([
            'estado'                => Publicacion::ESTADO_PUBLICADA,
            'publicada_at'          => $publicacion->publicada_at ?: now(),
            'resultado_publicacion' => $resultado,
        ]);

        self::invalidarCacheSiAplica($publicacion, $destinos);
    }

    /** Reintenta la publicación de UNA sola red (ej. tras corregir credenciales), sin tocar las demás. */
    public static function reintentar(Publicacion $publicacion, string $destino): array
    {
        $resultado = $publicacion->resultado_publicacion ?: [];
        $resultado[$destino] = self::publicarEnDestino($publicacion, $destino);
        $publicacion->update(['resultado_publicacion' => $resultado]);

        self::invalidarCacheSiAplica($publicacion, [$destino]);

        return $resultado[$destino];
    }

    private static function publicarEnDestino(Publicacion $publicacion, string $destino): array
    {
        if ($destino === 'web') {
            return ['ok' => true, 'mensaje' => 'Visible en la página pública.'];
        }

        $config = RedSocialConfig::paraAliado($publicacion->aliado_id, $destino);
        if (!$config->activo || !$config->credencialesCompletas()) {
            return ['ok' => false, 'mensaje' => 'La red no está activa o le faltan credenciales (ver Redes Sociales).'];
        }

        try {
            $urlPublica = asset('storage/' . $publicacion->imagen_path);
            $r = RedesFactory::make($config)->publicarImagen($urlPublica, $publicacion->copy ?: $publicacion->titulo);
            return ['ok' => $r['ok'], 'mensaje' => $r['mensaje'], 'id' => $r['id_publicacion'] ?? null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'Error inesperado: ' . $e->getMessage()];
        }
    }

    private static function invalidarCacheSiAplica(Publicacion $publicacion, array $destinos): void
    {
        if (!in_array('web', $destinos, true)) {
            return;
        }
        $slug = $publicacion->aliado?->slug;
        if ($slug) {
            PaginaAliadoController::invalidarCache($slug);
        }
    }
}
