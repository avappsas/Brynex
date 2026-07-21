<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finanzas\Concerns\DetectaDispositivoMovil;
use App\Services\Finanzas\CriptoApiService;
use App\Services\Finanzas\FinanzasAlertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


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

    // ─────────────────────────────────────────────────────────────
    //  INDEX / SHELL
    // ─────────────────────────────────────────────────────────────

    /**
     * Muestra el dashboard de Finanzas Personales.
     *
     * - Escritorio (PC): Carga Shell-First inmediato. El HTML se devuelve sin queries,
     *   y los datos se cargan asíncronamente por AJAX.
     * - Móvil (Celular): Carga directa con datos síncronos (el caché interno de cada
     *   servicio amortigua la latencia). Se eliminó el flujo de "pantalla cargando +
     *   exec() artisan en background" porque el exec() fallaba silenciosamente en
     *   producción con PHP-FPM, dejando el usuario atrapado indefinidamente.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $anio = (int) $request->input('anio', now()->year);
        $mes  = (int) $request->input('mes', now()->month);

        // Precio cripto (caché rápido, siempre disponible)
        $criptoPrecio = $this->criptoService->getPrecioUsdt();

        // Para modales de registro rápido (queries ligeras)
        $categorias = \App\Models\Finanzas\CategoriaGasto::where('user_id', $user->id)
            ->activas()->orderBy('orden')->get();
        $patrimonios = \App\Models\Finanzas\Patrimonio::where('user_id', $user->id)
            ->activos()->get();

        // Dispositivo móvil: carga directa (los servicios usan Cache::remember internamente)
        if ($this->isMobileDevice($request)) {
            $resumen         = $this->alertaService->getResumenMensual($user->id, $anio, $mes);
            $prestamosMora   = $this->alertaService->getPrestamosEnMora($user->id);
            $gastosFaltantes = $this->alertaService->getGastosRecurrentesPendientes($user->id, $anio, $mes);
            $consolidado     = $this->alertaService->getConsolidadoGlobal($user->id);
            $cuentas         = \App\Models\Finanzas\Cuenta::conSaldos($user->id);
            $evolucion       = $this->alertaService->getEvolucionAnual($user->id, $anio);

            $transacciones = \App\Models\Finanzas\Gasto::with('categoria')
                ->where('user_id', $user->id)
                ->whereYear('fecha', $anio)
                ->whereMonth('fecha', $mes)
                ->orderBy('fecha', 'desc')
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

        // Escritorio: Shell-First inmediato sin queries pesadas
        return view('finanzas.dashboard', compact(
            'anio', 'mes', 'criptoPrecio', 'categorias', 'patrimonios'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    //  ENDPOINTS AJAX
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /finanzas/api/resumen
     */
    public function apiResumen(Request $request): JsonResponse
    {
        $user = Auth::user();
        $anio = (int) $request->input('anio', now()->year);
        $mes  = (int) $request->input('mes', now()->month);

        $resumen = $this->alertaService->getResumenMensual($user->id, $anio, $mes);

        $gastosCategoria = \App\Models\Finanzas\Gasto::with('categoria')
            ->select('categoria_id', DB::raw('SUM(monto) as total'))
            ->where('user_id', $user->id)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('tipo_movimiento', 'gasto')
            ->groupBy('categoria_id')
            ->get()
            ->map(fn ($g) => [
                'total'    => (float) $g->total,
                'nombre'   => $g->categoria?->nombre ?? 'Sin categoría',
                'color'    => $g->categoria?->color  ?? '#64748b',
            ]);

        return response()->json([
            'resumen'          => $resumen,
            'gastos_categoria' => $gastosCategoria,
        ]);
    }

    /**
     * GET /finanzas/api/evolucion
     */
    public function apiEvolucion(Request $request): JsonResponse
    {
        $user = Auth::user();
        $anio = (int) $request->input('anio', now()->year);

        $evolucion = $this->alertaService->getEvolucionAnual($user->id, $anio);

        $ultimosMeses = collect($evolucion)
            ->sortByDesc('mes')
            ->take(6)
            ->sortBy('mes')
            ->map(fn ($m) => [
                'label'    => $m['label'],
                'entradas' => $m['entradas'],
                'gastos'   => $m['salidas'],
            ])
            ->values();

        return response()->json([
            'evolucion'     => $evolucion,
            'ultimos_meses' => $ultimosMeses,
        ]);
    }

    /**
     * GET /finanzas/api/consolidado
     */
    public function apiConsolidado(): JsonResponse
    {
        $user = Auth::user();
        $consolidado = $this->alertaService->getConsolidadoGlobal($user->id);
        return response()->json($consolidado);
    }

    /**
     * GET /finanzas/api/cuentas
     */
    public function apiCuentas(): JsonResponse
    {
        $user = Auth::user();
        $cuentas = \App\Models\Finanzas\Cuenta::conSaldos($user->id)
            ->map(fn ($c) => [
                'id'          => $c->id,
                'nombre'      => $c->nombre,
                'icono'       => $c->icono,
                'saldo_actual'=> (float) $c->saldo_actual,
            ]);

        return response()->json($cuentas);
    }

    /**
     * GET /finanzas/api/alertas
     */
    public function apiAlertas(Request $request): JsonResponse
    {
        $user = Auth::user();
        $anio = (int) $request->input('anio', now()->year);
        $mes  = (int) $request->input('mes', now()->month);

        $prestamosMora = $this->alertaService->getPrestamosEnMora($user->id)
            ->map(fn ($p) => [
                'id'           => $p->id,
                'nombre_deudor'=> $p->nombre_deudor,
                'saldo_actual' => (float) $p->saldo_actual,
                'dias_mora'    => $p->dias_mora ?? 0,
                'url_ficha'    => route('finanzas.prestamos.show', $p->id),
                'url_whatsapp' => route('finanzas.prestamos.whatsapp', $p->id),
            ]);

        $gastosFaltantes = $this->alertaService
            ->getGastosRecurrentesPendientes($user->id, $anio, $mes)
            ->map(fn ($g) => [
                'id'     => $g->id,
                'nombre' => $g->nombre,
                'icono'  => $g->icono,
            ])->values();

        return response()->json([
            'prestamos_mora'   => $prestamosMora->values(),
            'gastos_faltantes' => $gastosFaltantes,
        ]);
    }
}
