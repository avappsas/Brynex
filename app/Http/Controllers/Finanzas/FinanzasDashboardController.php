<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finanzas\Concerns\DetectaDispositivoMovil;
use App\Services\Finanzas\CriptoApiService;
use App\Services\Finanzas\FinanzasAlertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanzasDashboardController extends Controller
{
    use DetectaDispositivoMovil;

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

        // 6. Cuentas / bolsillos con su saldo actual
        $cuentas = \App\Models\Finanzas\Cuenta::conSaldos($user->id);

        // 7. Evolución mensual del año para las gráficas
        $evolucion = $this->alertaService->getEvolucionAnual($user->id, (int) $anio);

        // Si es dispositivo móvil, cargar la vista dedicada para celular
        if ($this->isMobileDevice($request)) {
            // Cargar transacciones de este mes para la pestaña Historial
            $transacciones = \App\Models\Finanzas\Gasto::with('categoria')
                ->where('user_id', $user->id)
                ->whereYear('fecha', $anio)
                ->whereMonth('fecha', $mes)
                ->orderBy('fecha', 'desc')
                ->get();

            // Cargar categorías y bienes para los modales bottom-sheet de celular
            $categorias = \App\Models\Finanzas\CategoriaGasto::where('user_id', $user->id)
                ->activas()
                ->orderBy('orden')
                ->get();

            $patrimonios = \App\Models\Finanzas\Patrimonio::where('user_id', $user->id)
                ->activos()
                ->get();

            return view('finanzas.dashboard_movil', compact(
                'resumen',
                'prestamosMora',
                'gastosFaltantes',
                'criptoPrecio',
                'consolidado',
                'cuentas',
                'evolucion',
                'anio',
                'mes',
                'transacciones',
                'categorias',
                'patrimonios'
            ));
        }

        return view('finanzas.dashboard', compact(
            'resumen',
            'prestamosMora',
            'gastosFaltantes',
            'criptoPrecio',
            'consolidado',
            'cuentas',
            'evolucion',
            'anio',
            'mes'
        ));
    }

}
