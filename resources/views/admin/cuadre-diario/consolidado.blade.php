@extends('layouts.app')
@section('modulo', 'Cuadre Consolidado')

@php
$fmt = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
@endphp

@section('contenido')
<style>
.con-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
table.tbl{width:100%;border-collapse:collapse;font-size:.8rem}
.tbl th{background:#0f172a;color:#94a3b8;font-size:.63rem;text-transform:uppercase;letter-spacing:.05em;padding:.4rem .6rem;position:sticky;top:0}
.tbl td{padding:.38rem .6rem;border-bottom:1px solid #f1f5f9}
.tbl tr:hover td{background:#f8fafc}
.num{text-align:right;font-family:monospace}
.badge-est{padding:.12rem .45rem;border-radius:20px;font-size:.67rem;font-weight:700}
</style>

<div class="con-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
        <div>
            <a href="{{ route('admin.cuadre-diario.index') }}"
               style="color:#94a3b8;font-size:.78rem;text-decoration:none">← Volver a mi cuadre</a>
            <div style="font-size:1.1rem;font-weight:800;margin-top:.2rem">📊 Cuadre Consolidado</div>
        </div>
        <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
            <form method="GET" style="display:flex;gap:.3rem;align-items:center">
                <input type="date" name="fecha" value="{{ $fecha }}"
                       onchange="this.form.submit()"
                       style="padding:.28rem .5rem;border-radius:6px;border:1.5px solid #334155;background:#1e293b;color:#fff;font-size:.8rem">
                <select name="usuario_id" onchange="this.form.submit()"
                        style="padding:.28rem .5rem;border-radius:6px;border:1.5px solid #334155;background:#1e293b;color:#fff;font-size:.8rem">
                    <option value="">Todos los usuarios</option>
                    @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" {{ request('usuario_id') == $u->id ? 'selected' : '' }}>
                        {{ $u->nombre }}
                    </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
</div>

{{-- Saldos bancarios --}}
@if($saldosBanco->isNotEmpty())
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.7rem;margin-bottom:.8rem">
    @foreach($saldosBanco as $sb)
    <div style="background:#fff;border-radius:10px;border:1px solid #e2e8f0;padding:.8rem 1rem">
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:.3rem">
            🏦 {{ $sb['banco']->banco }}
        </div>
        <div style="font-size:1.2rem;font-weight:800;color:#1d4ed8">{{ $fmt($sb['saldo']) }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- Tabla consolidada de cuadres --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden">
    @if($resumen->isEmpty())
    <div style="padding:2rem;text-align:center;color:#94a3b8">Sin cuadres para la fecha seleccionada</div>
    @else
    <div style="overflow-x:auto">
    <table class="tbl">
        <thead><tr>
            <th>Usuario</th>
            <th>Período</th>
            <th class="num">Caja Menor</th>
            <th class="num">Efectivo cobrado</th>
            <th class="num">Gastos efectivo</th>
            <th class="num">Saldo esperado</th>
            <th>Estado</th>
            <th>Cerrado por</th>
            <th style="text-align:center">Ver</th>
        </tr></thead>
        <tbody>
        @foreach($resumen as $r)
        <tr>
            <td style="font-weight:600">{{ $r->cuadre->usuario?->nombre ?? '—' }}</td>
            <td style="font-size:.75rem">
                {{ $r->cuadre->fecha_inicio->format('d/m/Y') }}
                @if($r->cuadre->fecha_fin) — {{ $r->cuadre->fecha_fin->format('d/m/Y') }} @endif
            </td>
            <td class="num">{{ $fmt($r->cuadre->saldo_apertura) }}</td>
            <td class="num" style="color:#15803d;font-weight:600">+{{ $fmt($r->efectivo_total) }}</td>
            <td class="num" style="color:#dc2626">-{{ $fmt($r->gastos_efectivo) }}</td>
            <td class="num" style="font-weight:800;color:#1d4ed8">{{ $fmt($r->saldo_esperado) }}</td>
            <td>
                @if($r->cuadre->estado === 'abierto')
                <span class="badge-est" style="background:#dbeafe;color:#1d4ed8">🟢 Abierto</span>
                @else
                <span class="badge-est" style="background:#dcfce7;color:#15803d">✅ Cerrado</span>
                @endif
            </td>
            <td style="font-size:.72rem;color:#64748b">
                {{ $r->cuadre->cerradoPor?->nombre ?? '—' }}
                @if($r->cuadre->saldo_cierre !== null)
                <div style="font-weight:600;color:#0f172a">{{ $fmt($r->cuadre->saldo_cierre) }}</div>
                @endif
            </td>
            <td style="text-align:center">
                <a href="{{ route('admin.cuadre-diario.ver', $r->cuadre->id) }}"
                   style="padding:.2rem .55rem;background:#0f172a;color:#fff;border-radius:5px;font-size:.72rem;text-decoration:none">
                    👁️ Ver
                </a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
@endsection
