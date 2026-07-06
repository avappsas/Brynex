<?php

namespace App\Services\Finanzas;

use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\Entrada;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\Patrimonio;
use Carbon\Carbon;

class FinanzasAlertaService
{
    protected static $fuentesCache = null;
    protected static $entradasCache = [];

    /**
     * Obtiene las categorías de gastos recurrentes que NO tienen registros este mes.
     *
     * @param int $userId
     * @param int $anio
     * @param int $mes
     * @return \Illuminate\Database\Eloquent\Collection
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

    /**
     * Obtiene la lista de préstamos activos que se encuentran en mora.
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPrestamosEnMora(int $userId)
    {
        return Prestamo::activos()
            ->where('user_id', $userId)
            ->where('alertas_activas', true)
            ->where('estado', 'mora')
            ->get();
    }

    /**
     * Genera estadísticas completas del mes para el dashboard.
     *
     * @param int $userId
     * @param int $anio
     * @param int $mes
     * @return array
     */
    public function getResumenMensual(int $userId, int $anio, int $mes): array
    {
        $fechaActual = Carbon::create($anio, $mes, 1);
        $fechaAnterior = $fechaActual->copy()->subMonth();

        // 1. Entradas del mes actual y anterior (usando cálculo consolidado)
        $entradasActual = $this->calculateTotalEntradas($userId, $anio, $mes);
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

        // Balance neto del mes
        $balanceActual = $entradasActual - $salidasTotalesActual;

        // 5. Total Cartera (Préstamos activos saldo actual)
        $totalCartera = (float) Prestamo::activos()->where('user_id', $userId)->sum('saldo_actual');

        // 6. Patrimonio Total
        $totalPatrimonio = (float) Patrimonio::activos()->where('user_id', $userId)->sum('valor_compra');

        // Calcular porcentajes de cambio
        $cambioEntradas = $entradasAnterior > 0 ? (($entradasActual - $entradasAnterior) / $entradasAnterior) * 100 : 0;
        $cambioGastos = $gastosAnterior > 0 ? (($gastosActual - $gastosAnterior) / $gastosAnterior) * 100 : 0;

        return [
            'entradas' => $entradasActual,
            'entradas_cambio' => round($cambioEntradas, 1),
            'salidas' => $salidasTotalesActual,
            'gastos_habituales' => $gastosActual,
            'gastos_cambio' => round($cambioGastos, 1),
            'prestado' => $prestadoActual,
            'invertido' => $invertidoActual,
            'balance' => $balanceActual,
            'total_cartera' => $totalCartera,
            'total_patrimonio' => $totalPatrimonio,
        ];
    }

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
        
        // 1. Obtener intereses generados para este mes y año
        $intereses = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('tipo', 'interes_mensual')
            ->sum('monto');

        // 2. Obtener utilidad mensual de OTRAS APP (tabla finanzas_app_lideres_pagos)
        $appLideres = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_app_lideres_pagos')
            ->where('user_id', $userId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->sum('monto');

        // 3. Obtener ingresos de Proyectos
        $proyectos = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_proyecto_movimientos')
            ->join('finanzas_proyectos', 'finanzas_proyecto_movimientos.proyecto_id', '=', 'finanzas_proyectos.id')
            ->where('finanzas_proyectos.user_id', $userId)
            ->whereYear('finanzas_proyecto_movimientos.fecha', $anio)
            ->whereMonth('finanzas_proyecto_movimientos.fecha', $mes)
            ->where('finanzas_proyecto_movimientos.tipo', 'entrada')
            ->sum('finanzas_proyecto_movimientos.monto');

        // 4. Obtener ingresos esporádicos
        $esporadicos = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->sum('monto');

        // 5. Obtener todos los montos manuales del mes en una sola consulta
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
                $montoBrynex = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
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

    /**
     * Obtiene el consolidado financiero global e histórico del usuario.
     */
    public function getConsolidadoGlobal(int $userId): array
    {
        // 1. LIQUIDEZ Personal (entradas - salidas)
        // Obtener ids de fuentes calculadas para no duplicar en entradas manuales
        $fuentesCalculadas = \App\Models\Finanzas\FuenteIngreso::where('user_id', $userId)
            ->whereIn(\DB::raw('UPPER(TRIM(nombre))'), ['BRYNEX', 'INTERESES PRESTAMOS', 'OTRAS APP', 'PROYECTOS', 'OTRAS ENTRADAS'])
            ->pluck('id')
            ->toArray();

        // Entradas manuales históricas
        $entradasManualesTotal = (float) \App\Models\Finanzas\Entrada::where('user_id', $userId)
            ->whereNotIn('fuente_id', $fuentesCalculadas)
            ->sum('monto');

        // Brynex pagos históricos
        $brynexTotal = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_brynex_pagos')
            ->where('user_id', $userId)
            ->sum('monto');

        // Otras App pagos históricos
        $otrasAppTotal = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_app_lideres_pagos')
            ->where('user_id', $userId)
            ->sum('monto');

        // Intereses recibidos históricos (abonos a interés y pagos totales)
        $interesesTotal = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereIn('finanzas_prestamo_movimientos.tipo', ['abono_interes', 'pago_total'])
            ->sum('finanzas_prestamo_movimientos.monto');

        // Abonos a capital históricos
        $abonosCapitalTotal = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->where('finanzas_prestamo_movimientos.tipo', 'abono_capital')
            ->sum('finanzas_prestamo_movimientos.monto');

        // Ingresos esporádicos históricos
        $esporadicosTotal = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
            ->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->sum('monto');

        $totalEntradasHistorico = $entradasManualesTotal + $brynexTotal + $otrasAppTotal + $interesesTotal + $abonosCapitalTotal + $esporadicosTotal;

        // Salidas históricas (gastos, préstamos e inversiones desembolsadas)
        $salidasTotal = (float) \Illuminate\Support\Facades\DB::connection('finanzas')
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

        // 5. PROYECTOS
        $proyectosLista = \App\Models\Finanzas\Proyecto::where('user_id', $userId)
            ->where('activo', true)
            ->get()
            ->map(function ($p) {
                $entradas = (float) $p->movimientos()->where('tipo', 'entrada')->sum('monto');
                $salidas = (float) $p->movimientos()->where('tipo', 'salida')->sum('monto');
                $saldo = $entradas - $salidas;
                return [
                    'nombre' => $p->nombre,
                    'entradas' => $entradas,
                    'salidas' => $salidas,
                    'saldo' => $saldo
                ];
            });

        $totalSaldoProyectos = $proyectosLista->sum('saldo');

        // Liquidez global (personal + proyectos)
        $liquidezGlobal = $liquidezPersonal + $totalSaldoProyectos;

        return [
            'liquidez_personal' => $liquidezPersonal,
            'inversiones_cripto' => $inversionesCripto,
            'patrimonio_total' => $patrimonioTotal,
            'prestado_cartera' => $prestamosCartera,
            'proyectos' => $proyectosLista->toArray(),
            'total_saldo_proyectos' => $totalSaldoProyectos,
            'liquidez_global' => $liquidezGlobal
        ];
    }
}
