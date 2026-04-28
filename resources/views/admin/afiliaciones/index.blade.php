@extends('layouts.app')
@section('modulo', 'Afiliaciones')

@push('styles')
<style>
/* ── Afiliaciones: layout de altura completa, solo tbody scrollea ── */
html, body {
    height: 100%;
    overflow: hidden;
}
body {
    display: flex;
    flex-direction: column;
}
.header {
    flex-shrink: 0;
}
.contenido {
    flex: 1 !important;
    min-height: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    padding: 0.75rem 1rem !important;
    gap: 0.5rem;
}
/* Los flash messages no deben comprimir la tabla */
.contenido > .flash {
    flex-shrink: 0;
}
</style>
@endpush

@section('contenido')
<style>
/* ── Layout ── */
.afil-header { background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:0.8rem 1.2rem;border-radius:12px;color:#fff;margin-bottom:0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;flex-shrink:0; }
.afil-title  { font-size:1.3rem;font-weight:800;letter-spacing:0.02em; }
.afil-sub    { font-size:0.78rem;color:#94a3b8;margin-top:0.15rem; }

/* ── Filtros ── */
.filtros { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:0.9rem 1.2rem;margin-bottom:0.8rem;display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center; }
.filtros select, .filtros input { padding:0.42rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.82rem;outline:none;background:#fff; }
.filtros select:focus, .filtros input:focus { border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,0.12); }
.btn-filtrar { padding:0.42rem 1rem;background:#1e40af;color:#fff;border:none;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;transition:background .15s; }
.btn-filtrar:hover { background:#1d4ed8; }
.btn-export  { padding:0.42rem 1rem;background:#15803d;color:#fff;border:none;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:0.35rem; }
.filtros-sep { width:100%;height:0;border-bottom:1px dashed #e2e8f0;margin:0.2rem 0; }

/* ── Tabla ── */
.tbl-wrap { overflow-x:auto;overflow-y:auto;border-radius:12px;border:1px solid #e2e8f0;background:#fff;flex:1;min-height:0; }
.tbl-afil { width:100%;border-collapse:collapse;font-size:0.78rem;white-space:nowrap; }
.tbl-afil thead th { background:#0f172a;color:#fff;padding:0.55rem 0.6rem;font-weight:600;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;position:sticky;top:0;z-index:2; }
.tbl-afil thead th a { color:#cbd5e1;text-decoration:none;display:flex;align-items:center;gap:0.2rem;justify-content:center; }
.tbl-afil thead th a:hover { color:#fff; }
.tbl-afil thead th a.sort-asc::after  { content:'\2191';color:#3b82f6;margin-left:0.15rem; }
.tbl-afil thead th a.sort-desc::after { content:'\2193';color:#3b82f6;margin-left:0.15rem; }
/* Select integrado en th */
.th-select { width:100%;background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,0.15);color:#fff;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;padding:0.22rem 0.2rem;cursor:pointer;outline:none;appearance:auto;-webkit-appearance:auto; }
.th-select:hover { border-bottom-color:rgba(255,255,255,0.5); }
.th-select:focus { border-bottom-color:#3b82f6;outline:none; }
.th-select option { background:#0f172a;color:#fff;font-weight:600;text-transform:none; }
.th-select.activo { border-bottom-color:#3b82f6;color:#93c5fd; }
.tbl-afil tbody tr { border-bottom:1px solid #f1f5f9;transition:background .12s; }
.tbl-afil tbody tr:hover { background:#f8fafc; }
.tbl-afil td { padding:0.45rem 0.55rem;vertical-align:middle; }
.razon-badge { font-weight:700;font-size:0.75rem;padding:0.2rem 0.6rem;border-radius:6px;background:#dbeafe;color:#1e40af;display:inline-block;min-width:130px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.cedula-link { color:#3b82f6;text-decoration:none;font-weight:600; }
.cedula-link:hover { text-decoration:underline; }

/* ── Badges estado ── */
.badge-estado { display:inline-flex;align-items:center;gap:0.2rem;padding:0.2rem 0.4rem;border-radius:20px;font-size:0.65rem;font-weight:700;cursor:pointer;transition:all .15s;border:1.5px solid transparent;min-width:52px;justify-content:center; }
.badge-estado:hover { transform:scale(1.05);box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.badge-inactivo { background:#f1f5f9;color:#94a3b8;border-color:#e2e8f0;cursor:default;opacity:0.5; }
.badge-pendiente { background:#fef3c7;color:#b45309;border-color:#fcd34d; }
.badge-tramite   { background:#dbeafe;color:#1e40af;border-color:#93c5fd; }
.badge-traslado  { background:#fed7aa;color:#c2410c;border-color:#fb923c; }
.badge-error     { background:#fee2e2;color:#b91c1c;border-color:#fca5a5; }
.badge-ok        { background:#dcfce7;color:#15803d;border-color:#86efac; }

/* Alerta días en trámite */
.alert-dias { background:#fef2f2;color:#b91c1c;border-radius:4px;padding:0.1rem 0.35rem;font-size:0.6rem;font-weight:700;margin-left:0.2rem;border:1px solid #fca5a5; }

/* ── Factura badge ── */
.fact-badge { background:#f0fdf4;color:#15803d;font-size:0.68rem;font-weight:700;padding:0.15rem 0.45rem;border-radius:6px;border:1px solid #86efac; }
.fact-none  { color:#cbd5e1;font-size:0.68rem; }

/* ── Botones acción ── */
.btn-docs { background:#6366f1;color:#fff;border:none;border-radius:6px;padding:0.22rem 0.5rem;font-size:0.65rem;cursor:pointer;transition:background .15s; }
.btn-docs:hover { background:#4f46e5; }
.btn-enviado-ok  { color:#15803d;font-size:0.85rem; }
.btn-enviado-no  { color:#cbd5e1;font-size:0.85rem;cursor:pointer; }

/* ── Modales ── */
.modal-bg { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(2px); }
.modal-bg.open { display:flex; }
.modal-box { background:#fff;border-radius:16px;padding:1.5rem;max-width:520px;width:94%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);animation:modalIn .2s ease; }
.modal-box.wide { max-width:680px; }
@keyframes modalIn { from{transform:translateY(-20px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-title { font-size:1rem;font-weight:800;color:#0f172a;margin-bottom:1rem;padding-bottom:0.6rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between; }
.modal-close { background:none;border:none;font-size:1.2rem;cursor:pointer;color:#94a3b8;padding:0;line-height:1; }
.modal-close:hover { color:#ef4444; }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:0.8rem; }
.form-group { display:flex;flex-direction:column;gap:0.25rem; }
.form-group label { font-size:0.72rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.04em; }
.form-group select, .form-group textarea, .form-group input { padding:0.5rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;outline:none;font-family:inherit; }
.form-group select:focus, .form-group textarea:focus, .form-group input:focus { border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,0.1); }
.form-group textarea { resize:vertical;min-height:80px; }
.btn-save { background:linear-gradient(135deg,#1e40af,#2563eb);color:#fff;border:none;border-radius:10px;padding:0.6rem 1.5rem;font-size:0.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(37,99,235,0.3);transition:all .15s;width:100%; }
.btn-save:hover { transform:translateY(-1px);box-shadow:0 5px 15px rgba(37,99,235,0.4); }

/* ── Timeline bitácora ── */
.timeline { position:relative;padding-left:1.5rem; }
.timeline::before { content:'';position:absolute;left:0.5rem;top:0;bottom:0;width:2px;background:#e2e8f0; }
.tl-item { position:relative;margin-bottom:1rem; }
.tl-item::before { content:'';position:absolute;left:-1.1rem;top:0.3rem;width:10px;height:10px;border-radius:50%;border:2px solid #3b82f6;background:#fff; }
.tl-date  { font-size:0.68rem;color:#94a3b8;margin-bottom:0.15rem; }
.tl-user  { font-size:0.7rem;font-weight:700;color:#1e40af; }
.tl-obs   { font-size:0.8rem;color:#334155;margin-top:0.2rem; }
.tl-estados { display:flex;align-items:center;gap:0.4rem;margin:0.15rem 0; }
.tl-dias  { font-size:0.65rem;background:#f1f5f9;color:#64748b;padding:0.1rem 0.4rem;border-radius:4px;margin-top:0.2rem;display:inline-block; }

/* ── Documentos ── */
.doc-section { margin-bottom:1rem; }
.doc-section h4 { font-size:0.75rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.25rem; }
.doc-item { display:flex;align-items:center;justify-content:space-between;padding:0.4rem 0.6rem;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:0.3rem;font-size:0.78rem; }
.doc-item-left { display:flex;align-items:center;gap:0.5rem; }
.doc-tipo { font-weight:600;color:#1e40af; }
.doc-arch { color:#64748b; }
.btn-dl   { background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;border-radius:6px;padding:0.2rem 0.5rem;font-size:0.65rem;font-weight:600;cursor:pointer;text-decoration:none;transition:background .15s; }
.btn-dl:hover { background:#e0f2fe; }
.empty-docs { text-align:center;color:#94a3b8;font-size:0.8rem;padding:1rem; }

/* ── PDF upload ── */
.pdf-upload-section { margin-top:0.8rem;padding-top:0.8rem;border-top:1px dashed #e2e8f0; }
.pdf-info { font-size:0.72rem;color:#94a3b8;margin-top:0.3rem; }
.pdf-link { color:#1e40af;font-weight:600;font-size:0.75rem;text-decoration:none; }
.pdf-link:hover { text-decoration:underline; }

/* ── Enviado al cliente ── */
.enviado-section { margin-top:0.8rem;padding:0.7rem;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0; }
.enviado-section h5 { font-size:0.72rem;font-weight:700;color:#15803d;margin-bottom:0.5rem;text-transform:uppercase; }

/* ── Responsive ── */
@media(max-width:768px) {
    .form-row { grid-template-columns:1fr; }
    .filtros { flex-direction:column;align-items:stretch; }
}
</style>

{{-- ══ HEADER + FILTROS UNIFICADOS ══ --}}
<form method="GET" action="{{ route('admin.afiliaciones.index') }}" id="formFiltros" style="flex-shrink:0;">
<div class="afil-header" style="flex-wrap:wrap;gap:0.5rem;">
    <div>
        <div class="afil-title">📋 Módulo de Afiliaciones</div>
        <div class="afil-sub">Seguimiento de radicados por entidad — {{ now()->format('d/m/Y') }}</div>
    </div>
    <div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;margin-left:auto;">
        {{-- Período --}}
        <select name="mes" style="font-size:0.8rem;padding:0.3rem 0.5rem;border:1px solid #334155;background:#1e3a5f;color:#e2e8f0;border-radius:6px;">
            @foreach(['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $i => $m)
            @if($i) <option value="{{ $i }}" {{ $mes == $i ? 'selected' : '' }}>{{ $m }}</option> @endif
            @endforeach
        </select>
        <select name="anio" style="font-size:0.8rem;padding:0.3rem 0.5rem;border:1px solid #334155;background:#1e3a5f;color:#e2e8f0;border-radius:6px;">
            @for($y = date('Y'); $y >= 2023; $y--)
            <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
        <span style="color:#4b6a8b;font-size:0.9rem;">|</span>

        {{-- Aliado (SOLO BryNex) --}}
        @if($user->es_brynex && count($alidosDisponibles) > 1)
        <select name="aliado_id" onchange="this.form.submit()" style="font-size:0.78rem;padding:0.3rem 0.5rem;border:1px solid #334155;background:#1e3a5f;color:#e2e8f0;border-radius:6px;font-weight:700;">
            @foreach($alidosDisponibles as $al)
            <option value="{{ $al->id }}" {{ $alidoId == $al->id ? 'selected' : '' }}>{{ $al->nombre }}</option>
            @endforeach
        </select>
        <span style="color:#4b6a8b;font-size:0.9rem;">|</span>
        @endif

        {{-- Encargado --}}
        <select name="encargado_id" onchange="this.form.submit()" style="font-size:0.78rem;padding:0.3rem 0.5rem;border:1px solid #334155;background:#1e3a5f;color:#e2e8f0;border-radius:6px;">
            <option value="">— Todos —</option>
            @foreach($encargados as $enc)
            <option value="{{ $enc->id }}" {{ $encId == $enc->id ? 'selected' : '' }}>{{ $enc->nombre }}</option>
            @endforeach
        </select>

        {{-- Estado del radicado --}}
        <select name="estado_rad" onchange="this.form.submit()" style="font-size:0.78rem;padding:0.3rem 0.5rem;border:1px solid #334155;border-radius:6px;cursor:pointer;
            @if($estadoRad === 'pendiente')   background:#b45309;color:#fff;
            @elseif($estadoRad === 'tramite') background:#1e40af;color:#fff;
            @elseif($estadoRad === 'traslado') background:#c2410c;color:#fff;
            @elseif($estadoRad === 'error')   background:#b91c1c;color:#fff;
            @elseif($estadoRad === 'ok')      background:#15803d;color:#fff;
            @else background:#1e3a5f;color:#e2e8f0;
            @endif">
            <option value="">⚡ Estado</option>
            <option value="pendiente"  {{ $estadoRad === 'pendiente'  ? 'selected' : '' }}>⏳ Pendiente</option>
            <option value="tramite"    {{ $estadoRad === 'tramite'    ? 'selected' : '' }}>🔵 Trámite</option>
            <option value="traslado"   {{ $estadoRad === 'traslado'   ? 'selected' : '' }}>🟠 Traslado</option>
            <option value="error"      {{ $estadoRad === 'error'      ? 'selected' : '' }}>🔴 Error</option>
            <option value="ok"         {{ $estadoRad === 'ok'         ? 'selected' : '' }}>✅ OK</option>
        </select>

        <span style="background:rgba(255,255,255,0.15);color:#fff;font-size:0.88rem;font-weight:800;padding:0.3rem 0.7rem;border-radius:20px;white-space:nowrap;letter-spacing:0.02em;">
            {{ $contratos->count() }} <span style="font-size:0.7rem;font-weight:500;opacity:0.75;">registros</span>
        </span>
        <a href="{{ route('admin.afiliaciones.exportar', request()->query()) }}" class="btn-export">📥 Excel</a>
    </div>
</div>
</form>

{{-- ══ TABLA PRINCIPAL ══ --}}
@if($contratos->isEmpty())
<div style="flex:1;display:flex;align-items:center;justify-content:center;">
<div style="text-align:center;padding:3rem;color:#94a3b8;background:#fff;border-radius:12px;border:1px solid #e2e8f0;width:100%;max-width:420px;">
    <div style="font-size:3rem;">📋</div>
    <div style="font-size:1rem;font-weight:600;margin-top:0.5rem;">Sin contratos para este período</div>
    <div style="font-size:0.8rem;margin-top:0.25rem;">No hay ingresos en el mes/año seleccionado.</div>
</div>
</div>
@else
@php
function sortUrl($col, $currSort, $currDir) {
    $newDir = ($currSort === $col && $currDir === 'asc') ? 'desc' : 'asc';
    $q = request()->except(['sort','dir']);
    $q['sort'] = $col;
    $q['dir']  = $newDir;
    return url()->current() . '?' . http_build_query($q);
}
function sortClass($col, $currSort, $currDir) {
    if ($currSort !== $col) return '';
    return $currDir === 'asc' ? 'sort-asc' : 'sort-desc';
}
@endphp
@php
// Macro: genera el bloque select+título para cada th filtrable
// Usamos una función en blade con include sintetizado directamente
@endphp
<div class="tbl-wrap">
<table class="tbl-afil">
    <thead>
        <tr>
            {{-- Razón Social --}}
            <th style="max-width:110px;width:110px;">
                <form method="GET" action="{{ route('admin.afiliaciones.index') }}" style="margin:0;">
                    @foreach(request()->except(['razon_social_id','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="razon_social_id" onchange="this.form.submit()" class="th-select {{ $rsId ? 'activo' : '' }}" style="max-width:105px;">
                        <option value="">↓ Razón Social</option>
                        @foreach($razonesDisponibles as $rs)<option value="{{ $rs->id }}" {{ $rsId == $rs->id ? 'selected' : '' }}>{{ $rs->razon_social }}</option>@endforeach
                    </select>
                </form>
            </th>

            {{-- Día --}}
            <th><a href="{{ sortUrl('fecha_ingreso', $sort, $dir) }}" class="{{ sortClass('fecha_ingreso', $sort, $dir) }}">Día</a></th>

            {{-- Fact. --}}
            <th>Fact.</th>

            {{-- Cédula --}}
            <th><a href="{{ sortUrl('cedula', $sort, $dir) }}" class="{{ sortClass('cedula', $sort, $dir) }}">Cédula</a></th>

            {{-- Nombres --}}
            <th>Nombres</th>

            {{-- Tipo Modalidad --}}
            <th style="white-space:nowrap">Modalidad</th>


            {{-- EPS --}}
            <th colspan="2">
                <form method="GET" action="{{ route('admin.afiliaciones.index') }}" style="margin:0;">
                    @foreach(request()->except(['eps_id','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="eps_id" onchange="this.form.submit()" class="th-select {{ $epsF ? 'activo' : '' }}">
                        <option value="">↓ EPS</option>
                        @foreach($epsDisponibles as $e)<option value="{{ $e->id }}" {{ $epsF == $e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>@endforeach
                    </select>
                </form>
            </th>

            {{-- ARL --}}
            <th colspan="2">
                <form method="GET" action="{{ route('admin.afiliaciones.index') }}" style="margin:0;">
                    @foreach(request()->except(['arl_id','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="arl_id" onchange="this.form.submit()" class="th-select {{ $arlF ? 'activo' : '' }}">
                        <option value="">↓ ARL</option>
                        @foreach($arlDisponibles as $a)<option value="{{ $a->id }}" {{ $arlF == $a->id ? 'selected' : '' }}>{{ $a->nombre_arl }}</option>@endforeach
                    </select>
                </form>
            </th>

            {{-- Caja --}}
            <th colspan="2">
                <form method="GET" action="{{ route('admin.afiliaciones.index') }}" style="margin:0;">
                    @foreach(request()->except(['caja_id','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="caja_id" onchange="this.form.submit()" class="th-select {{ $cajaF ? 'activo' : '' }}">
                        <option value="">↓ Caja</option>
                        @foreach($cajaDisponibles as $ca)<option value="{{ $ca->id }}" {{ $cajaF == $ca->id ? 'selected' : '' }}>{{ $ca->nombre }}</option>@endforeach
                    </select>
                </form>
            </th>

            {{-- Pensión --}}
            <th colspan="2">
                <form method="GET" action="{{ route('admin.afiliaciones.index') }}" style="margin:0;">
                    @foreach(request()->except(['pension_id','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="pension_id" onchange="this.form.submit()" class="th-select {{ $pensionF ? 'activo' : '' }}">
                        <option value="">↓ Pensión</option>
                        @foreach($pensionDisponibles as $p)<option value="{{ $p->id }}" {{ $pensionF == $p->id ? 'selected' : '' }}>{{ $p->razon_social }}</option>@endforeach
                    </select>
                </form>
            </th>

            {{-- Empresa --}}
            <th>Empresa</th>

            {{-- Docs --}}
            <th>Docs</th>
        </tr>
    </thead>


    <tbody>
    @foreach($contratos as $c)
    @php
        $radicados         = $c->radicados->keyBy('tipo');
        $plan              = $c->plan;
        $ctxNombre         = trim($c->cliente?->primer_nombre . ' ' . $c->cliente?->primer_apellido);
        $ctxRazonSocial    = $c->razonSocial?->razon_social ?? '—';
        $ctxTipoModalidad  = $c->tipo_modalidad_label ?? ($c->es_dependiente ? 'Dependiente' : 'Independiente');
        $ctxEmpresaCliente = $c->aliado?->nombre ?? '—';
        $contexto          = json_encode(['nombre'=>$ctxNombre,'razon_social'=>$ctxRazonSocial,'tipo_modalidad'=>$ctxTipoModalidad,'empresa_cliente'=>$ctxEmpresaCliente]);
    @endphp
    <tr>
        {{-- Empresa --}}
        <td>
            @if($c->razonSocial)
            <span class="razon-badge razon-badge-link"
                  title="Ver claves de {{ $c->razonSocial->razon_social }}"
                  onclick="abrirClavesRS({{ $c->razonSocial->id }}, '{{ addslashes($c->razonSocial->razon_social) }}')"
                  style="cursor:pointer;">
                {{ $c->razonSocial->razon_social }}
            </span>
            @else
            <span class="razon-badge">—</span>
            @endif
        </td>

        {{-- Día ingreso --}}
        <td style="text-align:center;font-weight:700;color:#1e40af;">
            {{ $c->fecha_ingreso?->format('d') ?? '—' }}
        </td>

        {{-- Factura --}}
        <td style="text-align:center;">
            @if($c->numero_factura_mes)
            <span class="fact-badge">{{ $c->numero_factura_mes }}</span>
            @else
            <span class="fact-none">–</span>
            @endif
        </td>

        {{-- Cédula --}}
        <td>
            <a href="{{ route('admin.contratos.edit', $c->id) }}" class="cedula-link" target="_blank">
                {{ $c->cedula }}
            </a>
        </td>

        {{-- Nombres --}}
        <td style="font-weight:600;color:#1e3a5f;max-width:130px;overflow:hidden;text-overflow:ellipsis;" title="{{ $c->cliente?->primer_nombre }} {{ $c->cliente?->segundo_nombre }} {{ $c->cliente?->primer_apellido }} {{ $c->cliente?->segundo_apellido }}">
            {{ $c->cliente?->primer_nombre }} {{ $c->cliente?->primer_apellido }}
        </td>

        {{-- Tipo Modalidad --}}
        <td style="font-size:0.68rem;color:#475569;white-space:nowrap;" title="{{ $c->tipoModalidad?->nombre ?? '' }}">
            <span style="background:#f1f5f9;color:#334155;padding:0.12rem 0.4rem;border-radius:5px;font-weight:700;font-size:0.67rem;">
                {{ $c->tipoModalidad?->tipo_modalidad ?? '—' }}
            </span>
        </td>


        {{-- EPS --}}
        @php $rEps = $radicados->get('eps'); @endphp
        <td style="font-size:0.7rem;color:#475569;max-width:60px;overflow:hidden;text-overflow:ellipsis;padding-right:0;" title="{{ $c->eps?->nombre }}">{{ $plan?->incluye_eps ? ($c->eps?->nombre ?? '[Ninguna]') : '—' }}</td>
        <td style="padding-left:2px;">
            @if($plan?->incluye_eps && $rEps)
            <button class="badge-estado badge-{{ $rEps->estado }} btn-rad"
                data-rad-id="{{ $rEps->id }}"
                data-contrato-id="{{ $c->id }}"
                data-eps-formulario="{{ $c->eps?->formulario_pdf ? '1' : '0' }}"
                data-rad='{{ json_encode(['id'=>$rEps->id,'tipo'=>$rEps->tipo,'estado'=>$rEps->estado,'numero_radicado'=>$rEps->numero_radicado,'canal_envio'=>$rEps->canal_envio,'canal_envio_cliente'=>$rEps->canal_envio_cliente,'enviado_al_cliente'=>$rEps->enviado_al_cliente,'ruta_pdf'=>$rEps->ruta_pdf]) }}'
                data-ctx='{{ $contexto }}'>
                {{ $rEps->estadoIcono() }}{{ strtoupper(substr($rEps->estado,0,1)) }}
                @if($rEps->tieneAlertaDias())<span class="alert-dias">{{ $rEps->diasEnEstado() }}d</span>@endif
            </button>
            @elseif($plan?->incluye_eps)
            <span class="badge-estado badge-pendiente">⏳P</span>
            @else
            <span class="badge-estado badge-inactivo">–</span>
            @endif
        </td>

        {{-- ARL — efectiva según razón social --}}
        @php $rArl = $radicados->get('arl'); @endphp
        <td style="font-size:0.7rem;color:#475569;max-width:60px;overflow:hidden;text-overflow:ellipsis;padding-right:0;" title="{{ $c->arl_efectiva_nombre }}">{{ $plan?->incluye_arl ? $c->arl_efectiva_nombre : '—' }}</td>
        <td style="padding-left:2px;">
            @if($plan?->incluye_arl && $rArl)
            <button class="badge-estado badge-{{ $rArl->estado }} btn-rad"
                data-rad-id="{{ $rArl->id }}"
                data-rad='{{ json_encode(['id'=>$rArl->id,'tipo'=>$rArl->tipo,'estado'=>$rArl->estado,'numero_radicado'=>$rArl->numero_radicado,'canal_envio'=>$rArl->canal_envio,'canal_envio_cliente'=>$rArl->canal_envio_cliente,'enviado_al_cliente'=>$rArl->enviado_al_cliente,'ruta_pdf'=>$rArl->ruta_pdf]) }}'
                data-ctx='{{ $contexto }}'>
                {{ $rArl->estadoIcono() }}{{ strtoupper(substr($rArl->estado,0,1)) }}
                @if($rArl->tieneAlertaDias())<span class="alert-dias">{{ $rArl->diasEnEstado() }}d</span>@endif
            </button>
            @elseif($plan?->incluye_arl)
            <span class="badge-estado badge-pendiente">⏳P</span>
            @else
            <span class="badge-estado badge-inactivo">–</span>
            @endif
        </td>

        {{-- Caja --}}
        @php $rCaja = $radicados->get('caja'); @endphp
        <td style="font-size:0.7rem;color:#475569;max-width:60px;overflow:hidden;text-overflow:ellipsis;padding-right:0;" title="{{ $c->caja?->nombre }}">{{ $plan?->incluye_caja ? ($c->caja?->nombre ?? '[Ninguna]') : '—' }}</td>
        <td style="padding-left:2px;">
            @if($plan?->incluye_caja && $rCaja)
            <button class="badge-estado badge-{{ $rCaja->estado }} btn-rad"
                data-rad-id="{{ $rCaja->id }}"
                data-rad='{{ json_encode(['id'=>$rCaja->id,'tipo'=>$rCaja->tipo,'estado'=>$rCaja->estado,'numero_radicado'=>$rCaja->numero_radicado,'canal_envio'=>$rCaja->canal_envio,'canal_envio_cliente'=>$rCaja->canal_envio_cliente,'enviado_al_cliente'=>$rCaja->enviado_al_cliente,'ruta_pdf'=>$rCaja->ruta_pdf]) }}'
                data-ctx='{{ $contexto }}'>
                {{ $rCaja->estadoIcono() }}{{ strtoupper(substr($rCaja->estado,0,1)) }}
                @if($rCaja->tieneAlertaDias())<span class="alert-dias">{{ $rCaja->diasEnEstado() }}d</span>@endif
            </button>
            @elseif($plan?->incluye_caja)
            <span class="badge-estado badge-pendiente">⏳P</span>
            @else
            <span class="badge-estado badge-inactivo">–</span>
            @endif
        </td>

        {{-- Pensón --}}
        @php $rPen = $radicados->get('pension'); @endphp
        <td style="font-size:0.7rem;color:#475569;max-width:60px;overflow:hidden;text-overflow:ellipsis;padding-right:0;" title="{{ $c->pension?->razon_social }}">{{ $plan?->incluye_pension ? ($c->pension?->razon_social ?? '[Ninguna]') : '—' }}</td>
        <td style="padding-left:2px;">
            @if($plan?->incluye_pension && $rPen)
            <button class="badge-estado badge-{{ $rPen->estado }} btn-rad"
                data-rad-id="{{ $rPen->id }}"
                data-rad='{{ json_encode(['id'=>$rPen->id,'tipo'=>$rPen->tipo,'estado'=>$rPen->estado,'numero_radicado'=>$rPen->numero_radicado,'canal_envio'=>$rPen->canal_envio,'canal_envio_cliente'=>$rPen->canal_envio_cliente,'enviado_al_cliente'=>$rPen->enviado_al_cliente,'ruta_pdf'=>$rPen->ruta_pdf]) }}'
                data-ctx='{{ $contexto }}'>
                {{ $rPen->estadoIcono() }}{{ strtoupper(substr($rPen->estado,0,1)) }}
                @if($rPen->tieneAlertaDias())<span class="alert-dias">{{ $rPen->diasEnEstado() }}d</span>@endif
            </button>
            @elseif($plan?->incluye_pension)
            <span class="badge-estado badge-pendiente">⏳P</span>
            @else
            <span class="badge-estado badge-inactivo">–</span>
            @endif
        </td>

        {{-- Empresa --}}
        @php
            $esBrynexUser = auth()->user()?->es_brynex;
            $codEmpresa   = $c->cliente?->empresa?->label ?? $c->cliente?->empresa?->empresa ?? '—';
            $labelEmpresa = $esBrynexUser
                ? trim(($c->aliado?->nombre ?? '') . ' · ' . $codEmpresa)
                : $codEmpresa;
        @endphp
        <td style="font-size:0.65rem;color:#475569;max-width:145px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $labelEmpresa }}">
            {{ $labelEmpresa ?: '—' }}
        </td>

        {{-- Docs --}}
        <td style="text-align:center;">
            @php $radIdDocs = $rEps?->id ?? $rArl?->id ?? $rCaja?->id ?? $rPen?->id ?? 0; @endphp
            <button class="btn-docs btn-docs-open"
                data-rad-id="{{ $radIdDocs }}"
                data-cedula="{{ $c->cedula }}"
                data-aliado-id="{{ $c->aliado_id }}"
                data-nombre="{{ $c->cliente?->primer_nombre }} {{ $c->cliente?->primer_apellido }}"
                title="Ver/subir documentos">📁</button>
        </td>
    </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif

{{-- ══ MODAL GESTIÓN RADICADO ══ --}}
<div class="modal-bg" id="modalRadicado">
    <div class="modal-box">
        <div class="modal-title">
            <span id="mrad-titulo">📝 Gestionar Radicado</span>
            <button class="modal-close" onclick="cerrarModal('modalRadicado')">✕</button>
        </div>

        {{-- Contexto: cotizante y empresa --}}
        <div style="background:#f0f9ff;border-radius:8px;padding:0.5rem 0.75rem;margin-bottom:0.75rem;display:flex;gap:0.8rem;flex-wrap:wrap;font-size:0.75rem;">
            <div><span style="color:#64748b;">👤</span> <strong id="mrad-cotizante"></strong></div>
            <div style="border-left:1px solid #bae6fd;padding-left:0.8rem;"><span style="color:#64748b;">Razón Social:</span> <strong id="mrad-empresa"></strong></div>
            <div style="border-left:1px solid #bae6fd;padding-left:0.8rem;"><span style="color:#64748b;">Modalidad:</span> <span id="mrad-modalidad" style="font-weight:600;"></span></div>
            <div style="border-left:1px solid #bae6fd;padding-left:0.8rem;"><span style="color:#64748b;">Empresa cliente:</span> <strong id="mrad-empresa-cliente"></strong></div>
        </div>

        {{-- Info del radicado --}}
        <div style="display:flex;gap:0.6rem;margin-bottom:0.8rem;flex-wrap:wrap;align-items:center;">
            <span style="font-size:0.75rem;background:#f1f5f9;padding:0.2rem 0.6rem;border-radius:6px;font-weight:600;" id="mrad-tipo"></span>
            <span style="font-size:0.75rem;background:#f0f9ff;padding:0.2rem 0.6rem;border-radius:6px;" id="mrad-num-rad"></span>
            {{-- Botón PDF formulario EPS (solo si la EPS tiene formulario configurado y el tipo es eps) --}}
            <div id="seccionFormularioEps" style="display:none;margin-left:auto;">
                <label style="font-size:0.72rem;color:#64748b;display:flex;align-items:center;gap:0.4rem;cursor:pointer;margin-right:0.5rem;">
                    <input type="checkbox" id="chkBeneficiarios" style="width:auto;height:auto;"> Beneficiarios
                </label>
                <a id="btnFormularioPdf"
                   href="#" target="_blank"
                   style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.3rem 0.7rem;background:#7c3aed;color:#fff;border-radius:7px;font-size:0.75rem;font-weight:700;text-decoration:none;">
                    📄 Formulario
                </a>
            </div>
        </div>

        <form id="formRadicado" onsubmit="guardarRadicado(event)">
            <input type="hidden" id="mrad-id">

            <div class="form-row">
                <div class="form-group">
                    <label>Estado *</label>
                    <select id="mrad-estado">
                        <option value="pendiente">⏳ Pendiente</option>
                        <option value="tramite">🔵 En Trámite</option>
                        <option value="traslado">🔄 Traslado</option>
                        <option value="error">❌ Error / Falta Doc.</option>
                        <option value="ok">✅ OK - Finalizado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Canal de trámite</label>
                    <select id="mrad-canal">
                        <option value="">— Sin especificar —</option>
                        <option value="portal">🌐 Portal</option>
                        <option value="correo">📧 Correo</option>
                        <option value="asesor">👤 Asesor</option>
                        <option value="presencial">🏢 Presencial</option>
                        <option value="otro">📌 Otro</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0.8rem;" id="seccionNumRadicado">
                <label>N° Radicado (asignado por la entidad)</label>
                <input type="text" id="mrad-numero" placeholder="Ej: EPS-2026-001234">
            </div>

            <div class="form-group" style="margin-bottom:0.8rem;">
                <label>Observación</label>
                <textarea id="mrad-observacion" placeholder="Describe la acción realizada. Ej: Se envió correo al asesor de EPS, esperando respuesta..."></textarea>
            </div>

            {{-- Sección PDF (solo si estado = ok) --}}
            <div class="pdf-upload-section" id="seccionPdf" style="display:none;">
                <div style="font-size:0.75rem;font-weight:700;color:#15803d;margin-bottom:0.5rem;">📎 PDF del Radicado</div>
                <div id="pdfActual" style="margin-bottom:0.5rem;"></div>
                <div class="form-group">
                    <label>Subir PDF (máx. 3MB)</label>
                    <input type="file" id="mrad-pdf" accept=".pdf" style="border:1px dashed #cbd5e1;border-radius:8px;padding:0.4rem;">
                </div>
                <div class="pdf-info">Solo se acepta el formato PDF. Si ya existe un PDF será reemplazado.</div>
            </div>

            {{-- Sección enviado al cliente --}}
            <div class="enviado-section" id="seccionEnviado">
                <h5>📤 Envío al Cliente</h5>
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    {{-- Checkbox --}}
                    <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.78rem;font-weight:600;color:#15803d;cursor:pointer;white-space:nowrap;flex-shrink:0;">
                        <input type="checkbox" id="mrad-enviado" style="width:15px;height:15px;cursor:pointer;accent-color:#15803d;">
                        Radicado enviado al cliente
                    </label>
                    {{-- Canal --}}
                    <div style="display:flex;align-items:center;gap:0.5rem;flex:1;min-width:160px;">
                        <span style="font-size:0.7rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;">Canal</span>
                        <select id="mrad-canal-cliente" style="flex:1;padding:0.38rem 0.5rem;border:1px solid #cbd5e1;border-radius:7px;font-size:0.82rem;">
                            <option value="">— —</option>
                            <option value="correo">📧 Correo</option>
                            <option value="whatsapp">💬 WhatsApp</option>
                            <option value="fisica">📄 Física</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="margin-top:1rem;">
                <button type="submit" class="btn-save" id="btnGuardarRadicado">💾 Guardar Cambios</button>
            </div>
        </form>

        {{-- Bitácora rápida (últimos 3 movimientos) --}}
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                <span style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;">Últimos movimientos</span>
                <button onclick="abrirBitacora()" style="background:none;border:none;color:#3b82f6;font-size:0.72rem;cursor:pointer;font-weight:600;">Ver todos →</button>
            </div>
            <div id="minibitacora" style="font-size:0.75rem;color:#64748b;">Cargando...</div>
        </div>
    </div>
</div>

{{-- ══ MODAL BITÁCORA COMPLETA ══ --}}
<div class="modal-bg" id="modalBitacora">
    <div class="modal-box wide">
        <div class="modal-title">
            <span>📜 Bitácora de Radicado — <span id="mbit-tipo"></span></span>
            <button class="modal-close" onclick="cerrarModal('modalBitacora')">✕</button>
        </div>
        <div id="mbit-resumen" style="background:#f8fafc;border-radius:8px;padding:0.6rem;margin-bottom:0.8rem;font-size:0.78rem;"></div>
        <div class="timeline" id="mbit-timeline">Cargando...</div>
    </div>
</div>

{{-- ══ MODAL DOCUMENTOS ══ --}}
<div class="modal-bg" id="modalDocs">
    <div class="modal-box wide">
        <div class="modal-title">
            <span>📁 Documentos de <span id="mdocs-nombre"></span></span>
            <button class="modal-close" onclick="cerrarModal('modalDocs')">✕</button>
        </div>
        <div id="mdocs-content">Cargando...</div>

        {{-- Subir nuevo documento --}}
        <div style="margin-top:1rem;padding-top:0.8rem;border-top:1px dashed #e2e8f0;">
            <div style="font-size:0.72rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:0.5rem;">📎 Subir Documento</div>
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:flex-end;">
                <div style="flex:1;min-width:130px;">
                    <label style="font-size:0.68rem;color:#64748b;display:block;margin-bottom:0.2rem;">¿Para quién?</label>
                    <select id="mdocs-para" style="width:100%;padding:0.42rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.82rem;">
                        <option value="">Cotizante</option>
                        {{-- Opciones de beneficiarios se llenan dinámicamente --}}
                    </select>
                </div>
                <div style="flex:1;min-width:130px;">
                    <label style="font-size:0.68rem;color:#64748b;display:block;margin-bottom:0.2rem;">Tipo de documento</label>
                    <select id="mdocs-tipo" style="width:100%;padding:0.42rem 0.7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.82rem;">
                        <option value="cedula">Cédula</option>
                        <option value="carta_laboral">Carta Laboral</option>
                        <option value="registro_civil">Registro Civil</option>
                        <option value="tarjeta_identidad">Tarjeta Identidad</option>
                        <option value="decl_juramentada">Decl. Juramentada</option>
                        <option value="acta_matrimonio">Acta Matrimonio</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div style="flex:2;min-width:160px;">
                    <label style="font-size:0.68rem;color:#64748b;display:block;margin-bottom:0.2rem;">Archivo</label>
                    <input type="file" id="mdocs-archivo" accept=".pdf,.jpg,.jpeg,.png,.webp"
                        style="width:100%;padding:0.35rem 0.5rem;border:1px dashed #cbd5e1;border-radius:8px;font-size:0.75rem;">
                </div>
                <button id="btnSubirDoc" onclick="subirDocumento()"
                    style="padding:0.42rem 1rem;background:#1e40af;color:#fff;border:none;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;white-space:nowrap;align-self:flex-end;">⬆ Subir</button>
            </div>
            <div style="font-size:0.68rem;color:#94a3b8;margin-top:0.25rem;">PDF, JPG o PNG · máx. 3MB</div>
        </div>
    </div>
</div>

{{-- ══ DRAWER CLAVES RAZÓN SOCIAL ══ --}}
<div id="rs-claves-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1050;backdrop-filter:blur(2px);"
     onclick="cerrarClavesRS()"></div>

<div id="rs-claves-panel"
     style="display:none;position:fixed;top:0;right:0;width:860px;max-width:97vw;height:100vh;
            background:#f8fafc;box-shadow:-8px 0 32px rgba(0,0,0,0.18);z-index:1051;
            flex-direction:column;transform:translateX(100%);transition:transform 0.28s cubic-bezier(.4,0,0.2,1);">

    {{-- Header --}}
    <div style="background:linear-gradient(135deg,#fbbf24,#f59e0b);padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <div style="display:flex;align-items:center;gap:0.6rem;">
            <span style="font-size:1.4rem;">🔑</span>
            <div>
                <div style="font-size:0.95rem;font-weight:800;color:#1c1917;">Claves y Accesos</div>
                <div id="rs-claves-subtitulo" style="font-size:0.72rem;color:rgba(28,25,23,0.7);font-weight:500;">Razón Social</div>
            </div>
        </div>
        <button onclick="cerrarClavesRS()"
                style="background:rgba(255,255,255,0.2);border:none;border-radius:8px;width:34px;height:34px;color:#1c1917;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;">✕</button>
    </div>

    {{-- Notif --}}
    <div id="rs-claves-notif" style="display:none;margin:0.5rem 1rem 0;padding:0.45rem 0.85rem;border-radius:7px;font-size:0.8rem;font-weight:600;"></div>

    {{-- Loading --}}
    <div id="rs-claves-loading" style="display:none;text-align:center;padding:2rem;color:#94a3b8;font-size:0.85rem;">⏳ Cargando claves...</div>

    {{-- Tabla --}}
    <div style="flex:1;overflow-y:auto;padding:1rem 1.25rem;" id="rs-claves-body">
        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
            <thead>
                <tr style="background:#fef9c3;border-bottom:2px solid #fde68a;">
                    <th class="ca-th">Tipo</th>
                    <th class="ca-th">Entidad / Portal</th>
                    <th class="ca-th">Usuario</th>
                    <th class="ca-th">Contraseña</th>
                    <th class="ca-th" style="text-align:center;">Link</th>
                    <th class="ca-th">Correo</th>
                    <th class="ca-th">Observación</th>
                    <th class="ca-th" style="text-align:center;">Estado</th>
                </tr>
            </thead>
            <tbody id="rs-claves-tbody">
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;font-size:0.85rem;">Selecciona una razón social.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.razon-badge-link:hover { background:#bfdbfe !important; color:#1e3a8a !important; box-shadow:0 2px 8px rgba(37,99,235,0.15); transition:all .15s; }
.ca-th { padding:0.5rem 0.65rem;font-size:0.72rem;font-weight:700;color:#92400e;white-space:nowrap;text-align:left; }
</style>

@push('scripts')
<script>
// ── Variables globales ──
let radicadoActivo = null;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

// ── Helpers ──
function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.modal-bg').forEach(m => {
    m.addEventListener('click', e => { if(e.target === m) m.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
});

// ── Event delegation para badges de radicado ──
let docContextCedula  = null;
let docContextAlidoId = null;

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-rad');
    if(btn) {
        const radId         = btn.dataset.radId;
        const radData       = JSON.parse(btn.dataset.rad);
        const ctx           = btn.dataset.ctx ? JSON.parse(btn.dataset.ctx) : {};
        const contratoId    = btn.dataset.contratoId || null;
        const epsFormulario = btn.dataset.epsFormulario === '1';
        abrirModalRadicado(radId, radData, ctx, contratoId, epsFormulario);
        return;
    }
    const docBtn = e.target.closest('.btn-docs-open');
    if(docBtn) {
        docContextCedula  = docBtn.dataset.cedula;
        docContextAlidoId = docBtn.dataset.alidoId;
        abrirDocs(docBtn.dataset.radId, docBtn.dataset.nombre);
    }
});

// ── Modal Radicado ──
function abrirModalRadicado(radId, radData, ctx = {}, contratoId = null, epsFormulario = false) {
    radicadoActivo = radData;
    document.getElementById('mrad-id').value = radId;
    document.getElementById('mrad-titulo').textContent = '📝 ' + (radData.tipo?.toUpperCase() || 'Radicado');
    document.getElementById('mrad-tipo').textContent = (radData.tipo || '').toUpperCase();
    document.getElementById('mrad-num-rad').textContent = radData.numero_radicado ? 'N°: ' + radData.numero_radicado : 'Sin número asignado';
    // Contexto del contrato
    document.getElementById('mrad-cotizante').textContent        = ctx.nombre         || '—';
    document.getElementById('mrad-empresa').textContent          = ctx.razon_social    || '—';
    document.getElementById('mrad-modalidad').textContent        = ctx.tipo_modalidad  || '—';
    document.getElementById('mrad-empresa-cliente').textContent  = ctx.empresa_cliente || '—';
    document.getElementById('mrad-estado').value = radData.estado || 'pendiente';

    // ARL: sin número de radicado, canal forzado a 'portal'
    const esArl = (radData.tipo === 'arl');
    document.getElementById('seccionNumRadicado').style.display = esArl ? 'none' : '';
    document.getElementById('mrad-canal').value = esArl ? 'portal' : (radData.canal_envio || '');
    document.getElementById('mrad-numero').value = esArl ? '' : (radData.numero_radicado || '');
    document.getElementById('mrad-observacion').value = '';
    document.getElementById('mrad-enviado').checked = !!radData.enviado_al_cliente;
    document.getElementById('mrad-canal-cliente').value = radData.canal_envio_cliente || '';

    // ── Botón PDF Formulario EPS ──
    const secFormulario = document.getElementById('seccionFormularioEps');
    const btnPdf        = document.getElementById('btnFormularioPdf');
    const chkBen        = document.getElementById('chkBeneficiarios');
    const esEps = (radData.tipo === 'eps');

    if (esEps && epsFormulario && contratoId) {
        const baseUrl = `/admin/afiliaciones/${contratoId}/formulario/eps`;
        const actualizarHref = () => {
            btnPdf.href = baseUrl + (chkBen.checked ? '?beneficiarios=1' : '');
        };
        chkBen.checked = false;
        chkBen.onchange = actualizarHref;
        actualizarHref();
        secFormulario.style.display = 'flex';
        secFormulario.style.alignItems = 'center';
    } else {
        secFormulario.style.display = 'none';
    }

    togglePdfSection(radData.estado);
    document.getElementById('mrad-estado').addEventListener('change', function() {
        togglePdfSection(this.value);
    });

    cargarMiniBitacora(radId);
    document.getElementById('modalRadicado').classList.add('open');
}

function togglePdfSection(estado) {
    const sec = document.getElementById('seccionPdf');
    sec.style.display = (estado === 'ok') ? 'block' : 'none';
    if(estado === 'ok' && radicadoActivo?.ruta_pdf) {
        document.getElementById('pdfActual').innerHTML =
            '<a class="pdf-link" href="{{ route("admin.radicados.pdf.download", ":id") }}" target="_blank">'.replace(':id', radicadoActivo.id) +
            '📄 Ver PDF actual</a>';
    } else {
        document.getElementById('pdfActual').innerHTML = '';
    }
}

async function guardarRadicado(e) {
    e.preventDefault();
    const id       = document.getElementById('mrad-id').value;
    const estado   = document.getElementById('mrad-estado').value;
    const obs      = document.getElementById('mrad-observacion').value;
    const canal    = document.getElementById('mrad-canal').value;
    const numRad   = document.getElementById('mrad-numero').value;
    const enviado  = document.getElementById('mrad-enviado').checked;
    const canalCli = document.getElementById('mrad-canal-cliente').value;
    const btn      = document.getElementById('btnGuardarRadicado');

    // Observación ya no es obligatoria

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        // 1. Actualizar estado
        const r = await fetch(`/admin/radicados/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ estado, observacion: obs, canal_envio: canal, numero_radicado: numRad })
        });
        const data = await r.json();
        if(!data.ok) throw new Error('Error al guardar estado');

        // 2. Subir PDF si hay archivo
        const pdfFile = document.getElementById('mrad-pdf')?.files?.[0];
        if(pdfFile) {
            const fd = new FormData();
            fd.append('pdf', pdfFile);
            fd.append('_token', CSRF);
            await fetch(`/admin/radicados/${id}/pdf`, { method: 'POST', body: fd });
        }

        // 3. Marcar enviado si cambió
        if(enviado) {
            await fetch(`/admin/radicados/${id}/enviado`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ enviado_al_cliente: true, canal_envio_cliente: canalCli })
            });
        }

        // Actualizar badge en tabla sin recargar
        actualizarBadgesEnTabla(id, data);
        cerrarModal('modalRadicado');
        mostrarToast('✅ Radicado actualizado correctamente', 'success');
    } catch(err) {
        mostrarToast('❌ Error al guardar: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Guardar Cambios';
    }
}

function actualizarBadgesEnTabla(radId, data) {
    // Buscar todos los botones con este radicadoId
    document.querySelectorAll('[onclick*="abrirModalRadicado(' + radId + '"]').forEach(btn => {
        // Actualizar data y clase
        const clases = ['badge-pendiente','badge-tramite','badge-traslado','badge-error','badge-ok'];
        clases.forEach(c => btn.classList.remove(c));
        btn.classList.add('badge-' + data.estado);
        btn.textContent = data.icono + ' ' + data.estado.toUpperCase();
    });
    // Recargar la página para reflejar todos los cambios
    setTimeout(() => location.reload(), 800);
}

// ── Mini bitácora ──
async function cargarMiniBitacora(radId) {
    const el = document.getElementById('minibitacora');
    try {
        const r = await fetch(`/admin/radicados/${radId}/bitacora`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
        });
        const data = await r.json();
        const movs = data.movimientos?.slice(0, 3) || [];
        if(!movs.length) { el.textContent = 'Sin movimientos aún.'; return; }
        el.innerHTML = movs.map(m =>
            `<div style="padding:0.3rem 0;border-bottom:1px solid #f1f5f9;color:#475569;">
                <span style="color:#94a3b8;font-size:0.65rem;">${m.fecha_rel}</span> —
                <strong>${m.usuario}</strong>: ${m.estado_anterior ?? '?'} → <strong>${m.estado_nuevo}</strong>
                ${m.observacion ? `<div style="font-size:0.7rem;color:#64748b;margin-top:0.1rem;">${m.observacion}</div>` : ''}
            </div>`
        ).join('');
    } catch { el.textContent = 'No se pudo cargar.'; }
}

// ── Modal Bitácora completa ──
function abrirBitacora() {
    const id = document.getElementById('mrad-id').value;
    const tipo = document.getElementById('mrad-tipo').textContent;
    document.getElementById('mbit-tipo').textContent = tipo;
    cerrarModal('modalRadicado');
    document.getElementById('mbit-timeline').innerHTML = '<div style="text-align:center;padding:1rem;color:#94a3b8;">⏳ Cargando...</div>';
    document.getElementById('modalBitacora').classList.add('open');

    fetch(`/admin/radicados/${id}/bitacora`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            const rad = data.radicado;
            document.getElementById('mbit-resumen').innerHTML =
                `<strong>Estado actual:</strong> ${rad.estado?.toUpperCase()} &nbsp;|&nbsp;
                 <strong>Días en estado:</strong> ${rad.dias_actual} días &nbsp;|&nbsp;
                 <strong>PDF:</strong> ${rad.tiene_pdf ? '✅ Disponible' : '❌ No subido'}`;

            const movs = data.movimientos || [];
            if(!movs.length) { document.getElementById('mbit-timeline').innerHTML = '<div style="color:#94a3b8;text-align:center;padding:1rem;">Sin movimientos registrados.</div>'; return; }

            document.getElementById('mbit-timeline').innerHTML = movs.map((m, i) => `
                <div class="tl-item">
                    <div class="tl-date">${m.fecha} &nbsp;<span style="color:#94a3b8;">(${m.fecha_rel})</span></div>
                    <div class="tl-user">👤 ${m.usuario}</div>
                    <div class="tl-estados">
                        <span class="badge-estado badge-${m.estado_anterior ?? 'pendiente'}" style="cursor:default;font-size:0.6rem;">${(m.estado_anterior ?? '?').toUpperCase()}</span>
                        <span style="color:#94a3b8;">→</span>
                        <span class="badge-estado badge-${m.estado_nuevo}" style="cursor:default;font-size:0.6rem;">${m.estado_nuevo.toUpperCase()}</span>
                    </div>
                    ${m.observacion ? `<div class="tl-obs">${m.observacion}</div>` : ''}
                    ${m.dias_desde_prev > 0 ? `<div class="tl-dias">⏱ ${m.dias_desde_prev} día(s) desde el movimiento anterior</div>` : ''}
                </div>
            `).join('');
        })
        .catch(() => { document.getElementById('mbit-timeline').innerHTML = '<div style="color:#ef4444;text-align:center;">Error al cargar la bitácora.</div>'; });
}

// ── Modal Documentos ──
function abrirDocs(radId, nombre) {
    document.getElementById('mdocs-nombre').textContent = nombre;
    document.getElementById('mdocs-content').innerHTML = '<div style="text-align:center;padding:1rem;color:#94a3b8;">⏳ Cargando...</div>';
    document.getElementById('modalDocs').classList.add('open');

    if(!radId) {
        document.getElementById('mdocs-content').innerHTML = '<div class="empty-docs">No hay radicado disponible para este contrato.</div>';
        return;
    }

    fetch(`/admin/radicados/${radId}/documentos`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            let html = '';

            // Cotizante
            html += '<div class="doc-section"><h4>👤 Documentos del Cotizante</h4>';
            if(!data.cotizante?.length) html += '<div class="empty-docs">Sin documentos del cotizante.</div>';
            else data.cotizante.forEach(d => {
                html += `<div class="doc-item">
                    <div class="doc-item-left">
                        <span>📄</span>
                        <div><div class="doc-tipo">${d.tipo}</div><div class="doc-arch">${d.archivo}</div></div>
                    </div>
                    <a href="${d.url_dl}" class="btn-dl" target="_blank">⬇ Descargar</a>
                </div>`;
            });
            html += '</div>';

            // Beneficiarios
            if(data.lista_benef?.length) {
                html += '<div class="doc-section"><h4>👨‍👩‍👧 Documentos de Beneficiarios</h4>';
                if(!data.beneficiarios?.length) html += '<div class="empty-docs">Sin documentos de beneficiarios.</div>';
                else data.beneficiarios.forEach(d => {
                    html += `<div class="doc-item">
                        <div class="doc-item-left">
                            <span>📄</span>
                            <div><div class="doc-tipo">${d.tipo}</div><div class="doc-arch">${d.para} — ${d.archivo}</div></div>
                        </div>
                        <a href="${d.url_dl}" class="btn-dl" target="_blank">⬇ Descargar</a>
                    </div>`;
                });
                html += '</div>';

                // Lista de beneficiarios registrados
                html += '<div class="doc-section"><h4>📋 Beneficiarios Registrados</h4>';
                data.lista_benef.forEach(b => {
                    html += `<div style="font-size:0.75rem;padding:0.2rem 0.6rem;background:#f8fafc;border-radius:6px;margin-bottom:0.25rem;">
                        <strong>${b.nombres}</strong> — ${b.parentesco ?? 'Sin parentesco'} — Doc: ${b.n_documento ?? '—'}
                    </div>`;
                });
                html += '</div>';
            }

            // Rellenar select "¿Para quién?" con beneficiarios
            const selPara = document.getElementById('mdocs-para');
            selPara.innerHTML = '<option value="">Cotizante</option>';
            if(data.lista_benef?.length) {
                data.lista_benef.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.n_documento;  // doc_beneficiario = n_documento del benef.
                    opt.textContent = `${b.nombres} (${b.parentesco ?? 'Benef.'} · ${b.n_documento})`;
                    selPara.appendChild(opt);
                });
            }

            document.getElementById('mdocs-content').innerHTML = html || '<div class="empty-docs">Sin documentos.</div>';
        })
        .catch(() => { document.getElementById('mdocs-content').innerHTML = '<div style="color:#ef4444;text-align:center;">Error al cargar documentos.</div>'; });
}

// ── Subir documento desde modal documentos ──
async function subirDocumento() {
    const tipo    = document.getElementById('mdocs-tipo').value;
    const archivo = document.getElementById('mdocs-archivo').files[0];
    if(!archivo) { mostrarToast('Selecciona un archivo primero', 'error'); return; }
    if(archivo.size > 3 * 1024 * 1024) { mostrarToast('El archivo supera 3MB', 'error'); return; }
    if(!docContextCedula) { mostrarToast('Error: sin cédula de contexto', 'error'); return; }

    const btn = document.getElementById('btnSubirDoc');
    btn.disabled = true; btn.textContent = 'Subiendo...';

    const fd = new FormData();
    fd.append('_method', 'POST');
    fd.append('archivo', archivo);
    fd.append('tipo_documento', tipo);
    fd.append('_token', CSRF);
    const para = document.getElementById('mdocs-para').value;
    if(para) fd.append('doc_beneficiario', para);  // vacío = cotizante

    try {
        const r = await fetch(`/admin/clientes/${docContextCedula}/documentos`, { method: 'POST', body: fd });
        const data = await r.json();
        if(r.ok || data.ok) {
            mostrarToast('Documento subido correctamente', 'success');
            document.getElementById('mdocs-archivo').value = '';
            // Recargar la lista de documentos
            const radId = document.getElementById('mdocs-nombre').dataset?.radId
                ?? document.querySelector('.btn-docs-open[data-cedula="' + docContextCedula + '"]')?.dataset?.radId;
            if(radId) abrirDocs(radId, document.getElementById('mdocs-nombre').textContent);
        } else {
            mostrarToast(data.message ?? 'Error al subir el documento', 'error');
        }
    } catch(e) {
        mostrarToast('Error de conexión al subir', 'error');
    } finally {
        btn.disabled = false; btn.textContent = '⬆ Subir';
    }
}

// ══ CLAVES RAZÓN SOCIAL ══
function abrirClavesRS(rsId, rsNombre) {
    var panel   = document.getElementById('rs-claves-panel');
    var overlay = document.getElementById('rs-claves-overlay');
    document.getElementById('rs-claves-subtitulo').textContent = rsNombre;
    document.getElementById('rs-claves-notif').style.display = 'none';
    overlay.style.display = 'block';
    panel.style.display   = 'flex';
    setTimeout(function(){ panel.style.transform = 'translateX(0)'; }, 10);

    // Cargar claves
    var loading = document.getElementById('rs-claves-loading');
    var body    = document.getElementById('rs-claves-body');
    var tbody   = document.getElementById('rs-claves-tbody');
    loading.style.display = 'block';
    body.style.display    = 'none';

    fetch('/admin/clave-accesos/razon-social/' + rsId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF }
    })
    .then(function(r){ return r.json(); })
    .then(function(claves) {
        loading.style.display = 'none';
        body.style.display    = 'block';
        tbody.innerHTML = '';
        if (!claves || claves.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;font-size:0.85rem;">No hay claves registradas para esta razón social.</td></tr>';
            return;
        }
        var colores = {
            'Portal':['#eff6ff','#1d4ed8'],'Correo':['#fef3c7','#92400e'],
            'EPS':['#dcfce7','#15803d'],'ARL':['#fce7f3','#9d174d'],
            'AFP':['#e0e7ff','#3730a3'],'CAJA':['#fff7ed','#c2410c'],
            'DIAN':['#fef9c3','#713f12'],'MinTrabajo':['#f0fdf4','#166534'],
            'Banco':['#f5f3ff','#6d28d9'],'Otro':['#f1f5f9','#475569']
        };
        claves.forEach(function(c) {
            var col = colores[c.tipo] || ['#f1f5f9','#475569'];
            var tipoBadge = '<span style="background:'+col[0]+';color:'+col[1]+';padding:0.15rem 0.5rem;border-radius:999px;font-size:0.68rem;font-weight:700;">'+(c.tipo||'—')+'</span>';
            var linkBtn = c.link_acceso
                ? '<a href="'+c.link_acceso+'" target="_blank" style="background:#eff6ff;color:#2563eb;padding:0.18rem 0.5rem;border-radius:5px;font-size:0.7rem;font-weight:600;border:1px solid #bfdbfe;text-decoration:none;">🔗 Abrir</a>'
                : '<span style="color:#cbd5e1;">—</span>';
            var estadoBadge = c.activo
                ? '<span style="background:#dcfce7;color:#16a34a;padding:0.12rem 0.45rem;border-radius:999px;font-size:0.65rem;font-weight:700;">ACTIVO</span>'
                : '<span style="background:#fee2e2;color:#dc2626;padding:0.12rem 0.45rem;border-radius:999px;font-size:0.65rem;font-weight:700;">INACTIVO</span>';
            var masked = c.contrasena ? '•'.repeat(Math.min(c.contrasena.length,8))+' 👁' : '<span style="color:#cbd5e1;">—</span>';
            var passHtml = c.contrasena
                ? '<span style="font-family:monospace;font-size:0.77rem;cursor:pointer;" onclick="this.textContent=this.dataset.show==\'1\'?\''+('•'.repeat(8))+' 👁\':\''+c.contrasena+' 👁\';this.dataset.show=this.dataset.show==\'1\'?\'0\':\'1\';" data-show="0">'+masked+'</span>'
                : masked;
            var tr = document.createElement('tr');
            tr.style.cssText = 'border-bottom:1px solid #fef3c7;';
            tr.onmouseover = function(){ this.style.background='#fffbeb'; };
            tr.onmouseout  = function(){ this.style.background='transparent'; };
            tr.innerHTML =
                '<td style="padding:0.38rem 0.65rem;">'+tipoBadge+'</td>'+
                '<td style="padding:0.38rem 0.65rem;font-weight:600;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+(c.entidad||'')+'">'+(c.entidad||'—')+'</td>'+
                '<td style="padding:0.38rem 0.65rem;font-family:monospace;font-size:0.77rem;">'+(c.usuario||'<span style="color:#cbd5e1;">—</span>')+'</td>'+
                '<td style="padding:0.38rem 0.65rem;">'+passHtml+'</td>'+
                '<td style="padding:0.38rem 0.65rem;text-align:center;">'+linkBtn+'</td>'+
                '<td style="padding:0.38rem 0.65rem;font-size:0.75rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+(c.correo_entidad||'')+'">'+(c.correo_entidad||'<span style="color:#cbd5e1;">—</span>')+'</td>'+
                '<td style="padding:0.38rem 0.65rem;font-size:0.73rem;color:#64748b;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+(c.observacion||'')+'">'+(c.observacion||'')+'</td>'+
                '<td style="padding:0.38rem 0.65rem;text-align:center;">'+estadoBadge+'</td>';
            tbody.appendChild(tr);
        });
    })
    .catch(function() {
        loading.style.display = 'none';
        body.style.display    = 'block';
        var notif = document.getElementById('rs-claves-notif');
        notif.style.cssText = 'display:block;margin:0.5rem 1rem 0;padding:0.45rem 0.85rem;border-radius:7px;font-size:0.8rem;font-weight:600;background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;';
        notif.textContent = '❌ Error al cargar las claves.';
    });
}

function cerrarClavesRS() {
    var panel   = document.getElementById('rs-claves-panel');
    var overlay = document.getElementById('rs-claves-overlay');
    panel.style.transform = 'translateX(100%)';
    setTimeout(function(){
        panel.style.display   = 'none';
        overlay.style.display = 'none';
    }, 300);
}

// ── Toast ──
function mostrarToast(msg, tipo) {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;background:${tipo==='success'?'#15803d':'#b91c1c'};color:#fff;padding:0.7rem 1.2rem;border-radius:10px;font-size:0.85rem;font-weight:600;z-index:9999;box-shadow:0 4px 15px rgba(0,0,0,0.2);animation:modalIn .2s ease;`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>
@endpush
@endsection
