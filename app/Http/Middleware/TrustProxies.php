<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Proxies de confianza para esta aplicación.
     *
     * Usamos '*' para confiar en cualquier proxy que envíe X-Forwarded-Proto: https.
     * Esto es necesario en entornos donde Apache/cPanel no siempre envía
     * exactamente 127.0.0.1 como IP de proxy al pasar por SSL offloading.
     *
     * Sin '*', Laravel no detecta HTTPS correctamente, lo que causa que:
     * - Las cookies Secure no se envíen (SESSION_SECURE_COOKIE=true)
     * - El CSRF token se invalide tras cada AJAX request
     * - La sesión se pierda al recargar la página
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * Headers a leer del proxy para detectar el protocolo/IP real.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PREFIX;
}
