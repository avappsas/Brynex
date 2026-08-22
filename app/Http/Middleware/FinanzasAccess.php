<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinanzasAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Acceso exclusivo del dueño de finanzas: ver `config/finanzas.php`.
        // Si la config faltara, `!==` contra null rechaza a todo el mundo — que
        // es la dirección correcta en la que fallar para una puerta de acceso.
        if (!$user
            || $user->cedula !== config('finanzas.cedula_dueno')
            || !$user->hasRole('superadmin')
            || !$user->es_brynex) {
            abort(403, 'Acceso no autorizado al módulo de Finanzas.');
        }

        return $next($request);
    }
}
