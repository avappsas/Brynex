@extends('layouts.app')
@section('titulo', 'Tareas')
@section('modulo', 'Gestión de Tareas')

@push('styles')
<style>
:root {
    --verde:   #10b981; --amarillo: #f59e0b;
    --rojo:    #ef4444; --azul:     #3b82f6;
    --naranja: #f97316; --gris:     #6b7280;
}
.tareas-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
.tareas-title  { font-size:1.35rem; font-weight:700; color:#1e293b; }

/* Tarjetas resumen */
.resumen-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.75rem; margin-bottom:1.25rem; }
.resumen-card { background:#fff; border-radius:12px; padding:.85rem 1rem; box-shadow:0 1px 6px rgba(0,0,0,.07); border-left:4px solid #e2e8f0; cursor:pointer; transition:transform .15s; }
.resumen-card:hover { transform:translateY(-2px); }
.resumen-card .rc-num  { font-size:1.8rem; font-weight:800; line-height:1; }
.resumen-card .rc-lbl  { font-size:.72rem; color:#64748b; margin-top:.2rem; font-weight:500; }
.resumen-card.pendiente { border-color:var(--amarillo); } .resumen-card.pendiente .rc-num { color:var(--amarillo); }
.resumen-card.en_gestion{ border-color:var(--azul); }     .resumen-card.en_gestion .rc-num{ color:var(--azul); }
.resumen-card.en_espera { border-color:var(--naranja); }  .resumen-card.en_espera .rc-num { color:var(--naranja); }
.resumen-card.vencidas  { border-color:var(--rojo); }     .resumen-card.vencidas .rc-num  { color:var(--rojo); }
.resumen-card.cerradas  { border-color:var(--gris); }     .resumen-card.cerradas .rc-num  { color:var(--gris); }

/* Tipos resumen */
.tipos-strip { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem; }
.tipo-badge  { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:99px; padding:.28rem .75rem; font-size:.72rem; font-weight:600; color:#334155; cursor:pointer; transition:background .15s; }
.tipo-badge:hover, .tipo-badge.activo { background:#dbeafe; border-color:#93c5fd; color:#1d4ed8; }
.tipo-badge .count { background:#e2e8f0; border-radius:99px; padding:.05rem .42rem; margin-left:.3rem; font-size:.65rem; }
.tipo-badge.activo .count { background:#bfdbfe; }

/* Filtros */
.filtros-bar { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1rem; align-items:center; }
.filtros-bar select, .filtros-bar input { border:1px solid #cbd5e1; border-radius:8px; padding:.38rem .65rem; font-size:.8rem; background:#fff; color:#1e293b; }
.btn-filtrar { background:#2563eb; color:#fff; border:none; border-radius:8px; padding:.38rem .9rem; font-size:.8rem; font-weight:600; cursor:pointer; }
.btn-filtrar:hover { background:#1d4ed8; }
.btn-limpiar { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:8px; padding:.38rem .75rem; font-size:.8rem; cursor:pointer; text-decoration:none; }
.btn-nueva   { background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; border-radius:9px; padding:.5rem 1.1rem; font-size:.82rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.4rem; box-shadow:0 2px 8px rgba(37,99,235,.3); }
.btn-nueva:hover { background:linear-gradient(135deg,#1d4ed8,#1e3a8a); }

/* Tabla */
.tabla-wrap { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow-x:auto; }
.tbl-tareas { width:100%; border-collapse:collapse; font-size:.8rem; }
.tbl-tareas thead th { background:linear-gradient(135deg,#0a1628,#0d2550); color:rgba(255,255,255,.85); padding:.65rem .75rem; white-space:nowrap; font-weight:600; font-size:.73rem; letter-spacing:.04em; }
.tbl-tareas thead th:first-child { border-radius:0; }
.tbl-tareas tbody tr { border-bottom:1px solid #f1f5f9; transition:background .12s; }
.tbl-tareas tbody tr:hover { background:#f8fafc; }
.tbl-tareas td { padding:.6rem .75rem; vertical-align:middle; color:#334155; }

/* Semáforo pastilla */
.semaforo { display:inline-flex; align-items:center; gap:.4rem; border-radius:99px; padding:.22rem .65rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
.semaforo.verde   { background:rgba(16,185,129,.12); color:#065f46; border:1px solid rgba(16,185,129,.3); }
.semaforo.amarillo{ background:rgba(245,158,11,.12); color:#92400e; border:1px solid rgba(245,158,11,.3); }
.semaforo.rojo    { background:rgba(239,68,68,.12);  color:#991b1b; border:1px solid rgba(239,68,68,.3); }
.semaforo.azul    { background:rgba(59,130,246,.12); color:#1e40af; border:1px solid rgba(59,130,246,.3); }
.semaforo.naranja { background:rgba(249,115,22,.12); color:#9a3412; border:1px solid rgba(249,115,22,.3); }
.semaforo.gris    { background:rgba(107,114,128,.1); color:#374151; border:1px solid rgba(107,114,128,.25); }

/* Badges estado */
.badge { display:inline-block; border-radius:99px; padding:.2rem .6rem; font-size:.68rem; font-weight:600; }
.badge.pendiente  { background:#fef3c7; color:#92400e; }
.badge.en_gestion { background:#dbeafe; color:#1e40af; }
.badge.en_espera  { background:#ffedd5; color:#9a3412; }
.badge.cerrada    { background:#f0fdf4; color:#166534; }

/* Acciones tabla */
.btn-accion { width:28px; height:28px; border:none; border-radius:7px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:.85rem; transition:background .15s; }
.btn-accion.ver     { background:#eff6ff; color:#2563eb; }
.btn-accion.gestion { background:#f0fdf4; color:#16a34a; }
.btn-accion.traslado{ background:#fff7ed; color:#ea580c; }
.btn-accion.cerrar  { background:#fef2f2; color:#dc2626; }
.btn-accion:hover   { opacity:.8; transform:scale(1.08); }

/* Modales */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:none; align-items:center; justify-content:center; padding:1rem; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.2); width:100%; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; }
.modal-box.sm { max-width:520px; }
.modal-box.md { max-width:720px; }
.modal-box.lg { max-width:960px; }
.modal-head { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#0a1628,#0d2550); }
.modal-head h3 { color:#fff; font-size:.95rem; font-weight:700; }
.btn-modal-close { background:rgba(255,255,255,.15); border:none; color:#fff; border-radius:7px; width:30px; height:30px; cursor:pointer; font-size:1.1rem; display:flex; align-items:center; justify-content:center; }
.btn-modal-close:hover { background:rgba(255,255,255,.25); }
.modal-body  { flex:1; overflow-y:auto; padding:1.25rem; }
.modal-foot  { padding:.85rem 1.25rem; border-top:1px solid #f1f5f9; display:flex; gap:.6rem; justify-content:flex-end; }

/* Formulario modal */
.form-row   { display:grid; gap:.75rem; margin-bottom:.75rem; }
.form-row.col2 { grid-template-columns:1fr 1fr; }
.form-row.col3 { grid-template-columns:1fr 1fr 1fr; }
.form-group label { display:block; font-size:.75rem; font-weight:600; color:#475569; margin-bottom:.3rem; }
.form-group input, .form-group select, .form-group textarea {
    width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:.45rem .7rem;
    font-size:.82rem; color:#1e293b; font-family:inherit; transition:border .15s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1);
}
.form-group textarea { resize:vertical; min-height:80px; }

/* Sugerencias cédula */
.autocomplete-list { position:absolute; z-index:999; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); max-height:220px; overflow-y:auto; width:100%; left:0; top:calc(100% + 4px); }
.autocomplete-item { padding:.5rem .85rem; cursor:pointer; font-size:.8rem; color:#1e293b; border-bottom:1px solid #f8fafc; }
.autocomplete-item:hover { background:#f0f9ff; }
.autocomplete-wrap { position:relative; }

/* Timeline gestiones */
.timeline { padding:.5rem 0; }
.tl-item  { display:flex; gap:.85rem; margin-bottom:1rem; align-items:flex-start; }
.tl-dot   { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.9rem; flex-shrink:0; margin-top:.1rem; }
.tl-dot.tramite      { background:#dbeafe; }
.tl-dot.traslado     { background:#ffedd5; }
.tl-dot.cambio_estado{ background:#f0fdf4; }
.tl-dot.nota         { background:#f8fafc; border:1px solid #e2e8f0; }
.tl-content   { flex:1; }
.tl-meta      { font-size:.7rem; color:#94a3b8; margin-bottom:.2rem; }
.tl-obs       { font-size:.8rem; color:#334155; background:#f8fafc; border-radius:8px; padding:.5rem .75rem; border-left:3px solid #e2e8f0; }
.tl-alerta    { font-size:.7rem; color:#92400e; background:#fef3c7; border-radius:6px; padding:.2rem .5rem; display:inline-block; margin-top:.3rem; }

/* Docs lista */
.docs-list { display:flex; flex-direction:column; gap:.5rem; }
.doc-item  { display:flex; align-items:center; gap:.75rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.6rem .85rem; }
.doc-icon  { font-size:1.3rem; }
.doc-info  { flex:1; }
.doc-info .doc-name { font-size:.82rem; font-weight:600; color:#1e293b; }
.doc-info .doc-meta { font-size:.68rem; color:#94a3b8; }
.btn-download { background:#eff6ff; color:#2563eb; border:none; border-radius:7px; padding:.3rem .65rem; font-size:.75rem; cursor:pointer; text-decoration:none; font-weight:600; }

/* Botones */
.btn-primary   { background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; border-radius:9px; padding:.5rem 1.1rem; font-size:.82rem; font-weight:700; cursor:pointer; }
.btn-secondary { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:9px; padding:.5rem 1rem; font-size:.82rem; cursor:pointer; }
.btn-danger    { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; border-radius:9px; padding:.5rem 1rem; font-size:.82rem; cursor:pointer; font-weight:700; }
.btn-success   { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; border-radius:9px; padding:.5rem 1rem; font-size:.82rem; cursor:pointer; font-weight:700; }

.toast { position:fixed; bottom:1.5rem; right:1.5rem; background:#1e293b; color:#fff; padding:.75rem 1.25rem; border-radius:10px; font-size:.82rem; z-index:9999; box-shadow:0 4px 20px rgba(0,0,0,.25); display:none; }
.toast.show { display:block; animation:slideIn .25s ease; }
@keyframes slideIn { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }

.pag-wrap { display:flex; justify-content:space-between; align-items:center; padding:.75rem 1rem; border-top:1px solid #f1f5f9; font-size:.78rem; color:#64748b; }
</style>
@endpush

@section('contenido')
@php
    $tipos = \App\Models\Tarea::TIPOS;
    $estados = \App\Models\Tarea::ESTADOS;
@endphp

<div class="tareas-header">
    <div>
        <div class="tareas-title">📌 Gestión de Tareas</div>
        <div style="font-size:.78rem;color:#64748b;">Seguimiento y control de trámites por cliente</div>
    </div>
    <div style="display:flex;gap:.6rem;align-items:center;">
        <a href="{{ route('admin.tareas.reporte') }}" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:9px;padding:.48rem 1rem;font-size:.8rem;text-decoration:none;font-weight:600;">📊 Reporte</a>
        <button class="btn-nueva" onclick="abrirModalNueva()">＋ Nueva Tarea</button>
    </div>
</div>

{{-- Resumen por estado --}}
<div class="resumen-grid">
    <div class="resumen-card pendiente" onclick="filtrarEstado('pendiente')">
        <div class="rc-num">{{ $resumenEstados['pendiente'] ?? 0 }}</div>
        <div class="rc-lbl">⏳ Pendientes</div>
    </div>
    <div class="resumen-card en_gestion" onclick="filtrarEstado('en_gestion')">
        <div class="rc-num">{{ $resumenEstados['en_gestion'] ?? 0 }}</div>
        <div class="rc-lbl">🔵 En Gestión</div>
    </div>
    <div class="resumen-card en_espera" onclick="filtrarEstado('en_espera')">
        <div class="rc-num">{{ $resumenEstados['en_espera'] ?? 0 }}</div>
        <div class="rc-lbl">🟠 En Espera</div>
    </div>
    <div class="resumen-card vencidas">
        <div class="rc-num">{{ $vencidas }}</div>
        <div class="rc-lbl">🔴 Vencidas</div>
    </div>
    <div class="resumen-card cerradas" onclick="filtrarEstado('cerrada')">
        <div class="rc-num">{{ $resumenEstados['cerrada'] ?? 0 }}</div>
        <div class="rc-lbl">✅ Cerradas</div>
    </div>
</div>

{{-- Tipos strip --}}
<div class="tipos-strip">
    <span class="tipo-badge {{ !request('tipo') ? 'activo' : '' }}" onclick="filtrarTipo('')">Todos</span>
    @foreach($tipos as $key => $label)
        <span class="tipo-badge {{ request('tipo') === $key ? 'activo' : '' }}" onclick="filtrarTipo('{{ $key }}')">
            {{ $label }} @if(($resumenTipos[$key] ?? 0) > 0)<span class="count">{{ $resumenTipos[$key] }}</span>@endif
        </span>
    @endforeach
</div>

{{-- Filtros --}}
<form method="GET" id="filtrosForm">
<div class="filtros-bar">
    <select name="encargado_id" onchange="this.form.submit()">
        <option value="">👤 Todos los encargados</option>
        @foreach($trabajadores as $t)
            <option value="{{ $t->id }}" {{ request('encargado_id') == $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
        @endforeach
    </select>
    <select name="estado" onchange="this.form.submit()">
        <option value="">📌 Todos los estados</option>
        @foreach($estados as $k => $v)
            <option value="{{ $k }}" {{ request('estado') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
    </select>
    <select name="semaforo" onchange="this.form.submit()">
        <option value="">🚦 Todos</option>
        <option value="urgente" {{ request('semaforo') === 'urgente' ? 'selected' : '' }}>🔴 Urgentes / Vencidas</option>
        <option value="en_espera" {{ request('semaforo') === 'en_espera' ? 'selected' : '' }}>🔵 En espera - Recordar</option>
    </select>
    <input name="cedula" value="{{ request('cedula') }}" placeholder="🔍 Cédula..." style="width:160px;" onchange="this.form.submit()">
    <input type="hidden" name="tipo" value="{{ request('tipo') }}" id="hiddenTipo">
    <input type="hidden" name="cerradas" value="{{ request('cerradas') }}" id="hiddenCerradas">
    <label style="font-size:.78rem;color:#64748b;display:flex;align-items:center;gap:.3rem;cursor:pointer;">
        <input type="checkbox" name="cerradas" value="1" {{ request('cerradas') ? 'checked' : '' }} onchange="this.form.submit()"> Ver cerradas
    </label>
    <a href="{{ route('admin.tareas.index') }}" class="btn-limpiar">✕ Limpiar</a>
</div>
</form>

{{-- Tabla --}}
<div class="tabla-wrap">
<table class="tbl-tareas">
    <thead>
        <tr>
            <th>🚦</th>
            <th>Tipo</th>
            <th>Cliente / Cédula</th>
            <th>Empresa / Entidad</th>
            <th>Tarea</th>
            <th>Encargado</th>
            <th>Estado</th>
            <th>Límite</th>
            <th>Días</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    @forelse($tareas as $t)
    @php
        $color = $t->colorSemaforo();
        $dias  = $t->diasRestantes();
        $diasText = $t->estado === 'cerrada' ? '—' : ($dias < 0 ? abs($dias).'d venc.' : $dias.'d');
    @endphp
    <tr>
        <td>
            <span class="semaforo {{ $color }}">
                {{ $t->iconoSemaforo() }}
                @if($color === 'rojo') Vencida
                @elseif($color === 'amarillo') Urgente
                @elseif($color === 'azul') Recordar
                @elseif($color === 'naranja') Espera
                @elseif($color === 'verde') OK
                @else —
                @endif
            </span>
        </td>
        <td style="font-size:.73rem;font-weight:600;color:#475569;">{{ $t->tipoLabel() }}</td>
        <td>
            <div style="font-weight:600;font-size:.8rem;">{{ $t->nombre_cliente }}</div>
            <div style="font-size:.7rem;color:#94a3b8;">{{ $t->cedula }}</div>
        </td>
        <td style="font-size:.78rem;">
            @if($t->razonSocial) <div style="font-weight:600;">{{ $t->razonSocial->razon_social }}</div> @endif
            @if($t->entidad) <div style="color:#94a3b8;font-size:.7rem;">{{ $t->entidad }}</div> @endif
        </td>
        <td style="max-width:220px;">
            <div style="font-size:.78rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;" title="{{ $t->tarea }}">{{ Str::limit($t->tarea, 60) }}</div>
            @if($t->numero_radicado)<div style="font-size:.68rem;color:#94a3b8;">📄 {{ $t->numero_radicado }}</div>@endif
        </td>
        <td style="font-size:.78rem;font-weight:600;">{{ $t->encargado?->nombre ?? '—' }}</td>
        <td><span class="badge {{ $t->estado }}">{{ $t->estadoLabel() }}</span></td>
        <td style="font-size:.75rem;white-space:nowrap;">{{ $t->fecha_limite?->format('d/m/y') ?? '—' }}</td>
        <td style="font-size:.75rem;font-weight:700;color:{{ $color === 'rojo' ? '#ef4444' : ($color === 'amarillo' ? '#f59e0b' : '#64748b') }};">{{ $diasText }}</td>
        <td>
            <div style="display:flex;gap:.3rem;">
                <button class="btn-accion ver"      onclick="verDetalle({{ $t->id }})"         title="Ver detalle">👁</button>
                @if($t->estado !== 'cerrada')
                <button class="btn-accion gestion"  onclick="abrirGestion({{ $t->id }}, '{{ addslashes($t->nombre_cliente) }}')" title="Registrar gestión">📋</button>
                <button class="btn-accion traslado" onclick="abrirTraslado({{ $t->id }}, '{{ addslashes($t->nombre_cliente) }}')" title="Trasladar">🔀</button>
                <button class="btn-accion cerrar"   onclick="abrirCerrar({{ $t->id }}, '{{ addslashes($t->nombre_cliente) }}')" title="Cerrar tarea">🏁</button>
                @endif
            </div>
        </td>
    </tr>
    @empty
    <tr><td colspan="10" style="text-align:center;padding:2.5rem;color:#94a3b8;font-size:.85rem;">No hay tareas con los filtros actuales.</td></tr>
    @endforelse
    </tbody>
</table>
@if($tareas->hasPages())
<div class="pag-wrap">
    <span>Mostrando {{ $tareas->firstItem() }}–{{ $tareas->lastItem() }} de {{ $tareas->total() }}</span>
    <div style="display:flex;gap:.3rem;">{{ $tareas->links() }}</div>
</div>
@endif
</div>

{{-- ══════════ MODAL NUEVA / EDITAR TAREA ══════════ --}}
<div class="modal-overlay" id="modalNueva">
<div class="modal-box md">
    <div class="modal-head">
        <h3 id="modalNuevaTitulo">➕ Nueva Tarea</h3>
        <button class="btn-modal-close" onclick="cerrarModal('modalNueva')">✕</button>
    </div>
    <div class="modal-body">
    <form id="formNueva" method="POST" action="{{ route('admin.tareas.store') }}">
        @csrf
        <div class="form-row col2">
            <div class="form-group">
                <label>Tipo de Tarea *</label>
                <select name="tipo" required onchange="actualizarLimiteTipo(this.value)">
                    <option value="">— Seleccionar —</option>
                    @foreach($tipos as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Encargado *</label>
                <select name="encargado_id" required>
                    <option value="">— Seleccionar —</option>
                    @foreach($trabajadores as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row col2">
            <div class="form-group autocomplete-wrap">
                <label>Cédula del Cliente *</label>
                <input type="text" name="cedula" id="inputCedula" placeholder="Buscar cédula..." required autocomplete="off" oninput="buscarCliente(this.value)">
                <div class="autocomplete-list" id="listaCedulas" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label>Nombre del Cliente</label>
                <input type="text" id="nombreClienteDisplay" readonly placeholder="Se carga al seleccionar cédula..." style="background:#f8fafc;">
            </div>
        </div>
        <div class="form-row col2">
            <div class="form-group">
                <label>Contrato (opcional)</label>
                <select name="contrato_id" id="selectContrato">
                    <option value="">— Sin contrato —</option>
                </select>
            </div>
            <div class="form-group">
                <label>Empresa (opcional)</label>
                <select name="razon_social_id">
                    <option value="">— Sin empresa —</option>
                    @foreach($razonesSociales as $rs)
                        <option value="{{ $rs->id }}">{{ $rs->razon_social }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Entidad donde se realiza el trámite</label>
                <input type="text" name="entidad" placeholder="Ej: Nueva EPS, Compensar, Porvenir...">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Descripción de la Tarea *</label>
                <textarea name="tarea" required placeholder="Detalle la tarea a realizar..."></textarea>
            </div>
        </div>
        <div class="form-row col3">
            <div class="form-group">
                <label>Fecha Radicado</label>
                <input type="date" name="fecha_radicado">
            </div>
            <div class="form-group">
                <label>Número Radicado</label>
                <input type="text" name="numero_radicado" placeholder="N° radicado">
            </div>
            <div class="form-group">
                <label>Correo Entidad</label>
                <input type="email" name="correo" placeholder="correo@entidad.com">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Observación adicional</label>
                <textarea name="observacion" placeholder="Información adicional..." style="min-height:60px;"></textarea>
            </div>
        </div>
    </form>
    </div>
    <div class="modal-foot">
        <button class="btn-secondary" onclick="cerrarModal('modalNueva')">Cancelar</button>
        <button class="btn-primary" onclick="document.getElementById('formNueva').submit()">💾 Crear Tarea</button>
    </div>
</div>
</div>

{{-- ══════════ MODAL REGISTRAR GESTIÓN ══════════ --}}
<div class="modal-overlay" id="modalGestion">
<div class="modal-box sm">
    <div class="modal-head">
        <h3>📋 Registrar Gestión</h3>
        <button class="btn-modal-close" onclick="cerrarModal('modalGestion')">✕</button>
    </div>
    <div class="modal-body">
        <div style="background:#f8fafc;border-radius:8px;padding:.6rem .85rem;margin-bottom:1rem;font-size:.8rem;">
            <strong id="gestionClienteNombre"></strong>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Tipo de Acción</label>
                <select id="gestionTipoAccion">
                    <option value="tramite_realizado">📋 Trámite realizado</option>
                    <option value="nota">📝 Nota / Observación</option>
                    <option value="cambio_estado">🔄 Cambio de estado</option>
                </select>
            </div>
        </div>
        <div class="form-row" id="rowNuevoEstado" style="display:none;">
            <div class="form-group">
                <label>Nuevo Estado</label>
                <select id="gestionNuevoEstado">
                    @foreach($estados as $k => $v)
                        @if($k !== 'cerrada')
                        <option value="{{ $k }}">{{ $v }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Observación / Trámite realizado *</label>
                <textarea id="gestionObservacion" placeholder="Describa lo que se realizó..." style="min-height:100px;"></textarea>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>¿Recordar en cuántos días? <span style="color:#94a3b8;">(0 = sin recordatorio)</span></label>
                <input type="number" id="gestionRecordarDias" min="0" max="365" value="0" placeholder="Ej: 8">
                <div style="font-size:.72rem;color:#94a3b8;margin-top:.25rem;" id="gestionFechaAlertaPreview"></div>
            </div>
        </div>
    </div>
    <div class="modal-foot">
        <button class="btn-secondary" onclick="cerrarModal('modalGestion')">Cancelar</button>
        <button class="btn-primary" onclick="enviarGestion()">💾 Guardar Gestión</button>
    </div>
</div>
</div>

{{-- ══════════ MODAL TRASLADAR ══════════ --}}
<div class="modal-overlay" id="modalTraslado">
<div class="modal-box sm">
    <div class="modal-head">
        <h3>🔀 Trasladar Tarea</h3>
        <button class="btn-modal-close" onclick="cerrarModal('modalTraslado')">✕</button>
    </div>
    <div class="modal-body">
        <div style="background:#fff7ed;border-radius:8px;padding:.6rem .85rem;margin-bottom:1rem;font-size:.8rem;border:1px solid #fed7aa;">
            ⚠️ El traslado quedará registrado en la bitácora de la tarea.<br>
            <strong id="trasladoClienteNombre"></strong>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Nuevo Encargado *</label>
                <select id="trasladoEncargado">
                    @foreach($trabajadores as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Motivo del traslado *</label>
                <textarea id="trasladoObservacion" placeholder="Motivo del traslado..." style="min-height:80px;"></textarea>
            </div>
        </div>
    </div>
    <div class="modal-foot">
        <button class="btn-secondary" onclick="cerrarModal('modalTraslado')">Cancelar</button>
        <button class="btn-primary" onclick="enviarTraslado()">🔀 Trasladar</button>
    </div>
</div>
</div>

{{-- ══════════ MODAL CERRAR TAREA ══════════ --}}
<div class="modal-overlay" id="modalCerrar">
<div class="modal-box sm">
    <div class="modal-head">
        <h3>🏁 Cerrar Tarea</h3>
        <button class="btn-modal-close" onclick="cerrarModal('modalCerrar')">✕</button>
    </div>
    <div class="modal-body">
        <div style="background:#f8fafc;border-radius:8px;padding:.6rem .85rem;margin-bottom:1rem;font-size:.8rem;">
            <strong id="cerrarClienteNombre"></strong>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Resultado *</label>
                <div style="display:flex;gap:.75rem;margin-top:.3rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.85rem;font-weight:400;">
                        <input type="radio" name="cerrarResultado" value="positivo" checked> ✅ Positivo (logrado)
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.85rem;font-weight:400;">
                        <input type="radio" name="cerrarResultado" value="negativo"> ❌ Negativo
                    </label>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Observación de cierre *</label>
                <textarea id="cerrarObservacion" placeholder="Describa el resultado final..." style="min-height:80px;"></textarea>
            </div>
        </div>
    </div>
    <div class="modal-foot">
        <button class="btn-secondary" onclick="cerrarModal('modalCerrar')">Cancelar</button>
        <button class="btn-danger" onclick="enviarCerrar()">🏁 Cerrar Tarea</button>
    </div>
</div>
</div>

{{-- ══════════ MODAL DETALLE ══════════ --}}
<div class="modal-overlay" id="modalDetalle">
<div class="modal-box lg">
    <div class="modal-head">
        <h3>👁 Detalle de la Tarea</h3>
        <button class="btn-modal-close" onclick="cerrarModal('modalDetalle')">✕</button>
    </div>
    <div class="modal-body" id="detalleContenido">
        <div style="text-align:center;padding:2rem;color:#94a3b8;">Cargando...</div>
    </div>
    <div class="modal-foot" id="detalleFooter">
        <button class="btn-secondary" onclick="cerrarModal('modalDetalle')">Cerrar</button>
    </div>
</div>
</div>

<div class="toast" id="toast"></div>

@endsection

@push('scripts')
<script>
let tareaIdActivo = null;

function abrirModalNueva() {
    document.getElementById('modalNueva').classList.add('open');
}
function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Filtros rápidos
function filtrarEstado(estado) {
    const url = new URL(window.location);
    url.searchParams.set('estado', estado);
    window.location = url;
}
function filtrarTipo(tipo) {
    document.getElementById('hiddenTipo').value = tipo;
    document.getElementById('filtrosForm').submit();
}

// Autocompletado cédula
let cedulaTimer = null;
function buscarCliente(val) {
    clearTimeout(cedulaTimer);
    if (val.length < 3) { document.getElementById('listaCedulas').style.display='none'; return; }
    cedulaTimer = setTimeout(() => {
        fetch(`{{ route('admin.tareas.api.clientes') }}?cedula=${encodeURIComponent(val)}`)
            .then(r => r.json()).then(data => {
                const list = document.getElementById('listaCedulas');
                if (!data.length) { list.style.display='none'; return; }
                list.innerHTML = data.map(c =>
                    `<div class="autocomplete-item" onclick="seleccionarCliente('${c.cedula}','${c.primer_nombre} ${c.segundo_nombre??''} ${c.primer_apellido} ${c.segundo_apellido??''}')">
                        <strong>${c.cedula}</strong> — ${c.primer_nombre} ${c.primer_apellido}
                    </div>`
                ).join('');
                list.style.display = 'block';
            });
    }, 350);
}
function seleccionarCliente(cedula, nombre) {
    document.getElementById('inputCedula').value = cedula;
    document.getElementById('nombreClienteDisplay').value = nombre.trim();
    document.getElementById('listaCedulas').style.display = 'none';
    // Cargar contratos
    fetch(`{{ route('admin.tareas.api.contratos') }}?cedula=${cedula}`)
        .then(r => r.json()).then(data => {
            const sel = document.getElementById('selectContrato');
            sel.innerHTML = '<option value="">— Sin contrato —</option>' +
                data.map(c => `<option value="${c.id}">${c.id} — ${c.fecha_ingreso}</option>`).join('');
        });
}

// Gestión
function abrirGestion(id, nombre) {
    tareaIdActivo = id;
    document.getElementById('gestionClienteNombre').textContent = nombre;
    document.getElementById('gestionObservacion').value = '';
    document.getElementById('gestionRecordarDias').value = 0;
    document.getElementById('gestionFechaAlertaPreview').textContent = '';
    document.getElementById('modalGestion').classList.add('open');
}
document.getElementById('gestionTipoAccion')?.addEventListener('change', function() {
    document.getElementById('rowNuevoEstado').style.display = this.value === 'cambio_estado' ? '' : 'none';
});
document.getElementById('gestionRecordarDias')?.addEventListener('input', function() {
    const dias = parseInt(this.value) || 0;
    const prev = document.getElementById('gestionFechaAlertaPreview');
    if (dias > 0) {
        const fecha = new Date(); fecha.setDate(fecha.getDate() + dias);
        prev.textContent = `🔔 Alerta el: ${fecha.toLocaleDateString('es-CO', {day:'2-digit',month:'short',year:'numeric'})}`;
    } else {
        prev.textContent = '';
    }
});
function enviarGestion() {
    const obs = document.getElementById('gestionObservacion').value.trim();
    if (!obs) { mostrarToast('⚠️ Escriba la observación'); return; }
    const body = {
        tipo_accion: document.getElementById('gestionTipoAccion').value,
        observacion: obs,
        recordar_dias: document.getElementById('gestionRecordarDias').value,
        nuevo_estado: document.getElementById('gestionNuevoEstado')?.value,
        _token: '{{ csrf_token() }}'
    };
    fetch(`/admin/tareas/${tareaIdActivo}/gestion`, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:JSON.stringify(body) })
        .then(r => r.json()).then(d => {
            if (d.ok) { cerrarModal('modalGestion'); mostrarToast('✅ ' + d.message); setTimeout(() => location.reload(), 1200); }
            else mostrarToast('❌ Error');
        });
}

// Traslado
function abrirTraslado(id, nombre) {
    tareaIdActivo = id;
    document.getElementById('trasladoClienteNombre').textContent = nombre;
    document.getElementById('trasladoObservacion').value = '';
    document.getElementById('modalTraslado').classList.add('open');
}
function enviarTraslado() {
    const obs = document.getElementById('trasladoObservacion').value.trim();
    const enc = document.getElementById('trasladoEncargado').value;
    if (!obs || !enc) { mostrarToast('⚠️ Complete todos los campos'); return; }
    fetch(`/admin/tareas/${tareaIdActivo}/trasladar`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({ encargado_id: enc, observacion: obs })
    }).then(r => r.json()).then(d => {
        if (d.ok) { cerrarModal('modalTraslado'); mostrarToast('✅ ' + d.message); setTimeout(() => location.reload(), 1200); }
        else mostrarToast('❌ Error');
    });
}

// Cerrar
function abrirCerrar(id, nombre) {
    tareaIdActivo = id;
    document.getElementById('cerrarClienteNombre').textContent = nombre;
    document.getElementById('cerrarObservacion').value = '';
    document.getElementById('modalCerrar').classList.add('open');
}
function enviarCerrar() {
    const obs = document.getElementById('cerrarObservacion').value.trim();
    const resultado = document.querySelector('input[name="cerrarResultado"]:checked')?.value;
    if (!obs || !resultado) { mostrarToast('⚠️ Complete todos los campos'); return; }
    fetch(`/admin/tareas/${tareaIdActivo}/cerrar`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({ resultado, observacion: obs })
    }).then(r => r.json()).then(d => {
        if (d.ok) { cerrarModal('modalCerrar'); mostrarToast('🏁 ' + d.message); setTimeout(() => location.reload(), 1200); }
        else mostrarToast('❌ Error');
    });
}

// Ver Detalle
function verDetalle(id) {
    tareaIdActivo = id;
    document.getElementById('detalleContenido').innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;">⏳ Cargando...</div>';
    document.getElementById('modalDetalle').classList.add('open');
    fetch(`/admin/tareas/${id}/show`).then(r => r.json()).then(renderDetalle);
}
function renderDetalle(data) {
    const t = data.tarea;
    const c = data.cliente;
    const semaforo = `<span class="semaforo ${data.semaforo}">${data.icono} ${data.semaforo}</span>`;
    let html = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
        <div>
            <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.2rem;">CLIENTE</div>
            <div style="font-weight:700;font-size:.95rem;">${c ? (c.primer_nombre+' '+(c.segundo_nombre??'')+' '+c.primer_apellido+' '+(c.segundo_apellido??'')).trim() : t.cedula}</div>
            <div style="font-size:.75rem;color:#64748b;">CC: ${t.cedula} ${c?.celular ? '· 📱 '+c.celular : ''}</div>
        </div>
        <div>
            <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.2rem;">SEMÁFORO / ESTADO</div>
            ${semaforo}
            <span class="badge ${t.estado}" style="margin-left:.4rem;">${t.estado}</span>
        </div>
        <div>
            <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.2rem;">TIPO</div>
            <div style="font-weight:600;font-size:.85rem;">${t.tipo}</div>
        </div>
        <div>
            <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.2rem;">ENCARGADO</div>
            <div style="font-weight:600;font-size:.85rem;">${t.encargado?.nombre ?? '—'}</div>
        </div>
        <div>
            <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.2rem;">ENTIDAD</div>
            <div style="font-size:.82rem;">${t.entidad ?? '—'}</div>
        </div>
        <div>
            <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.2rem;">EMPRESA</div>
            <div style="font-size:.82rem;">${t.razon_social?.razon_social ?? '—'}</div>
        </div>
    </div>
    <div style="background:#f8fafc;border-radius:10px;padding:.85rem 1rem;margin-bottom:1.25rem;">
        <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.3rem;">TAREA</div>
        <div style="font-size:.85rem;color:#1e293b;">${t.tarea}</div>
        ${t.observacion ? `<div style="font-size:.78rem;color:#64748b;margin-top:.4rem;padding-top:.4rem;border-top:1px solid #e2e8f0;">${t.observacion}</div>` : ''}
    </div>`;

    // Documentos
    html += `<div style="margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;">
            <strong style="font-size:.82rem;">📎 Documentos adjuntos (${t.documentos?.length ?? 0})</strong>
            <label style="background:#eff6ff;color:#2563eb;border:none;border-radius:7px;padding:.3rem .75rem;font-size:.75rem;cursor:pointer;font-weight:600;">
                ➕ Subir <input type="file" style="display:none;" onchange="subirDoc(this)">
            </label>
        </div>
        <div class="docs-list">`;
    (t.documentos ?? []).forEach(d => {
        html += `<div class="doc-item">
            <div class="doc-icon">${d.icono ?? '📎'}</div>
            <div class="doc-info">
                <div class="doc-name">${d.nombre}</div>
                <div class="doc-meta">Subido por ${d.user?.nombre ?? '?'}</div>
            </div>
            <a href="/admin/tareas/documento/${d.id}" class="btn-download" target="_blank">⬇ Descargar</a>
        </div>`;
    });
    if (!(t.documentos?.length)) html += `<div style="font-size:.78rem;color:#94a3b8;padding:.5rem;">Sin documentos adjuntos.</div>`;
    html += `</div></div>`;

    // Línea de tiempo gestiones
    html += `<div><strong style="font-size:.82rem;">📋 Línea de tiempo (${t.gestiones?.length ?? 0} gestiones)</strong>
    <div class="timeline" style="margin-top:.75rem;">`;
    (t.gestiones ?? []).forEach(g => {
        const iconosAccion = {tramite_realizado:'📋', traslado:'🔀', cambio_estado:'🔄', nota:'📝'};
        const ico = iconosAccion[g.tipo_accion] ?? '📌';
        const fecha = new Date(g.created_at).toLocaleString('es-CO', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
        let trasladoInfo = '';
        if (g.tipo_accion === 'traslado') {
            trasladoInfo = `<div style="font-size:.72rem;margin-top:.25rem;color:#ea580c;">De: <strong>${g.encargado_anterior?.nombre??'?'}</strong> → <strong>${g.encargado_nuevo?.nombre??'?'}</strong></div>`;
        }
        html += `<div class="tl-item">
            <div class="tl-dot ${g.tipo_accion}">${ico}</div>
            <div class="tl-content">
                <div class="tl-meta">${g.user?.nombre ?? '?'} · ${fecha}</div>
                <div class="tl-obs">${g.observacion}${trasladoInfo}</div>
                ${g.fecha_alerta ? `<div class="tl-alerta">🔔 Recordar el: ${g.fecha_alerta}</div>` : ''}
            </div>
        </div>`;
    });
    if (!(t.gestiones?.length)) html += '<div style="font-size:.78rem;color:#94a3b8;">Sin gestiones registradas.</div>';
    html += `</div></div>`;

    document.getElementById('detalleContenido').innerHTML = html;

    // Footer con acciones si no está cerrada
    if (t.estado !== 'cerrada') {
        document.getElementById('detalleFooter').innerHTML = `
            <button class="btn-secondary" onclick="cerrarModal('modalDetalle')">Cerrar</button>
            <button class="btn-success" onclick="cerrarModal('modalDetalle');abrirGestion(${t.id},'${c?c.primer_nombre+' '+c.primer_apellido:t.cedula}')">📋 Registrar Gestión</button>
        `;
    } else {
        document.getElementById('detalleFooter').innerHTML = `<button class="btn-secondary" onclick="cerrarModal('modalDetalle')">Cerrar</button>`;
    }
}

// Subir documento desde detalle
function subirDoc(input) {
    if (!input.files.length || !tareaIdActivo) return;
    const nombre = prompt('Nombre del documento:', input.files[0].name.replace(/\.[^.]+$/, ''));
    if (!nombre) return;
    const fd = new FormData();
    fd.append('archivo', input.files[0]);
    fd.append('nombre', nombre);
    fd.append('_token', '{{ csrf_token() }}');
    fetch(`/admin/tareas/${tareaIdActivo}/documento`, { method:'POST', body:fd })
        .then(r => r.json()).then(d => {
            if (d.ok) { mostrarToast('✅ ' + d.message); verDetalle(tareaIdActivo); }
            else mostrarToast('❌ Error al subir');
        });
}

function actualizarLimiteTipo(tipo) {
    // El backend calcula la fecha límite al crear
}

function mostrarToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// Cerrar modal al hacer clic fuera
document.querySelectorAll('.modal-overlay').forEach(ol => {
    ol.addEventListener('click', e => { if (e.target === ol) ol.classList.remove('open'); });
});
</script>
@endpush
