<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Inversion;
use App\Models\Finanzas\InversionMovimiento;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\CategoriaGasto;
use App\Services\Finanzas\CriptoApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InversionController extends Controller
{
    use \App\Http\Controllers\Finanzas\Concerns\ResuelveCuenta;
    use \App\Http\Controllers\Finanzas\Concerns\InvalidaFinanzasCache;

    protected CriptoApiService $criptoService;

    public function __construct(CriptoApiService $criptoService)
    {
        $this->middleware('auth');
        $this->criptoService = $criptoService;
    }

    /**
     * Muestra la lista de inversiones con las cotizaciones en tiempo real y ganancia/pérdida.
     */
    public function index()
    {
        $user = Auth::id();
        $inversiones = Inversion::where('user_id', $user)->get();

        // Obtener cotización actual del USDT
        $precioUsdtData = $this->criptoService->getPrecioUsdt();
        $precioUsdtCop = $precioUsdtData['precio_cop'];

        // Recalcular valor actual estimado en COP para inversiones en USDT/Cripto
        $valorTotalInvertido = 0.00;
        $valorTotalActual = 0.00;

        foreach ($inversiones as $inversion) {
            if ($inversion->tipo === 'cripto' && $inversion->cantidad_tokens > 0) {
                // Estimamos valor actual = cantidad_tokens * precio_usdt_cop
                $inversion->valor_actual_cop = $inversion->cantidad_tokens * $precioUsdtCop;
                $inversion->save();
            }

            $valorTotalInvertido += $inversion->monto_invertido_cop;
            $valorTotalActual += $inversion->valor_actual_cop ?? $inversion->monto_invertido_cop;
        }

        $balanceNeta = $valorTotalActual - $valorTotalInvertido;

        $cuentas = \App\Models\Finanzas\Cuenta::where('user_id', $user)->activas()->orderBy('orden')->get();

        return view('finanzas.inversiones.index', compact(
            'inversiones',
            'precioUsdtData',
            'valorTotalInvertido',
            'valorTotalActual',
            'balanceNeta',
            'cuentas'
        ));
    }

    /**
     * Registra una nueva inversión.
     * Crea el registro en finanzas_inversiones y crea un gasto de tipo 'inversion' en la tabla de gastos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string|in:cripto,trading,otro',
            'monto_invertido_cop' => 'required|numeric|min:1',
            'cantidad_tokens' => 'nullable|numeric|min:0',
            'precio_token_cop' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'fecha' => 'required|date',
            'cuenta_id' => 'nullable|integer',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($request, $user) {
            $inversion = Inversion::create([
                'user_id' => $user->id,
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'monto_invertido_cop' => $request->monto_invertido_cop,
                'cantidad_tokens' => $request->cantidad_tokens ?: null,
                'precio_compra_promedio' => $request->precio_token_cop ?: null,
                'valor_actual_cop' => $request->monto_invertido_cop, // Inicialmente vale lo mismo
                'activo' => true,
                'observaciones' => $request->observaciones,
            ]);

            // Registrar movimiento inicial
            InversionMovimiento::create([
                'inversion_id' => $inversion->id,
                'tipo' => 'compra',
                'fecha' => $request->fecha,
                'monto_cop' => $request->monto_invertido_cop,
                'cantidad_tokens' => $request->cantidad_tokens ?: null,
                'precio_token_cop' => $request->precio_token_cop ?: null,
                'observacion' => 'Compra inicial de inversión.',
            ]);

            // Registrar la salida en gastos
            $categoriaOtros = CategoriaGasto::where('user_id', $user->id)->where('nombre', 'Otros')->first();
            $catId = $categoriaOtros ? $categoriaOtros->id : 1;

            Gasto::create([
                'user_id' => $user->id,
                'categoria_id' => $catId,
                'cuenta_id' => $this->resolverCuenta($request->cuenta_id),
                'fecha' => $request->fecha,
                'monto' => $request->monto_invertido_cop,
                'descripcion' => "Inversión registrada: {$request->nombre}",
                'tipo_movimiento' => 'inversion',
                'es_patrimonio' => false,
                'patrimonio_id' => null,
            ]);
        });

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($request->fecha)),
            (int) date('n', strtotime($request->fecha))
        );

        return redirect()->route('finanzas.inversiones.index')->with('success', 'Inversión registrada con éxito.');
    }

    public function update(Request $request, $id)
    {
        $inversion = Inversion::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string|in:cripto,trading,otro',
            'monto_invertido_cop' => 'required|numeric|min:0',
            'cantidad_tokens' => 'nullable|numeric|min:0',
            'precio_compra_promedio' => 'nullable|numeric|min:0',
            'valor_actual_cop' => 'nullable|numeric|min:0',
            'activo' => 'required|boolean',
            'observaciones' => 'nullable|string',
        ]);

        $inversion->update($request->only('nombre', 'tipo', 'monto_invertido_cop', 'cantidad_tokens', 'precio_compra_promedio', 'valor_actual_cop', 'activo', 'observaciones'));

        // Recalcular valor actual si es cripto
        if ($inversion->tipo === 'cripto' && $inversion->cantidad_tokens > 0) {
            $precioUsdtData = $this->criptoService->getPrecioUsdt();
            $inversion->valor_actual_cop = $inversion->cantidad_tokens * $precioUsdtData['precio_cop'];
            $inversion->save();
        }

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.inversiones.index')->with('success', 'Inversión actualizada.');
    }

    public function destroy($id)
    {
        $inversion = Inversion::where('user_id', Auth::id())->findOrFail($id);

        DB::transaction(function () use ($inversion) {
            // Eliminar movimientos asociados
            $inversion->movimientos()->delete();
            $inversion->delete();
        });

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.inversiones.index')->with('success', 'Inversión eliminada.');
    }

    /**
     * AJAX endpoint para refrescar el precio de USDT.
     */
    public function precioUsdt()
    {
        return response()->json($this->criptoService->getPrecioUsdt());
    }
}
