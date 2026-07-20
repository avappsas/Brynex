<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Proyecto;
use App\Models\Finanzas\ProyectoMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProyectoController extends Controller
{
    use \App\Http\Controllers\Finanzas\Concerns\ResuelveCuenta;
    use \App\Http\Controllers\Finanzas\Concerns\InvalidaFinanzasCache;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra la lista de proyectos con sus balances consolidados (Entradas - Salidas).
     */
    public function index()
    {
        $proyectos = Proyecto::where('user_id', Auth::id())
            ->orderBy('activo', 'desc')
            ->orderBy('nombre')
            ->get();

        return view('finanzas.proyectos.index', compact('proyectos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
        ]);

        Proyecto::create([
            'user_id'     => Auth::id(),
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'activo'      => true,
        ]);

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.proyectos.index')->with('success', 'Proyecto creado con éxito.');
    }

    /**
     * Muestra el detalle del proyecto con todo su historial de movimientos.
     */
    public function show($id)
    {
        $proyecto = Proyecto::where('user_id', Auth::id())
            ->with(['movimientos' => function ($q) {
                $q->orderBy('fecha', 'desc')->orderBy('id', 'desc');
            }])
            ->findOrFail($id);

        $cuentas = \App\Models\Finanzas\Cuenta::where('user_id', Auth::id())->activas()->orderBy('orden')->get();

        return view('finanzas.proyectos.show', compact('proyecto', 'cuentas'));
    }

    public function update(Request $request, $id)
    {
        $proyecto = Proyecto::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'required|boolean',
        ]);

        $proyecto->update($request->only('nombre', 'descripcion', 'activo'));
        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.proyectos.show', $proyecto->id)->with('success', 'Proyecto actualizado.');
    }

    /**
     * Registra un movimiento (entrada o salida) dentro de un proyecto específico.
     */
    public function agregarMovimiento(Request $request, $id)
    {
        $proyecto = Proyecto::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'tipo' => 'required|string|in:entrada,salida',
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
            'cuenta_id' => 'nullable|integer',
        ]);

        ProyectoMovimiento::create([
            'proyecto_id' => $proyecto->id,
            'tipo'        => $request->tipo,
            'monto'       => $request->monto,
            'fecha'       => $request->fecha,
            'observacion' => $request->observacion,
            'cuenta_id'   => $this->resolverCuenta($request->cuenta_id),
        ]);

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($request->fecha)),
            (int) date('n', strtotime($request->fecha))
        );

        return redirect()->route('finanzas.proyectos.show', $proyecto->id)->with('success', 'Movimiento registrado con éxito.');
    }

    /**
     * Elimina (revierte) un movimiento del proyecto validando la propiedad del usuario.
     */
    public function eliminarMovimiento($id)
    {
        $movimiento = ProyectoMovimiento::findOrFail($id);
        $proyecto = Proyecto::where('user_id', Auth::id())->findOrFail($movimiento->proyecto_id);

        $movimiento->delete();
        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.proyectos.show', $proyecto->id)->with('success', 'Movimiento eliminado con éxito.');
    }
}
