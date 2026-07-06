<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Patrimonio;
use App\Models\Finanzas\PatrimonioGasto;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\CategoriaGasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PatrimonioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Lista todos los bienes de patrimonio con sus valores de compra y actual acumulados.
     */
    public function index()
    {
        $patrimonios = Patrimonio::where('user_id', Auth::id())
            ->orderBy('activo', 'desc')
            ->orderBy('fecha_adquisicion', 'desc')
            ->get();

        $valorTotalPatrimonio = $patrimonios->where('activo', true)->sum('valor_compra');
        $valorTotalActual = $patrimonios->where('activo', true)->sum(function ($item) {
            return $item->valor_actual ?? $item->valor_compra;
        });

        return view('finanzas.patrimonio.index', compact('patrimonios', 'valorTotalPatrimonio', 'valorTotalActual'));
    }

    /**
     * Registra un bien patrimonial.
     * Si el usuario marca que el bien ya se compró y desea registrar la salida de dinero,
     * podemos opcionalmente ligar esto con un gasto existente o crear uno nuevo.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'categoria' => 'required|string|in:inmueble,vehiculo,electronico,joya,otro',
            'valor_compra' => 'required|numeric|min:1',
            'fecha_adquisicion' => 'required|date',
            'valor_actual' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'registrar_gasto' => 'nullable|boolean', // Si desea crear la salida en gastos automáticamente
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($request, $user) {
            $patrimonio = Patrimonio::create([
                'user_id' => $user->id,
                'nombre' => $request->nombre,
                'categoria' => $request->categoria,
                'valor_compra' => $request->valor_compra,
                'fecha_adquisicion' => $request->fecha_adquisicion,
                'valor_actual' => $request->valor_actual ?: $request->valor_compra,
                'activo' => true,
                'observaciones' => $request->observaciones,
            ]);

            // Si desea registrar automáticamente como gasto del mes
            if ($request->has('registrar_gasto') && $request->registrar_gasto) {
                // Obtener o crear categoría correspondiente
                $categoriaNombre = match ($request->categoria) {
                    'inmueble' => 'Apartamento',
                    'vehiculo' => 'empresa', // o gasolina/impuestos
                    default => 'Otros',
                };

                $categoria = CategoriaGasto::where('user_id', $user->id)
                    ->where('nombre', $categoriaNombre)
                    ->first();
                $catId = $categoria ? $categoria->id : 1;

                Gasto::create([
                    'user_id' => $user->id,
                    'categoria_id' => $catId,
                    'fecha' => $request->fecha_adquisicion,
                    'monto' => $request->valor_compra,
                    'descripcion' => "Adquisición de patrimonio: {$request->nombre}",
                    'tipo_movimiento' => 'gasto',
                    'es_patrimonio' => true,
                    'patrimonio_id' => $patrimonio->id,
                ]);
            }
        });

        return redirect()->route('finanzas.patrimonio.index')->with('success', 'Bien patrimonial registrado con éxito.');
    }

    /**
     * Muestra la ficha técnica/detalle de un bien patrimonial con sus gastos asociados.
     */
    public function show($id)
    {
        $patrimonio = Patrimonio::where('user_id', Auth::id())
            ->with(['gastos' => function ($q) {
                $q->orderBy('fecha', 'desc');
            }])
            ->findOrFail($id);

        return view('finanzas.patrimonio.show', compact('patrimonio'));
    }

    public function update(Request $request, $id)
    {
        $patrimonio = Patrimonio::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'categoria' => 'required|string|in:inmueble,vehiculo,electronico,joya,otro',
            'valor_compra' => 'required|numeric|min:1',
            'fecha_adquisicion' => 'required|date',
            'valor_actual' => 'nullable|numeric|min:0',
            'activo' => 'required|boolean',
            'observaciones' => 'nullable|string',
        ]);

        $patrimonio->update($request->only('nombre', 'categoria', 'valor_compra', 'fecha_adquisicion', 'valor_actual', 'activo', 'observaciones'));

        return redirect()->route('finanzas.patrimonio.show', $patrimonio->id)->with('success', 'Patrimonio actualizado con éxito.');
    }

    /**
     * Registra un gasto asociado al bien (ej. Impuestos, SOAT, llantas, reparaciones).
     * Esto crea el gasto en patrimonio_gastos y también en la tabla general de gastos.
     */
    public function agregarGasto(Request $request, $id)
    {
        $patrimonio = Patrimonio::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'concepto' => 'required|string|max:100',
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($request, $patrimonio, $user) {
            // 1. Guardar en gastos de patrimonio
            PatrimonioGasto::create([
                'patrimonio_id' => $patrimonio->id,
                'concepto' => $request->concepto,
                'monto' => $request->monto,
                'fecha' => $request->fecha,
                'observacion' => $request->observacion,
            ]);

            // 2. Mapear a la categoría de gasto "IMPUESTOS" o similar en la general
            $catNombre = str_contains(strtolower($request->concepto), 'impuesto') || str_contains(strtolower($request->concepto), 'predial')
                ? 'Impuestos'
                : 'Otros';

            $categoria = CategoriaGasto::where('user_id', $user->id)
                ->where('nombre', $catNombre)
                ->first();
            $catId = $categoria ? $categoria->id : 1;

            Gasto::create([
                'user_id' => $user->id,
                'categoria_id' => $catId,
                'fecha' => $request->fecha,
                'monto' => $request->monto,
                'descripcion' => "Gasto de patrimonio ({$patrimonio->nombre}): {$request->concepto}",
                'tipo_movimiento' => 'gasto',
                'es_patrimonio' => false,
                'patrimonio_id' => $patrimonio->id,
            ]);
        });

        return redirect()->route('finanzas.patrimonio.show', $patrimonio->id)->with('success', 'Gasto de patrimonio registrado.');
    }
}
