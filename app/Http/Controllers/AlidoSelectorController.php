<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Aliado;

class AlidoSelectorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra las tarjetas de aliados disponibles para el usuario BryNex.
     */
    public function index()
    {
        $user = Auth::user();

        // Solo usuarios BryNex llegan aquí
        if (!$user->es_brynex) {
            return redirect()->route('dashboard');
        }

        // Aliado propio + aliados del pivot activos
        $aliados = Aliado::where('aliados.activo', true)
            ->where(function ($q) use ($user) {
                $q->where('aliados.id', $user->aliado_id)
                  ->orWhereHas('usuariosBrynex', fn($q2) =>
                        $q2->where('aliado_user.user_id', $user->id)
                           ->where('aliado_user.activo', true)
                  );
            })
            ->orderBy('nombre')
            ->get();

        return view('auth.selector-aliado', compact('aliados'));
    }

    /**
     * Selecciona un aliado y redirige al dashboard.
     */
    public function seleccionar(Request $request)
    {
        $request->validate(['aliado_id' => 'required|integer|exists:aliados,id']);

        $user    = Auth::user();
        $alidoId = (int) $request->aliado_id;

        if (!$user->puedeAccederAliado($alidoId)) {
            abort(403, 'No tiene acceso a este aliado.');
        }

        session(['aliado_id_activo' => $alidoId]);

        return redirect()->route('dashboard');
    }

    /**
     * Cambia de aliado desde cualquier pantalla (menú superior, solo BryNex).
     */
    public function cambiar(Request $request)
    {
        $request->validate(['aliado_id' => 'required|integer|exists:aliados,id']);

        $user    = Auth::user();
        $alidoId = (int) $request->aliado_id;

        if (!$user->es_brynex || !$user->puedeAccederAliado($alidoId)) {
            abort(403);
        }

        session(['aliado_id_activo' => $alidoId]);

        return redirect()->back()->with('success', 'Aliado cambiado correctamente.');
    }
}
