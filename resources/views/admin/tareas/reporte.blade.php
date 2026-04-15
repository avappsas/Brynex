@extends('layouts.app')
@section('titulo', 'Reporte de Tareas')
@section('modulo', 'Rendimiento por Trabajador')

@push('styles')
<style>
.rep-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem; }
.rep-title  { font-size:1.3rem; font-weight:700; color:#1e293b; }
.filtros-bar { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem; }
.filtros-bar select, .filtros-bar input { border:1px solid #cbd5e1; border-radius:8px; padding:.38rem .65rem; font-size:.8rem; background:#fff; }
.btn-ver { background:#2563eb; color:#fff; border:none; border-radius:8px; padding:.38rem 1rem; font-size:.8rem; font-weight:600; cursor:pointer; }

.tabla-wrap { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow-x:auto; margin-bottom:1.5rem; }
.tbl-rep { width:100%; border-collapse:collapse; font-size:.82rem; }
.tbl-rep thead th { background:linear-gradient(135deg,#0a1628,#0d2550); color:rgba(255,255,255,.85); padding:.65rem .85rem; text-align:left; font-size:.73rem; font-weight:600; white-space:nowrap; }
.tbl-rep tbody tr { border-bottom:1px solid #f1f5f9; }
.tbl-rep tbody tr:hover { background:#f8fafc; }
.tbl-rep td { padding:.65rem .85rem; color:#334155; vertical-align:middle; }

.puntualidad-bar { height:8px; border-radius:99px; background:#e2e8f0; width:120px; overflow:hidden; display:inline-block; vertical-align:middle; margin-right:.5rem; }
.puntualidad-fill { height:100%; border-radius:99px; }

.badge-pos { background:#f0fdf4; color:#166534; border-radius:99px; padding:.2rem .6rem; font-size:.72rem; font-weight:700; }
.badge-neg { background:#fef2f2; color:#991b1b; border-radius:99px; padding:.2rem .6rem; font-size:.72rem; font-weight:700; }
.badge-venc{ background:#fef3c7; color:#92400e; border-radius:99px; padding:.2rem .6rem; font-size:.72rem; font-weight:700; }

.grafico-wrap { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); padding:1.25rem; }
.grafico-titulo { font-size:.9rem; font-weight:700; color:#1e293b; margin-bottom:1rem; }
.bar-chart { display:flex; flex-direction:column; gap:.75rem; }
.bar-row { display:flex; align-items:center; gap:.75rem; }
.bar-label { width:160px; font-size:.78rem; color:#475569; font-weight:600; text-align:right; flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bar-outer { flex:1; background:#f1f5f9; border-radius:99px; height:22px; overflow:hidden; }
.bar-inner { height:100%; border-radius:99px; display:flex; align-items:center; padding-left:.5rem; font-size:.72rem; font-weight:700; color:#fff; min-width:30px; transition:width .5s ease; }
.bar-val { margin-left:.5rem; font-size:.75rem; font-weight:700; color:#475569; white-space:nowrap; }

.sin-datos { text-align:center; padding:3rem; color:#94a3b8; font-size:.9rem; }
</style>
@endpush

@section('contenido')
<div class="rep-header">
    <div>
        <div class="rep-title">📊 Reporte de Rendimiento — Tareas</div>
        <div style="font-size:.78rem;color:#64748b;">Desempeño por trabajador en gestión de tareas</div>
    </div>
    <a href="{{ route('admin.tareas.index') }}" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:9px;padding:.48rem 1rem;font-size:.8rem;text-decoration:none;font-weight:600;">← Volver a Tareas</a>
</div>

<form method="GET">
<div class="filtros-bar">
    <select name="mes">
        @foreach(range(1,12) as $m)
            <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->locale('es')->isoFormat('MMMM') }}</option>
        @endforeach
    </select>
    <select name="anio">
        @foreach(range(now()->year, now()->year - 2, -1) as $a)
            <option value="{{ $a }}" {{ $anio == $a ? 'selected' : '' }}>{{ $a }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-ver">🔍 Ver Reporte</button>
</div>
</form>

@if(empty($datos))
    <div class="sin-datos">No hay datos de tareas para mostrar.</div>
@else

{{-- Tabla resumen --}}
<div class="tabla-wrap">
<table class="tbl-rep">
    <thead>
        <tr>
            <th>#</th>
            <th>Trabajador</th>
            <th>Total Asignadas</th>
            <th>✅ Positivas</th>
            <th>❌ Negativas</th>
            <th>🔴 Vencidas activas</th>
            <th>Gestiones / Tarea</th>
            <th>Puntualidad en seguimiento</th>
            <th>Eficiencia global</th>
        </tr>
    </thead>
    <tbody>
    @foreach($datos as $i => $d)
    @php
        $eficiencia = $d['total'] > 0
            ? round((($d['cerradas_positivo'] / $d['total']) * 0.6 + ($d['puntualidad'] / 100) * 0.4) * 100, 1)
            : 0;
        $colorEf = $eficiencia >= 80 ? '#10b981' : ($eficiencia >= 60 ? '#f59e0b' : '#ef4444');
    @endphp
    <tr>
        <td style="color:#94a3b8;font-weight:700;">{{ $i + 1 }}</td>
        <td>
            <div style="font-weight:700;font-size:.85rem;">{{ $d['trabajador']->nombre }}</div>
            <div style="font-size:.7rem;color:#94a3b8;">{{ $d['trabajador']->email }}</div>
        </td>
        <td style="font-weight:700;font-size:.9rem;">{{ $d['total'] }}</td>
        <td><span class="badge-pos">{{ $d['cerradas_positivo'] }}</span></td>
        <td><span class="badge-neg">{{ $d['cerradas_negativo'] }}</span></td>
        <td><span class="badge-venc">{{ $d['vencidas'] }}</span></td>
        <td style="font-weight:600;">{{ $d['avg_gestiones'] }} gest.</td>
        <td>
            <div style="display:flex;align-items:center;gap:.5rem;">
                <div class="puntualidad-bar">
                    <div class="puntualidad-fill" style="width:{{ $d['puntualidad'] }}%;background:{{ $d['puntualidad'] >= 80 ? '#10b981' : ($d['puntualidad'] >= 60 ? '#f59e0b' : '#ef4444') }};"></div>
                </div>
                <span style="font-size:.78rem;font-weight:700;color:{{ $d['puntualidad'] >= 80 ? '#065f46' : ($d['puntualidad'] >= 60 ? '#92400e' : '#991b1b') }};">{{ $d['puntualidad'] }}%</span>
            </div>
        </td>
        <td>
            <span style="font-size:.95rem;font-weight:800;color:{{ $colorEf }};">{{ $eficiencia }}%</span>
        </td>
    </tr>
    @endforeach
    </tbody>
</table>
</div>

{{-- Gráfico de barras tareas totales --}}
<div class="grafico-wrap">
    <div class="grafico-titulo">📊 Total Tareas por Trabajador</div>
    @php $maxTotal = collect($datos)->max('total') ?: 1; @endphp
    <div class="bar-chart">
    @foreach($datos as $d)
        <div class="bar-row">
            <div class="bar-label">{{ Str::limit($d['trabajador']->nombre, 22) }}</div>
            <div class="bar-outer">
                <div class="bar-inner" style="width:{{ round(($d['total'] / $maxTotal) * 100) }}%;background:linear-gradient(90deg,#2563eb,#3b82f6);">
                    @if($d['total'] > 0) {{ $d['total'] }} @endif
                </div>
            </div>
            <div class="bar-val">{{ $d['cerradas_positivo'] }}✅ {{ $d['cerradas_negativo'] }}❌ {{ $d['vencidas'] }}🔴</div>
        </div>
    @endforeach
    </div>
</div>

{{-- Leyenda/explicación --}}
<div style="background:#f8fafc;border-radius:12px;padding:1rem 1.25rem;margin-top:1.25rem;font-size:.78rem;color:#64748b;border:1px solid #e2e8f0;">
    <strong style="color:#334155;">📌 Cómo se calcula la Eficiencia Global:</strong><br>
    <span>60% peso en tareas cerradas positivamente vs total asignadas + 40% peso en puntualidad de seguimiento (gestiones realizadas antes de que venza la fecha límite).</span><br><br>
    <strong style="color:#334155;">📌 Puntualidad en seguimiento:</strong><br>
    <span>% de gestiones registradas mientras la tarea aún no había vencido. Una trabajadora que revisa cada 8 días una tarea con límite de 15 días tendrá mayor puntualidad que una que revisa cada 15 días.</span>
</div>

@endif
@endsection
