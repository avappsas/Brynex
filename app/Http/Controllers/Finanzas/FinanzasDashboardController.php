<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Services\Finanzas\CriptoApiService;
use App\Services\Finanzas\FinanzasAlertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanzasDashboardController extends Controller
{
    protected FinanzasAlertaService $alertaService;
    protected CriptoApiService $criptoService;

    public function __construct(FinanzasAlertaService $alertaService, CriptoApiService $criptoService)
    {
        $this->middleware('auth');
        $this->alertaService = $alertaService;
        $this->criptoService = $criptoService;
    }

    /**
     * Muestra el dashboard principal de Finanzas Personales.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $anio = $request->input('anio', now()->year);
        $mes = $request->input('mes', now()->month);

        // 1. Resumen de saldos, entradas y salidas del mes
        $resumen = $this->alertaService->getResumenMensual($user->id, $anio, $mes);

        // 2. Alertas de deudores en mora
        $prestamosMora = $this->alertaService->getPrestamosEnMora($user->id);

        // 3. Gastos recurrentes que hacen falta registrar este mes
        $gastosFaltantes = $this->alertaService->getGastosRecurrentesPendientes($user->id, $anio, $mes);

        // 4. Precio actual del USDT (CoinGecko)
        $criptoPrecio = $this->criptoService->getPrecioUsdt();

        // 5. Consolidado global e histórico
        $consolidado = $this->alertaService->getConsolidadoGlobal($user->id);

        return view('finanzas.dashboard', compact(
            'resumen',
            'prestamosMora',
            'gastosFaltantes',
            'criptoPrecio',
            'consolidado',
            'anio',
            'mes'
        ));
    }
}
