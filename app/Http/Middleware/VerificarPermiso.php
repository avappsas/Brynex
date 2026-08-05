<?php

namespace App\Http\Middleware;

use App\Models\Bitacora;
use App\Services\PermisoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de permisos por módulo: `->middleware('permiso:facturacion.anular')`
 *
 * Acepta varios separados por `|` (basta con tener uno):
 *   ->middleware('permiso:cobros.ver|cobros.registrar')
 *
 * Diferencias con el `permission:` de Spatie, que es la razón de existir de
 * esta clase:
 *
 *  1. **Mensaje claro.** Spatie tira un 403 seco. Aquí se dice qué permiso
 *     falta, de qué módulo, y a quién pedírselo.
 *  2. **Queda registrado.** Cada bloqueo entra a la bitácora, así se ve al día
 *     siguiente a quién le faltó algo en vez de esperar la llamada.
 *  3. **AJAX.** Devuelve JSON en peticiones asíncronas, que el panel usa mucho.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Los permisos pueden venir como 'a|b' o como argumentos sueltos
        $requeridos = collect($permisos)
            ->flatMap(fn ($p) => explode('|', $p))
            ->filter()
            ->values();

        foreach ($requeridos as $permiso) {
            if ($user->can($permiso)) {
                return $next($request);
            }
        }

        return $this->denegar($request, $requeridos->all());
    }

    private function denegar(Request $request, array $requeridos): Response
    {
        $descripcion = collect($requeridos)
            ->map(fn ($p) => PermisoService::describir($p))
            ->join(' o ');

        $mensaje = "No tienes permiso para «{$descripcion}». "
                 .'Pídeselo a un administrador o al superadministrador de tu empresa.';

        Bitacora::registrar(
            'acceso_denegado',
            'Permiso',
            null,
            "Intento de acceso sin permiso: {$request->path()}",
            [
                'permisos_requeridos' => $requeridos,
                'ruta' => $request->route()?->getName(),
                'metodo' => $request->method(),
            ]
        );

        if ($request->ajax() || $request->expectsJson() || $request->hasHeader('X-Requested-With')) {
            return response()->json([
                'ok' => false,
                'mensaje' => $mensaje,
            ], 403);
        }

        abort(403, $mensaje);
    }
}
