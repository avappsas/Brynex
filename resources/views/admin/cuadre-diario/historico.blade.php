@extends('layouts.app')
@section('modulo', 'Cuadre Histórico')

{{--
  Cuadres del modelo anterior: períodos de varios días que se abrían y se
  cerraban. Solo lectura — el cuadre vigente es por día (ver index).
--}}

@php
$fmt = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
@endphp

@section('contenido')
<style>
.cd-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
.cd-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.8rem;margin-bottom:1rem}
.cd-card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.2rem}
.cd-card-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:.4rem}
.cd-card-val{font-size:1.5rem;font-weight:800;color:#0f172a}
.cd-card.efectivo .cd-card-val{color:#15803d}
.cd-card.gastos .cd-card-val{color:#dc2626}
.cd-card.saldo .cd-card-val{color:#1d4ed8}
.tbl-cd{width:100%;border-collapse:collapse;font-size:.8rem}
.tbl-cd th{background:#0f172a;color:#94a3b8;font-size:.65rem;text-transform:uppercase;padding:.4rem .6rem}
.tbl-cd td{padding:.38rem .6rem;border-bottom:1px solid #f1f5f9}
.num{text-align:right;font-family:monospace}
.badge-tipo{padding:.12rem .4rem;border-radius:20px;font-size:.66rem;font-weight:700}
</style>

<div class="cd-header">
    <a href="{{ route('admin.cuadre-diario.index') }}"
       style="color:rgba(255,255,255,.55);font-size:.75rem;text-decoration:none">← Cuadre diario</a>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-top:.35rem">
        <div>
            <div style="font-size:1.15rem;font-weight:800">🗂️ Cuadre histórico por período</div>
            <div style="font-size:.8rem;color:#94a3b8;margin-top:.2rem">
                {{ $cuadre->usuario?->nombre ?? '—' }} ·
                {{ $cuadre->fecha_inicio->format('d/m/Y') }}
                @if($cuadre->fecha_fin) — {{ $cuadre->fecha_fin->format('d/m/Y') }} @endif
            </div>
        </div>
        <span style="padding:.3rem .8rem;border-radius:20px;font-size:.75rem;font-weight:700;
                     background:{{ $cuadre->estaAbierto() ? 'rgba(253,230,138,.25)' : 'rgba(74,222,128,.2)' }};
                     color:{{ $cuadre->estaAbierto() ? '#fde68a' : '#4ade80' }}">
            {{ $cuadre->estaAbierto() ? 'Abierto' : 'Cerrado' }}
            @if($cuadre->cerradoPor) · por {{ $cuadre->cerradoPor->nombre }} @endif
        </span>
    </div>
</div>

<div class="cd-cards">
    <div class="cd-card efectivo">
        <div class="cd-card-title">💵 Efectivo cobrado</div>
        <div class="cd-card-val">{{ $fmt($datosPeriodo['efectivo_total']) }}</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.3rem">{{ $facturasPeriodo->count() }} facturas</div>
    </div>
    @if(($datosPeriodo['cobros_cartera'] ?? 0) > 0)
    <div class="cd-card" style="border-color:#d1fae5">
        <div class="cd-card-title" style="color:#065f46">📋 Cobros cartera</div>
        <div class="cd-card-val" style="color:#065f46">{{ $fmt($datosPeriodo['cobros_cartera']) }}</div>
    </div>
    @endif
    @if(($datosPeriodo['anticipos_efectivo'] ?? 0) > 0)
    <div class="cd-card" style="border-color:#fde68a">
        <div class="cd-card-title" style="color:#78350f">💰 Anticipos recibidos</div>
        <div class="cd-card-val" style="color:#78350f">{{ $fmt($datosPeriodo['anticipos_efectivo']) }}</div>
    </div>
    @endif
    @if(($datosPeriodo['total_prestado'] ?? 0) > 0)
    <div class="cd-card" style="border-color:#fde68a">
        <div class="cd-card-title" style="color:#92400e">⚠️ Total prestado</div>
        <div class="cd-card-val" style="color:#b45309">{{ $fmt($datosPeriodo['total_prestado']) }}</div>
    </div>
    @endif
    <div class="cd-card gastos">
        <div class="cd-card-title">📤 Gastos efectivo</div>
        <div class="cd-card-val">-{{ $fmt($datosPeriodo['gastos_efectivo']) }}</div>
    </div>
    <div class="cd-card saldo">
        <div class="cd-card-title">✅ Saldo de cierre</div>
        <div class="cd-card-val">{{ $fmt($cuadre->saldo_cierre ?? $datosPeriodo['saldo_final']) }}</div>
    </div>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:.8rem">
    <div style="padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between">
        <div style="font-size:.85rem;font-weight:700">📅 Movimiento por día</div>
        <div style="font-size:.75rem;color:#64748b">Saldo de apertura: <strong>{{ $fmt($cajaMenor) }}</strong></div>
    </div>
    <div style="overflow-x:auto">
    <table class="tbl-cd">
        <thead><tr>
            <th>Día</th>
            <th class="num">+ Ingresos efectivo</th>
            <th class="num">+ Cobros cartera</th>
            <th class="num">💰 Anticipos</th>
            <th class="num">- Gastos efectivo</th>
            <th class="num">Saldo acumulado</th>
        </tr></thead>
        <tbody>
        @foreach($datosPeriodo['por_dia'] as $dia)
        <tr>
            <td>{{ sqldate($dia['fecha'])->locale('es')->isoFormat('ddd DD MMM') }}</td>
            <td class="num" style="color:#15803d">{{ $dia['ingresos'] ? '+'.$fmt($dia['ingresos']) : '—' }}</td>
            <td class="num" style="color:#065f46;font-size:.75rem">{{ ($dia['cartera'] ?? 0) > 0 ? '+'.$fmt($dia['cartera']) : '' }}</td>
            <td class="num" style="color:#d97706;font-size:.75rem">{{ ($dia['anticipos'] ?? 0) > 0 ? '+'.$fmt($dia['anticipos']) : '' }}</td>
            <td class="num" style="color:#dc2626">{{ $dia['gastos'] ? '-'.$fmt($dia['gastos']) : '—' }}</td>
            <td class="num" style="font-weight:700;color:{{ $dia['saldo'] >= 0 ? '#1d4ed8' : '#dc2626' }}">{{ $fmt($dia['saldo']) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden">
    <div style="padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;font-size:.85rem;font-weight:700">
        📋 Gastos del período
    </div>
    @if($gastos->isEmpty())
    <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:.83rem">Sin gastos registrados</div>
    @else
    <div style="overflow-x:auto">
    <table class="tbl-cd">
        <thead><tr>
            <th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Forma pago</th><th>Banco</th><th class="num">Valor</th>
        </tr></thead>
        <tbody>
        @foreach($gastos as $g)
        <tr>
            <td style="font-size:.75rem">{{ $g->fecha->format('d/m/Y') }}</td>
            <td><span class="badge-tipo" style="background:#dbeafe;color:#1d4ed8">{{ $g->tipoLabel() }}</span></td>
            <td style="max-width:250px;font-size:.77rem">
                {{ $g->descripcion }}
                @if($g->pagado_a)<div style="color:#64748b;font-size:.7rem">→ {{ $g->pagado_a }}</div>@endif
            </td>
            <td style="font-size:.75rem">
                {{ match($g->forma_pago) {
                    'efectivo' => '💵 Efectivo',
                    'transferencia_bancaria' => '🏦 Banco',
                    'banco_banco' => '🔄 Banco→Banco',
                    default => $g->forma_pago
                } }}
            </td>
            <td style="font-size:.72rem;color:#64748b">
                {{ $g->bancoOrigen?->banco ?? '—' }}@if($g->bancoDestino) → {{ $g->bancoDestino->banco }} @endif
            </td>
            <td class="num" style="color:#dc2626;font-weight:700">-{{ $fmt($g->valor) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

@if($cuadre->observacion)
<div style="background:#fef3c7;border-radius:10px;padding:.8rem 1rem;margin-top:.8rem;font-size:.82rem;color:#92400e">
    📝 {{ $cuadre->observacion }}
</div>
@endif

@endsection
