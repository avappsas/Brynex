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
    public function index(Request $request)
    {
        // Obtener el año de consulta, por defecto el actual
        $anioActual = (int) date('Y');
        $anio = $request->get('anio');

        // Determinar qué años tienen movimientos en total para llenar el select
        $aniosDisponibles = DB::connection('finanzas')
            ->table('finanzas_proyecto_movimientos')
            ->selectRaw('YEAR(fecha) as anio')
            ->distinct()
            ->orderBy('anio', 'desc')
            ->pluck('anio')
            ->toArray();

        if ($anio === null) {
            if (!empty($aniosDisponibles)) {
                $anio = in_array($anioActual, $aniosDisponibles) ? $anioActual : $aniosDisponibles[0];
            } else {
                $anio = $anioActual;
            }
        }

        $proyectos = Proyecto::where('user_id', Auth::id())
            ->orderBy('activo', 'desc')
            ->orderBy('nombre')
            ->get();

        // Para cada proyecto, cargaremos dinámicamente las entradas y salidas de ese año específico (o total)
        foreach ($proyectos as $proy) {
            $queryEntradas = $proy->movimientos()->where('tipo', 'ingreso');
            $querySalidas = $proy->movimientos()->where('tipo', 'egreso');

            if ($anio !== 'todos') {
                $queryEntradas->whereYear('fecha', (int) $anio);
                $querySalidas->whereYear('fecha', (int) $anio);
            }

            $proy->periodo_entradas = (float) $queryEntradas->sum('monto');
            $proy->periodo_salidas = (float) $querySalidas->sum('monto');
            $proy->periodo_balance = $proy->periodo_entradas - $proy->periodo_salidas;
        }

        return view('finanzas.proyectos.index', compact('proyectos', 'anio', 'aniosDisponibles'));
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
    public function show(Request $request, $id)
    {
        $proyecto = Proyecto::where('user_id', Auth::id())->findOrFail($id);

        // Años disponibles en los movimientos de este proyecto para el filtro
        $aniosDisponibles = $proyecto->movimientos()
            ->selectRaw('YEAR(fecha) as anio')
            ->distinct()
            ->orderBy('anio', 'desc')
            ->pluck('anio')
            ->toArray();

        // Determinar año seleccionado: si no se provee, por defecto el actual
        // o el más reciente si el actual no tiene datos.
        $anioActual = (int) date('Y');
        $anio = $request->get('anio');

        if ($anio === null) {
            if (!empty($aniosDisponibles)) {
                $anio = in_array($anioActual, $aniosDisponibles) ? $anioActual : $aniosDisponibles[0];
            } else {
                $anio = $anioActual;
            }
        }

        // Obtener movimientos ordenados cronológicamente
        $queryMovs = $proyecto->movimientos()
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc');

        if ($anio !== 'todos') {
            $queryMovs->whereYear('fecha', (int) $anio);
        }

        $movimientos = $queryMovs->get();

        // Calcular saldos acumulados e históricos del período
        $saldoAcumulado = 0;
        $totalEntradas = 0;
        $totalSalidas = 0;

        foreach ($movimientos as $mov) {
            if ($mov->tipo === 'ingreso' || $mov->tipo === 'entrada') {
                $totalEntradas += $mov->monto;
                $saldoAcumulado += $mov->monto;
            } else {
                $totalSalidas += $mov->monto;
                $saldoAcumulado -= $mov->monto;
            }
            $mov->saldo_acumulado = $saldoAcumulado;
        }

        $balancePeriodo = $totalEntradas - $totalSalidas;
        $cuentas = \App\Models\Finanzas\Cuenta::where('user_id', Auth::id())->activas()->orderBy('orden')->get();

        return view('finanzas.proyectos.show', compact(
            'proyecto',
            'cuentas',
            'movimientos',
            'anio',
            'aniosDisponibles',
            'totalEntradas',
            'totalSalidas',
            'balancePeriodo'
        ));
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
            'tipo' => 'required|string|in:entrada,salida,ingreso,egreso',
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
            'cuenta_id' => 'nullable|integer',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $tipo = $request->tipo;
        if ($tipo === 'entrada') {
            $tipo = 'ingreso';
        } elseif ($tipo === 'salida') {
            $tipo = 'egreso';
        }

        // Manejo del archivo de soporte
        $soportePath = null;
        if ($request->hasFile('soporte')) {
            $soportePath = $request->file('soporte')->store('finanzas/proyectos_soportes', 'local');
        }

        ProyectoMovimiento::create([
            'proyecto_id' => $proyecto->id,
            'tipo'        => $tipo,
            'monto'       => $request->monto,
            'fecha'       => $request->fecha,
            'observacion' => $request->observacion,
            'cuenta_id'   => $this->resolverCuenta($request->cuenta_id),
            'soporte_path'=> $soportePath,
        ]);

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($request->fecha)),
            (int) date('n', strtotime($request->fecha))
        );

        return redirect()->route('finanzas.proyectos.show', $proyecto->id)->with('success', 'Movimiento registrado con éxito.');
    }

    /**
     * Actualiza un movimiento de proyecto existente.
     */
    public function actualizarMovimiento(Request $request, $id)
    {
        $movimiento = ProyectoMovimiento::findOrFail($id);
        $proyecto = Proyecto::where('user_id', Auth::id())->findOrFail($movimiento->proyecto_id);

        $request->validate([
            'tipo' => 'required|string|in:entrada,salida,ingreso,egreso',
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
            'cuenta_id' => 'nullable|integer',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'eliminar_soporte' => 'nullable|boolean',
        ]);

        $tipo = $request->tipo;
        if ($tipo === 'entrada') {
            $tipo = 'ingreso';
        } elseif ($tipo === 'salida') {
            $tipo = 'egreso';
        }

        // Manejo del archivo de soporte
        $soportePath = $movimiento->soporte_path;

        if ($request->boolean('eliminar_soporte')) {
            if ($movimiento->soporte_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($movimiento->soporte_path);
            }
            $soportePath = null;
        }

        if ($request->hasFile('soporte')) {
            // Eliminar soporte anterior
            if ($movimiento->soporte_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($movimiento->soporte_path);
            }
            $soportePath = $request->file('soporte')->store('finanzas/proyectos_soportes', 'local');
        }

        $movimiento->update([
            'tipo'        => $tipo,
            'monto'       => $request->monto,
            'fecha'       => $request->fecha,
            'observacion' => $request->observacion,
            'cuenta_id'   => $this->resolverCuenta($request->cuenta_id),
            'soporte_path'=> $soportePath,
        ]);

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($request->fecha)),
            (int) date('n', strtotime($request->fecha))
        );

        return redirect()->route('finanzas.proyectos.show', $proyecto->id)->with('success', 'Movimiento actualizado con éxito.');
    }

    /**
     * Elimina (revierte) un movimiento del proyecto validando la propiedad del usuario.
     */
    public function eliminarMovimiento($id)
    {
        $movimiento = ProyectoMovimiento::findOrFail($id);
        $proyecto = Proyecto::where('user_id', Auth::id())->findOrFail($movimiento->proyecto_id);

        if ($movimiento->soporte_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($movimiento->soporte_path);
        }

        $movimiento->delete();
        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.proyectos.show', $proyecto->id)->with('success', 'Movimiento eliminado con éxito.');
    }

    /**
     * Descarga de forma segura el archivo de soporte de un movimiento de proyecto.
     */
    public function descargarSoporteMovimiento($id)
    {
        $movimiento = ProyectoMovimiento::findOrFail($id);
        // Validar propiedad a través del proyecto
        $proyecto = Proyecto::where('user_id', Auth::id())->findOrFail($movimiento->proyecto_id);

        if (!$movimiento->soporte_path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($movimiento->soporte_path)) {
            abort(404, 'Archivo de soporte no encontrado.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download($movimiento->soporte_path);
    }
}
