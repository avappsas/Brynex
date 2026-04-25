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

        // Superadmin BryNex → todos los aliados activos
        // BryNex regular    → solo los del pivot aliado_user
        // Otros             → solo su propio aliado
        if ($user->es_brynex && $user->hasRole('superadmin')) {
            $aliados = Aliado::where('activo', true)->orderBy('nombre')->get();
        } elseif ($user->es_brynex) {
            $propios  = Aliado::where('activo', true)->where('id', $user->aliado_id)->get();
            $pivotIds = $user->aliados()->where('aliados.activo', true)->wherePivot('activo', true)->pluck('aliados.id');
            $aliados  = Aliado::where('activo', true)->whereIn('id', $pivotIds->push($user->aliado_id))->orderBy('nombre')->get();
        } else {
            $aliados = Aliado::where('activo', true)->where('id', $user->aliado_id)->get();
        }

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
