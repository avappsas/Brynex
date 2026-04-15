<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CajaMenor, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CajaMenorController extends Controller
{
    public function index()
    {
        $aliadoId = session('aliado_id_activo');

        $usuarios = User::where('aliado_id', $aliadoId)->orderBy('nombre')->get();

        $asignaciones = CajaMenor::where('aliado_id', $aliadoId)
            ->with(['usuario', 'asignadoPor'])
            ->orderByDesc('fecha')
            ->get();

        return view('admin.caja-menor.index', compact('usuarios', 'asignaciones'));
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            abort(403);
        }

        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'usuario_id'  => 'required|integer',
            'monto'       => 'required|integer|min:0',
            'fecha'       => 'required|date',
            'observacion' => 'nullable|string|max:500',
        ]);

        // Desactivar asignación anterior activa del mismo usuario
        CajaMenor::where('aliado_id', $aliadoId)
            ->where('usuario_id', $validated['usuario_id'])
            ->where('activo', true)
            ->update(['activo' => false]);

        CajaMenor::create(array_merge($validated, [
            'aliado_id'    => $aliadoId,
            'asignado_por' => Auth::id(),
            'activo'       => true,
        ]));

        return back()->with('success', 'Caja menor asignada correctamente.');
    }
}
