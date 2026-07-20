@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Dashboard')

@section('contenido')
@php
    $categorias = \App\Models\Finanzas\CategoriaGasto::where('user_id', auth()->id())->activas()->orderBy('orden')->get();
    $patrimonios = \App\Models\Finanzas\Patrimonio::where('user_id', auth()->id())->activos()->get();

    // Obtener datos históricos de los últimos 6 meses para la gráfica
    $ultimosMeses = collect();
    $alertaService = resolve(\App\Services\Finanzas\FinanzasAlertaService::class);
    for ($i = 5; $i >= 0; $i--) {
        $fecha = now()->subMonths($i);
        $m = $fecha->month;
        $y = $fecha->year;
        
        $ent = (float) $alertaService->calculateTotalEntradas(auth()->id(), $y, $m);
        $gast = (float) \App\Models\Finanzas\Gasto::where('user_id', auth()->id())
            ->whereYear('fecha', $y)
            ->whereMonth('fecha', $m)
            ->where('tipo_movimiento', '!=', 'ingreso_esporadico')
            ->sum('monto');
        
        $nombreMes = $fecha->locale('es')->shortMonthName;
        $ultimosMeses->push([
            'label' => ucfirst($nombreMes) . ' ' . $y,
            'entradas' => $ent,
            'gastos' => $gast
        ]);
    }

    // Obtener gastos de este mes agrupados por categoría para la dona
    $gastosCategoria = \App\Models\Finanzas\Gasto::with('categoria')
        ->select('categoria_id', \DB::raw('SUM(monto) as total'))
        ->where('user_id', auth()->id())
        ->whereYear('fecha', $anio)
        ->whereMonth('fecha', $mes)
        ->where('tipo_movimiento', 'gasto')
        ->groupBy('categoria_id')
        ->get();
@endphp

<div class="finanzas-container" x-data="{ openGastoRapido: false, openEntradaRapida: false, openConsolidadoGlobal: false }">

    {{-- Breadcrumb & Period Selector --}}
    <div class="fin-top-bar">
        <div class="breadcrumb-bx">
            <a href="{{ route('brynex.hub') }}">🔵 BryNex</a>
            <span>›</span>
            <span>Finanzas Personales</span>
        </div>
        
        <form method="GET" action="{{ route('finanzas.dashboard') }}" class="period-selector-bx">
            <select name="mes" class="select-fin" onchange="this.form.submit()">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" @selected($mes == $m)>
                        {{ ucfirst(\Carbon\Carbon::create()->month($m)->locale('es')->monthName) }}
                    </option>
                @endforeach
            </select>
            <select name="anio" class="select-fin" onchange="this.form.submit()">
                @foreach(range(2020, now()->year + 1) as $a)
                    <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Header del Dashboard --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>💰 Mi Contabilidad Privada</h1>
            <p>Resumen financiero consolidado personal, cobros y patrimonio.</p>
        </div>
        <div class="header-actions" style="display:flex; gap:0.5rem;">
            <button @click="openEntradaRapida = true" class="btn-fin success" style="background:#10b981;">
                ⚡ Entrada Rápida
            </button>
            <button @click="openGastoRapido = true" class="btn-fin success" style="background:#ef4444;">
                ⚡ Gasto Rápido
            </button>
        </div>
    </div>

    {{-- Cripto Widget (Precio USDT) --}}
    <div class="cripto-widget-bx">
        <div class="cw-title">🪙 Cotización Tether (USDT):</div>
        <div class="cw-values">
            <span class="cw-cop"><strong>${{ number_format($criptoPrecio['precio_cop'], 0, ',', '.') }} COP</strong></span>
            <span class="cw-usd">${{ number_format($criptoPrecio['precio_usd'], 2) }} USD</span>
            <span class="cw-date">(Refrescado: {{ Carbon\Carbon::parse($criptoPrecio['actualizado'])->format('H:i') }})</span>
        </div>
        @if($criptoPrecio['fallback'])
            <span class="badge-warn" style="font-size:0.65rem;">Fallback</span>
        @endif
    </div>

    {{-- Grid de KPIs principales --}}
    <div class="fin-kpis-grid">
        @include('finanzas.partials._kpi_card', [
            'label' => 'Total Entradas',
            'value' => '$' . number_format($resumen['entradas'], 0, ',', '.'),
            'change' => $resumen['entradas_cambio'],
            'icon' => '📥',
            'color' => '#10b981'
        ])
        @include('finanzas.partials._kpi_card', [
            'label' => 'Gastos Habituales',
            'value' => '$' . number_format($resumen['gastos_habituales'], 0, ',', '.'),
            'change' => $resumen['gastos_cambio'],
            'icon' => '📤',
            'color' => '#ef4444'
        ])
        @include('finanzas.partials._kpi_card', [
            'label' => 'Balance del Mes',
            'value' => '$' . number_format($resumen['balance'], 0, ',', '.'),
            'icon' => '⚖️',
            'color' => $resumen['balance'] >= 0 ? '#10b981' : '#ef4444'
        ])
        @include('finanzas.partials._kpi_card', [
            'label' => 'Préstamos del Mes',
            'value' => '$' . number_format($resumen['prestado'], 0, ',', '.'),
            'icon' => '🤝',
            'color' => '#f59e0b'
        ])
    </div>

    {{-- Intereses del mes: causados (liquidados al saldo) vs cobrados (pagados de verdad) --}}
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; margin-top:-0.5rem;">
        <div style="flex:1; min-width:220px; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:0.6rem 1rem; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:0.72rem; font-weight:700; color:#92400e; text-transform:uppercase;">📈 Intereses causados (mes)</span>
            <strong style="color:#92400e; font-size:1rem;">${{ number_format($resumen['intereses_causados'] ?? 0, 0, ',', '.') }}</strong>
        </div>
        <div style="flex:1; min-width:220px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:0.6rem 1rem; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:0.72rem; font-weight:700; color:#166534; text-transform:uppercase;">💰 Intereses cobrados (mes)</span>
            <strong style="color:#166534; font-size:1rem;">${{ number_format($resumen['intereses_cobrados'] ?? 0, 0, ',', '.') }}</strong>
        </div>
    </div>

    {{-- Grid de Contenido (Gráficas y Alertas) --}}
    <div class="fin-dashboard-grid">
        
        {{-- Lado Izquierdo: Gráficas y Alertas --}}
        <div class="fin-main-panel">
            
            {{-- Alertas Críticas --}}
            @if(count($prestamosMora) > 0 || count($gastosFaltantes) > 0)
                <div class="alert-section-bx">
                    
                    {{-- Préstamos en Mora --}}
                    @if(count($prestamosMora) > 0)
                        <div class="alert-card-bx error">
                            <div class="ac-icon">⚠️</div>
                            <div class="ac-body">
                                <h3>Deudores en Mora (Vencidos)</h3>
                                <p>Las siguientes personas tienen préstamos activos vencidos hace más de 30 días:</p>
                                <div class="ac-list" style="margin-top:0.5rem; display:flex; flex-direction:column; gap:0.5rem;">
                                    @foreach($prestamosMora as $p)
                                        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(239,68,68,0.06); padding:0.4rem 0.6rem; border-radius:6px; font-size:0.8rem;">
                                            <div>
                                                <strong>{{ $p->nombre_deudor }}</strong> 
                                                <span style="color:#64748b;">(Debe: ${{ number_format($p->saldo_actual, 0, ',', '.') }} COP)</span>
                                                <span class="badge-err" style="font-size:0.65rem; margin-left:5px;">{{ $p->dias_mora }} días mora</span>
                                            </div>
                                            <div style="display:flex; gap:0.4rem;">
                                                <a href="{{ route('finanzas.prestamos.show', $p->id) }}" class="btn-fin-small primary">Ficha</a>
                                                <form action="{{ route('finanzas.prestamos.whatsapp', $p->id) }}" method="POST" style="display:inline;">
                                                    @csrf
                                                    <button type="submit" class="btn-fin-small success">🟢 Cobrar WA</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Gastos Recurrentes Faltantes --}}
                    @if(count($gastosFaltantes) > 0)
                        <div class="alert-card-bx warning" style="margin-top:0.75rem;">
                            <div class="ac-icon">💡</div>
                            <div class="ac-body">
                                <h3>Gastos Recurrentes Pendientes</h3>
                                <p>El sistema detecta que aún no has registrado estos gastos mensuales obligatorios:</p>
                                <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-top:0.5rem;">
                                    @foreach($gastosFaltantes as $gf)
                                        <span class="badge-warn" style="font-size:0.75rem;">
                                            {{ $gf->icono }} {{ $gf->nombre }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            @endif

            {{-- Gráfica Histórica --}}
            <div class="chart-container-card">
                <h3>📈 Entradas vs Egresos (Últimos 6 meses)</h3>
                <div style="height:250px; position:relative;">
                    <canvas id="historicalChart"></canvas>
                </div>
                <div style="margin-top: 1rem;">
                    <button @click="openConsolidadoGlobal = true" class="btn-fin" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; width: 100%; border: none; padding: 0.75rem; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25); display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        🌐 Ver Consolidado Global de Saldos
                    </button>
                </div>
            </div>

            {{-- Evolución de intereses del año --}}
            @if(isset($evolucion) && count($evolucion) > 0)
            <div class="chart-container-card" style="margin-top:1.25rem;">
                <h3>🤝 Intereses de Préstamos {{ $anio }} (Causados vs Cobrados)</h3>
                <div style="height:220px; position:relative;">
                    <canvas id="interesesChart"></canvas>
                </div>
            </div>

            {{-- Evolución de liquidez acumulada --}}
            <div class="chart-container-card" style="margin-top:1.25rem;">
                <h3>💵 Evolución de Liquidez {{ $anio }} (Acumulado del año)</h3>
                <div style="height:220px; position:relative;">
                    <canvas id="liquidezChart"></canvas>
                </div>
            </div>
            @endif

        </div>

        {{-- Lado Derecho: Accesos Rápidos y Distribución --}}
        <div class="fin-side-panel">

            {{-- Saldos por Cuenta / Bolsillo --}}
            @if(isset($cuentas) && $cuentas->isNotEmpty())
            <div class="menu-modulos-card" style="margin-bottom:1rem;">
                <h3>💳 Mis Cuentas</h3>
                <div style="display:flex; flex-direction:column; gap:0.4rem; margin-top:0.5rem;">
                    @foreach($cuentas as $cta)
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.45rem 0.6rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:0.8rem;">
                            <span>{{ $cta->icono }} <strong>{{ $cta->nombre }}</strong></span>
                            <span style="font-weight:700; color:{{ $cta->saldo_actual >= 0 ? '#0f172a' : '#ef4444' }};">${{ number_format($cta->saldo_actual, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <a href="{{ route('finanzas.cuentas.index') }}" style="font-size:0.72rem; font-weight:700; color:#4f46e5; text-decoration:none; text-align:right; margin-top:0.25rem;">Administrar cuentas y transferencias →</a>
                </div>
            </div>
            @endif

            {{-- Enlaces a Módulos --}}
            <div class="menu-modulos-card">
                <h3>📂 Módulos Financieros</h3>
                <div class="modulos-list">
                    <a href="{{ route('finanzas.cuentas.index') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#eef2ff; color:#4338ca;">💳</span>
                        <div class="mil-body">
                            <h4>Cuentas y Bolsillos</h4>
                            <p>Banco, efectivo y transferencias</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                    <a href="{{ route('finanzas.entradas.index') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#d1fae5; color:#065f46;">📥</span>
                        <div class="mil-body">
                            <h4>Entradas / Fuentes</h4>
                            <p>Ingresos fijos y variables</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                    <a href="{{ route('finanzas.gastos.index') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#e0f2fe; color:#0369a1;">💸</span>
                        <div class="mil-body">
                            <h4>Transacciones Diarias</h4>
                            <p>Gastos cotidianos e ingresos extras</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                    <a href="{{ route('finanzas.prestamos.index') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#fef3c7; color:#92400e;">🤝</span>
                        <div class="mil-body">
                            <h4>Préstamos a Terceros</h4>
                            <p>Control de deudas e intereses</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                    <a href="{{ route('finanzas.prestamos.cuenta-corriente') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#f3e8ff; color:#6b21a8;">💼</span>
                        <div class="mil-body">
                            <h4>Cuenta Corriente (Servicios)</h4>
                            <p>Cliente recurrente de trabajos</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                    <a href="{{ route('finanzas.inversiones.index') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#e0f2fe; color:#075985;">🪙</span>
                        <div class="mil-body">
                            <h4>Inversiones Cripto</h4>
                            <p>Binance USDT y rentabilidades</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                    <a href="{{ route('finanzas.patrimonio.index') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#e0f7fa; color:#006064;">🏠</span>
                        <div class="mil-body">
                            <h4>Patrimonio Físico</h4>
                            <p>Vehículos, apartamentos y gastos</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                    <a href="{{ route('finanzas.proyectos.index') }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#f0fdf4; color:#166534;">🏗️</span>
                        <div class="mil-body">
                            <h4>Proyectos de Negocio</h4>
                            <p>CuentaFacil y balances individuales</p>
                        </div>
                        <span class="mil-arrow">→</span>
                    </a>
                </div>
            </div>

            {{-- Distribución de Gastos --}}
            <div class="chart-container-card" style="margin-top:1rem;">
                <h3>📊 Distribución de Gastos (Mes Actual)</h3>
                @if(count($gastosCategoria) > 0)
                    <div style="height:200px; position:relative; display:flex; justify-content:center;">
                        <canvas id="categoriesChart"></canvas>
                    </div>
                @else
                    <div style="height:200px; display:flex; align-items:center; justify-content:center; color:#64748b; font-size:0.85rem;">
                        No hay gastos registrados en este mes.
                    </div>
                @endif
            </div>

        </div>

    </div>

    {{-- Gasto Rapido Modal --}}
    @include('finanzas.partials._gasto_rapido_modal')

    {{-- Entrada Rapida Modal --}}
    @include('finanzas.partials._entrada_rapida_modal')

    {{-- Modal Consolidado Global --}}
    <div x-show="openConsolidadoGlobal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="modal-overlay-bx" 
         @click.self="openConsolidadoGlobal = false">
        
        <div class="modal-box-bx" style="max-width:650px; border-radius:16px;">
            <div class="modal-head-bx" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 1.25rem; border-bottom: 1px solid #cbd5e1;">
                <div>
                    <h3 style="color:#fff; margin:0; font-size:1.2rem; display:flex; align-items:center; gap:0.5rem;">
                        🌐 Consolidado Global de Saldos
                    </h3>
                    <p style="margin:2px 0 0 0; font-size:0.75rem; color:#cbd5e1;">Resumen histórico y acumulación de activos financieros</p>
                </div>
                <button @click="openConsolidadoGlobal = false" class="modal-close-bx" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:rgba(255,255,255,0.7);">&times;</button>
            </div>
            
            <div class="modal-body-bx" style="padding:1.5rem; max-height:480px; overflow-y:auto; background:#f8fafc;">
                {{-- Grid de Saldos Principales --}}
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom:1.5rem;">
                    
                    {{-- Liquidez Personal --}}
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <span style="display:block; font-size:0.72rem; font-weight:700; color:#64748b; margin-bottom:0.25rem; text-transform:uppercase;">💵 Liquidez Personal</span>
                        <span style="font-size:1.2rem; font-weight:800; color:#1e293b;">
                            ${{ number_format($consolidado['liquidez_personal'], 0, ',', '.') }}
                        </span>
                        <small style="display:block; font-size:0.65rem; color:#94a3b8; margin-top:2px;">Entradas menos Salidas acumuladas</small>
                    </div>

                    {{-- Inversiones Cripto --}}
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <span style="display:block; font-size:0.72rem; font-weight:700; color:#64748b; margin-bottom:0.25rem; text-transform:uppercase;">🪙 Inversiones (Cripto)</span>
                        <span style="font-size:1.2rem; font-weight:800; color:#2563eb;">
                            ${{ number_format($consolidado['inversiones_cripto'], 0, ',', '.') }}
                        </span>
                        <small style="display:block; font-size:0.65rem; color:#94a3b8; margin-top:2px;">Valor actual de mercado</small>
                    </div>

                    {{-- Patrimonio Físico --}}
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <span style="display:block; font-size:0.72rem; font-weight:700; color:#64748b; margin-bottom:0.25rem; text-transform:uppercase;">🏠 Patrimonio</span>
                        <span style="font-size:1.2rem; font-weight:800; color:#006064;">
                            ${{ number_format($consolidado['patrimonio_total'], 0, ',', '.') }}
                        </span>
                        <small style="display:block; font-size:0.65rem; color:#94a3b8; margin-top:2px;">Valor actual estimado</small>
                    </div>

                    {{-- Prestado (Cartera) --}}
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <span style="display:block; font-size:0.72rem; font-weight:700; color:#64748b; margin-bottom:0.25rem; text-transform:uppercase;">🤝 Prestado (Cartera)</span>
                        <span style="font-size:1.2rem; font-weight:800; color:#d97706;">
                            ${{ number_format($consolidado['prestado_cartera'], 0, ',', '.') }}
                        </span>
                        <small style="display:block; font-size:0.65rem; color:#94a3b8; margin-top:2px;">Saldo pendiente de cobro</small>
                    </div>
                </div>

                {{-- Sección Proyectos --}}
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem; margin-bottom:1.5rem; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <h4 style="margin:0 0 0.75rem 0; font-size:0.85rem; font-weight:800; color:#334155; display:flex; align-items:center; gap:0.35rem;">
                        🏗️ Cajas de Proyectos (Activos)
                    </h4>
                    
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.75rem; text-align:left;">
                            <thead>
                                <tr style="border-bottom:2px solid #e2e8f0; color:#64748b;">
                                    <th style="padding:0.4rem 0.5rem; font-weight:700;">Proyecto</th>
                                    <th style="padding:0.4rem 0.5rem; text-align:right; font-weight:700;">Entradas</th>
                                    <th style="padding:0.4rem 0.5rem; text-align:right; font-weight:700;">Gastado (Salidas)</th>
                                    <th style="padding:0.4rem 0.5rem; text-align:right; font-weight:700; color:#0f172a;">Quedan (Saldo)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($consolidado['proyectos'] as $proj)
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:0.5rem; font-weight:600; color:#334155;">{{ $proj['nombre'] }}</td>
                                        <td style="padding:0.5rem; text-align:right; color:#10b981;">${{ number_format($proj['entradas'], 0, ',', '.') }}</td>
                                        <td style="padding:0.5rem; text-align:right; color:#ef4444;">${{ number_format($proj['salidas'], 0, ',', '.') }}</td>
                                        <td style="padding:0.5rem; text-align:right; font-weight:700; color: {{ $proj['saldo'] >= 0 ? '#10b981' : '#ef4444' }};">
                                            ${{ number_format($proj['saldo'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align:center; padding:1rem; color:#94a3b8;">No hay proyectos activos registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($consolidado['proyectos']) > 0)
                                <tfoot>
                                    <tr style="border-top:2px solid #cbd5e1; background:#f8fafc; font-weight:bold;">
                                        <td style="padding:0.5rem; font-size:0.75rem; color:#475569;">TOTAL CAJAS PROYECTOS</td>
                                        <td colspan="2"></td>
                                        <td style="padding:0.5rem; text-align:right; font-size:0.78rem; color:#0f172a; font-weight:800;">
                                            ${{ number_format($consolidado['total_saldo_proyectos'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- Gran Total: Liquidez Global Recomendada --}}
                <div style="background: linear-gradient(135deg, #10b981, #059669); color:#fff; border-radius:12px; padding:1.25rem; box-shadow:0 10px 15px -3px rgba(16,185,129,0.2);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="display:block; font-size:0.75rem; font-weight:700; color:rgba(255,255,255,0.85); text-transform:uppercase; letter-spacing:0.5px;">💰 Liquidez Global Requerida</span>
                            <span style="font-size:0.65rem; color:rgba(255,255,255,0.75); display:block; margin-top:2px;">(Liquidez Personal + Saldo Disponible en Proyectos)</span>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:1.5rem; font-weight:950; color:#fff;">
                                ${{ number_format($consolidado['liquidez_global'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="modal-foot-bx" style="display:flex; justify-content:end; padding:1rem; border-top:1px solid #cbd5e1; background:#f8fafc;">
                <button type="button" @click="openConsolidadoGlobal = false" class="btn-glass-bx" style="padding:0.5rem 1.25rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer; background:#fff; color:#475569;">Cerrar</button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.finanzas-container { max-width: 1040px; margin: 0 auto; padding: 0.5rem; }

/* Top Bar */
.fin-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.breadcrumb-bx { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #64748b; }
.breadcrumb-bx a { color: var(--azul-btn); text-decoration: none; font-weight: 500; }
.period-selector-bx { display: flex; gap: 0.5rem; }
.select-fin { padding: 0.35rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.8rem; background: #fff; cursor: pointer; outline: none; }

/* Header Section */
.fin-header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
.header-text h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; }
.header-text p { font-size: 0.85rem; color: #64748b; margin-top: 0.2rem; }
.btn-fin { padding: 0.5rem 1.25rem; border: none; border-radius: 9px; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
.btn-fin.success { background: #22c55e; color: #fff; }
.btn-fin.success:hover { background: #16a34a; transform: translateY(-1px); }

/* Cripto Widget */
.cripto-widget-bx { display: flex; align-items: center; gap: 0.5rem; background: #fff; border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; border-radius: 9px; font-size: 0.78rem; color: #334155; margin-bottom: 1rem; width: fit-content; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.cw-cop { color: #8b5cf6; font-size: 0.85rem; }
.cw-usd { color: #64748b; }
.cw-date { color: #94a3b8; font-size: 0.7rem; }

/* KPIs Grid */
.fin-kpis-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi-card { background: #fff; border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; }
.kpi-icon { font-size: 1.7rem; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: #f8fafc; border-radius: 10px; }
.kpi-content { display: flex; flex-direction: column; }
.kpi-label { font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; }
.kpi-val { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0.1rem 0; }
.kpi-change { font-size: 0.68rem; font-weight: 600; }
.kpi-change.pos { color: #22c55e; }
.kpi-change.neg { color: #ef4444; }

/* Dashboard Grid Layout */
.fin-dashboard-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.25rem; align-items: start; }
@media (max-width: 768px) {
    .fin-dashboard-grid { grid-template-columns: 1fr; }
}

/* Alertas */
.alert-section-bx { margin-bottom: 1.25rem; }
.alert-card-bx { display: flex; gap: 0.75rem; padding: 1rem; border-radius: 12px; border: 1px solid; }
.alert-card-bx.error { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.alert-card-bx.warning { background: #fffbeb; border-color: #fef3c7; color: #92400e; }
.ac-icon { font-size: 1.4rem; }
.ac-body h3 { font-size: 0.9rem; font-weight: 700; }
.ac-body p { font-size: 0.78rem; margin-top: 0.15rem; }
.btn-fin-small { padding: 0.25rem 0.5rem; border: none; border-radius: 6px; font-size: 0.72rem; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn-fin-small.primary { background: #3b82f6; color: #fff; }
.btn-fin-small.success { background: #22c55e; color: #fff; }

/* Cards Contenedores */
.chart-container-card, .menu-modulos-card { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.chart-container-card h3, .menu-modulos-card h3 { font-size: 0.9rem; font-weight: 700; color: #334155; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }

/* Módulos Menu List */
.modulos-list { display: flex; flex-direction: column; gap: 0.5rem; }
.modulo-item-link { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; padding: 0.6rem; border-radius: 9px; border: 1px solid #f1f5f9; transition: all 0.2s; }
.modulo-item-link:hover { border-color: #cbd5e1; background: #f8fafc; transform: translateX(2px); }
.mil-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.mil-body { flex-grow: 1; }
.mil-body h4 { font-size: 0.8rem; font-weight: 700; color: #1e293b; }
.mil-body p { font-size: 0.68rem; color: #64748b; }
.mil-arrow { font-size: 0.85rem; color: #94a3b8; }

/* Modales */
.modal-overlay { position: fixed; inset: 0; z-index: 9998; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box { background: #fff; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 100%; overflow: hidden; }
.modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #cbd5e1; }
.modal-close { background: none; border: none; font-size: 1.3rem; cursor: pointer; }
.modal-body { padding: 1.25rem; }
.modal-foot { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid #cbd5e1; background: #f8fafc; }
.btn-glass { padding: 0.45rem 1rem; border: 1px solid; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; background: #fff; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Gráfica Histórica de los últimos 6 meses (Bar)
    const histCtx = document.getElementById('historicalChart').getContext('2d');
    const histData = @json($ultimosMeses);
    
    new Chart(histCtx, {
        type: 'bar',
        data: {
            labels: histData.map(d => d.label),
            datasets: [
                {
                    label: 'Entradas',
                    data: histData.map(d => d.entradas),
                    backgroundColor: '#10b981',
                    borderRadius: 5,
                },
                {
                    label: 'Salidas',
                    data: histData.map(d => d.gastos),
                    backgroundColor: '#ef4444',
                    borderRadius: 5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('es-CO', {maximumFractionDigits: 0});
                        },
                        font: { size: 9 }
                    }
                },
                x: { ticks: { font: { size: 9 } } }
            }
        }
    });

    // 1.1 Evolución de intereses del año: causados vs cobrados (Line)
    @if(isset($evolucion) && count($evolucion) > 0)
    const evolucionData = @json($evolucion);
    const moneyTick = value => '$' + value.toLocaleString('es-CO', {maximumFractionDigits: 0});

    new Chart(document.getElementById('interesesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: evolucionData.map(d => d.label),
            datasets: [
                {
                    label: 'Causados (liquidados)',
                    data: evolucionData.map(d => d.intereses_causados),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.12)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Cobrados (pagados)',
                    data: evolucionData.map(d => d.intereses_cobrados),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    fill: true,
                    tension: 0.3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: moneyTick, font: { size: 9 } } },
                x: { ticks: { font: { size: 9 } } }
            }
        }
    });

    // 1.2 Liquidez acumulada del año (Line)
    new Chart(document.getElementById('liquidezChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: evolucionData.map(d => d.label),
            datasets: [{
                label: 'Liquidez acumulada (entradas - salidas)',
                data: evolucionData.map(d => d.liquidez_acumulada),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.12)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
            scales: {
                y: { ticks: { callback: moneyTick, font: { size: 9 } } },
                x: { ticks: { font: { size: 9 } } }
            }
        }
    });
    @endif

    // 2. Gráfica de Categorías de Gastos de este mes (Doughnut)
    @if(count($gastosCategoria) > 0)
        const catCtx = document.getElementById('categoriesChart').getContext('2d');
        const catData = @json($gastosCategoria);
        
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: catData.map(d => d.categoria ? d.categoria.nombre : 'Otros'),
                datasets: [{
                    data: catData.map(d => d.total),
                    backgroundColor: catData.map(d => d.categoria && d.categoria.color ? d.categoria.color : '#64748b'),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 10, font: { size: 9 } }
                    }
                }
            }
        });
    @endif
});
</script>
@endpush
