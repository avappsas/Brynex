<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'cedula'   => 'required|string',
            'password' => 'required|string',
        ], [
            'cedula.required'   => 'La cédula es obligatoria.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        // Buscar usuario por cédula
        $user = User::where('cedula', $request->cedula)
                    ->where('activo', true)
                    ->first();

        if (!$user || !password_verify($request->password, $user->password)) {
            return back()->withErrors([
                'cedula' => 'Cédula o contraseña incorrectos.',
            ])->withInput(['cedula' => $request->cedula]);
        }

        Auth::login($user, $request->boolean('remember'));

        // Limpiar sesión de aliado anterior
        session()->forget('aliado_id_activo');

        // Usuario BryNex con acceso a múltiples aliados → selector
        if ($user->es_brynex && $user->aliados()->where('aliados.activo', true)->wherePivot('activo', true)->exists()) {
            return redirect()->route('aliado.selector');
        }

        // Usuario normal → dashboard directo (el middleware SetAlidoContext pondrá su aliado)
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
