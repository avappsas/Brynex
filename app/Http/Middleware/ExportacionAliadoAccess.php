<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quién puede sacar los datos de un aliado de la plataforma.
 *
 * Tres condiciones a la vez: estar en la lista blanca de `config/exportacion`,
 * ser `es_brynex` y tener el rol `superadmin`. La cuarta — el código por
 * WhatsApp — la exige el controlador, porque es por solicitud y no por sesión.
 *
 * La lista blanca no es un permiso de Spatie a propósito: un permiso lo puede
 * otorgar cualquiera que administre permisos, y esto saca los datos personales
 * de miles de personas. Para sumar a alguien hay que desplegar. Eso también
 * deja fuera a la cuenta genérica admin@brynex.co, que hoy es superadmin.
 */
class ExportacionAliadoAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $autorizados = array_map('strtolower', (array) config('exportacion.correos_autorizados'));

        if (! $user
            || ! $user->es_brynex
            || ! $user->hasRole('superadmin')
            || ! in_array(strtolower((string) $user->email), $autorizados, true)) {
            abort(403, 'Esta sección no está disponible para su usuario.');
        }

        return $next($request);
    }
}
