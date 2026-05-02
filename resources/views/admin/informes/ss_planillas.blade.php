@extends('layouts.app')
@section('modulo','SS por Planillas')
@push('styles')
<style>
.plan-card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:hidden;margin-bottom:.85rem;}
.plan-head{display:grid;grid-template-columns:1fr 130px 130px 130px 130px 130px;gap:.5rem;
           padding:.65rem 1rem;align-items:center;cursor:pointer;transition:background .12s;}
.plan-head:hover{background:#f8fafc;}
.plan-head.tiene-gap{border-left:4px solid #ef4444;}
.plan-head.ok{border-left:4px solid #10b981;}
.fact-row{display:grid;grid-template-columns:60px 110px 130px 100px 90px 90px 90px 90px 110px;
          gap:.35rem;padding:.45rem 1rem .45rem 2.5rem;font-size:.74rem;border-bottom:1px solid #f1f5f9;
          align-items:center;}
.fact-row.otro-periodo{background:#fff7ed;}
.fact-row.retiro{background:#f5f3ff;}
.fact-head{display:grid;grid-template-columns:60px 110px 130px 100px 90px 90px 90px 90px 110px;
           gap:.35rem;padding:.35rem 1rem .35rem 2.5rem;font-size:.64rem;font-weight:700;
           text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.badge{display:inline-block;padding:.1rem .45rem;border-radius:5px;font-size:.65rem;font-weight:700;}
.badge-ok{background:#dcfce7;color:#166534;}
.badge-other{background:#ffedd5;color:#9a3412;}
.badge-retiro{background:#ede9fe;color:#5b21b6;}
.badge-pagada{background:#dcfce7;color:#166534;}
.badge-abono{background:#fef3c7;color:#92400e;}
.badge-pendiente{background:#fee2e2;color:#991b1b;}
.kpi-box{background:#fff;border-radius:12px;padding:.9rem 1.1rem;box-shadow:0 1px 6px rgba(0,0,0,.06);border-top:3px solid var(--c);}
.kpi-box .v{font-size:1.15rem;font-weight:800;color:var(--c);}
.kpi-box .l{font-size:.71rem;font-weight:600;color:#64748b;margin-top:.2rem;}
</style>
@endpush
@section('contenido')
@php
$fmt = fn($v) => '$ '.number_format($v,0,',','.');
$mesesEs=['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$years = range(now()->year-3, now()->year);
@endphp

<div style="max-width:1280px;margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ route('admin.informes.financiero', ['mes'=>$mes,'anio'=>$anio]) }}"
           style="color:#64748b;font-size:.82rem;text-decoration:none;">← Estado Financiero</a>
        <h1 style="font-size:1.15rem;font-weight:700;color:#0d2550;flex:1;">
            📋 Facturas por Planilla SS — {{ $mesesEs[$mes] }} {{ $anio }}
        </h1>
        <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
            <select name="mes" style="padding:.4rem .65rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                @foreach($mesesEs as $n=>$nm) @if($n>0)<option value="{{ $n }}" {{ $mes==$n?'selected':'' }}>{{ $nm }}</option>@endif @endforeach
            </select>
            <select name="anio" style="padding:.4rem .65rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">
                @foreach($years as $y)<option value="{{ $y }}" {{ $anio==$y?'selected':'' }}>{{ $y }}</option>@endforeach
            </select>
            <button type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.4rem 1rem;font-size:.82rem;cursor:pointer;">Ver</button>
        </form>
    </div>

    {{-- KPIs --}}
    <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:.85rem;margin-bottom:1.5rem;">
        <div class="kpi-box" style="--c:#7c3aed;">
            <div class="v">{{ $fmt($totales['gasto_total']) }}</div>
            <div class="l">💸 Total Gasto SS</div>
        </div>
        <div class="kpi-box" style="--c:#10b981;">
            <div class="v">{{ $fmt($totales['ss_mismo_periodo']) }}</div>
            <div class="l">✅ SS Cobrado (mismo período)</div>
        </div>
        <div class="kpi-box" style="--c:#f59e0b;">
            <div class="v">{{ $fmt($totales['ss_otro_periodo']) }}</div>
            <div class="l">📅 SS de otro período</div>
        </div>
        <div class="kpi-box" style="--c:#94a3b8;">
            <div class="v">{{ $fmt($totales['ss_retiros']) }}</div>
            <div class="l">🔴 SS de retiros (núm=0)</div>
        </div>
        <div class="kpi-box" style="--c:#0ea5e9;">
            <div class="v">{{ $fmt($totales['ss_total_cobrado']) }}</div>
            <div class="l">📊 Total SS Cobrado</div>
        </div>
        <div class="kpi-box" style="--c:{{ $totales['diferencia'] > 0 ? '#ef4444' : '#10b981' }};">
            <div class="v">{{ $fmt($totales['diferencia']) }}</div>
            <div class="l">{{ $totales['diferencia'] > 0 ? '⚠️ Déficit SS' : '✅ Exceso SS' }}</div>
        </div>
    </div>

    {{-- Leyenda --}}
    <div style="display:flex;gap:.85rem;margin-bottom:1rem;flex-wrap:wrap;font-size:.74rem;">
        <div style="display:flex;align-items:center;gap:.35rem;">
            <div style="width:12px;height:12px;background:#f0fdf4;border:1px solid #10b981;border-radius:3px;"></div>
            <span style="color:#166534;font-weight:600;">Factura del mismo período ({{ $mesesEs[$mes] }} {{ $anio }})</span>
        </div>
        <div style="display:flex;align-items:center;gap:.35rem;">
            <div style="width:12px;height:12px;background:#fff7ed;border:1px solid #f59e0b;border-radius:3px;"></div>
            <span style="color:#92400e;font-weight:600;">Factura de OTRO período — explica el gap</span>
        </div>
        <div style="display:flex;align-items:center;gap:.35rem;">
            <div style="width:12px;height:12px;background:#f5f3ff;border:1px solid #8b5cf6;border-radius:3px;"></div>
            <span style="color:#5b21b6;font-weight:600;">Retiro (numero_factura=0) — SS pagado sin cobro al cliente</span>
        </div>
    </div>

    {{-- Planillas sin planos --}}
    @if($sinPlanos->count() > 0)
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:.85rem 1.1rem;margin-bottom:1.25rem;">
        <div style="font-size:.82rem;font-weight:700;color:#dc2626;margin-bottom:.5rem;">
            🚨 {{ $sinPlanos->count() }} planilla(s) SIN planos asociados en el sistema
        </div>
        @foreach($sinPlanos as $sp)
        <div style="display:flex;justify-content:space-between;font-size:.78rem;padding:.3rem 0;border-bottom:1px solid #fecaca;">
            <span style="color:#1e293b;font-weight:600;">{{ $sp->numero_planilla ?? 'SIN NÚMERO' }}
                <span style="color:#94a3b8;font-weight:400;">— {{ $sp->descripcion ?: $sp->pagado_a }}</span>
            </span>
            <span style="font-weight:700;color:#dc2626;">{{ $fmt($sp->gasto_total) }}</span>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Tabla de planillas y sus facturas --}}
    {{-- Cabecera fija de la tabla de resumen --}}
    <div style="display:grid;grid-template-columns:1fr 130px 130px 130px 130px 130px;gap:.5rem;
                padding:.45rem 1rem;background:#f1f5f9;border-radius:8px 8px 0 0;border:1px solid #e2e8f0;
                font-size:.67rem;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:0;">
        <span>Planilla / Descripción</span>
        <span style="text-align:right;color:#7c3aed;">Gasto SS</span>
        <span style="text-align:right;color:#10b981;">SS Mismo Per.</span>
        <span style="text-align:right;color:#f59e0b;">SS Otro Per.</span>
        <span style="text-align:right;color:#94a3b8;">SS Retiros</span>
        <span style="text-align:right;color:#ef4444;">Diferencia</span>
    </div>

    @foreach($resumen as $idx => $plan)
    @php
        $tieneGap   = abs($plan['diferencia']) > 100;
        $tieneOtro  = $plan['ss_otro_periodo'] > 0;
        $headBg     = $tieneGap ? '#fff' : '#fff';
        $leftColor  = $tieneGap ? '#ef4444' : '#10b981';
    @endphp
    <div class="plan-card" style="border-left:4px solid {{ $leftColor }};">

        {{-- Fila resumen (clickeable para expandir) --}}
        <div class="plan-head" onclick="togglePlan('plan{{ $idx }}')" id="head{{ $idx }}">
            {{-- Nombre planilla --}}
            <div>
                <div style="font-size:.82rem;font-weight:700;color:#0d2550;">
                    {{ $plan['numero_planilla'] ?? 'SIN NÚMERO' }}
                    @if($plan['cant_gastos'] > 1)
                        <span style="background:#fef2f2;color:#dc2626;border-radius:5px;padding:.1rem .4rem;font-size:.65rem;font-weight:700;margin-left:.35rem;">⚠️ x{{ $plan['cant_gastos'] }} gastos</span>
                    @endif
                </div>
                <div style="font-size:.71rem;color:#64748b;margin-top:.1rem;">
                    {{ Str::limit($plan['descripcion'], 70) }}
                </div>
                <div style="font-size:.68rem;color:#94a3b8;">
                    {{ sqldate($plan['fecha_gasto'])?->format('d/m/Y') ?? '—' }}
                    @if($plan['banco']) · {{ $plan['banco'] }} @endif
                    · {{ count($plan['facturas']) }} factura(s)
                    @if($tieneOtro)
                        <span style="color:#d97706;font-weight:700;">⚠️ hay facturas de otro período</span>
                    @endif
                </div>
            </div>
            {{-- Gasto --}}
            <div style="text-align:right;font-size:.88rem;font-weight:800;color:#7c3aed;">
                {{ $fmt($plan['gasto_total']) }}
            </div>
            {{-- SS mismo período --}}
            <div style="text-align:right;font-size:.85rem;font-weight:700;color:#10b981;">
                {{ $plan['ss_mismo_periodo'] > 0 ? $fmt($plan['ss_mismo_periodo']) : '—' }}
            </div>
            {{-- SS otro período --}}
            <div style="text-align:right;font-size:.85rem;font-weight:700;color:#f59e0b;">
                {{ $plan['ss_otro_periodo'] > 0 ? $fmt($plan['ss_otro_periodo']) : '—' }}
            </div>
            {{-- SS retiros --}}
            <div style="text-align:right;font-size:.85rem;font-weight:700;color:#94a3b8;">
                {{ $plan['ss_retiros'] > 0 ? $fmt($plan['ss_retiros']) : '—' }}
            </div>
            {{-- Diferencia --}}
            <div style="text-align:right;">
                @if(abs($plan['diferencia']) <= 100)
                    <span style="font-size:.8rem;color:#10b981;font-weight:700;">✅ Cuadra</span>
                @else
                    <div style="font-size:.9rem;font-weight:800;color:{{ $plan['diferencia'] > 0 ? '#dc2626' : '#10b981' }};">
                        {{ $plan['diferencia'] > 0 ? '▼ ' : '▲ ' }}{{ $fmt(abs($plan['diferencia'])) }}
                    </div>
                @endif
                <div style="font-size:.62rem;color:#94a3b8;margin-top:.1rem;">▾ ver facturas</div>
            </div>
        </div>

        {{-- Detalle de facturas (oculto por defecto, expandible) --}}
        <div id="plan{{ $idx }}" style="display:none;">
            @if(count($plan['facturas']) === 0)
            <div style="padding:1rem 2.5rem;font-size:.78rem;color:#ef4444;font-weight:600;">
                ⚠️ Esta planilla no tiene planos ni facturas vinculadas en el sistema.
            </div>
            @else
            {{-- Cabecera de facturas --}}
            <div class="fact-head">
                <span>#Factura</span>
                <span>Período</span>
                <span>Razón Social</span>
                <span>Estado</span>
                <span style="text-align:right;">EPS</span>
                <span style="text-align:right;">AFP</span>
                <span style="text-align:right;">ARL</span>
                <span style="text-align:right;">Caja</span>
                <span style="text-align:right;">Total SS</span>
            </div>
            @php $subTotalSS = 0; @endphp
            @foreach($plan['facturas'] as $f)
            @php
                $esOtro   = $f['es_otro_periodo'];
                $esRetiro = $f['es_retiro'];
                $rowClass = $esRetiro ? 'retiro' : ($esOtro ? 'otro-periodo' : '');
                $subTotalSS += $f['total_ss'];
                $badgeEstado = match($f['estado'] ?? '') {
                    'pagada'   => 'badge-pagada',
                    'abono'    => 'badge-abono',
                    default    => 'badge-pendiente',
                };
            @endphp
            <div class="fact-row {{ $rowClass }}">
                {{-- # Factura --}}
                <div>
                    @if($esRetiro)
                        <span class="badge badge-retiro">RETIRO</span>
                    @elseif($f['numero_factura'])
                        <span style="font-weight:700;color:#1e293b;">#{{ $f['numero_factura'] }}</span>
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </div>
                {{-- Período --}}
                <div>
                    @if($esOtro && !$esRetiro)
                        <span class="badge badge-other">{{ $f['periodo'] }}</span>
                    @else
                        <span style="color:#334155;">{{ $f['periodo'] }}</span>
                    @endif
                </div>
                {{-- Razón Social --}}
                <div style="color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $f['razon_social'] }}">
                    {{ Str::limit($f['razon_social'] ?? '—', 22) }}
                    <div style="font-size:.62rem;color:#94a3b8;">{{ $f['cant_empleados'] }} emp.</div>
                </div>
                {{-- Estado --}}
                <div>
                    <span class="badge {{ $badgeEstado }}">{{ $f['estado'] ?? '—' }}</span>
                    @if($f['fecha_pago'])
                    <div style="font-size:.62rem;color:#94a3b8;margin-top:.1rem;">
                        {{ sqldate($f['fecha_pago'])?->format('d/m/Y') }}
                    </div>
                    @endif
                </div>
                {{-- EPS --}}
                <div style="text-align:right;color:#0ea5e9;font-weight:600;">{{ $fmt($f['v_eps']) }}</div>
                {{-- AFP --}}
                <div style="text-align:right;color:#10b981;font-weight:600;">{{ $fmt($f['v_afp']) }}</div>
                {{-- ARL --}}
                <div style="text-align:right;color:#8b5cf6;font-weight:600;">{{ $fmt($f['v_arl']) }}</div>
                {{-- Caja --}}
                <div style="text-align:right;color:#f59e0b;font-weight:600;">{{ $fmt($f['v_caja']) }}</div>
                {{-- Total SS --}}
                <div style="text-align:right;font-weight:800;color:{{ $esOtro && !$esRetiro ? '#d97706' : ($esRetiro ? '#8b5cf6' : '#334155') }};">
                    {{ $fmt($f['total_ss']) }}
                </div>
            </div>
            @endforeach

            {{-- Fila totales de la planilla --}}
            <div style="display:grid;grid-template-columns:60px 110px 130px 100px 90px 90px 90px 90px 110px;
                        gap:.35rem;padding:.5rem 1rem .5rem 2.5rem;background:#f8fafc;
                        border-top:2px solid #e2e8f0;font-size:.76rem;font-weight:700;">
                <span></span><span></span><span style="color:#64748b;">SUBTOTAL</span><span></span>
                <span style="text-align:right;color:#0ea5e9;">{{ $fmt(collect($plan['facturas'])->sum('v_eps')) }}</span>
                <span style="text-align:right;color:#10b981;">{{ $fmt(collect($plan['facturas'])->sum('v_afp')) }}</span>
                <span style="text-align:right;color:#8b5cf6;">{{ $fmt(collect($plan['facturas'])->sum('v_arl')) }}</span>
                <span style="text-align:right;color:#f59e0b;">{{ $fmt(collect($plan['facturas'])->sum('v_caja')) }}</span>
                <span style="text-align:right;color:#7c3aed;">{{ $fmt($subTotalSS) }}</span>
            </div>
            @endif
        </div>

    </div>
    @endforeach

    {{-- Totales globales --}}
    <div style="display:grid;grid-template-columns:1fr 130px 130px 130px 130px 130px;gap:.5rem;
                padding:.65rem 1rem;background:#f1f5f9;border-radius:0 0 8px 8px;border:1px solid #e2e8f0;
                font-size:.8rem;font-weight:700;margin-top:0;">
        <span style="color:#0d2550;">TOTAL — {{ $totales['cant_planillas'] }} planilla(s)</span>
        <span style="text-align:right;color:#7c3aed;">{{ $fmt($totales['gasto_total']) }}</span>
        <span style="text-align:right;color:#10b981;">{{ $fmt($totales['ss_mismo_periodo']) }}</span>
        <span style="text-align:right;color:#f59e0b;">{{ $fmt($totales['ss_otro_periodo']) }}</span>
        <span style="text-align:right;color:#94a3b8;">{{ $fmt($totales['ss_retiros']) }}</span>
        <span style="text-align:right;color:{{ $totales['diferencia'] > 0 ? '#ef4444' : '#10b981' }};">
            {{ $totales['diferencia'] > 0 ? '▼ ' : '▲ ' }}{{ $fmt(abs($totales['diferencia'])) }}
        </span>
    </div>

    {{-- Nota explicativa --}}
    <div style="margin-top:1.25rem;background:#f0f9ff;border-left:4px solid #0ea5e9;border-radius:0 10px 10px 0;padding:.85rem 1.1rem;font-size:.78rem;color:#0c4a6e;line-height:1.6;">
        <strong>¿Cómo leer este reporte?</strong><br>
        • <strong style="color:#10b981;">Mismo período:</strong> SS cobrado en facturas de {{ $mesesEs[$mes] }} {{ $anio }} — entra al recaudo SS del mes.<br>
        • <strong style="color:#f59e0b;">Otro período:</strong> SS de facturas de meses distintos vinculadas a esta planilla. Ese SS <em>no entra al recaudo de {{ $mesesEs[$mes] }}</em>, pero el gasto sí se paga aquí — <strong>esta columna explica el gap principal.</strong><br>
        • <strong style="color:#8b5cf6;">Retiros (núm=0):</strong> Empleados que se retiraron. Su SS se pagó pero no se cobró al cliente (factura sin número).<br>
        • <strong style="color:#ef4444;">Diferencia:</strong> Gasto − (Mismo per. + Otro per. + Retiros). Debería ser ≈ 0 si todos los planos están correctamente vinculados.
    </div>

</div>

@push('scripts')
<script>
function togglePlan(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
// Abrir automáticamente las planillas con diferencia
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.plan-card').forEach((card, i) => {
        const borde = card.style.borderLeftColor;
        // Si tiene borde rojo (gap), expandir
        if (borde.includes('239') || borde.includes('ef4444')) {
            const inner = card.querySelector('[id^="plan"]');
            if (inner) inner.style.display = 'block';
        }
    });
});
</script>
@endpush
@endsection
