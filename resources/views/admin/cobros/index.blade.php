@extends('layouts.app')
@section('modulo', 'Cobros')

@php
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fmt   = fn($v) => '$' . number_format($v ?? 0, 0, ',', '.');
$semLabel = fn($s) => match($s) {
    'verde'    => ['🟢', '#15803d', '#dcfce7', 'Llamado reciente'],
    'amarillo' => ['🟡', '#92400e', '#fef3c7', '3–7 días sin llamar'],
    'rojo'     => ['🔴', '#b91c1c', '#fee2e2', 'Más de 7 días'],
    default    => ['⬜', '#64748b', '#f1f5f9', 'Sin llamadas'],
};
$estadoFact = fn($e) => match($e) {
    'pagada'      => ['Pagada',      '#15803d', '#dcfce7'],
    'abono'       => ['Abono',       '#92400e', '#fef3c7'],
    'prestamo'    => ['Préstamo',    '#6d28d9', '#ede9fe'],
    'pre_factura' => ['Pre-factura', '#64748b', '#f1f5f9'],
    default       => [ucfirst($e ?? '—'), '#64748b', '#f1f5f9'],
};
function sortUrlC($col, $cs, $cd) {
    $d = ($cs===$col && $cd==='asc') ? 'desc' : 'asc';
    $q = request()->except(['sort','dir']); $q['sort']=$col; $q['dir']=$d;
    return url()->current().'?'.http_build_query($q);
}
function sortClassC($col, $cs, $cd) {
    if($cs!==$col) return ''; return $cd==='asc'?'sort-asc':'sort-desc';
}
@endphp

@section('contenido')



<style>
/* ── Layout ── */
.cob-wrap { display:flex; flex-direction:column; gap:.8rem; }

/* ── Header ── */
.cob-header {
    background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1e40af 100%);
    border-radius:14px; padding:1rem 1.4rem; color:#fff;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.7rem;
}
.cob-title { font-size:1.3rem; font-weight:800; letter-spacing:.02em; }
.cob-sub   { font-size:.77rem; color:#94a3b8; margin-top:.15rem; }

/* ── Cards ── */
.cards-row { display:grid; grid-template-columns: repeat(6, 1fr); gap:.7rem; }
.card-item {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px;
    padding:.8rem 1rem; display:flex; flex-direction:column; gap:.2rem;
}
.card-item .ci-label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; }
.card-item .ci-val   { font-size:1.45rem; font-weight:800; color:#0f172a; font-family:monospace; }
.card-item .ci-sub   { font-size:.68rem; color:#94a3b8; }
.card-admon { border-top:3px solid #2563eb; }
.card-total { border-top:3px solid #0f172a; }
.card-sem-r { border-top:3px solid #dc2626; }
.card-sem-a { border-top:3px solid #d97706; }
.card-prom  { border-top:3px solid #7c3aed; }

/* ── Filtros ── */
.filtros {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px;
    padding:.75rem 1rem; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
}
.filtros select, .filtros input {
    padding:.38rem .7rem; border:1px solid #cbd5e1; border-radius:8px;
    font-size:.81rem; outline:none; background:#fff; color:#0f172a;
}
.filtros select:focus, .filtros input:focus { border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,.12); }
.btn-filtrar {
    padding:.38rem .95rem; background:#1e40af; color:#fff; border:none;
    border-radius:8px; font-size:.81rem; font-weight:600; cursor:pointer; transition:background .15s;
}
.btn-filtrar:hover { background:#1d4ed8; }
.btn-limpiar {
    padding:.38rem .8rem; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;
    border-radius:8px; font-size:.81rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;
}
.fil-sep { width:1px; height:22px; background:#e2e8f0; }

/* ── Tabla ── */
.tbl-wrap { overflow-x:auto; border-radius:12px; border:1px solid #e2e8f0; background:#fff; }
.tbl-cob  { width:100%; border-collapse:collapse; font-size:.775rem; white-space:nowrap; }
.tbl-cob thead th {
    background:#0f172a; color:#fff; padding:.5rem .55rem;
    font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em;
    position:sticky; top:0; z-index:2;
}
.tbl-cob thead th a { color:#cbd5e1; text-decoration:none; display:flex; align-items:center; gap:.2rem; justify-content:center; }
.tbl-cob thead th a:hover { color:#fff; }
.tbl-cob thead th a.sort-asc::after  { content:'\2191'; color:#3b82f6; margin-left:.15rem; }
.tbl-cob thead th a.sort-desc::after { content:'\2193'; color:#3b82f6; margin-left:.15rem; }
/* th-select */
.th-select {
    width:100%; background:transparent; border:none; border-bottom:1px solid rgba(255,255,255,.15);
    color:#fff; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
    padding:.2rem .2rem; cursor:pointer; outline:none; appearance:auto; -webkit-appearance:auto;
}
.th-select:hover { border-bottom-color:rgba(255,255,255,.5); }
.th-select:focus { border-bottom-color:#3b82f6; }
.th-select option { background:#0f172a; color:#fff; font-weight:600; text-transform:none; }
.th-select.activo { border-bottom-color:#3b82f6; color:#93c5fd; }

.tbl-cob tbody tr { border-bottom:1px solid #f1f5f9; transition:background .12s; }
.tbl-cob tbody tr:hover { background:#f8fafc; }
.tbl-cob td { padding:.42rem .52rem; vertical-align:middle; }

/* Badges */
.badge-tipo { display:inline-flex; align-items:center; gap:.2rem; padding:.18rem .45rem; border-radius:20px; font-size:.63rem; font-weight:700; }
.badge-afil { background:#ede9fe; color:#6d28d9; }
.badge-plan { background:#dbeafe; color:#1e40af; }
.badge-fact { display:inline-flex; align-items:center; padding:.16rem .45rem; border-radius:20px; font-size:.63rem; font-weight:700; }

/* Semáforo */
.sem-dot { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:600; }
.sem-dias { font-size:.62rem; color:#94a3b8; margin-left:.2rem; }

/* Botones acción */
.btn-llamar {
    padding:.25rem .6rem; border-radius:7px; font-size:.72rem; font-weight:700;
    cursor:pointer; border:none; transition:all .15s;
    background:linear-gradient(135deg,#1e40af,#2563eb); color:#fff;
    display:inline-flex; align-items:center; gap:.25rem;
}
.btn-llamar:hover { transform:translateY(-1px); box-shadow:0 3px 10px rgba(37,99,235,.3); }

.razon-badge {
    font-weight:700; font-size:.68rem; padding:.16rem .48rem; border-radius:6px;
    background:#dbeafe; color:#1e40af; display:inline-block; max-width:120px;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.num-mono { font-family:monospace; font-size:.77rem; }

/* ── Modal ── */
.modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.modal-bg.open { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:1.4rem; max-width:520px; width:95%; max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.22); animation:mIn .18s ease; }
.modal-box.wide { max-width:640px; }
@keyframes mIn { from{transform:translateY(-18px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-title  { font-size:.97rem; font-weight:800; color:#0f172a; margin-bottom:1rem; padding-bottom:.55rem; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
.modal-close  { background:none; border:none; font-size:1.2rem; cursor:pointer; color:#94a3b8; padding:0; line-height:1; }
.modal-close:hover { color:#ef4444; }
.form-grp { display:flex; flex-direction:column; gap:.22rem; margin-bottom:.75rem; }
.form-grp label { font-size:.7rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
.form-grp select, .form-grp textarea, .form-grp input {
    padding:.46rem .65rem; border:1px solid #cbd5e1; border-radius:8px;
    font-size:.85rem; outline:none; font-family:inherit;
}
.form-grp select:focus, .form-grp textarea:focus { border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,.1); }
.form-grp textarea { resize:vertical; min-height:80px; }
.btn-save {
    background:linear-gradient(135deg,#1e40af,#2563eb); color:#fff; border:none;
    border-radius:10px; padding:.58rem 1.5rem; font-size:.88rem; font-weight:700;
    cursor:pointer; box-shadow:0 3px 10px rgba(37,99,235,.3); transition:all .15s; width:100%;
}
.btn-save:hover { transform:translateY(-1px); box-shadow:0 5px 15px rgba(37,99,235,.4); }
/* Info box modal */
.info-box {
    background:#f0f9ff; border-radius:9px; padding:.55rem .85rem;
    margin-bottom:.85rem; display:flex; flex-wrap:wrap; gap:.6rem; font-size:.77rem;
}
.info-box strong { color:#0f172a; }
.info-box span   { color:#64748b; }
/* Timeline */
.timeline { position:relative; padding-left:1.4rem; }
.timeline::before { content:''; position:absolute; left:.45rem; top:0; bottom:0; width:2px; background:#e2e8f0; }
.tl-item { position:relative; margin-bottom:.9rem; }
.tl-item::before { content:''; position:absolute; left:-1.05rem; top:.28rem; width:9px; height:9px; border-radius:50%; border:2px solid #3b82f6; background:#fff; }
.tl-date { font-size:.66rem; color:#94a3b8; }
.tl-user { font-size:.7rem; font-weight:700; color:#1e40af; }
.tl-obs  { font-size:.78rem; color:#334155; margin-top:.15rem; }
.tl-res  { font-size:.68rem; font-weight:700; padding:.12rem .4rem; border-radius:5px; background:#f0fdf4; color:#15803d; display:inline-block; margin-top:.15rem; }

/* Toast */
.toast {
    position:fixed; bottom:1.2rem; right:1.2rem; z-index:9999;
    padding:.65rem 1.2rem; border-radius:10px; font-weight:600; font-size:.85rem;
    box-shadow:0 4px 16px rgba(0,0,0,.15); animation:toastIn .25s ease;
    display:none;
}
.toast.show { display:block; }
.toast.success { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.toast.error   { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
@keyframes toastIn { from{transform:translateY(10px);opacity:0} to{transform:translateY(0);opacity:1} }

/* Responsive */
@media(max-width:768px) {
    .cards-row { grid-template-columns:1fr 1fr; }
}
@media(max-width:1100px) {
    .cards-row { grid-template-columns:repeat(3,1fr); }
}
</style>

<div class="cob-wrap">

{{-- ══ HEADER ══ --}}
<form method="GET" action="{{ route('admin.cobros.index') }}" id="formFiltros">
<div class="cob-header">
    <div>
        <div class="cob-title">💰 Módulo de Cobros</div>
        <div class="cob-sub">Gestión de cartera pendiente · {{ $meses[$mes] }} {{ $anio }}</div>
    </div>
    <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
        {{-- Tabs navegación --}}
        <a href="{{ route('admin.cobros.index') }}"
           style="padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:700;text-decoration:none;background:#ffffff;color:#0f172a;border:1px solid rgba(255,255,255,.3);">
            👤 Individuales
        </a>
        <a href="{{ route('admin.cobros.empresas') }}"
           style="padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:700;text-decoration:none;background:rgba(255,255,255,.15);color:#cbd5e1;border:1px solid rgba(255,255,255,.15);">
            🏢 Empresas
        </a>
        <span style="width:1px;height:22px;background:rgba(255,255,255,.2);display:inline-block;"></span>
        <select name="mes" onchange="this.form.submit()" style="font-size:.8rem;padding:.3rem .5rem;border:1px solid #334155;background:#1e3a5f;color:#e2e8f0;border-radius:6px;">
            @foreach($meses as $i => $m)
            @if($i) <option value="{{ $i }}" {{ $mes==$i?'selected':'' }}>{{ $m }}</option> @endif
            @endforeach
        </select>
        <select name="anio" onchange="this.form.submit()" style="font-size:.8rem;padding:.3rem .5rem;border:1px solid #334155;background:#1e3a5f;color:#e2e8f0;border-radius:6px;">
            @for($y=date('Y');$y>=2023;$y--)
            <option value="{{ $y }}" {{ $anio==$y?'selected':'' }}>{{ $y }}</option>
            @endfor
        </select>
        {{-- Estado --}}
        <select name="estado" onchange="this.form.submit()" style="font-size:.78rem;padding:.3rem .5rem;border:1px solid #334155;border-radius:6px;
            {{ $soloPend==='pendiente' ? 'background:#b45309;color:#fff;' : 'background:#1e3a5f;color:#e2e8f0;' }}">
            <option value="pendiente" {{ $soloPend==='pendiente'?'selected':'' }}>⏳ Pendientes</option>
            <option value="todos"     {{ $soloPend==='todos'?'selected':'' }}>📋 Todos</option>
        </select>
        {{-- Tipo --}}
        <select name="tipo" onchange="this.form.submit()" style="font-size:.78rem;padding:.3rem .5rem;border:1px solid #334155;background:#1e3a5f;color:#e2e8f0;border-radius:6px;">
            <option value="individual" {{ $soloInd==='individual'?'selected':'' }}>👤 Individual</option>
            <option value="todos"      {{ $soloInd==='todos'?'selected':'' }}>🏢 Todos</option>
        </select>
        <span style="background:rgba(255,255,255,.15);color:#fff;font-size:.85rem;font-weight:800;padding:.3rem .7rem;border-radius:20px;white-space:nowrap;">
            {{ $contratos->count() }} <span style="font-size:.7rem;font-weight:500;opacity:.75;">registros</span>
        </span>
    </div>
</div>
</form>

{{-- ══ CARDS RESUMEN ══ --}}
<div class="cards-row">
    <div class="card-item card-admon">
        <div class="ci-label">💰 Admon por cobrar</div>
        <div class="ci-val" style="color:#1e40af;">{{ $fmt($totalAdmon) }}</div>
        <div class="ci-sub">Solo administración</div>
    </div>
    <div class="card-item card-total">
        <div class="ci-label">📋 Contratos</div>
        <div class="ci-val">{{ $totalPendientes }}</div>
        <div class="ci-sub">Con pago pendiente</div>
    </div>
    <div class="card-item card-sem-r">
        <div class="ci-label">🔴 Sin gestionar</div>
        <div class="ci-val" style="color:#dc2626;">{{ $sinLlamar }}</div>
        <div class="ci-sub">Nunca llamado o >7 días</div>
    </div>
    <div class="card-item card-sem-a">
        <div class="ci-label">🤝 Prometieron pago</div>
        <div class="ci-val" style="color:#d97706;">{{ $prometieronPago }}</div>
        <div class="ci-sub">Última llamada = promesa</div>
    </div>
    <div class="card-item card-prom">
        <div class="ci-label">📊 Total SS estimado</div>
        <div class="ci-val" style="color:#7c3aed; font-size:1.1rem;">{{ $fmt($totalSS) }}</div>
        <div class="ci-sub">EPS+ARL+AFP+Caja</div>
    </div>
    {{-- Tarjeta Préstamos --}}
    <a href="{{ route('admin.prestamos.index') }}" id="card-prestamos"
       style="text-decoration:none;"
       title="Ver módulo Préstamos">
        <div class="card-item" style="border-top:3px solid #4f46e5;cursor:pointer;transition:transform .15s,box-shadow .15s;"
             onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(79,70,229,.15)'"
             onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div class="ci-label" style="color:#4f46e5;">💳 Préstamos del Mes</div>
            <div class="ci-val" id="kpi-prest-val" style="color:#4f46e5;font-size:1.1rem;">—</div>
            <div class="ci-sub" id="kpi-prest-sub">Cargando…</div>
            <div style="margin-top:.35rem;font-size:.65rem;font-weight:700;color:#6d28d9;">→ Ver módulo Préstamos</div>
        </div>
    </a>
</div>

{{-- ══ FILTROS SECUNDARIOS ══ --}}
<form method="GET" action="{{ route('admin.cobros.index') }}" id="formFiltros2">
<input type="hidden" name="mes"    value="{{ $mes }}">
<input type="hidden" name="anio"   value="{{ $anio }}">
<input type="hidden" name="estado" value="{{ $soloPend }}">
<input type="hidden" name="tipo"   value="{{ $soloInd }}">
<div class="filtros">
    {{-- Buscar --}}
    <input type="text" name="buscar" value="{{ $buscar }}" placeholder="🔍 Nombre o cédula..." style="min-width:180px;">
    <div class="fil-sep"></div>
    {{-- Razón Social --}}
    <select name="razon_social_id" onchange="this.form.submit()">
        <option value="">— Razón Social —</option>
        @foreach($razonesDisponibles as $rs)
        <option value="{{ $rs->id }}" {{ $rsId==$rs->id?'selected':'' }}>{{ $rs->razon_social }}</option>
        @endforeach
    </select>
    {{-- Asesor --}}
    <select name="asesor_id" onchange="this.form.submit()">
        <option value="">— Asesor —</option>
        @foreach($asesoresDisponibles as $as)
        <option value="{{ $as->id }}" {{ $asesorId==$as->id?'selected':'' }}>{{ $as->nombre }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-filtrar">Filtrar</button>
    <a href="{{ route('admin.cobros.index') }}" class="btn-limpiar">✕ Limpiar</a>
</div>
</form>

{{-- ══ TABLA ══ --}}
@if($contratos->isEmpty())
<div style="text-align:center;padding:3rem;color:#94a3b8;background:#fff;border-radius:12px;border:1px solid #e2e8f0;">
    <div style="font-size:3rem;">💰</div>
    <div style="font-size:1rem;font-weight:600;margin-top:.5rem;">Sin contratos pendientes para este período</div>
    <div style="font-size:.8rem;margin-top:.25rem;">Prueba cambiando el filtro de Estado a "Todos".</div>
</div>
@else
<div class="tbl-wrap">
<table class="tbl-cob">
<thead>
<tr>
    {{-- N° Contrato --}}
    <th><a href="{{ sortUrlC('contrato', $sort, $dir) }}" class="{{ sortClassC('contrato', $sort, $dir) }}">N°</a></th>
    {{-- Cédula --}}
    <th><a href="{{ sortUrlC('cedula', $sort, $dir) }}"   class="{{ sortClassC('cedula', $sort, $dir) }}">Cédula</a></th>
    {{-- Nombre --}}
    <th>Nombre</th>
    {{-- Celular --}}
    <th style="text-align:center;" title="Celular">Celular</th>
    {{-- Razón Social --}}
    <th>
        <form method="GET" action="{{ route('admin.cobros.index') }}" style="margin:0">
            @foreach(request()->except(['razon_social_id','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="razon_social_id" onchange="this.form.submit()" class="th-select {{ $rsId ? 'activo' : '' }}">
                <option value="">↓ Razón Social</option>
                @foreach($razonesDisponibles as $rs)<option value="{{ $rs->id }}" {{ $rsId==$rs->id?'selected':'' }}>{{ \Illuminate\Support\Str::limit($rs->razon_social, 20, '…') }}</option>@endforeach
            </select>
        </form>
    </th>
    {{-- Ingreso --}}
    <th><a href="{{ sortUrlC('ingreso', $sort, $dir) }}"  class="{{ sortClassC('ingreso', $sort, $dir) }}">Ingreso</a></th>
    {{-- Tipo Modalidad --}}
    <th>
        <form method="GET" action="{{ route('admin.cobros.index') }}" style="margin:0">
            @foreach(request()->except(['tipo_modal','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="tipo_modal" onchange="this.form.submit()" class="th-select">
                <option value="">↓ Modalidad</option>
                <option value="dependiente">Dependiente</option>
                <option value="independiente">Independiente</option>
            </select>
        </form>
    </th>
    <th title="Afiliación o Planilla">AFIL/PLAN</th>
    {{-- Empresa/Cliente: solo cuando tipo = todos --}}
    @if($soloInd === 'todos')
    <th style="text-align:center">Empresa/Cliente</th>
    @endif
    {{-- Admon: solo cuando tipo = individual --}}
    @if($soloInd !== 'todos')
    <th class="num-col" title="Administración (solo empresa)">Admon</th>
    @endif
    <th class="num-col" title="Total estimado (SS+Admon+Seguro)">Total</th>
    {{-- Mora estimada al cliente --}}
    <th class="num-col" title="Mora estimada por pago tardío" style="color:#fbbf24;">⚠️ Mora</th>
    {{-- Factura: solo cuando filtro = todos --}}
    @if($soloPend === 'todos')
    <th style="text-align:center">Factura</th>
    <th title="N° Planilla">N° Planilla</th>
    @endif
    {{-- Semáforo siempre --}}
    <th style="text-align:center;min-width:90px">Semáforo</th>
    {{-- Gestión: solo cuando filtro = pendientes --}}
    @if($soloPend === 'pendiente')
    <th style="min-width:120px">Última gestión</th>
    @endif
    <th style="text-align:center">📞</th>
</tr>
</thead>
<tbody>
@foreach($contratos as $c)
@php
$nombre     = trim(($c->cliente?->primer_nombre ?? '') . ' ' . ($c->cliente?->primer_apellido ?? ''));
$rs         = $c->razonSocial?->razon_social ?? '—';
$celular    = $c->cliente?->celular ?? '—';
$fIng       = $c->fecha_ingreso?->format('d/m/Y') ?? '—';
$tipoMod    = $c->tipoModalidad?->tipo_modalidad ?? '?';
$tipoNom    = $c->tipoModalidad?->nombre ?? '—';
$esIndep    = $c->tipoModalidad?->esIndependiente() ? 'true' : 'false';
$costoAfil  = (int)($c->costo_afiliacion ?? 0);
$arlNivel   = $c->n_arl ?? 1;
$distAsesor = (int)($c->asesor?->comision_afil_valor ?? 0);
$fIngMes    = $c->fecha_ingreso?->month ?? 0;
$fIngAnio   = $c->fecha_ingreso?->year ?? 0;
[$semIco, $semColor, $semBg, $semTip] = $semLabel($c->semaforo);
@endphp
<tr data-cid="{{ $c->id }}">
    {{-- N° Contrato --}}
    <td style="text-align:center;font-weight:700;color:#1e40af;font-size:.72rem;">{{ $c->id }}</td>

    {{-- Cédula → abre contrato en modal iframe --}}
    <td>
        <button type="button"
            class="btn-facturar-cedula num-mono"
            data-contrato-id="{{ $c->id }}"
            data-nombre="{{ $nombre }}"
            data-cedula="{{ $c->cedula }}"
            title="Clic para abrir contrato"
            style="background:none;border:none;color:#3b82f6;font-weight:700;cursor:pointer;padding:0;font-family:monospace;font-size:.77rem;text-decoration:underline dotted;">
            {{ $c->cedula }}
        </button>
    </td>

    {{-- Nombre --}}
    <td>
        <div style="font-weight:600;color:#1e3a5f;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $nombre }}">{{ $nombre ?: '—' }}</div>
        {{-- Badge préstamo pendiente --}}
        @if($c->tiene_prestamo ?? false)
        <a href="{{ route('admin.prestamos.index', ['buscar' => $c->cedula, 'tab' => 'individuales']) }}"
           style="display:inline-block;margin-top:.15rem;padding:.08rem .35rem;border-radius:20px;font-size:.58rem;font-weight:700;background:#ede9fe;color:#6d28d9;text-decoration:none;"
           title="Tiene préstamo pendiente — clic para ver">
            💳 Préstamo
        </a>
        @endif
    </td>

    {{-- Celular + WhatsApp --}}
    <td style="white-space:nowrap;">
        @if($celular && $celular !== '—')
        <div style="display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;color:#334155;font-family:monospace;font-weight:600;">
            {{ $celular }}
            <a href="https://wa.me/57{{ preg_replace('/\D/', '', $celular) }}" target="_blank"
               title="Abrir WhatsApp" style="text-decoration:none;line-height:1;display:inline-flex;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#25d366" width="14" height="14"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        </div>
        @else
        <span style="color:#cbd5e1;font-size:.7rem;">—</span>
        @endif
    </td>

    {{-- Razón Social --}}
    <td><span class="razon-badge" title="{{ $rs }}">{{ \Illuminate\Support\Str::limit($rs, 20, '…') }}</span></td>

    {{-- Ingreso --}}
    <td style="text-align:center;font-size:.72rem;color:#64748b;">{{ $fIng }}</td>

    {{-- Tipo Modalidad --}}
    <td style="text-align:center;font-size:.72rem;font-weight:700;" title="{{ $tipoNom }}">{{ $tipoMod }}</td>

    {{-- AFIL / PLAN --}}
    <td style="text-align:center;">
        @if($c->es_ind_act_primer_mes ?? false)
            <span class="badge-tipo" style="background:#f3e8ff;color:#7c3aed;" title="I ACT · Cobra Afiliación + Planilla juntas este mes">⚡ ACT</span>
        @elseif($c->es_afil)
            <span class="badge-tipo badge-afil">📌 AFIL</span>
        @else
            <span class="badge-tipo badge-plan">📄 PLAN</span>
        @endif
    </td>

    {{-- Empresa/Cliente: solo cuando tipo = todos --}}
    @if($soloInd === 'todos')
    <td style="text-align:center;font-size:.72rem;">
        @if($c->es_empresa)
            <span style="display:inline-block;padding:.15rem .45rem;border-radius:20px;font-size:.62rem;font-weight:700;background:#dbeafe;color:#1e40af;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;"
                  title="{{ $c->nombre_empresa }}">
                🏢 {{ \Illuminate\Support\Str::limit($c->nombre_empresa, 12, '…') }}
            </span>
        @else
            <span style="display:inline-block;padding:.15rem .45rem;border-radius:20px;font-size:.62rem;font-weight:700;background:#f0fdf4;color:#15803d;">
                👤 Individual
            </span>
        @endif
    </td>
    @endif

    {{-- Admon: solo cuando tipo = individual --}}
    @if($soloInd !== 'todos')
    <td class="num-col" style="font-weight:600;color:#0f172a;">
        {{ $fmt($c->administracion ?? 0) }}
    </td>
    @endif

    {{-- Total estimado --}}
    <td class="num-col" style="font-weight:700;color:#1e40af;" title="SS: {{ $fmt($c->v_ss) }}">
        {{ $fmt($c->total_estimado) }}
    </td>

    {{-- Mora estimada --}}
    <td class="num-col">
        @if(($c->mora_estimada ?? 0) > 0)
            <span style="display:inline-block;padding:.12rem .42rem;border-radius:20px;font-size:.62rem;font-weight:700;background:#fef3c7;color:#92400e;" title="Mora estimada por pago tardío">
                {{ $fmt($c->mora_estimada) }}
            </span>
        @else
            <span style="color:#cbd5e1;font-size:.7rem;">—</span>
        @endif
    </td>

    {{-- Factura y N° Planilla: solo cuando filtro = todos --}}
    @if($soloPend === 'todos')
    <td style="text-align:center;">
        @if($c->fact_id)
            @php [$fl, $fc, $fb] = $estadoFact($c->fact_estado); @endphp
            <a href="{{ route('admin.facturacion.recibo', $c->fact_id) }}" target="_blank"
               style="display:inline-block;padding:.15rem .5rem;border-radius:20px;font-size:.62rem;font-weight:700;background:{{ $fb }};color:{{ $fc }};text-decoration:none;"
               title="Recibo #{{ $c->fact_numero }}">
                {{ $fl }} #{{ $c->fact_numero }}
            </a>
        @else
            <span style="color:#cbd5e1;font-size:.7rem;">Sin factura</span>
        @endif
    </td>
    <td style="text-align:center;font-size:.72rem;color:#64748b;font-weight:700;">
        {{ $c->fact_n_plano ?? '—' }}
    </td>
    @endif

    {{-- Semáforo (siempre) --}}
    <td style="text-align:center;">
        <span class="sem-dot" style="color:{{ $semColor }};" title="{{ $semTip }}">
            {{ $semIco }}
            @if($c->dias_sin_llamar !== null)
                <span class="sem-dias">{{ $c->dias_sin_llamar }}d</span>
            @endif
        </span>
    </td>

    {{-- Gestión: solo cuando filtro = pendientes --}}
    @if($soloPend === 'pendiente')
    <td>
        @if($c->ultima_llamada)
            <div style="font-size:.7rem;font-weight:600;color:#334155;">
                {{ \App\Models\BitacoraCobro::RESULTADOS[$c->ultima_llamada->resultado] ?? $c->ultima_llamada->resultado }}
            </div>
            @if($c->ultima_llamada->observacion)
            <div style="font-size:.66rem;color:#64748b;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $c->ultima_llamada->observacion }}">
                {{ $c->ultima_llamada->observacion }}
            </div>
            @endif
        @else
            <span style="color:#cbd5e1;font-size:.7rem;">Sin gestiones</span>
        @endif
    </td>
    @endif

    {{-- Botón llamar (siempre) --}}
    <td style="text-align:center;">
        <button class="btn-llamar btn-abrir-modal"
            data-contrato-id="{{ $c->id }}"
            data-nombre="{{ $nombre }}"
            data-cedula="{{ $c->cedula }}"
            data-celular="{{ $celular }}"
            data-admon="{{ $fmt($c->administracion ?? 0) }}"
            data-total="{{ $fmt($c->total_estimado) }}"
            data-factura-id="{{ $c->fact_id ?? '' }}"
            data-semaforo="{{ $c->semaforo }}"
            title="Registrar llamada de cobro">
            📞
        </button>
    </td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr style="background:#0f172a;color:#fff;font-weight:700;">
    <td colspan="8" style="padding:.5rem .55rem;font-size:.72rem;">TOTALES ({{ $contratos->count() }} registros)</td>
    <td class="num-col" style="color:#34d399;padding:.5rem .55rem;">{{ $fmt($totalAdmon) }}</td>
    <td class="num-col" style="color:#34d399;padding:.5rem .55rem;">{{ $fmt($contratos->sum('total_estimado')) }}</td>
    <td class="num-col" style="color:#fbbf24;padding:.5rem .55rem;" title="Mora total estimada">
        {{ $contratos->sum('mora_estimada') > 0 ? $fmt($contratos->sum('mora_estimada')) : '—' }}
    </td>
    <td colspan="{{ $soloPend === 'todos' ? 4 : ($soloPend === 'pendiente' ? 3 : 3) }}"></td>
</tr>
</tfoot>
</table>
</div>
@endif

</div>{{-- /cob-wrap --}}

{{-- ══ MODAL REGISTRAR LLAMADA ══ --}}
<div class="modal-bg" id="modalLlamada">
<div class="modal-box">
    <div class="modal-title">
        <span>📞 Registrar Llamada de Cobro</span>
        <button class="modal-close" onclick="cerrarModal('modalLlamada')">✕</button>
    </div>

    <div class="info-box">
        <div><span>👤 Cliente:</span> <strong id="ml-nombre"></strong></div>
        <div><span>ID:</span> <strong id="ml-cedula"></strong></div>
        <div><span>Admon:</span> <strong id="ml-admon"></strong></div>
        <div><span>Total estimado:</span> <strong id="ml-total"></strong></div>
        <div style="display:flex;align-items:center;gap:.4rem;">
            <span>📞</span> <strong id="ml-celular"></strong>
            <a id="ml-wa-link" href="#" target="_blank" title="WhatsApp"
               style="text-decoration:none;display:none;line-height:1;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#25d366" width="16" height="16"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        </div>
    </div>

    <form id="formLlamada" onsubmit="guardarLlamada(event)">
        <input type="hidden" id="ml-contrato-id">
        <input type="hidden" id="ml-factura-id">

        <div class="form-grp">
            <label>Resultado de la llamada *</label>
            <select id="ml-resultado">
                <option value="no_contesta">📵 No contesta</option>
                <option value="promesa_pago">🤝 Promesa de pago</option>
                <option value="pagado">✅ Ya pagó / Pagará hoy</option>
                <option value="numero_errado">❌ Número errado</option>
                <option value="otro">📝 Otro</option>
            </select>
        </div>

        <div class="form-grp">
            <label>Observación — ¿Qué dijo el cliente?</label>
            <textarea id="ml-observacion" placeholder="Ej: informó que consigna el viernes..."></textarea>
        </div>

        <button type="submit" class="btn-save" id="btnGuardarLlamada">💾 Guardar Llamada</button>
    </form>

    {{-- Historial de llamadas previas --}}
    <div style="margin-top:1rem;padding-top:.9rem;border-top:1px solid #f1f5f9;">
        <div style="font-size:.7rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">
            Historial de gestiones
        </div>
        <div id="ml-historial" style="font-size:.75rem;color:#94a3b8;">Cargando...</div>
    </div>
</div>
</div>

{{-- ═══ Modal iframe: Contrato ═══ --}}
<div id="modalContratoOverlay" style="
    display:none; position:fixed; inset:0; z-index:3000;
    background:rgba(10,10,20,.7); backdrop-filter:blur(4px);
    align-items:center; justify-content:center; padding:.75rem;
" onclick="if(event.target===this)cerrarModalContrato()">
    <div style="
        background:#fff; border-radius:16px; width:min(1180px,97vw);
        height:94vh; display:flex; flex-direction:column;
        box-shadow:0 32px 100px rgba(0,0,0,.5);
        overflow:hidden;
    ">
        {{-- Header del modal --}}
        <div style="
            background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);
            padding:.65rem 1.2rem; display:flex; align-items:center;
            justify-content:space-between; flex-shrink:0;
        ">
            <div style="display:flex;align-items:center;gap:.6rem;">
                <span style="font-size:1.1rem;">📋</span>
                <div>
                    <div style="font-size:.9rem;font-weight:800;color:#fff;" id="iframeContratoTitulo">Contrato</div>
                    <div style="font-size:.62rem;color:rgba(255,255,255,.5);">Puede facturar o marcar retiro desde esta ventana</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;">
                <a id="iframeContratoLink" href="#" target="_blank"
                   style="font-size:.72rem;font-weight:600;color:rgba(255,255,255,.6);text-decoration:none;padding:.3rem .7rem;border:1px solid rgba(255,255,255,.2);border-radius:6px;transition:all .15s;"
                   onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.6)'">
                   &#x2197; Abrir pestaña
                </a>
                <button onclick="cerrarModalContrato()" style="
                    width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;
                    background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);
                    font-size:1rem;display:flex;align-items:center;justify-content:center;
                    transition:background .15s;
                " onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
                    ✕
                </button>
            </div>
        </div>
        {{-- iframe container con spinner --}}
        <div style="position:relative;flex:1;overflow:hidden;">
            {{-- Spinner de carga --}}
            <div id="iframeLoading" style="
                position:absolute;inset:0;background:#f8fafc;
                display:flex;flex-direction:column;align-items:center;justify-content:center;
                gap:1rem;z-index:10;
            ">
                <div style="
                    width:44px;height:44px;border-radius:50%;
                    border:4px solid #e2e8f0;border-top-color:#3b82f6;
                    animation:spinIframe .7s linear infinite;
                "></div>
                <div style="font-size:.82rem;color:#64748b;font-weight:600;">Cargando contrato...</div>
            </div>
            <iframe id="iframeContrato" src=""
                style="width:100%;height:100%;border:none;display:block;"
                onload="document.getElementById('iframeLoading').style.display='none'">
            </iframe>
        </div>
    </div>
</div>

{{-- Toast --}}
<div class="toast" id="toastMsg"></div>

@push('scripts')
<style>
@keyframes spinIframe { to { transform: rotate(360deg); } }
</style>
<script>
const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content;
const URL_LLAMADA  = '{{ route("admin.cobros.llamada.store", ["contratoId" => "__ID__"]) }}';
const URL_LLAMADAS = '{{ route("admin.cobros.llamadas",     ["contratoId" => "__ID__"]) }}';
const BASE_CONTRATO = '{{ url("admin/contratos") }}';
let contratoActivo = null;

// ── Modal iframe: contrato ──────────────────────────────────────────────
function cerrarModalContrato() {
    const ov = document.getElementById('modalContratoOverlay');
    const fr = document.getElementById('iframeContrato');
    ov.style.display = 'none';
    // Resetear antes de limpiar src para que el onload de la página vacía
    // NO sea interpretado como "segunda carga = acción completada"
    _iframeFirstLoad = false;
    fr.src = '';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        cerrarModalContrato();
        document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
    }
});

// Click en cédula → abrir iframe
let _iframeFirstLoad  = false; // flag para detectar solo la 1era carga
let _iframeContratoId = null;  // ID del contrato activo en el iframe

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-facturar-cedula');
    if (!btn) return;

    _iframeContratoId = btn.dataset.contratoId;
    _iframeFirstLoad  = false; // reset para este contrato

    const cid     = _iframeContratoId;
    const nombre  = btn.dataset.nombre || btn.dataset.cedula;
    const fullUrl = `${BASE_CONTRATO}/${cid}/edit`;
    const url     = `${fullUrl}?iframe=1`;

    document.getElementById('iframeContratoTitulo').textContent = nombre;
    document.getElementById('iframeContratoLink').href = fullUrl;
    document.getElementById('iframeLoading').style.display = 'flex';
    document.getElementById('iframeContrato').src = url;
    document.getElementById('modalContratoOverlay').style.display = 'flex';
});

// ── Acción completada: quitar solo la fila afectada ──────────────────────
function onAccionCompletada(contratoId, accion, mensaje) {
    cerrarModalContrato();

    const cid = contratoId || _iframeContratoId;
    if (!cid) return;

    const tr = document.querySelector(`tr[data-cid="${cid}"]`);
    if (tr) {
        // Animación de salida
        tr.style.transition = 'opacity .35s ease, transform .35s ease';
        tr.style.opacity    = '0';
        tr.style.transform  = 'translateX(50px)';
        setTimeout(() => {
            tr.remove();
            // Actualizar contador en el footer
            const rows = document.querySelectorAll('tbody tr').length;
            const footerTd = document.querySelector('tfoot tr td:first-child');
            if (footerTd) footerTd.textContent = `TOTALES (${rows} registros)`;
        }, 380);
    }

    mostrarToast('✅ ' + (mensaje || 'Acción completada'), 'success');
    _iframeContratoId = null;
}

// ── Detectar acción desde iframe ──────────────────────────────────────────

// 1) postMessage: enviado por form.blade.php al facturar
window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'brynex:iframe_done') {
        onAccionCompletada(e.data.contratoId, e.data.accion, e.data.mensaje);
    }
});

// ── Helpers ──
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-bg').forEach(m => {
    m.addEventListener('click', e => { if(e.target === m) m.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
});

function mostrarToast(msg, tipo = 'success') {
    const t = document.getElementById('toastMsg');
    t.textContent = msg;
    t.className = `toast show ${tipo}`;
    setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Abrir modal llamada ──
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-abrir-modal');
    if (!btn) return;

    contratoActivo = btn.dataset.contratoId;
    document.getElementById('ml-nombre').textContent  = btn.dataset.nombre;
    document.getElementById('ml-cedula').textContent  = btn.dataset.cedula;
    document.getElementById('ml-admon').textContent   = btn.dataset.admon;
    document.getElementById('ml-total').textContent   = btn.dataset.total;
    document.getElementById('ml-contrato-id').value   = contratoActivo;
    document.getElementById('ml-factura-id').value    = btn.dataset.facturaId || '';
    document.getElementById('ml-resultado').value     = 'no_contesta';
    document.getElementById('ml-observacion').value   = '';
    // Celular + WhatsApp
    const celEl = document.getElementById('ml-celular');
    const waElI = document.getElementById('ml-wa-link');
    const rawCel = (btn.dataset.celular || '').replace(/\D/g,'');
    if (celEl) celEl.textContent = btn.dataset.celular || '';
    if (waElI && rawCel) { waElI.href = 'https://wa.me/57' + rawCel; waElI.style.display = 'inline'; }
    else if (waElI)      { waElI.style.display = 'none'; }
    // Cargar historial
    cargarHistorial(contratoActivo);
    document.getElementById('modalLlamada').classList.add('open');
});

// ── Guardar llamada ──
async function guardarLlamada(e) {
    e.preventDefault();
    const id         = document.getElementById('ml-contrato-id').value;
    const resultado  = document.getElementById('ml-resultado').value;
    const observacion= document.getElementById('ml-observacion').value;
    const facturaId  = document.getElementById('ml-factura-id').value;
    const btn        = document.getElementById('btnGuardarLlamada');

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        const r = await fetch(URL_LLAMADA.replace('__ID__', id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ resultado, observacion, factura_id: facturaId || null })
        });
        const data = await r.json();
        if (!data.ok) throw new Error('Error al guardar');

        // Actualizar semáforo en la fila de la tabla
        actualizarFilaSemaforo(id, data);
        cerrarModal('modalLlamada');
        mostrarToast('✅ Llamada registrada correctamente');
    } catch(err) {
        mostrarToast('❌ Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Guardar Llamada';
    }
}

// ── Cargar historial ──
async function cargarHistorial(contratoId) {
    const el = document.getElementById('ml-historial');
    el.innerHTML = '<span style="color:#94a3b8;">Cargando...</span>';
    try {
        const r = await fetch(URL_LLAMADAS.replace('__ID__', contratoId), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
        });
        const data = await r.json();
        if (!data.llamadas || !data.llamadas.length) {
            el.innerHTML = '<span style="color:#94a3b8;">Sin gestiones previas</span>';
            return;
        }
        el.innerHTML = '<div class="timeline">' +
            data.llamadas.map(l => `
                <div class="tl-item">
                    <div class="tl-date">${l.fecha} &nbsp; <span class="tl-user">${l.usuario}</span></div>
                    <div class="tl-res">${l.etiqueta}</div>
                    ${l.observacion ? `<div class="tl-obs">${l.observacion}</div>` : ''}
                </div>`).join('') +
        '</div>';
    } catch {
        el.innerHTML = '<span style="color:#94a3b8;">Error al cargar el historial</span>';
    }
}

// ── Actualizar semáforo en la tabla ──
function actualizarFilaSemaforo(contratoId, data) {
    // Recargar para reflejar cambios
    setTimeout(() => location.reload(), 600);
}

// ── KPI Préstamos del mes (carga asíncrona) ──────────────────────────
(function() {
    const valEl = document.getElementById('kpi-prest-val');
    const subEl = document.getElementById('kpi-prest-sub');
    if (!valEl) return;
    const fmtP = v => '$' + Math.round(v||0).toLocaleString('es-CO');
    fetch(`{{ route('admin.informes.financiero.prestamos_mes') }}?mes={{ $mes }}&anio={{ $anio }}`)
        .then(r => r.json())
        .then(data => {
            const t = data.totales || {};
            valEl.textContent = fmtP(t.saldo_pendiente || 0);
            subEl.textContent = (t.cant || 0) + ' préstamo(s) — saldo pendiente';
        })
        .catch(() => {
            valEl.textContent = '—';
            subEl.textContent = 'No disponible';
        });
})();
</script>
@endpush
@endsection
