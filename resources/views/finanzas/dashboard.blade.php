@extends('layouts.app')

@section('titulo', 'Finanzas')
@section('modulo', 'Dashboard')

@section('contenido')
@include('finanzas.partials._responsive_fin')

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

    {{-- Header --}}
    <div class="fin-header-section">
        <div class="header-text">
            <h1>💰 Mi Contabilidad Privada</h1>
            <p>Resumen financiero consolidado personal, cobros y patrimonio.</p>
        </div>
        <div class="header-actions" style="display:flex; gap:0.5rem;">
            <button @click="openEntradaRapida = true" class="btn-fin success" style="background:#10b981;">⚡ Entrada Rápida</button>
            <button @click="openGastoRapido = true"   class="btn-fin success" style="background:#ef4444;">⚡ Gasto Rápido</button>
        </div>
    </div>

    {{-- Cripto Widget — carga en el shell (caché CoinGecko) --}}
    <div class="cripto-widget-bx">
        <div class="cw-title">🪙 Cotización Tether (USDT):</div>
        <div class="cw-values">
            <span class="cw-cop"><strong>${{ number_format($criptoPrecio['precio_cop'], 0, ',', '.') }} COP</strong></span>
            <span class="cw-usd">${{ number_format($criptoPrecio['precio_usd'], 2) }} USD</span>
            <span class="cw-date">({{ \Carbon\Carbon::parse($criptoPrecio['actualizado'])->format('H:i') }})</span>
        </div>
        @if($criptoPrecio['fallback'])<span class="badge-warn" style="font-size:0.65rem;">Fallback</span>@endif
    </div>

    {{-- KPIs skeleton → llenado por /api/resumen --}}
    <div id="kpis-skeleton" class="fin-kpis-grid">
        @for($i=0;$i<4;$i++)
        <div class="kpi-card sk-card">
            <div class="kpi-icon"><div class="sk-icon-ph" style="width: 32px; height: 32px;"></div></div>
            <div class="kpi-content" style="width: 100%;">
                <div class="sk-title-ph" style="margin-bottom: 0.4rem;"></div>
                <div class="sk-val-ph" style="width: 80%;"></div>
            </div>
        </div>
        @endfor
    </div>
    <div id="kpis-real" style="display:none;">
        <div class="fin-kpis-grid" id="kpis-grid"></div>
        <div id="intereses-row" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;margin-top:-0.5rem;"></div>
    </div>

    <div class="fin-dashboard-grid">

        {{-- ── Panel Izquierdo ── --}}
        <div class="fin-main-panel">

            {{-- Alertas mora/faltantes → /api/alertas --}}
            <div id="alertas-section" style="display:none;" class="alert-section-bx"></div>

            {{-- Gráfica histórica 6 meses --}}
            <div class="chart-container-card">
                <h3>📈 Entradas vs Egresos (Últimos 6 meses)</h3>
                <div style="height:250px;position:relative;">
                    <div class="sk-chart" id="hist-sk"></div>
                    <canvas id="historicalChart" style="display:none;"></canvas>
                </div>
                <div style="margin-top:1rem;">
                    <button @click="openConsolidadoGlobal = true; $dispatch('load-consolidado')"
                            class="btn-fin" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;width:100%;border:none;padding:0.75rem;border-radius:12px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(59,130,246,0.25);display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                        🌐 Ver Consolidado Global de Saldos
                    </button>
                </div>
            </div>

            {{-- Gráfica intereses --}}
            <div class="chart-container-card" style="margin-top:1.25rem;">
                <h3>🤝 Intereses de Préstamos {{ $anio }} (Causados vs Cobrados)</h3>
                <div style="height:220px;position:relative;">
                    <div class="sk-chart" id="int-sk"></div>
                    <canvas id="interesesChart" style="display:none;"></canvas>
                </div>
            </div>

            {{-- Gráfica liquidez --}}
            <div class="chart-container-card" style="margin-top:1.25rem;">
                <h3>💵 Evolución de Liquidez {{ $anio }} (Acumulado del año)</h3>
                <div style="height:220px;position:relative;">
                    <div class="sk-chart" id="liq-sk"></div>
                    <canvas id="liquidezChart" style="display:none;"></canvas>
                </div>
            </div>
        </div>

        {{-- ── Panel Derecho ── --}}
        <div class="fin-side-panel">

            {{-- Cuentas → /api/cuentas --}}
            <div class="menu-modulos-card" style="margin-bottom:1rem;">
                <h3>💳 Mis Cuentas</h3>
                <div id="cuentas-list">
                    @for($i=0;$i<3;$i++)<div class="sk-cuenta"></div>@endfor
                </div>
            </div>

            {{-- Módulos (estáticos) --}}
            <div class="menu-modulos-card">
                <h3>📂 Módulos Financieros</h3>
                <div class="modulos-list">
                    @php $modulos = [
                        ['finanzas.cuentas.index','💳','eef2ff','4338ca','Cuentas y Bolsillos','Banco, efectivo y transferencias'],
                        ['finanzas.entradas.index','📥','d1fae5','065f46','Entradas / Fuentes','Ingresos fijos y variables'],
                        ['finanzas.gastos.index','💸','e0f2fe','0369a1','Transacciones Diarias','Gastos cotidianos e ingresos extras'],
                        ['finanzas.prestamos.index','🤝','fef3c7','92400e','Préstamos a Terceros','Control de deudas e intereses'],
                        ['finanzas.prestamos.cuenta-corriente','💼','f3e8ff','6b21a8','Cuenta Corriente (Servicios)','Cliente recurrente de trabajos'],
                        ['finanzas.inversiones.index','🪙','e0f2fe','075985','Inversiones Cripto','Binance USDT y rentabilidades'],
                        ['finanzas.patrimonio.index','🏠','e0f7fa','006064','Patrimonio Físico','Vehículos, apartamentos y gastos'],
                        ['finanzas.proyectos.index','🏗️','f0fdf4','166534','Proyectos de Negocio','CuentaFacil y balances individuales'],
                    ] @endphp
                    @foreach($modulos as [$ruta,$ico,$bg,$fg,$titulo,$sub])
                    <a href="{{ route($ruta) }}" class="modulo-item-link">
                        <span class="mil-icon" style="background:#{{ $bg }};color:#{{ $fg }};">{{ $ico }}</span>
                        <div class="mil-body"><h4>{{ $titulo }}</h4><p>{{ $sub }}</p></div>
                        <span class="mil-arrow">→</span>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Dona distribución gastos → /api/resumen --}}
            <div class="chart-container-card" style="margin-top:1rem;">
                <h3>📊 Distribución de Gastos (Mes Actual)</h3>
                <div style="height:200px;position:relative;display:flex;justify-content:center;" id="dona-wrap">
                    <div class="sk-chart" id="dona-sk"></div>
                    <canvas id="categoriesChart" style="display:none;"></canvas>
                    <div id="dona-empty" style="display:none;align-items:center;justify-content:center;color:#64748b;font-size:0.85rem;height:200px;">No hay gastos registrados en este mes.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modales registro rápido --}}
    @include('finanzas.partials._gasto_rapido_modal')
    @include('finanzas.partials._entrada_rapida_modal')

    {{-- Modal Consolidado Global → datos por /api/consolidado --}}
    <div x-show="openConsolidadoGlobal"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"  x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         x-cloak class="modal-overlay-bx" @click.self="openConsolidadoGlobal = false"
         @load-consolidado.window="$nextTick(() => window.cargarConsolidado && window.cargarConsolidado())">
        <div class="modal-box-bx" style="max-width:650px;border-radius:16px;">
            <div class="modal-head-bx" style="background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;display:flex;align-items:center;justify-content:space-between;padding:1.25rem;border-bottom:1px solid #cbd5e1;">
                <div>
                    <h3 style="color:#fff;margin:0;font-size:1.2rem;">🌐 Consolidado Global de Saldos</h3>
                    <p style="margin:2px 0 0;font-size:0.75rem;color:#cbd5e1;">Resumen histórico y acumulación de activos financieros</p>
                </div>
                <button @click="openConsolidadoGlobal = false" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:rgba(255,255,255,0.7);">&times;</button>
            </div>
            <div class="modal-body-bx" style="padding:1.5rem;max-height:480px;overflow-y:auto;background:#f8fafc;">
                <div id="consolidado-skeleton">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.5rem;">
                        @for($i=0;$i<4;$i++)<div class="sk-consolidado-card"></div>@endfor
                    </div>
                    <div class="sk-line" style="height:120px;border-radius:12px;"></div>
                </div>
                <div id="consolidado-real" style="display:none;"></div>
            </div>
            <div class="modal-foot-bx" style="display:flex;justify-content:end;padding:1rem;border-top:1px solid #cbd5e1;background:#f8fafc;">
                <button @click="openConsolidadoGlobal = false" style="padding:0.5rem 1.25rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.8rem;font-weight:600;cursor:pointer;background:#fff;color:#475569;">Cerrar</button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.finanzas-container{max-width:1040px;margin:0 auto;padding:0.5rem;}
.cripto-widget-bx{display:flex;align-items:center;gap:0.5rem;background:#fff;border:1px solid #e2e8f0;padding:0.5rem 0.75rem;border-radius:9px;font-size:0.78rem;color:#334155;margin-bottom:1rem;width:fit-content;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
.cw-cop{color:#8b5cf6;font-size:0.85rem;}.cw-usd{color:#64748b;}.cw-date{color:#94a3b8;font-size:0.7rem;}
.kpi-change{font-size:0.68rem;font-weight:600;}.kpi-change.pos{color:#22c55e;}.kpi-change.neg{color:#ef4444;}
.fin-dashboard-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem;align-items:start;}
@media(max-width:768px){.fin-dashboard-grid{grid-template-columns:1fr;}}
.alert-section-bx{margin-bottom:1.25rem;}
.alert-card-bx{display:flex;gap:0.75rem;padding:1rem;border-radius:12px;border:1px solid;}
.alert-card-bx.error{background:#fef2f2;border-color:#fca5a5;color:#991b1b;}
.alert-card-bx.warning{background:#fffbeb;border-color:#fef3c7;color:#92400e;}
.ac-icon{font-size:1.4rem;}.ac-body h3{font-size:0.9rem;font-weight:700;}.ac-body p{font-size:0.78rem;margin-top:0.15rem;}
.btn-fin-small{padding:0.25rem 0.5rem;border:none;border-radius:6px;font-size:0.72rem;font-weight:600;cursor:pointer;text-decoration:none;}
.btn-fin-small.primary{background:#3b82f6;color:#fff;}.btn-fin-small.success{background:#22c55e;color:#fff;}
.chart-container-card,.menu-modulos-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1.25rem;box-shadow:0 2px 8px rgba(0,0,0,0.04);}
.chart-container-card h3,.menu-modulos-card h3{font-size:0.9rem;font-weight:700;color:#334155;margin-bottom:1rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.5rem;}
.modulos-list{display:flex;flex-direction:column;gap:0.5rem;}
.modulo-item-link{display:flex;align-items:center;gap:0.75rem;text-decoration:none;padding:0.6rem;border-radius:9px;border:1px solid #f1f5f9;transition:all 0.2s;}
.modulo-item-link:hover{border-color:#cbd5e1;background:#f8fafc;transform:translateX(2px);}
.mil-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
.mil-body{flex-grow:1;}.mil-body h4{font-size:0.8rem;font-weight:700;color:#1e293b;}.mil-body p{font-size:0.68rem;color:#64748b;}
.mil-arrow{font-size:0.85rem;color:#94a3b8;}
/* Skeletons */
@keyframes shimmer{0%{background-position:-600px 0}100%{background-position:600px 0}}
.sk-line,.sk-chart,.sk-cuenta,.sk-card,.sk-consolidado-card{background:linear-gradient(90deg,#f0f4f8 25%,#e2e8f0 50%,#f0f4f8 75%);background-size:600px 100%;animation:shimmer 1.4s infinite linear;border-radius:8px;}
.sk-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;box-shadow:0 2px 8px rgba(0,0,0,0.04);display:flex;flex-direction:column;gap:0.5rem;}
.sk-icon-ph{width:36px;height:36px;border-radius:50%;background:linear-gradient(90deg,#f0f4f8 25%,#e2e8f0 50%,#f0f4f8 75%);background-size:600px 100%;animation:shimmer 1.4s infinite linear;}
.sk-title-ph{height:10px;width:60%;background:linear-gradient(90deg,#f0f4f8 25%,#e2e8f0 50%,#f0f4f8 75%);background-size:600px 100%;animation:shimmer 1.4s infinite linear;border-radius:4px;}
.sk-val-ph{height:24px;width:80%;background:linear-gradient(90deg,#f0f4f8 25%,#e2e8f0 50%,#f0f4f8 75%);background-size:600px 100%;animation:shimmer 1.4s infinite linear;border-radius:6px;}
.sk-chart{width:100%;height:100%;border-radius:8px;position:absolute;top:0;left:0;}
.sk-cuenta{height:36px;margin-bottom:0.4rem;border-radius:8px;}
.sk-consolidado-card{height:80px;border-radius:12px;}
</style>
@endpush

@push('scripts')
<script>
(function(){
const ANIO={{ $anio }}, MES={{ $mes }};
const CSRF=document.querySelector('meta[name="csrf-token"]')?.content??'';
const BASE='/finanzas/api';
let consolidadoCargado=false;

const fmt=v=>'$'+Number(v).toLocaleString('es-CO',{maximumFractionDigits:0});
const pct=v=>v==null?'':(v>=0?`+${v.toFixed(1)}%`:`${v.toFixed(1)}%`);
const $=id=>document.getElementById(id);
const show=id=>{const e=$(id);if(e)e.style.display='';};
const hide=id=>{const e=$(id);if(e)e.style.display='none';};

async function get(url){
    const r=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(!r.ok)throw new Error('HTTP '+r.status);
    return r.json();
}

// ── 1. Resumen + Dona ──────────────────────────────────────────
async function cargarResumen(){
    try{
        const{resumen:r,gastos_categoria:cats}=await get(`${BASE}/resumen?anio=${ANIO}&mes=${MES}`);
        const defs=[
            {label:'Total Entradas',    value:fmt(r.entradas),          change:r.entradas_cambio, color:'#10b981',icon:'📥'},
            {label:'Gastos Habituales', value:fmt(r.gastos_habituales),  change:r.gastos_cambio,   color:'#ef4444',icon:'📤'},
            {label:'Balance del Mes',   value:fmt(r.balance),            change:null,              color:r.balance>=0?'#10b981':'#ef4444',icon:'⚖️'},
            {label:'Préstamos del Mes', value:fmt(r.prestado),           change:null,              color:'#f59e0b',icon:'🤝'},
        ];
        $('kpis-grid').innerHTML=defs.map(k=>`
            <div class="kpi-card" style="border-left:4px solid ${k.color};">
                <div class="kpi-icon">${k.icon}</div>
                <div class="kpi-content">
                    <span class="kpi-label">${k.label}</span>
                    <span class="kpi-val">${k.value}</span>
                    ${k.change!=null?`<span class="kpi-change ${k.change>=0?'pos':'neg'}">${k.change>=0?'▲':'▼'} ${Math.abs(k.change).toFixed(1)}% vs mes ant.</span>`:''}
                </div>
            </div>`).join('');
        $('intereses-row').innerHTML=`
            <div style="flex:1;min-width:220px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:0.6rem 1rem;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:0.72rem;font-weight:700;color:#92400e;text-transform:uppercase;">📈 Intereses causados (mes)</span>
                <strong style="color:#92400e;font-size:1rem;">${fmt(r.intereses_causados??0)}</strong>
            </div>
            <div style="flex:1;min-width:220px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:0.6rem 1rem;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:0.72rem;font-weight:700;color:#166534;text-transform:uppercase;">💰 Intereses cobrados (mes)</span>
                <strong style="color:#166534;font-size:1rem;">${fmt(r.intereses_cobrados??0)}</strong>
            </div>`;
        hide('kpis-skeleton'); show('kpis-real');
        // Dona
        hide('dona-sk');
        if(cats.length>0){
            show('categoriesChart');
            new Chart($('categoriesChart').getContext('2d'),{
                type:'doughnut',
                data:{labels:cats.map(d=>d.nombre),datasets:[{data:cats.map(d=>d.total),backgroundColor:cats.map(d=>d.color),borderWidth:1}]},
                options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{boxWidth:10,font:{size:9}}}}}
            });
        }else{
            const e=$('dona-empty'); if(e)e.style.display='flex';
        }
    }catch(e){
        hide('kpis-skeleton'); show('kpis-real');
        $('kpis-grid').innerHTML='<div style="color:#ef4444;font-size:0.8rem;padding:0.5rem;">Error cargando datos del mes</div>';
        hide('dona-sk');
    }
}

// ── 2. Gráficas evolución ──────────────────────────────────────
async function cargarEvolucion(){
    const tick=v=>'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});
    try{
        const{evolucion:ev,ultimos_meses:um}=await get(`${BASE}/evolucion?anio=${ANIO}`);
        hide('hist-sk'); show('historicalChart');
        new Chart($('historicalChart').getContext('2d'),{
            type:'bar',
            data:{labels:um.map(d=>d.label),datasets:[
                {label:'Entradas',data:um.map(d=>d.entradas),backgroundColor:'#10b981',borderRadius:5},
                {label:'Salidas', data:um.map(d=>d.gastos),  backgroundColor:'#ef4444',borderRadius:5}
            ]},
            options:{responsive:true,maintainAspectRatio:false,
                plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:10}}}},
                scales:{y:{beginAtZero:true,ticks:{callback:tick,font:{size:9}}},x:{ticks:{font:{size:9}}}}}
        });
        hide('int-sk'); show('interesesChart');
        new Chart($('interesesChart').getContext('2d'),{
            type:'line',
            data:{labels:ev.map(d=>d.label),datasets:[
                {label:'Causados (liquidados)',data:ev.map(d=>d.intereses_causados),borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,0.12)',fill:true,tension:0.3},
                {label:'Cobrados (pagados)',   data:ev.map(d=>d.intereses_cobrados),borderColor:'#10b981',backgroundColor:'rgba(16,185,129,0.12)', fill:true,tension:0.3}
            ]},
            options:{responsive:true,maintainAspectRatio:false,
                plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:10}}}},
                scales:{y:{beginAtZero:true,ticks:{callback:tick,font:{size:9}}},x:{ticks:{font:{size:9}}}}}
        });
        hide('liq-sk'); show('liquidezChart');
        new Chart($('liquidezChart').getContext('2d'),{
            type:'line',
            data:{labels:ev.map(d=>d.label),datasets:[
                {label:'Liquidez acumulada',data:ev.map(d=>d.liquidez_acumulada),borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.12)',fill:true,tension:0.3}
            ]},
            options:{responsive:true,maintainAspectRatio:false,
                plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:10}}}},
                scales:{y:{ticks:{callback:tick,font:{size:9}}},x:{ticks:{font:{size:9}}}}}
        });
    }catch(e){
        ['hist-sk','int-sk','liq-sk'].forEach(id=>{
            const el=$(id); if(el)el.innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;font-size:0.8rem;">Error cargando gráfica</div>';
        });
    }
}

// ── 3. Cuentas ────────────────────────────────────────────────
async function cargarCuentas(){
    try{
        const cuentas=await get(`${BASE}/cuentas`);
        const adminUrl='{{ route("finanzas.cuentas.index") }}';
        $('cuentas-list').innerHTML=cuentas.map(c=>`
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.45rem 0.6rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:0.8rem;margin-bottom:0.4rem;">
                <span>${c.icono??'💳'} <strong>${c.nombre}</strong></span>
                <span style="font-weight:700;color:${c.saldo_actual>=0?'#0f172a':'#ef4444'};">${fmt(c.saldo_actual)}</span>
            </div>`).join('')+
            `<a href="${adminUrl}" style="font-size:0.72rem;font-weight:700;color:#4f46e5;text-decoration:none;text-align:right;display:block;margin-top:0.25rem;">Administrar cuentas y transferencias →</a>`;
    }catch(e){
        $('cuentas-list').innerHTML='<div style="color:#ef4444;font-size:0.8rem;">Error cargando cuentas</div>';
    }
}

// ── 4. Alertas ────────────────────────────────────────────────
async function cargarAlertas(){
    try{
        const{prestamos_mora:mora,gastos_faltantes:faltantes}=await get(`${BASE}/alertas?anio=${ANIO}&mes=${MES}`);
        const sec=$('alertas-section'); let html='';
        if(mora.length>0) html+=`<div class="alert-card-bx error"><div class="ac-icon">⚠️</div><div class="ac-body">
            <h3>Deudores en Mora (Vencidos)</h3><p>Las siguientes personas tienen préstamos activos vencidos hace más de 30 días:</p>
            <div class="ac-list" style="margin-top:0.5rem;display:flex;flex-direction:column;gap:0.5rem;">
            ${mora.map(p=>`<div style="display:flex;justify-content:space-between;align-items:center;background:rgba(239,68,68,0.06);padding:0.4rem 0.6rem;border-radius:6px;font-size:0.8rem;">
                <div><strong>${p.nombre_deudor}</strong> <span style="color:#64748b;">(Debe: ${fmt(p.saldo_actual)} COP)</span>
                <span class="badge-err" style="font-size:0.65rem;margin-left:5px;">${p.dias_mora} días mora</span></div>
                <div style="display:flex;gap:0.4rem;"><a href="${p.url_ficha}" class="btn-fin-small primary">Ficha</a>
                <form action="${p.url_whatsapp}" method="POST" style="display:inline;"><input type="hidden" name="_token" value="${CSRF}">
                <button type="submit" class="btn-fin-small success">🟢 Cobrar WA</button></form></div></div>`).join('')}
            </div></div></div>`;
        if(faltantes.length>0) html+=`<div class="alert-card-bx warning" style="margin-top:0.75rem;"><div class="ac-icon">💡</div><div class="ac-body">
            <h3>Gastos Recurrentes Pendientes</h3><p>Aún no has registrado estos gastos mensuales obligatorios:</p>
            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.5rem;">
            ${faltantes.map(g=>`<span class="badge-warn" style="font-size:0.75rem;">${g.icono} ${g.nombre}</span>`).join('')}
            </div></div></div>`;
        if(html){sec.innerHTML=html; sec.style.display='';}
    }catch(e){}
}

// ── 5. Consolidado (solo al abrir modal) ──────────────────────
window.cargarConsolidado=async function(){
    if(consolidadoCargado)return;
    try{
        const c=await get(`${BASE}/consolidado`);
        consolidadoCargado=true;
        const filas=(c.proyectos??[]).map(p=>`
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:0.5rem;font-weight:600;color:#334155;">${p.nombre}</td>
                <td style="padding:0.5rem;text-align:right;color:#10b981;">${fmt(p.entradas)}</td>
                <td style="padding:0.5rem;text-align:right;color:#ef4444;">${fmt(p.salidas)}</td>
                <td style="padding:0.5rem;text-align:right;font-weight:700;color:${p.saldo>=0?'#10b981':'#ef4444'};">${fmt(p.saldo)}</td>
            </tr>`).join('')||'<tr><td colspan="4" style="text-align:center;padding:1rem;color:#94a3b8;">No hay proyectos activos.</td></tr>';
        $('consolidado-real').innerHTML=`
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.5rem;">
            ${[['💵 Liquidez Personal',c.liquidez_personal,'#1e293b','Entradas menos Salidas acumuladas'],
               ['🪙 Inversiones (Cripto)',c.inversiones_cripto,'#2563eb','Valor actual de mercado'],
               ['🏠 Patrimonio',c.patrimonio_total,'#006064','Valor actual estimado'],
               ['🤝 Prestado (Cartera)',c.prestado_cartera,'#d97706','Saldo pendiente de cobro']
              ].map(([lbl,val,col,nota])=>`
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <span style="display:block;font-size:0.72rem;font-weight:700;color:#64748b;margin-bottom:0.25rem;text-transform:uppercase;">${lbl}</span>
                    <span style="font-size:1.2rem;font-weight:800;color:${col};">${fmt(val)}</span>
                    <small style="display:block;font-size:0.65rem;color:#94a3b8;margin-top:2px;">${nota}</small>
                </div>`).join('')}
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;">
                <h4 style="margin:0 0 0.75rem;font-size:0.85rem;font-weight:800;color:#334155;">🏗️ Cajas de Proyectos (Activos)</h4>
                <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:0.75rem;text-align:left;">
                    <thead><tr style="border-bottom:2px solid #e2e8f0;color:#64748b;">
                        <th style="padding:0.4rem 0.5rem;font-weight:700;">Proyecto</th>
                        <th style="padding:0.4rem 0.5rem;text-align:right;font-weight:700;">Entradas</th>
                        <th style="padding:0.4rem 0.5rem;text-align:right;font-weight:700;">Gastado</th>
                        <th style="padding:0.4rem 0.5rem;text-align:right;font-weight:700;color:#0f172a;">Quedan</th>
                    </tr></thead>
                    <tbody>${filas}</tbody>
                    ${c.proyectos?.length>0?`<tfoot><tr style="border-top:2px solid #cbd5e1;background:#f8fafc;font-weight:bold;">
                        <td style="padding:0.5rem;font-size:0.75rem;color:#475569;">TOTAL CAJAS</td><td colspan="2"></td>
                        <td style="padding:0.5rem;text-align:right;font-size:0.78rem;font-weight:800;">${fmt(c.total_saldo_proyectos)}</td>
                    </tr></tfoot>`:''}
                </table></div>
            </div>
            <div style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 10px 15px -3px rgba(16,185,129,0.2);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="display:block;font-size:0.75rem;font-weight:700;color:rgba(255,255,255,0.85);text-transform:uppercase;letter-spacing:0.5px;">💰 Liquidez Global Requerida</span>
                        <span style="font-size:0.65rem;color:rgba(255,255,255,0.75);display:block;margin-top:2px;">(Liquidez Personal + Saldo Disponible en Proyectos)</span>
                    </div>
                    <span style="font-size:1.5rem;font-weight:950;color:#fff;">${fmt(c.liquidez_global)}</span>
                </div>
            </div>`;
        hide('consolidado-skeleton'); show('consolidado-real');
    }catch(e){
        $('consolidado-real').innerHTML='<div style="color:#ef4444;padding:1rem;font-size:0.85rem;">Error cargando consolidado.</div>';
        hide('consolidado-skeleton'); show('consolidado-real');
    }
};

// ── Lanzar todo en paralelo ───────────────────────────────────
document.addEventListener('DOMContentLoaded',function(){
    Promise.allSettled([cargarResumen(),cargarEvolucion(),cargarCuentas(),cargarAlertas()]);
});
})();
</script>
@endpush
