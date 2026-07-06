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

        // Acceso exclusivo para el usuario Brayan García (cédula 1143944458), superadmin de brynex.
        if (!$user 
            || $user->cedula !== '1143944458' 
            || !$user->hasRole('superadmin') 
            || !$user->es_brynex) {
            abort(403, 'Acceso no autorizado al módulo de Finanzas.');
        }

        return $next($request);
    }
}
