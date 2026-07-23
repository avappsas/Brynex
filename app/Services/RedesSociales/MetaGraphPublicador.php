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

        $publicar = Http::timeout(15)->asForm()->post(self::BASE_URL . "/{$this->config->identificador}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $this->config->access_token,
        ]);

        if (!$publicar->successful()) {
            return ['ok' => false, 'mensaje' => $this->errorDeMeta($publicar), 'id_publicacion' => null];
        }

        return ['ok' => true, 'mensaje' => 'Publicado en Instagram.', 'id_publicacion' => $publicar->json('id')];
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
