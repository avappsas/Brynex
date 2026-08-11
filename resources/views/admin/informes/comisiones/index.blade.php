@extends('layouts.app')
@section('title', 'Comisiones Asesores')

@push('styles')
<style>
    :root {
        --c-bg:       #0d1b2e;
        --c-surface:  #111e33;
        --c-border:   rgba(59,130,246,.18);
        --c-blue:     #3b82f6;
        --c-green:    #10b981;
        --c-yellow:   #f59e0b;
        --c-red:      #ef4444;
        --c-purple:   #8b5cf6;
        --c-text:     #e2e8f0;
        --c-muted:    rgba(226,232,240,.45);
    }

    .com-wrap { max-width: 1200px; margin: 0 auto; }

    /* Header */
    .com-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1.5rem; flex-wrap: wrap; gap: .75rem;
    }
    .com-title { font-size: 1.4rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: .5rem; }
    .com-title small { font-size: .75rem; font-weight: 400; color: #475569; }

    /* Filtros */
    .filtros-bar {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: 12px;
        padding: .85rem 1.1rem;
        display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }
    .filtros-bar label { font-size: .75rem; color: var(--c-muted); }
    .filtros-bar select, .filtros-bar input {
        background: #0d1b2e; border: 1px solid var(--c-border);
        color: var(--c-text); border-radius: 7px; padding: .4rem .65rem;
        font-size: .82rem; outline: none;
    }
    .filtros-bar select:focus { border-color: var(--c-blue); }
    .btn-filtrar {
        background: var(--c-blue); color: #fff; border: none;
        border-radius: 7px; padding: .42rem .9rem; font-size: .82rem;
        font-weight: 600; cursor: pointer; transition: opacity .15s;
    }
    .btn-filtrar:hover { opacity: .85; }
    .btn-outline {
        background: transparent; color: var(--c-blue);
        border: 1px solid var(--c-blue); border-radius: 7px;
        padding: .42rem .9rem; font-size: .82rem; font-weight: 600;
        cursor: pointer; text-decoration: none; transition: background .15s;
    }
    .btn-outline:hover { background: rgba(59,130,246,.12); }

    /* Grid de dos columnas */
    .com-grid { display: grid; grid-template-columns: 280px 1fr; gap: 1.25rem; align-items: start; }
    @media (max-width: 800px) { .com-grid { grid-template-columns: 1fr; } }

    /* Panel */
    .panel {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: 14px;
        overflow: hidden;
    }
    .panel-head {
        padding: .7rem 1rem;
        border-bottom: 1px solid var(--c-border);
        font-size: .78rem; font-weight: 700;
        color: var(--c-muted); text-transform: uppercase; letter-spacing: .06em;
        display: flex; align-items: center; justify-content: space-between;
    }

    /* Lista asesores */
    .asesor-list { list-style: none; padding: .4rem; margin: 0; }
    .asesor-item a {
        display: flex; align-items: center; gap: .55rem;
        padding: .55rem .7rem; border-radius: 8px;
        text-decoration: none; transition: background .12s;
        color: var(--c-text); font-size: .83rem;
    }
    .asesor-item a:hover { background: rgba(59,130,246,.12); }
    .asesor-item a.activo { background: rgba(59,130,246,.2); color: #93c5fd; }
    .asesor-avatar {
        width: 32px; height: 32px; border-radius: 50%;
        background: rgba(59,130,246,.2); display: flex;
        align-items: center; justify-content: center;
        font-size: .9rem; flex-shrink: 0;
    }
    .asesor-nombre { font-weight: 600; }
    .asesor-cedula { font-size: .7rem; color: var(--c-muted); }

    /* Cards KPI */
    .kpi-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: .75rem; margin-bottom: 1rem; }
    @media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr 1fr; } }
    .kpi-card {
        background: var(--c-bg); border: 1px solid var(--c-border);
        border-radius: 11px; padding: .8rem 1rem;
    }
    .kpi-label { font-size: .7rem; color: var(--c-muted); margin-bottom: .2rem; }
    .kpi-val { font-size: 1.25rem; font-weight: 700; color: var(--c-text); }
    .kpi-val.green { color: var(--c-green); }
    .kpi-val.yellow { color: var(--c-yellow); }
    .kpi-val.red { color: var(--c-red); }
    .kpi-val.purple { color: var(--c-purple); }

    /* Saldo acumulado banner */
    .saldo-banner {
        background: linear-gradient(135deg, #1e1b4b, #312e81);
        border: 2px solid #4f46e5;
        border-radius: 12px; padding: 1.1rem 1.4rem;
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1rem; flex-wrap: wrap; gap: .5rem;
        box-shadow: 0 4px 20px rgba(79, 70, 229, 0.15);
    }
    .saldo-banner-label { font-size: .82rem; color: #a5b4fc; font-weight: 600; letter-spacing: 0.02em; }
    .saldo-banner-val { font-size: 1.8rem; font-weight: 900; color: #f59e0b; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
    .btn-pagar {
        background: #ffffff; color: #7c3aed; border: none;
        border-radius: 8px; padding: .5rem 1.1rem; font-size: .85rem;
        font-weight: 800; cursor: pointer; transition: all .15s;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .btn-pagar:hover { background: #f8fafc; transform: translateY(-1px); }

    /* Tabla */
    .tabla-wrap { overflow-x: auto; }
    table.com-table { width: 100%; border-collapse: collapse; }
    .com-table th {
        padding: .55rem .7rem; text-align: left;
        font-size: .7rem; font-weight: 700; color: var(--c-muted);
        text-transform: uppercase; letter-spacing: .06em;
        border-bottom: 1px solid var(--c-border);
    }
    .com-table td {
        padding: .6rem .7rem; font-size: .82rem;
        color: var(--c-text); border-bottom: 1px solid rgba(59,130,246,.06);
    }
    .com-table tr:last-child td { border-bottom: none; }
    .com-table tr:hover td { background: rgba(59,130,246,.04); }

    /* Badges */
    .badge {
        display: inline-block; padding: .18rem .55rem;
        border-radius: 20px; font-size: .68rem; font-weight: 700;
    }
    .badge-plan { background: rgba(59,130,246,.15); color: #93c5fd; }
    .badge-afil { background: rgba(139,92,246,.15); color: #c4b5fd; }
    .badge-pagada { background: rgba(16,185,129,.15); color: #6ee7b7; }
    .badge-pre { background: rgba(245,158,11,.15); color: #fcd34d; }

    /* Historial pagos */
    .pago-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: .6rem .8rem; border-bottom: 1px solid var(--c-border);
        font-size: .82rem;
    }
    .pago-item:last-child { border-bottom: none; }
    .pago-fecha { color: var(--c-muted); font-size: .75rem; }
    .pago-valor { font-weight: 700; color: var(--c-green); }
    .pago-tipo { color: var(--c-muted); font-size: .72rem; }

    /* Empty state */
    .empty-state {
        padding: 2.5rem; text-align: center;
        color: var(--c-muted); font-size: .85rem;
    }
    .empty-state .icon { font-size: 2.5rem; margin-bottom: .5rem; }

    /* Modal */
    .modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.65); z-index: 9000;
        align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: 16px; padding: 1.5rem;
        width: 100%; max-width: 460px;
        box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }
    .modal-title { font-size: 1.05rem; font-weight: 700; color: var(--c-text); margin-bottom: 1rem; }
    .form-row { margin-bottom: .85rem; }
    .form-row label { display: block; font-size: .75rem; color: var(--c-muted); margin-bottom: .3rem; }
    .form-row input, .form-row select, .form-row textarea {
        width: 100%; background: var(--c-bg);
        border: 1px solid var(--c-border); color: var(--c-text);
        border-radius: 8px; padding: .5rem .75rem; font-size: .85rem; outline: none;
        box-sizing: border-box;
    }
    .form-row input:focus, .form-row select:focus { border-color: var(--c-blue); }
    .modal-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1rem; }
    .btn-cancel {
        background: transparent; color: var(--c-muted);
        border: 1px solid var(--c-border); border-radius: 8px;
        padding: .5rem 1rem; font-size: .83rem; cursor: pointer;
    }
    .btn-confirmar {
        background: var(--c-purple); color: #fff; border: none;
        border-radius: 8px; padding: .5rem 1.2rem;
        font-size: .83rem; font-weight: 700; cursor: pointer;
    }
    .saldo-info-modal {
        background: rgba(139,92,246,.1); border: 1px solid rgba(139,92,246,.25);
        border-radius: 8px; padding: .6rem .9rem; margin-bottom: .85rem;
        font-size: .82rem; color: #c4b5fd;
    }
</style>
@endpush

@section('contenido')
<div class="com-wrap">

    {{-- Header --}}
    <div class="com-header">
        <div class="com-title">
            💼 Comisiones Asesores
            <small>Gestión de comisiones y pagos por período</small>
        </div>
        <a href="{{ route('admin.informes.comisiones.afiliaciones', ['mes' => $mes, 'anio' => $anio]) }}"
           class="btn-outline">
            📋 Ver Distribución Afiliaciones
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('admin.informes.comisiones.index') }}" class="filtros-bar">
        <div>
            <label>Asesor</label>
            <select name="asesor_id" onchange="this.form.submit()">
                <option value="">— Selecciona un asesor —</option>
                @foreach($asesores as $a)
                    <option value="{{ $a->id }}" {{ $asesorId == $a->id ? 'selected' : '' }}>
                        {{ $a->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Mes</label>
            <select name="mes">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->locale('es')->monthName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Año</label>
            <select name="anio">
                @foreach(range(2026, now()->year) as $y)
                    <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-filtrar">🔍 Buscar</button>
    </form>

    <div class="com-grid">

        {{-- Columna izquierda: lista de asesores --}}
        <div class="panel">
            <div class="panel-head">👤 Asesores</div>
            <ul class="asesor-list">
                @forelse($asesores as $a)
                    <li class="asesor-item">
                        <a href="{{ route('admin.informes.comisiones.index', ['asesor_id' => $a->id, 'mes' => $mes, 'anio' => $anio]) }}"
                           class="{{ $asesorId == $a->id ? 'activo' : '' }}">
                            <div class="asesor-avatar">👤</div>
                            <div>
                                <div class="asesor-nombre">{{ $a->nombre }}</div>
                                <div class="asesor-cedula">CC {{ is_numeric($a->cedula) ? number_format($a->cedula) : ($a->cedula ?: '—') }}</div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li><div class="empty-state"><div class="icon">🤷</div>Sin asesores registrados</div></li>
                @endforelse
            </ul>
        </div>

        {{-- Columna derecha: detalle del asesor seleccionado --}}
        <div>
            @if($asesor)

                {{-- Saldo acumulado total --}}
                <div class="saldo-banner">
                    <div>
                        <div class="saldo-banner-label">💰 Saldo acumulado total (desde mayo 2026)</div>
                        <div class="saldo-banner-val">${{ number_format($saldoTotal) }}</div>
                    </div>
                    @if($saldoTotal > 0)
                        <button class="btn-pagar" onclick="abrirModalPago()">
                            💵 Registrar Pago
                        </button>
                    @endif
                </div>

                {{-- KPIs del período --}}
                @if(!empty($consolidado))
                {{-- Desglose por categoría. Ingreso-Retiro y Gestión ARL se cobran todos los
                     meses aunque se facturen como afiliación, por eso van aparte. La suma de
                     las 4 es exactamente el total; el saldo no cambia. --}}
                @if(!empty($consolidado['categorias']))
                <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
                    @foreach($consolidado['categorias'] as $cat)
                    <div class="kpi-card">
                        <div class="kpi-label">{{ $cat['icono'] }} {{ $cat['label'] }}</div>
                        <div class="kpi-val" style="color:{{ $cat['valor'] > 0 ? $cat['color'] : 'var(--c-muted)' }}">
                            ${{ number_format($cat['valor']) }}
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                <div class="kpi-grid">
                    @if(empty($consolidado['categorias']))
                    <div class="kpi-card">
                        <div class="kpi-label">🤝 Afiliaciones</div>
                        <div class="kpi-val purple">${{ number_format($consolidado['afiliaciones']) }}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">📋 Planillas</div>
                        <div class="kpi-val" style="color:#38bdf8">${{ number_format($consolidado['planillas']) }}</div>
                    </div>
                    @endif
                    <div class="kpi-card">
                        <div class="kpi-label">✅ Pagado período</div>
                        <div class="kpi-val green">${{ number_format($consolidado['pagado']) }}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">📊 Total ganado</div>
                        <div class="kpi-val">${{ number_format($consolidado['total']) }}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">⏳ Saldo del período</div>
                        <div class="kpi-val {{ $consolidado['saldo'] > 0 ? 'yellow' : 'green' }}">
                            ${{ number_format($consolidado['saldo']) }}
                        </div>
                    </div>
                </div>
                @endif

                {{-- Facturas del período --}}
                <div class="panel" style="margin-bottom: 1rem;">
                    <div class="panel-head">
                        📄 Facturas del período —
                        {{ \Carbon\Carbon::create()->month($mes)->locale('es')->monthName }}
                        {{ $anio }}
                        <span style="color:var(--c-blue)">{{ $facturas->count() }} registros</span>
                    </div>
                    @if($facturas->isEmpty())
                        <div class="empty-state">
                            <div class="icon">📭</div>
                            Sin facturas con comisión para este asesor en el período
                        </div>
                    @else
                        <div class="tabla-wrap">
                            <table class="com-table">
                                <thead>
                                    <tr>
                                        <th>#Factura</th>
                                        <th>Cliente</th>
                                        <th>Empresa</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th style="text-align:right">Comisión</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($facturas as $f)
                                    <tr>
                                        <td><strong>#{{ $f->numero_factura }}</strong></td>
                                        <td>
                                            <div>{{ trim($f->nombre_cliente) ?: $f->cedula }}</div>
                                            <div style="font-size:.7rem;color:var(--c-muted)">{{ $f->cedula }}</div>
                                        </td>
                                        <td style="font-size:.78rem">{{ $f->empresa_nombre }}</td>
                                        <td>
                                            {{-- La categoría manda sobre f.tipo: en IR y Gestión ARL
                                                 la factura es de tipo afiliación pero se cobra cada mes. --}}
                                            @php
                                                $cat = \App\Http\Controllers\Admin\ComisionesController::CATEGORIAS[$f->categoria ?? ''] ?? null;
                                            @endphp
                                            @if($cat)
                                                <span class="badge" style="background:{{ $cat['color'] }}22;color:{{ $cat['color'] }};border:1px solid {{ $cat['color'] }}55;">
                                                    {{ $cat['icono'] }} {{ $cat['label'] }}
                                                </span>
                                            @elseif($f->tipo === 'planilla')
                                                <span class="badge badge-plan">Planilla</span>
                                            @else
                                                <span class="badge badge-afil">Afiliación</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($f->estado === 'pagada')
                                                <span class="badge badge-pagada">Pagada</span>
                                            @else
                                                <span class="badge badge-pre">{{ ucfirst($f->estado) }}</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right; font-weight:700; color:var(--c-green)">
                                            ${{ number_format($f->valor_comision) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" style="text-align:right; font-size:.78rem; color:var(--c-muted); padding:.7rem;">
                                            Total comisiones período:
                                        </td>
                                        <td style="text-align:right; font-weight:800; color:var(--c-green); font-size:1rem;">
                                            ${{ number_format($facturas->sum('valor_comision')) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Historial de pagos del período --}}
                <div class="panel">
                    <div class="panel-head">
                        💳 Pagos registrados en el período
                        <button onclick="abrirModalPago()" style="font-size:.72rem; background:var(--c-purple); color:#fff; border:none; border-radius:6px; padding:.25rem .65rem; cursor:pointer; font-weight:600;">
                            + Agregar
                        </button>
                    </div>
                    @if($pagos->isEmpty())
                        <div class="empty-state">
                            <div class="icon">💸</div>
                            Sin pagos registrados para este período
                        </div>
                    @else
                        @foreach($pagos as $p)
                        <div class="pago-item">
                            <div>
                                <div class="pago-fecha">{{ $p->fecha->format('d/m/Y') }}</div>
                                <div class="pago-tipo">
                                    {{ $p->tipo === 'banco' ? '🏦 ' . ($p->bancoCuenta?->banco ?? 'Banco') : '💵 Efectivo' }}
                                    @if($p->observacion)
                                        — <em style="font-size:.72rem">{{ $p->observacion }}</em>
                                    @endif
                                </div>
                            </div>
                            <div class="pago-valor">${{ number_format($p->valor) }}</div>
                        </div>
                        @endforeach
                        <div style="padding:.6rem .8rem; text-align:right; font-size:.8rem; color:var(--c-muted); border-top: 1px solid var(--c-border);">
                            Total pagado período: <strong style="color:var(--c-green)">${{ number_format($pagos->sum('valor')) }}</strong>
                        </div>
                    @endif
                </div>

            @else
                {{-- Estado vacío --}}
                <div class="panel">
                    <div class="empty-state" style="padding: 4rem 2rem;">
                        <div class="icon">👈</div>
                        <div>Selecciona un asesor del panel izquierdo</div>
                        <div style="margin-top:.5rem; font-size:.75rem;">
                            Luego escoge el mes y año para ver el detalle de comisiones
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- Modal Pagar --}}
@if($asesor)
<div class="modal-overlay" id="modalPago">
    <div class="modal-box">
        <div class="modal-title">💵 Registrar Pago — {{ $asesor->nombre }}</div>

        <div class="saldo-info-modal">
            Saldo acumulado total: <strong>${{ number_format($saldoTotal) }}</strong>
            @if(!empty($consolidado))
            &nbsp;|&nbsp; Saldo período {{ $mes }}/{{ $anio }}: <strong>${{ number_format($consolidado['saldo']) }}</strong>
            @endif
        </div>

        <form id="formPago">
            @csrf
            <div class="form-row">
                <label>Valor a pagar *</label>
                <input type="number" name="valor" id="inputValor" min="1" max="{{ $saldoTotal }}"
                       placeholder="0" required>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem">
                <div class="form-row">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-row">
                    <label>Forma de pago *</label>
                    <select name="tipo" id="selectTipo" onchange="toggleBanco(this.value)" required>
                        <option value="efectivo">💵 Efectivo</option>
                        <option value="banco">🏦 Banco</option>
                    </select>
                </div>
            </div>
            <div class="form-row" id="rowBanco" style="display:none">
                <label>Banco origen *</label>
                <select name="banco_cuenta_id">
                    <option value="">— Selecciona —</option>
                    @foreach($bancos as $b)
                        <option value="{{ $b->id }}">{{ $b->banco }} — {{ $b->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem">
                <div class="form-row">
                    <label>Período (mes) *</label>
                    <select name="periodo_mes">
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->locale('es')->monthName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label>Período (año) *</label>
                    <select name="periodo_anio">
                        @foreach(range(2025, now()->year) as $y)
                            <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <label>Observación</label>
                <textarea name="observacion" rows="2" placeholder="Opcional..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="cerrarModalPago()">Cancelar</button>
                <button type="submit" class="btn-confirmar" id="btnConfirmar">✅ Registrar Pago</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
function abrirModalPago() {
    document.getElementById('modalPago')?.classList.add('open');
}
function cerrarModalPago() {
    document.getElementById('modalPago')?.classList.remove('open');
}
function toggleBanco(val) {
    document.getElementById('rowBanco').style.display = val === 'banco' ? '' : 'none';
}

// Cerrar al hacer clic fuera del box
document.getElementById('modalPago')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalPago();
});

// Envío del formulario de pago
document.getElementById('formPago')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnConfirmar');
    btn.disabled = true; btn.textContent = 'Guardando...';

    const data = new FormData(this);
    try {
        const res = await fetch('{{ route('admin.informes.comisiones.pagar', ['asesor' => $asesor?->id ?? 0]) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: data,
        });
        const json = await res.json();
        if (json.ok) {
            location.reload();
        } else {
            alert('Error: ' + (json.error || 'No se pudo registrar el pago.'));
            btn.disabled = false; btn.textContent = '✅ Registrar Pago';
        }
    } catch(err) {
        alert('Error de red. Intenta de nuevo.');
        btn.disabled = false; btn.textContent = '✅ Registrar Pago';
    }
});
</script>
@endpush
