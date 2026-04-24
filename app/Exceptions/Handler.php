<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Intercepta el TokenMismatchException (error 419 CSRF) para que:
     *  - Si la petición es AJAX (X-Requested-With / Accept: application/json)
     *    → devuelve JSON con mensaje claro (el JS puede mostrarlo en un toast).
     *  - Si el usuario AUN está autenticado (petición web) → recarga la página
     *    con un aviso y los datos del formulario restaurados.
     *  - Si NO está autenticado → redirige al login con mensaje claro.
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof TokenMismatchException) {

            // ── Petición AJAX: responder JSON, nunca redirect ──────────────
            if ($request->ajax() || $request->expectsJson() || $request->hasHeader('X-Requested-With')) {
                return response()->json([
                    'ok'      => false,
                    'csrf'    => true,   // señal para el JS
                    'mensaje' => 'Sesión expirada (token CSRF). Recargue la página e intente de nuevo.',
                ], 419);
            }

            // ── Petición web: si sigue autenticado → back con aviso ────────
            if (auth()->check()) {
                return redirect()
                    ->back()
                    ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                    ->with('warning', 'El formulario fue recargado por seguridad (token expirado). Por favor verifique los datos y vuelva a guardar.');
            }

            // ── No autenticado → login con mensaje ─────────────────────────
            return redirect()->route('login')
                ->with('error', 'Tu sesión ha expirado. Por favor inicia sesión de nuevo.');
        }

        return parent::render($request, $e);
    }
}
