<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expira el cookie de sesión anterior (brynex_session) que puedan tener
 * los browsers al cambiar el nombre del cookie a brynex_s2.
 * Enviar Set-Cookie con expiración en el pasado elimina el cookie del browser.
 *
 * Usa la misma configuración de secure/sameSite que el resto de la app
 * para garantizar que el browser procese el header de expiración.
 */
class ExpireOldSessionCookie
{
    // Nombres de cookies viejas que deben limpiarse del browser
    protected array $oldCookieNames = [
        'brynex_session',   // nombre viejo — se invalida al cambiar a brynex_s2
        'laravel_session',  // por si quedó alguna sesión de desarrollo local
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Leer configuración real de session para ser consistentes
        $isSecure  = config('session.secure', false);
        $sameSite  = config('session.same_site', 'lax');

        foreach ($this->oldCookieNames as $name) {
            if ($request->cookies->has($name)) {
                // Expira el cookie viejo enviando un Set-Cookie con max-age=0
                $response->headers->setCookie(
                    new \Symfony\Component\HttpFoundation\Cookie(
                        name:     $name,
                        value:    '',
                        expire:   1,  // epoch 1 = expirado
                        path:     '/',
                        domain:   null,
                        secure:   $isSecure,
                        httpOnly: true,
                        raw:      false,
                        sameSite: $sameSite,
                    )
                );
            }
        }

        return $response;
    }
}
