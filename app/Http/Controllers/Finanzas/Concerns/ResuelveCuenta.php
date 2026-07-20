<?php

namespace App\Http\Controllers\Finanzas\Concerns;

use App\Models\Finanzas\Cuenta;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve la cuenta/bolsillo de un movimiento de dinero:
 * valida que pertenezca al usuario y, si no se envió, usa la
 * primera cuenta activa (por orden) como cuenta por defecto.
 */
trait ResuelveCuenta
{
    protected function resolverCuenta($cuentaId): ?int
    {
        if ($cuentaId) {
            $cuenta = Cuenta::where('user_id', Auth::id())->find($cuentaId);
            if ($cuenta) {
                return $cuenta->id;
            }
        }

        $porDefecto = Cuenta::where('user_id', Auth::id())
            ->activas()
            ->orderBy('orden')
            ->first();

        return $porDefecto?->id;
    }
}
