<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\Entrada;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\Patrimonio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinanzasAlertaService
{
    protected static $fuentesCache = null;
    protected static $entradasCache = [];

    // TTLs de caché en segundos
    const TTL_RESUMEN    = 600;  // 10 minutos
    const TTL_EVOLUCION  = 1800; // 30 minutos
    const TTL_CONSOLIDADO = 900; // 15 minutos

    // ─────────────────────────────────────────────────────────────
    //  Claves de caché
    // ─────────────────────────────────────────────────────────────

    public static function cacheKeyResumen(int $userId, int $anio, int $mes): string
    {
        return "finanzas_resumen_{$userId}_{$anio}_{$mes}";
    }

    public static function cacheKeyEvolucion(int $userId, int $anio): string
    {
        return "finanzas_evolucion_{$userId}_{$anio}";
    }

    public static function cacheKeyConsolidado(int $userId): string
    {
        return "finanzas_consolidado_{$userId}";
    }

    // ─────────────────────────────────────────────────────────────
    //  Invalidación de caché (llamar desde controladores de escritura)
    // ─────────────────────────────────────────────────────────────

    /**
     * Invalida toda la caché del dashboard de un usuario.
     * Llamar desde cualquier controlador que modifique datos financieros.
     *
     * @param int $userId
     * @param int|null $anio  Si se conoce el año del dato modificado, también borra el mes anterior/siguiente
     * @param int|null $mes   Mes del dato modificado
     */
    public function invalidarCacheUsuario(int $userId, ?int $anio = null, ?int $mes = null): void
    {
        // Consolidado global (siempre)
        Cache::forget(self::cacheKeyConsolidado($userId));

        // Cuentas / bolsillos
        Cache::forget("finanzas_cuentas_{$userId}");

        if ($anio && $mes) {
            // Resumen del mes modificado
            Cache::forget(self::cacheKeyResumen($userId, $anio, $mes));

            // También el mes anterior (para los "cambio vs anterior")
            $fechaAnterior = Carbon::create($anio, $mes, 1)->subMonth();
            Cache::forget(self::cacheKeyResumen($userId, $fechaAnterior->year, $fechaAnterior->month));

            // Evolución anual del año afectado
            Cache::forget(self::cacheKeyEvolucion($userId, $anio));
        } else {
            // Sin fecha específica: invalidar el año actual y el anterior
            Cache::forget(self::cacheKeyEvolucion($userId, now()->year));
            Cache::forget(self::cacheKeyEvolucion($userId, now()->year - 1));
            Cache::forget(self::cacheKeyResumen($userId, now()->year, now()->month));
        }

        // Limpiar caché estática de la instancia actual
        self::$fuentesCache = null;
        self::$entradasCache = [];
    }

    // ─────────────────────────────────────────────────────────────
    //  Gastos recurrentes pendientes
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene las categorías de gastos recurrentes que NO tienen registros este mes.
     */
    public function getGastosRecurrentesPendientes(int $userId, int $anio, int $mes)
    {
        // Obtener categorías de gastos marcadas como recurrentes y activas
        $recurrentes = CategoriaGasto::activas()
            ->where('user_id', $userId)
            ->where('es_recurrente', true)
            ->get();

        // Obtener IDs de categorías que ya tienen al menos un gasto en este mes
        $categoriasConGasto = Gasto::where('user_id', $userId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->pluck('categoria_id')
            ->unique()
            ->toArray();

        // Filtrar las que aún no tienen gastos este mes
        return $recurrentes->filter(function ($cat) use ($categoriasConGasto) {
            return !in_array($cat->id, $categoriasConGasto);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Préstamos en mora
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene la lista de préstamos activos que se encuentran en mora.
     */
    public function getPrestamosEnMora(int $userId)
    {
        return Prestamo::activos()
            ->where('user_id', $userId)
            ->where('alertas_activas', true)
            ->where('estado', 'mora')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────
    //  Resumen mensual — con caché 10 min
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera estadísticas completas del mes para el dashboard.
     */
    public function getResumenMensual(int $userId, int $anio, int $mes): array
    {
        return Cache::remember(
            self::cacheKeyResumen($userId, $anio, $mes),
            self::TTL_RESUMEN,
            fn () => $this->calcularResumenMensual($userId, $anio, $mes)
        );
    }

    private function calcularResumenMensual(int $userId, int $anio, int $mes): array
    {
        $fechaActual   = Carbon::create($anio, $mes, 1);
        $fechaAnterior = $fechaActual->copy()->subMonth();

        // 1. Entradas del mes actual y anterior (usando cálculo consolidado)
        $entradasActual   = $this->calculateTotalEntradas($userId, $anio, $mes);
        $entradasAnterior = $this->calculateTotalEntradas($userId, $fechaAnterior->year, $fechaAnterior->month);

        // 2. Gastos del mes actual y anterior (excluyendo abonos a préstamos o inversiones)
        $gastosActual = (float) Gasto::where('user_id', $userId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('tipo_movimiento', 'gasto')
            ->sum('monto');

        $gastosAnterior = (float) Gasto::where('user_id', $userId)
            ->whereYear('fecha', $fechaAnterior->year)
            ->whereMonth('fecha', $fechaAnterior->month)
            ->where('tipo_movimiento', 'gasto')
            ->sum('monto');

        // 3. Préstamos desembolsados en este mes (salida de dinero)
        $prestadoActual = (float) Gasto::where('user_id', $userId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('tipo_movimiento', 'prestamo')
            ->sum('monto');

        // 4. Inversiones realizadas en este mes (salida de dinero)
        $invertidoActual = (float) Gasto::where('user_id', $userId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('tipo_movimiento', 'inversion')
            ->sum('monto');

        // Salidas totales = Gastos habituales + Préstamos nuevos + Inversiones nuevas
        $salidasTotalesActual = $gastosActual + $prestadoActual + $invertidoActual;

        // Balance neto del mes = Entradas del mes - Gastos habituales
        $balanceActual = $entradasActual - $gastosActual;

        // 5. Total Cartera (Préstamos activos saldo actual)
        $totalCartera = (float) Prestamo::activos()->where('user_id', $userId)->sum('saldo_actual');

        // 5.1 Intereses CAUSADOS del mes (liquidados al saldo)
        $interesesCausados = (float) DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereYear('finanzas_prestamo_movimientos.fecha', $anio)
            ->whereMonth('finanzas_prestamo_movimientos.fecha', $mes)
            ->where('finanzas_prestamo_movimientos.tipo', 'interes_mensual')
            ->sum('finanzas_prestamo_movimientos.monto');

        // 5.2 Intereses COBRADOS del mes (lo que realmente pagaron los deudores)
        $interesesCobrados = (float) DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereYear('finanzas_prestamo_movimientos.fecha', $anio)
            ->whereMonth('finanzas_prestamo_movimientos.fecha', $mes)
            ->whereIn('finanzas_prestamo_movimientos.tipo', ['abono_interes', 'pago_total'])
            ->sum('finanzas_prestamo_movimientos.monto');

        // 6. Patrimonio Total (valor actual)
        $totalPatrimonio = (float) Patrimonio::activos()->where('user_id', $userId)->sum('valor_actual');

        // Calcular porcentajes de cambio
        $cambioEntradas = $entradasAnterior > 0 ? (($entradasActual - $entradasAnterior) / $entradasAnterior) * 100 : 0;
        $cambioGastos   = $gastosAnterior   > 0 ? (($gastosActual - $gastosAnterior)   / $gastosAnterior)   * 100 : 0;

        return [
            'entradas'            => $entradasActual,
            'entradas_cambio'     => round($cambioEntradas, 1),
            'salidas'             => $salidasTotalesActual,
            'gastos_habituales'   => $gastosActual,
            'gastos_cambio'       => round($cambioGastos, 1),
            'prestado'            => $prestadoActual,
            'invertido'           => $invertidoActual,
            'balance'             => $balanceActual,
            'total_cartera'       => $totalCartera,
            'total_patrimonio'    => $totalPatrimonio,
            'intereses_causados'  => $interesesCausados,
            'intereses_cobrados'  => $interesesCobrados,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Cálculo de entradas totales por mes (interno)
    // ─────────────────────────────────────────────────────────────

    public function calculateTotalEntradas(int $userId, int $anio, int $mes): float
    {
        $cacheKey = "{$userId}_{$anio}_{$mes}";
        if (isset(self::$entradasCache[$cacheKey])) {
            return self::$entradasCache[$cacheKey];
        }

        if (self::$fuentesCache === null) {
            self::$fuentesCache = \App\Models\Finanzas\FuenteIngreso::where('user_id', $userId)->activas()->get();
        }
        $fuentes = self::$fuentesCache;

        $total = 0.00;

        // 1. Intereses generados para este mes y año
        $intereses = (float) DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereYear('finanzas_prestamo_movimientos.fecha', $anio)
            ->whereMonth('finanzas_prestamo_movimientos.fecha', $mes)
            ->where('finanzas_prestamo_movimientos.tipo', 'interes_mensual')
            ->sum('finanzas_prestamo_movimientos.monto');

        // 2. Utilidad mensual de OTRAS APP
        $appLideres = (float) DB::connection('finanzas')
            ->table('finanzas_app_lideres_pagos')
            ->where('user_id', $userId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->sum('monto');

        // 3. NETO mensual de Proyectos (entradas - salidas)
        $proyectos = (float) DB::connection('finanzas')
            ->table('finanzas_proyecto_movimientos')
            ->join('finanzas_proyectos', 'finanzas_proyecto_movimientos.proyecto_id', '=', 'finanzas_proyectos.id')
            ->where('finanzas_proyectos.user_id', $userId)
            ->whereYear('finanzas_proyecto_movimientos.fecha', $anio)
            ->whereMonth('finanzas_proyecto_movimientos.fecha', $mes)
            ->selectRaw("COALESCE(SUM(CASE WHEN finanzas_proyecto_movimientos.tipo IN ('ingreso', 'entrada') THEN finanzas_proyecto_movimientos.monto ELSE -finanzas_proyecto_movimientos.monto END), 0) as total")
            ->value('total');

        // 4. Ingresos esporádicos
        $esporadicos = (float) DB::connection('finanzas')
            ->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->sum('monto');

        // 5. Todos los montos manuales del mes en una sola consulta
        $manualEntradas = Entrada::where('user_id', $userId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->selectRaw('fuente_id, SUM(monto) as total')
            ->groupBy('fuente_id')
            ->pluck('total', 'fuente_id')
            ->toArray();

        foreach ($fuentes as $fuente) {
            $nombreUpper = strtoupper(trim($fuente->nombre));
            if ($nombreUpper === 'INTERESES PRESTAMOS') {
                $total += $intereses;
            } elseif ($nombreUpper === 'OTRAS APP') {
                $total += $appLideres;
            } elseif ($nombreUpper === 'PROYECTOS') {
                $total += $proyectos;
            } elseif ($nombreUpper === 'OTRAS ENTRADAS') {
                $total += $esporadicos;
            } elseif ($nombreUpper === 'BRYNEX') {
                $montoBrynex = (float) DB::connection('finanzas')
                    ->table('finanzas_brynex_pagos')
                    ->where('user_id', $userId)
                    ->where('anio', $anio)
                    ->where('mes', $mes)
                    ->sum('monto');
                $total += $montoBrynex;
            } else {
                // Fuente manual desde la consulta unificada
                $total += (float) ($manualEntradas[$fuente->id] ?? 0.00);
            }
        }

        self::$entradasCache[$cacheKey] = $total;
        return $total;
    }

    // ─────────────────────────────────────────────────────────────
    //  Evolución anual — con caché 30 min
    // ─────────────────────────────────────────────────────────────

    /**
     * Evolución mes a mes del año: entradas, salidas, intereses causados/cobrados
     * y liquidez acumulada del año. Alimenta las gráficas de los dashboards.
     */
    public function getEvolucionAnual(int $userId, int $anio): array
    {
        return Cache::remember(
            self::cacheKeyEvolucion($userId, $anio),
            self::TTL_EVOLUCION,
            fn () => $this->calcularEvolucionAnual($userId, $anio)
        );
    }

    private function calcularEvolucionAnual(int $userId, int $anio): array
    {
        $conn = DB::connection('finanzas');

        // Salidas por mes (gastos + préstamos otorgados + inversiones)
        $salidasMes = $conn->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->whereYear('fecha', $anio)
            ->whereIn('tipo_movimiento', ['gasto', 'prestamo', 'inversion'])
            ->selectRaw('MONTH(fecha) as mes, SUM(monto) as total')
            ->groupByRaw('MONTH(fecha)')
            ->pluck('total', 'mes');

        // Intereses causados por mes
        $causadosMes = $conn->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereYear('finanzas_prestamo_movimientos.fecha', $anio)
            ->where('finanzas_prestamo_movimientos.tipo', 'interes_mensual')
            ->selectRaw('MONTH(finanzas_prestamo_movimientos.fecha) as mes, SUM(finanzas_prestamo_movimientos.monto) as total')
            ->groupByRaw('MONTH(finanzas_prestamo_movimientos.fecha)')
            ->pluck('total', 'mes');

        // Intereses cobrados por mes
        $cobradosMes = $conn->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereYear('finanzas_prestamo_movimientos.fecha', $anio)
            ->whereIn('finanzas_prestamo_movimientos.tipo', ['abono_interes', 'pago_total'])
            ->selectRaw('MONTH(finanzas_prestamo_movimientos.fecha) as mes, SUM(finanzas_prestamo_movimientos.monto) as total')
            ->groupByRaw('MONTH(finanzas_prestamo_movimientos.fecha)')
            ->pluck('total', 'mes');

        $meses          = [];
        $liquidezAcumulada = 0.00;
        $mesLimite      = ($anio == now()->year) ? now()->month : 12;

        for ($m = 1; $m <= $mesLimite; $m++) {
            $entradas = $this->calculateTotalEntradas($userId, $anio, $m);
            $salidas  = (float) ($salidasMes[$m] ?? 0);
            $liquidezAcumulada += ($entradas - $salidas);

            $meses[] = [
                'mes'                  => $m,
                'label'                => ucfirst(Carbon::create($anio, $m, 1)->locale('es')->shortMonthName),
                'entradas'             => round($entradas, 2),
                'salidas'              => round($salidas, 2),
                'intereses_causados'   => round((float) ($causadosMes[$m] ?? 0), 2),
                'intereses_cobrados'   => round((float) ($cobradosMes[$m] ?? 0), 2),
                'liquidez_acumulada'   => round($liquidezAcumulada, 2),
            ];
        }

        return $meses;
    }

    // ─────────────────────────────────────────────────────────────
    //  Consolidado global — con caché 15 min + fix N+1
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el consolidado financiero global e histórico del usuario.
     */
    public function getConsolidadoGlobal(int $userId): array
    {
        return Cache::remember(
            self::cacheKeyConsolidado($userId),
            self::TTL_CONSOLIDADO,
            fn () => $this->calcularConsolidadoGlobal($userId)
        );
    }

    private function calcularConsolidadoGlobal(int $userId): array
    {
        // 1. LIQUIDEZ Personal (entradas - salidas)
        // Obtener ids de fuentes calculadas para no duplicar en entradas manuales
        $fuentesCalculadas = \App\Models\Finanzas\FuenteIngreso::where('user_id', $userId)
            ->whereIn(DB::raw('UPPER(TRIM(nombre))'), ['BRYNEX', 'INTERESES PRESTAMOS', 'OTRAS APP', 'PROYECTOS', 'OTRAS ENTRADAS'])
            ->pluck('id')
            ->toArray();

        // Entradas manuales históricas
        $entradasManualesTotal = (float) \App\Models\Finanzas\Entrada::where('user_id', $userId)
            ->whereNotIn('fuente_id', $fuentesCalculadas)
            ->sum('monto');

        // Brynex pagos históricos
        $brynexTotal = (float) DB::connection('finanzas')
            ->table('finanzas_brynex_pagos')
            ->where('user_id', $userId)
            ->sum('monto');

        // Otras App pagos históricos
        $otrasAppTotal = (float) DB::connection('finanzas')
            ->table('finanzas_app_lideres_pagos')
            ->where('user_id', $userId)
            ->sum('monto');

        // Intereses recibidos históricos (abonos a interés y pagos totales)
        $interesesTotal = (float) DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereIn('finanzas_prestamo_movimientos.tipo', ['abono_interes', 'pago_total'])
            ->sum('finanzas_prestamo_movimientos.monto');

        // Abonos a capital históricos
        $abonosCapitalTotal = (float) DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->where('finanzas_prestamo_movimientos.tipo', 'abono_capital')
            ->sum('finanzas_prestamo_movimientos.monto');

        // Ingresos esporádicos históricos
        $esporadicosTotal = (float) DB::connection('finanzas')
            ->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->sum('monto');

        $totalEntradasHistorico = $entradasManualesTotal + $brynexTotal + $otrasAppTotal
            + $interesesTotal + $abonosCapitalTotal + $esporadicosTotal;

        // Salidas históricas (gastos, préstamos e inversiones desembolsadas)
        $salidasTotal = (float) DB::connection('finanzas')
            ->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->whereIn('tipo_movimiento', ['gasto', 'prestamo', 'inversion'])
            ->sum('monto');

        $liquidezPersonal = $totalEntradasHistorico - $salidasTotal;

        // 2. INVERSIONES (Cripto)
        $inversionesCripto = (float) \App\Models\Finanzas\Inversion::where('user_id', $userId)
            ->where('activo', true)
            ->sum('valor_actual_cop');

        // 3. PATRIMONIO (valor de invertido)
        $patrimonioTotal = (float) \App\Models\Finanzas\Patrimonio::where('user_id', $userId)
            ->where('activo', true)
            ->sum('valor_actual');

        // 4. PRESTADO (valor prestado)
        $prestamosCartera = (float) \App\Models\Finanzas\Prestamo::activos()
            ->where('user_id', $userId)
            ->sum('saldo_actual');

        // 5. PROYECTOS — Fix N+1: una sola query para todos los proyectos activos
        $proyectosList = \App\Models\Finanzas\Proyecto::where('user_id', $userId)
            ->where('activo', true)
            ->get(['id', 'nombre']);

        $proyectosLista = collect();

        if ($proyectosList->isNotEmpty()) {
            // Una sola query con GROUP BY proyecto_id y tipo
            $movimientos = DB::connection('finanzas')
                ->table('finanzas_proyecto_movimientos')
                ->join('finanzas_proyectos', 'finanzas_proyecto_movimientos.proyecto_id', '=', 'finanzas_proyectos.id')
                ->where('finanzas_proyectos.user_id', $userId)
                ->where('finanzas_proyectos.activo', true)
                ->selectRaw('finanzas_proyecto_movimientos.proyecto_id, finanzas_proyecto_movimientos.tipo, SUM(finanzas_proyecto_movimientos.monto) as total')
                ->groupBy('finanzas_proyecto_movimientos.proyecto_id', 'finanzas_proyecto_movimientos.tipo')
                ->get()
                ->groupBy('proyecto_id');

            $proyectosLista = $proyectosList->map(function ($p) use ($movimientos) {
                $movs     = $movimientos->get($p->id, collect());
                $entradas = (float) ($movs->firstWhere('tipo', 'entrada')?->total ?? 0);
                $salidas  = (float) ($movs->firstWhere('tipo', 'salida')?->total ?? 0);
                $saldo    = $entradas - $salidas;
                return [
                    'nombre'   => $p->nombre,
                    'entradas' => $entradas,
                    'salidas'  => $salidas,
                    'saldo'    => $saldo,
                ];
            });
        }

        $totalSaldoProyectos = $proyectosLista->sum('saldo');

        // Liquidez global (personal + proyectos)
        $liquidezGlobal = $liquidezPersonal + $totalSaldoProyectos;

        return [
            'liquidez_personal'     => $liquidezPersonal,
            'inversiones_cripto'    => $inversionesCripto,
            'patrimonio_total'      => $patrimonioTotal,
            'prestado_cartera'      => $prestamosCartera,
            'proyectos'             => $proyectosLista->toArray(),
            'total_saldo_proyectos' => $totalSaldoProyectos,
            'liquidez_global'       => $liquidezGlobal,
        ];
    }
}
