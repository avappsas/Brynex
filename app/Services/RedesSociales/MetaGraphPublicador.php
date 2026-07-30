<?php

namespace App\Services\RedesSociales;

use App\Models\RedSocialConfig;
use Illuminate\Support\Facades\Http;

/**
 * Publicador para Facebook Page e Instagram Business, ambas sobre la Meta Graph API.
 * Facebook: POST /{page_id}/photos con `url` (imagen pública) + caption.
 * Instagram: dos pasos — POST /{ig_id}/media (crea el contenedor) y luego
 * POST /{ig_id}/media_publish (lo publica) — así lo exige la API de Meta para IG.
 */
class MetaGraphPublicador implements PublicadorRed
{
    private const BASE_URL = 'https://graph.facebook.com/v19.0';

    public function __construct(private RedSocialConfig $config) {}

    public function publicarImagen(string $urlImagenPublica, string $texto): array
    {
        if (!$this->config->credencialesCompletas()) {
            return ['ok' => false, 'mensaje' => 'Faltan credenciales (identificador o token) para esta red.', 'id_publicacion' => null];
        }

        try {
            return $this->config->red === RedSocialConfig::INSTAGRAM
                ? $this->publicarInstagram($urlImagenPublica, $texto)
                : $this->publicarFacebook($urlImagenPublica, $texto);
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'Error al publicar: ' . $e->getMessage(), 'id_publicacion' => null];
        }
    }

    public function publicarVideo(string $urlVideoPublica, string $texto): array
    {
        if (!$this->config->credencialesCompletas()) {
            return ['ok' => false, 'mensaje' => 'Faltan credenciales (identificador o token) para esta red.', 'id_publicacion' => null];
        }

        try {
            return $this->config->red === RedSocialConfig::INSTAGRAM
                ? $this->publicarVideoInstagram($urlVideoPublica, $texto)
                : $this->publicarVideoFacebook($urlVideoPublica, $texto);
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'Error al publicar el video: ' . $e->getMessage(), 'id_publicacion' => null];
        }
    }

    /** Facebook procesa el video en segundo plano tras subirlo — el post aparece cuando termina, sin necesidad de sondear. */
    private function publicarVideoFacebook(string $url, string $texto): array
    {
        $resp = Http::timeout(30)->asForm()->post(self::BASE_URL . "/{$this->config->identificador}/videos", [
            'file_url'     => $url,
            'description'  => $texto,
            'access_token' => $this->config->access_token,
        ]);

        if (!$resp->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($resp), 'id_publicacion' => null];
        }

        return ['ok' => true, 'mensaje' => 'Video publicado en Facebook (procesando).', 'id_publicacion' => $resp->json('id')];
    }

    /** Reel de Instagram — mismo patrón de contenedor que la imagen, pero con presupuesto de espera mucho mayor (el video tarda más en procesarse). */
    private function publicarVideoInstagram(string $url, string $texto): array
    {
        $crear = Http::timeout(15)->asForm()->post(self::BASE_URL . "/{$this->config->identificador}/media", [
            'media_type'   => 'REELS',
            'video_url'    => $url,
            'caption'      => $texto,
            'access_token' => $this->config->access_token,
        ]);

        if (!$crear->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($crear), 'id_publicacion' => null];
        }

        $creationId = $crear->json('id');

        if (!$this->esperarContenedorListo($creationId, 40, 5)) {
            return ['ok' => false, 'mensaje' => 'El video no terminó de procesarse en Instagram a tiempo. Puedes reintentar más tarde.', 'id_publicacion' => null];
        }

        $publicar = Http::timeout(15)->asForm()->post(self::BASE_URL . "/{$this->config->identificador}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $this->config->access_token,
        ]);

        if (!$publicar->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($publicar), 'id_publicacion' => null];
        }

        return ['ok' => true, 'mensaje' => 'Reel publicado en Instagram.', 'id_publicacion' => $publicar->json('id')];
    }

    private function publicarFacebook(string $url, string $texto): array
    {
        $resp = Http::timeout(15)->asForm()->post(self::BASE_URL . "/{$this->config->identificador}/photos", [
            'url'          => $url,
            'caption'      => $texto,
            'access_token' => $this->config->access_token,
        ]);

        if (!$resp->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($resp), 'id_publicacion' => null];
        }

        return ['ok' => true, 'mensaje' => 'Publicado en Facebook.', 'id_publicacion' => $resp->json('post_id') ?? $resp->json('id')];
    }

    private function publicarInstagram(string $url, string $texto): array
    {
        $crear = Http::timeout(15)->asForm()->post(self::BASE_URL . "/{$this->config->identificador}/media", [
            'image_url'    => $url,
            'caption'      => $texto,
            'access_token' => $this->config->access_token,
        ]);

        if (!$crear->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($crear), 'id_publicacion' => null];
        }

        $creationId = $crear->json('id');

        // La creación del contenedor es asíncrona en Meta (empieza en IN_PROGRESS mientras
        // descarga/procesa la imagen) — publicar antes de que quede FINISHED falla con
        // "Media ID is not available". Se espera activamente en vez de asumir que ya terminó.
        if (!$this->esperarContenedorListo($creationId)) {
            return ['ok' => false, 'mensaje' => 'La imagen no terminó de procesarse en Instagram a tiempo. Intenta de nuevo.', 'id_publicacion' => null];
        }

        $publicar = Http::timeout(15)->asForm()->post(self::BASE_URL . "/{$this->config->identificador}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $this->config->access_token,
        ]);

        if (!$publicar->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($publicar), 'id_publicacion' => null];
        }

        return ['ok' => true, 'mensaje' => 'Publicado en Instagram.', 'id_publicacion' => $publicar->json('id')];
    }

    /**
     * Sondea status_code del contenedor hasta FINISHED (o ERROR/timeout). Por defecto ~15s
     * (imagen); para video se pasa un presupuesto mucho mayor porque Meta tarda bastante más
     * en procesarlo (ver publicarVideoInstagram).
     */
    private function esperarContenedorListo(string $creationId, int $intentos = 8, float $segundosEntreSondeos = 1.5): bool
    {
        for ($intento = 0; $intento < $intentos; $intento++) {
            $resp = Http::timeout(10)->get(self::BASE_URL . "/{$creationId}", [
                'fields'       => 'status_code',
                'access_token' => $this->config->access_token,
            ]);

            $estado = $resp->json('status_code');

            if ($estado === 'FINISHED') {
                return true;
            }
            if ($estado === 'ERROR' || $estado === 'EXPIRED') {
                return false;
            }

            usleep((int) round($segundosEntreSondeos * 1000000));
        }

        return false;
    }

    public function probarConexion(): array
    {
        if (!$this->config->credencialesCompletas()) {
            return ['ok' => false, 'mensaje' => 'Completa el identificador y el token de acceso primero.'];
        }

        $campo = $this->config->red === RedSocialConfig::INSTAGRAM ? 'username' : 'name';

        try {
            $resp = Http::timeout(10)->get(self::BASE_URL . "/{$this->config->identificador}", [
                'fields'       => "id,{$campo}",
                'access_token' => $this->config->access_token,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar con Meta: ' . $e->getMessage()];
        }

        if (!$resp->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($resp)];
        }

        $nombre = $resp->json($campo) ?? $resp->json('id');
        return ['ok' => true, 'mensaje' => "Conexión exitosa. Cuenta: {$nombre}"];
    }

    private function errorDeMeta(\Illuminate\Http\Client\Response $resp): string
    {
        $mensaje = $resp->json('error.message');
        return $mensaje ? "Meta respondió: {$mensaje}" : "Meta respondió con error HTTP {$resp->status()}.";
    }
}
