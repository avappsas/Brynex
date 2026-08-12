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

        // `titulo` es una etiqueta interna/administrativa (a veces literalmente dice "prueba"
        // o el nombre del prompt usado) — NUNCA debe salir como texto público si no hay copy.
        if (!$publicacion->copy) {
            return ['ok' => false, 'mensaje' => 'Esta pieza no tiene copy (texto de red social) — agrégalo antes de publicar aquí.'];
        }

        try {
            $publicador = RedesFactory::make($config);
            $texto = $publicacion->copy;

            if ($publicacion->tipo_pieza === 'video' && $publicacion->video_path) {
                $urlPublica = asset('storage/' . $publicacion->video_path);
                $r = $publicador->publicarVideo($urlPublica, $texto);
            } else {
                $urlPublica = asset('storage/' . $publicacion->imagen_path);
                $r = $publicador->publicarImagen($urlPublica, $texto);
            }

            // El link de WhatsApp va como PRIMER COMENTARIO, no en el texto del post — Meta
            // penaliza el alcance orgánico de posts con links de salida en el cuerpo. Si el
            // comentario falla, el post ya quedó publicado igual (no se revierte por esto).
            if ($r['ok'] && $r['id_publicacion']) {
                $link = self::linkWhatsappRastreado($publicacion);
                if ($link) {
                    $publicador->comentar($r['id_publicacion'], $link);
                }
            }

            return ['ok' => $r['ok'], 'mensaje' => $r['mensaje'], 'id' => $r['id_publicacion'] ?? null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'Error inesperado: ' . $e->getMessage()];
        }
    }

    /**
     * Link de WhatsApp con un código de referencia ("ref: P{id}") en el mensaje precargado —
     * si el cliente lo manda tal cual, WhatsappWebhookService atribuye la conversación a esta
     * pieza exacta (ver buscarPublicacionOrigen). Se publica como primer comentario en vez de
     * ir dentro del texto del post (ver publicarEnDestino) para no perder alcance orgánico.
     *
     * Se apunta al wa.me DIRECTO, aunque la URL se vea larga en el comentario: Meta devalúa
     * el alcance de los enlaces que sacan al usuario de la plataforma, pero exime los que van
     * a sus propias tecnologías (WhatsApp, Instagram, Facebook). Un acortador propio en
     * brynex.co se ve mejor pero cae del lado penalizado — se probó y se revirtió por eso.
     * La ruta /wa/{publicacion} sigue existiendo para no romper los comentarios ya publicados
     * que apuntan a ella.
     *
     * IMPORTANTE: el número tiene que ser el del BOT de WhatsApp (WhatsappConfig::numero_telefono,
     * el phone_number_id que escucha el webhook), NUNCA el de un humano — si no, el mensaje
     * del cliente nunca llega al sistema y la atribución no puede funcionar.
     */
    private static function linkWhatsappRastreado(Publicacion $publicacion): ?string
    {
        $waConfig = \App\Models\WhatsappConfig::where('aliado_id', $publicacion->aliado_id)->where('activo', true)->first();
        $numero = preg_replace('/\D/', '', $waConfig->numero_telefono ?? '');
        if (!$numero) {
            return null;
        }
        if (!str_starts_with($numero, '57')) {
            $numero = '57' . $numero;
        }

        return '👉 Escríbenos: https://wa.me/' . $numero . '?text=' . rawurlencode($publicacion->mensajeWhatsappRastreado());
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
