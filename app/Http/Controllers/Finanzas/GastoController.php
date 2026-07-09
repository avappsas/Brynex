<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Patrimonio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GastoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra la lista de gastos del mes seleccionado con filtros y formulario rápido de registro.
     */
    public function index(Request $request)
    {
        $user = Auth::id();
        $anio = $request->input('anio', now()->year);
        $mes = $request->input('mes', now()->month);
        $categoriaId = $request->input('categoria_id');

        // Categorías para los selectores
        $categorias = CategoriaGasto::where('user_id', $user)->activas()->orderBy('orden')->get();

        // Bienes de patrimonio en caso de marcar el gasto como adquisición
        $patrimonios = Patrimonio::where('user_id', $user)->activos()->get();

        // Gastos filtrados del período (incluye ingresos esporádicos)
        $query = Gasto::with('categoria')
            ->where('user_id', $user)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes);

        if ($categoriaId) {
            $query->where('categoria_id', $categoriaId);
        }

        $gastos = $query->orderBy('fecha', 'desc')->get();
        
        // Egresos son gasto, prestamo e inversion
        $totalGastos = $gastos->where('tipo_movimiento', '!=', 'ingreso_esporadico')->sum('monto');
        $totalIngresos = $gastos->where('tipo_movimiento', 'ingreso_esporadico')->sum('monto');

        return view('finanzas.gastos.index', compact(
            'gastos',
            'categorias',
            'patrimonios',
            'totalGastos',
            'totalIngresos',
            'anio',
            'mes',
            'categoriaId'
        ));
    }

    /**
     * Registra un nuevo gasto o ingreso esporádico.
     */
    public function store(Request $request)
    {
        $request->validate([
            'categoria_id' => 'required_without:nueva_categoria|nullable|integer',
            'nueva_categoria' => 'required_without:categoria_id|nullable|string|max:50',
            'fecha' => 'required|date',
            'monto' => 'required|numeric|min:1',
            'descripcion' => 'nullable|string|max:255',
            'tipo_movimiento' => 'required|string|in:gasto,prestamo,inversion,ingreso_esporadico',
            'es_patrimonio' => 'nullable|boolean',
            'patrimonio_id' => 'nullable|integer',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $user = Auth::user();
        $categoriaId = $request->categoria_id;

        // Si viene nueva categoría, la creamos al vuelo
        if (empty($categoriaId) && $request->filled('nueva_categoria')) {
            $catExistente = CategoriaGasto::where('user_id', $user->id)
                ->where('nombre', trim($request->nueva_categoria))
                ->first();

            if ($catExistente) {
                $categoriaId = $catExistente->id;
            } else {
                $nuevaCat = CategoriaGasto::create([
                    'user_id' => $user->id,
                    'nombre' => trim($request->nueva_categoria),
                    'icono' => '📂',
                    'color' => '#64748b',
                    'es_recurrente' => false,
                    'activo' => true,
                    'orden' => 50,
                ]);
                $categoriaId = $nuevaCat->id;
            }
        }

        // Manejo del archivo de soporte
        $soportePath = null;
        if ($request->hasFile('soporte')) {
            $soportePath = $request->file('soporte')->store('finanzas/gastos_soportes', 'local');
        }

        Gasto::create([
            'user_id' => $user->id,
            'categoria_id' => $categoriaId,
            'fecha' => $request->fecha,
            'monto' => $request->monto,
            'descripcion' => $request->descripcion,
            'tipo_movimiento' => $request->tipo_movimiento,
            'es_patrimonio' => $request->has('es_patrimonio') ? (bool) $request->es_patrimonio : false,
            'patrimonio_id' => $request->patrimonio_id,
            'soporte_path' => $soportePath,
        ]);

        return redirect()->route('finanzas.gastos.index')->with('success', 'Transacción registrada con éxito.');
    }

    public function update(Request $request, $id)
    {
        $gasto = Gasto::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'categoria_id' => 'required_without:nueva_categoria|nullable|integer',
            'nueva_categoria' => 'required_without:categoria_id|nullable|string|max:50',
            'fecha' => 'required|date',
            'monto' => 'required|numeric|min:1',
            'descripcion' => 'nullable|string|max:255',
            'tipo_movimiento' => 'required|string|in:gasto,prestamo,inversion,ingreso_esporadico',
            'es_patrimonio' => 'nullable|boolean',
            'patrimonio_id' => 'nullable|integer',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'eliminar_soporte' => 'nullable|boolean',
        ]);

        $categoriaId = $request->categoria_id;

        // Si viene nueva categoría, la creamos al vuelo
        if (empty($categoriaId) && $request->filled('nueva_categoria')) {
            $catExistente = CategoriaGasto::where('user_id', Auth::id())
                ->where('nombre', trim($request->nueva_categoria))
                ->first();

            if ($catExistente) {
                $categoriaId = $catExistente->id;
            } else {
                $nuevaCat = CategoriaGasto::create([
                    'user_id' => Auth::id(),
                    'nombre' => trim($request->nueva_categoria),
                    'icono' => '📂',
                    'color' => '#64748b',
                    'es_recurrente' => false,
                    'activo' => true,
                    'orden' => 50,
                ]);
                $categoriaId = $nuevaCat->id;
            }
        }

        // Manejo del archivo de soporte
        $soportePath = $gasto->soporte_path;

        if ($request->boolean('eliminar_soporte')) {
            if ($gasto->soporte_path) {
                Storage::disk('local')->delete($gasto->soporte_path);
            }
            $soportePath = null;
        }

        if ($request->hasFile('soporte')) {
            // Eliminar soporte anterior
            if ($gasto->soporte_path) {
                Storage::disk('local')->delete($gasto->soporte_path);
            }
            $soportePath = $request->file('soporte')->store('finanzas/gastos_soportes', 'local');
        }

        $gasto->update([
            'categoria_id' => $categoriaId,
            'fecha' => $request->fecha,
            'monto' => $request->monto,
            'descripcion' => $request->descripcion,
            'tipo_movimiento' => $request->tipo_movimiento,
            'es_patrimonio' => $request->has('es_patrimonio') ? (bool) $request->es_patrimonio : false,
            'patrimonio_id' => $request->patrimonio_id,
            'soporte_path' => $soportePath,
        ]);

        return redirect()->route('finanzas.gastos.index')->with('success', 'Transacción actualizada.');
    }

    public function destroy($id)
    {
        $gasto = Gasto::where('user_id', Auth::id())->findOrFail($id);

        if ($gasto->soporte_path) {
            Storage::disk('local')->delete($gasto->soporte_path);
        }

        $gasto->delete();

        return redirect()->route('finanzas.gastos.index')->with('success', 'Gasto eliminado.');
    }

    /**
     * Descarga de forma segura el archivo de soporte de un gasto.
     */
    public function descargarSoporte($id)
    {
        $gasto = Gasto::where('user_id', Auth::id())->findOrFail($id);

        if (!$gasto->soporte_path || !Storage::disk('local')->exists($gasto->soporte_path)) {
            abort(404, 'Archivo de soporte no encontrado.');
        }

        return Storage::disk('local')->download($gasto->soporte_path);
    }

    /**
     * Genera la tabla cruzada de Informe de Gastos (categorías × meses del año).
     */
    public function informe(Request $request)
    {
        $user = Auth::id();
        $anio = $request->input('anio', now()->year);

        // Obtener categorías de este usuario
        $categorias = CategoriaGasto::where('user_id', $user)
            ->activas()
            ->orderBy('orden')
            ->get();

        // Obtener la matriz de gastos agrupados por categoría y mes
        // Ejecutamos en la conexión de finanzas
        $gastosPeriodo = DB::connection('finanzas')
            ->table('finanzas_gastos')
            ->select('categoria_id', DB::raw('MONTH(fecha) as mes'), DB::raw('SUM(monto) as total'))
            ->where('user_id', $user)
            ->where('tipo_movimiento', 'gasto')
            ->whereYear('fecha', $anio)
            ->groupBy('categoria_id', DB::raw('MONTH(fecha)'))
            ->get()
            ->groupBy(['categoria_id', 'mes']);

        return view('finanzas.gastos.informe', compact('categorias', 'gastosPeriodo', 'anio'));
    }

    // ── CRUD de Categorías de Gasto ────────────────────────

    public function categoriasIndex()
    {
        $categorias = CategoriaGasto::where('user_id', Auth::id())
            ->orderBy('orden')
            ->get();

        return view('finanzas.gastos.categorias.index', compact('categorias'));
    }

    public function categoriaStore(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:50',
            'icono' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:10',
            'es_recurrente' => 'required|boolean',
            'orden' => 'required|integer',
        ]);

        CategoriaGasto::create([
            'user_id' => Auth::id(),
            'nombre' => $request->nombre,
            'icono' => $request->icono,
            'color' => $request->color,
            'es_recurrente' => $request->es_recurrente,
            'activo' => true,
            'orden' => $request->orden,
        ]);

        return redirect()->route('finanzas.categorias.index')->with('success', 'Categoría de gasto creada.');
    }

    public function categoriaUpdate(Request $request, $id)
    {
        $categoria = CategoriaGasto::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:50',
            'icono' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:10',
            'es_recurrente' => 'required|boolean',
            'orden' => 'required|integer',
            'activo' => 'required|boolean',
        ]);

        $categoria->update($request->only('nombre', 'icono', 'color', 'es_recurrente', 'orden', 'activo'));

        return redirect()->route('finanzas.categorias.index')->with('success', 'Categoría actualizada.');
    }

    public function categoriaDestroy($id)
    {
        $categoria = CategoriaGasto::where('user_id', Auth::id())->findOrFail($id);
        $categoria->update(['activo' => false]);

        return redirect()->route('finanzas.categorias.index')->with('success', 'Categoría desactivada.');
    }
}
