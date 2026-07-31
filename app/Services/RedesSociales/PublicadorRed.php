<?php

namespace App\Services\RedesSociales;

/**
 * Contrato que debe implementar cualquier red social soportada. Agregar una red nueva
 * (ej. TikTok) significa: agregar su clave a RedSocialConfig::REDES_DISPONIBLES, implementar
 * esta interfaz en una clase nueva, y registrarla en RedesFactory::make() — el resto del
 * sistema (controller admin, comando artisan, publicación de Fase 4) no cambia.
 */
interface PublicadorRed
{
    /**
     * Publica una imagen con texto. $urlImagenPublica debe ser una URL accesible públicamente
     * (no un path local) — las redes sociales la descargan desde ahí.
     *
     * @return array{ok: bool, mensaje: string, id_publicacion: ?string}
     */
    public function publicarImagen(string $urlImagenPublica, string $texto): array;

    /**
     * Publica un video con texto. $urlVideoPublica debe ser una URL accesible públicamente
     * (no un path local) — las redes sociales lo descargan desde ahí. El procesamiento del
     * video en la red social es más lento que el de una imagen (puede tardar varios minutos).
     *
     * @return array{ok: bool, mensaje: string, id_publicacion: ?string}
     */
    public function publicarVideo(string $urlVideoPublica, string $texto): array;

    /**
     * Prueba que las credenciales configuradas funcionan, sin publicar nada.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function probarConexion(): array;

    /**
     * Comenta en una publicación ya hecha — se usa para poner el link de WhatsApp en el
     * primer comentario en vez del texto principal (Meta penaliza el alcance orgánico de
     * posts con links de salida en el cuerpo del post).
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function comentar(string $idPublicacion, string $texto): array;
}
