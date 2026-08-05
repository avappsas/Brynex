<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Igual que {@see VerificarPermiso}, pero solo exige el permiso cuando la
 * petición ESCRIBE (POST, PUT, PATCH, DELETE). Los GET pasan derecho.
 *
 * Existe para poder blindar un módulo entero con dos líneas:
 *
 *     Route::prefix('admin/tareas')
 *          ->middleware(['permiso:tareas.ver', 'permiso.escritura:tareas.gestionar'])
 *
 * En vez de repetir el middleware en cada una de las 13 rutas del grupo y
 * arriesgarse a olvidar justo la que borra. Si mañana se agrega una ruta POST
 * al grupo, queda protegida sola.
 *
 * Ojo con la excepción del patrón: cuando una acción de escritura necesita un
 * permiso DISTINTO al general del módulo (anular una factura, eliminar un
 * cobro), esa ruta lleva además su propio `permiso:` — el más restrictivo de
 * los dos manda, porque ambos tienen que pasar.
 */
class VerificarPermisoEscritura extends VerificarPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$permisos);
    }
}
