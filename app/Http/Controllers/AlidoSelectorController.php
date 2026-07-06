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
        $today = now()->toDateString();

        $queryActivos = Aliado::where('activo', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('brynex_fecha_fin')
                  ->orWhere('brynex_fecha_fin', '>=', $today);
            });

        if ($user->es_brynex && $user->hasRole('superadmin')) {
            $aliados = $queryActivos->orderBy('nombre')->get();
        } elseif ($user->es_brynex) {
            $pivotIds = $user->aliados()->where('aliados.activo', true)->wherePivot('activo', true)->pluck('aliados.id');
            $aliados  = $queryActivos->whereIn('id', $pivotIds->push($user->aliado_id))->orderBy('nombre')->get();
        } else {
            $aliados = $queryActivos->where('id', $user->aliado_id)->get();
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
