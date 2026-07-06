<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\AppLiderAliado;
use App\Models\Finanzas\Entrada;
use App\Models\Finanzas\FuenteIngreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EntradaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra la cuadrícula de entradas por fuente y mes del año seleccionado.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $anio = $request->input('anio', now()->year);

        // Obtener fuentes activas del usuario
        $fuentes = FuenteIngreso::where('user_id', $user->id)
            ->activas()
            ->orderBy('orden')
            ->get();

        // Obtener entradas manuales de este año de la BD
        $entradasRaw = Entrada::where('user_id', $user->id)
            ->where('anio', $anio)
            ->get()
            ->groupBy(['fuente_id', 'mes']);

        // 1. Obtener intereses generados por mes para este año
        $intereses = \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->whereYear('fecha', $anio)
            ->where('tipo', 'interes_mensual')
            ->selectRaw("MONTH(fecha) as mes, SUM(monto) as total")
            ->groupByRaw("MONTH(fecha)")
            ->pluck('total', 'mes')
            ->toArray();

        // 2. Obtener utilidad mensual de OTRAS APP (tabla finanzas_app_lideres_pagos)
        $appLideresValores = \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_app_lideres_pagos')
            ->where('user_id', $user->id)
            ->where('anio', $anio)
            ->selectRaw("mes, SUM(monto) as total")
            ->groupBy("mes")
            ->pluck('total', 'mes')
            ->toArray();

        // 3. Obtener ingresos de Proyectos (entradas de flujo de caja de proyectos)
        $proyectos = \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_proyecto_movimientos')
            ->join('finanzas_proyectos', 'finanzas_proyecto_movimientos.proyecto_id', '=', 'finanzas_proyectos.id')
            ->where('finanzas_proyectos.user_id', $user->id)
            ->whereYear('finanzas_proyecto_movimientos.fecha', $anio)
            ->where('finanzas_proyecto_movimientos.tipo', 'entrada')
            ->selectRaw("MONTH(finanzas_proyecto_movimientos.fecha) as mes, SUM(finanzas_proyecto_movimientos.monto) as total")
            ->groupByRaw("MONTH(finanzas_proyecto_movimientos.fecha)")
            ->pluck('total', 'mes')
            ->toArray();

        // 4. Obtener ingresos esporádicos (transacciones registradas como ingreso_esporadico)
        $ingresosEsporadicos = \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_gastos')
            ->where('user_id', $user->id)
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->whereYear('fecha', $anio)
            ->selectRaw("MONTH(fecha) as mes, SUM(monto) as total")
            ->groupByRaw("MONTH(fecha)")
            ->pluck('total', 'mes')
            ->toArray();

        // 5. Obtener cobros a aliados de Brynex (tabla finanzas_brynex_pagos) (sin filtrar por user_id para consolidación global)
        $brynexPagosValores = \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_brynex_pagos')
            ->where('anio', $anio)
            ->selectRaw("mes, SUM(monto) as total")
            ->groupBy("mes")
            ->pluck('total', 'mes')
            ->toArray();

        // Mezclar las entradas reales con los montos dinámicos en memoria
        $entradas = $entradasRaw;

        foreach ($fuentes as $fuente) {
            $nombreUpper = strtoupper(trim($fuente->nombre));
            
            if ($nombreUpper === 'INTERESES PRESTAMOS') {
                $entradas->put($fuente->id, collect());
                for ($m = 1; $m <= 12; $m++) {
                    $total = $intereses[$m] ?? 0;
                    if ($total > 0) {
                        $entradaObj = new Entrada([
                            'fuente_id' => $fuente->id,
                            'anio' => $anio,
                            'mes' => $m,
                            'monto' => $total,
                            'observacion' => 'Calculado automáticamente de préstamos.',
                        ]);
                        $entradaObj->is_calculated = true;
                        
                        $entradas->get($fuente->id)->put($m, collect([$entradaObj]));
                    }
                }
            } elseif ($nombreUpper === 'OTRAS APP') {
                $entradas->put($fuente->id, collect());
                for ($m = 1; $m <= 12; $m++) {
                    $total = $appLideresValores[$m] ?? 0;
                    if ($total > 0) {
                        $entradaObj = new Entrada([
                            'fuente_id' => $fuente->id,
                            'anio' => $anio,
                            'mes' => $m,
                            'monto' => $total,
                            'observacion' => 'Consolidado automático de aliados.',
                        ]);
                        $entradaObj->is_calculated = true;
                        
                        $entradas->get($fuente->id)->put($m, collect([$entradaObj]));
                    }
                }
            } elseif ($nombreUpper === 'PROYECTOS') {
                $entradas->put($fuente->id, collect());
                for ($m = 1; $m <= 12; $m++) {
                    $total = $proyectos[$m] ?? 0;
                    if ($total > 0) {
                        $entradaObj = new Entrada([
                            'fuente_id' => $fuente->id,
                            'anio' => $anio,
                            'mes' => $m,
                            'monto' => $total,
                            'observacion' => 'Calculado automáticamente de flujos de proyectos.',
                        ]);
                        $entradaObj->is_calculated = true;
                        
                        $entradas->get($fuente->id)->put($m, collect([$entradaObj]));
                    }
                }
            } elseif ($nombreUpper === 'OTRAS ENTRADAS') {
                $entradas->put($fuente->id, collect());
                for ($m = 1; $m <= 12; $m++) {
                    $total = $ingresosEsporadicos[$m] ?? 0;
                    if ($total > 0) {
                        $entradaObj = new Entrada([
                            'fuente_id' => $fuente->id,
                            'anio' => $anio,
                            'mes' => $m,
                            'monto' => $total,
                            'observacion' => 'Calculado automáticamente de ingresos esporádicos.',
                        ]);
                        $entradaObj->is_calculated = true;
                        
                        $entradas->get($fuente->id)->put($m, collect([$entradaObj]));
                    }
                }
            } elseif ($nombreUpper === 'BRYNEX') {
                $entradas->put($fuente->id, collect());
                for ($m = 1; $m <= 12; $m++) {
                    $total = $brynexPagosValores[$m] ?? 0;
                    if ($total > 0) {
                        $entradaObj = new Entrada([
                            'fuente_id' => $fuente->id,
                            'anio' => $anio,
                            'mes' => $m,
                            'monto' => $total,
                            'observacion' => 'Consolidado automático de aliados.',
                        ]);
                        $entradaObj->is_calculated = true;
                        
                        $entradas->get($fuente->id)->put($m, collect([$entradaObj]));
                    }
                }
            }
        }

        $categorias = \App\Models\Finanzas\CategoriaGasto::where('user_id', $user->id)
            ->activas()
            ->orderBy('orden')
            ->get();

        return view('finanzas.entradas.index', compact('fuentes', 'entradas', 'anio', 'categorias'));
    }

    /**
     * Guarda o actualiza una entrada mensual (generalmente mediante AJAX).
     */
    public function store(Request $request)
    {
        $request->validate([
            'fuente_id' => 'required|integer',
            'anio' => 'required|integer',
            'mes' => 'required|integer|between:1,12',
            'monto' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        
        // Bloquear edición si es una fuente calculada dinámicamente
        $fuente = FuenteIngreso::where('user_id', $user->id)->findOrFail($request->fuente_id);
        $nombreUpper = strtoupper(trim($fuente->nombre));
        $calculadas = ['INTERESES PRESTAMOS', 'OTRAS APP', 'PROYECTOS', 'OTRAS ENTRADAS', 'BRYNEX'];
        
        if (in_array($nombreUpper, $calculadas)) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Esta fuente se calcula automáticamente y no puede editarse.'], 422);
            }
            return redirect()->route('finanzas.entradas.index', ['anio' => $request->anio])
                ->with('error', 'Esta fuente se calcula automáticamente y no puede editarse.');
        }

        $entrada = Entrada::updateOrCreate(
            [
                'fuente_id' => $request->fuente_id,
                'anio' => $request->anio,
                'mes' => $request->mes,
            ],
            [
                'user_id' => $user->id,
                'monto' => $request->monto,
                'observacion' => $request->observacion,
            ]
        );

        if ($request->ajax()) {
            return response()->json(['success' => true, 'monto' => $entrada->monto]);
        }

        return redirect()->route('finanzas.entradas.index', ['anio' => $request->anio])
            ->with('success', 'Entrada registrada correctamente.');
    }

    // ── CRUD de Fuentes de Ingreso ─────────────────────────

    public function fuentesIndex()
    {
        $fuentes = FuenteIngreso::where('user_id', Auth::id())
            ->orderBy('orden')
            ->get();

        return view('finanzas.entradas.fuentes.index', compact('fuentes'));
    }

    public function fuenteStore(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string|in:fijo,proyecto,esporadico',
            'descripcion' => 'nullable|string|max:500',
            'orden' => 'required|integer',
        ]);

        FuenteIngreso::create([
            'user_id' => Auth::id(),
            'nombre' => $request->nombre,
            'tipo' => $request->tipo,
            'descripcion' => $request->descripcion,
            'orden' => $request->orden,
            'activo' => true,
        ]);

        return redirect()->route('finanzas.fuentes.index')->with('success', 'Fuente de ingreso creada con éxito.');
    }

    public function fuenteUpdate(Request $request, $id)
    {
        $fuente = FuenteIngreso::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string|in:fijo,proyecto,esporadico',
            'descripcion' => 'nullable|string|max:500',
            'orden' => 'required|integer',
            'activo' => 'required|boolean',
        ]);

        $fuente->update($request->only('nombre', 'tipo', 'descripcion', 'orden', 'activo'));

        return redirect()->route('finanzas.fuentes.index')->with('success', 'Fuente de ingreso actualizada.');
    }

    public function fuenteDestroy($id)
    {
        $fuente = FuenteIngreso::where('user_id', Auth::id())->findOrFail($id);

        // Desactivar en lugar de borrar para proteger consistencia histórica
        $fuente->update(['activo' => false]);

        return redirect()->route('finanzas.fuentes.index')->with('success', 'Fuente de ingreso desactivada.');
    }

    // ── CRUD de Aliados de App Líderes ─────────────────────

    public function appLideresIndex()
    {
        $aliados = AppLiderAliado::where('user_id', Auth::id())
            ->orderBy('activo', 'desc')
            ->orderBy('nombre')
            ->get();

        return view('finanzas.entradas.app-lideres.index', compact('aliados'));
    }

    public function appLiderStore(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'valor_mensual' => 'required|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'observaciones' => 'nullable|string|max:500',
        ]);

        AppLiderAliado::create([
            'user_id' => Auth::id(),
            'nombre' => $request->nombre,
            'valor_mensual' => $request->valor_mensual,
            'fecha_inicio' => $request->fecha_inicio,
            'activo' => true,
            'observaciones' => $request->observaciones,
        ]);

        return redirect()->route('finanzas.app-lideres.index')->with('success', 'Aliado de App Líderes registrado.');
    }

    public function appLiderUpdate(Request $request, $id)
    {
        $aliado = AppLiderAliado::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'valor_mensual' => 'required|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date',
            'activo' => 'required|boolean',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $aliado->update($request->only('nombre', 'valor_mensual', 'fecha_inicio', 'fecha_fin', 'activo', 'observaciones'));

        return redirect()->route('finanzas.app-lideres.index')->with('success', 'Aliado de App Líderes actualizado.');
    }

    public function appLiderDestroy($id)
    {
        $aliado = AppLiderAliado::where('user_id', Auth::id())->findOrFail($id);
        $aliado->update([
            'activo' => false,
            'fecha_fin' => now()->toDateString()
        ]);

        return redirect()->route('finanzas.app-lideres.index')->with('success', 'Aliado de App Líderes desactivado.');
    }

    public function getDetalleEsporadico(Request $request)
    {
        $request->validate([
            'mes' => 'required|integer|between:1,12',
            'anio' => 'required|integer',
        ]);

        $user = Auth::id();
        $detalles = \App\Models\Finanzas\Gasto::with('categoria')
            ->where('user_id', $user)
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->whereYear('fecha', $request->anio)
            ->whereMonth('fecha', $request->mes)
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'fecha' => \Carbon\Carbon::parse($item->fecha)->format('d/m/Y'),
                    'fecha_raw' => \Carbon\Carbon::parse($item->fecha)->format('Y-m-d'),
                    'monto' => $item->monto,
                    'descripcion' => $item->descripcion,
                    'categoria_id' => $item->categoria_id,
                    'categoria_nombre' => $item->categoria ? $item->categoria->nombre : 'Sin categoría',
                ];
            });

        return response()->json($detalles);
    }

    /**
     * Actualiza un ingreso esporádico.
     */
    public function updateEsporadico(Request $request, $id)
    {
        $request->validate([
            'categoria_id' => 'required|integer',
            'fecha' => 'required|date',
            'monto' => 'required|numeric|min:1',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $gasto = \App\Models\Finanzas\Gasto::where('user_id', Auth::id())
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->findOrFail($id);

        $gasto->update([
            'categoria_id' => $request->categoria_id,
            'fecha' => $request->fecha,
            'monto' => $request->monto,
            'descripcion' => $request->descripcion,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Elimina un ingreso esporádico.
     */
    public function destroyEsporadico($id)
    {
        $gasto = \App\Models\Finanzas\Gasto::where('user_id', Auth::id())
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->findOrFail($id);

        $gasto->delete();

        return response()->json(['success' => true]);
    }
}
