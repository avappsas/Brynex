@extends('layouts.app')
@section('modulo', 'Cobros · Empresas')

@php
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$semLabel = fn($s) => match($s) {
    'verde'    => ['🟢', '#15803d', 'Llamado reciente'],
    'amarillo' => ['🟡', '#92400e', '3–7 días sin llamar'],
    'rojo'     => ['🔴', '#b91c1c', 'Más de 7 días'],
    default    => ['⬜', '#64748b', 'Sin llamadas'],
};
$sortUrlE = function($col, $cs, $cd) {
    $d = ($cs===$col && $cd==='asc') ? 'desc' : 'asc';
    $q = request()->except(['sort','dir']); $q['sort']=$col; $q['dir']=$d;
    return url()->current().'?'.http_build_query($q);
};
$sortClassE = function($col, $cs, $cd) {
    if($cs!==$col) return ''; return $cd==='asc'?'sort-asc':'sort-desc';
};
$fmt  = fn($v) => number_format($v ?? 0, 0, '', '');
$waUrl = fn($tel) => $tel ? 'https://wa.me/57' . preg_replace('/\D/', '', $tel) : null;
@endphp

@section('contenido')
<style>
/* Tabs */
.cobros-tabs {
    display:flex; gap:.45rem; margin-bottom:.75rem;
}
.cobros-tab {
    padding:.45rem 1.1rem; border-radius:9px; font-size:.8rem; font-weight:700;
    text-decoration:none; border:1px solid #e2e8f0; background:#f8fafc; color:#475569;
    transition:all .15s;
}
.cobros-tab:hover   { background:#e2e8f0; color:#1e3a5f; }
.cobros-tab.activo  { background:#0f172a; color:#fff; border-color:#0f172a; }

/* Header */
.cob-header {
    background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1e40af 100%);
    border-radius:14px; padding:1rem 1.4rem; color:#fff;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.7rem;
    margin-bottom:.75rem;
}
.cob-title { font-size:1.25rem; font-weight:800; }
.cob-sub   { font-size:.75rem; color:#94a3b8; margin-top:.1rem; }

/* Cards */
.cards-row { display:grid; grid-template-columns:repeat(4,1fr); gap:.7rem; margin-bottom:.75rem; }
.card-item { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:.8rem 1rem; }
.card-item .ci-label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; }
.card-item .ci-val   { font-size:1.4rem; font-weight:800; color:#0f172a; font-family:monospace; }
.card-item .ci-sub   { font-size:.67rem; color:#94a3b8; }
.c-emp  { border-top:3px solid #1e40af; }
.c-cont { border-top:3px solid #0f172a; }
.c-pag  { border-top:3px solid #15803d; }
.c-pend { border-top:3px solid #dc2626; }

/* Filtros */
.filtros { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:.7rem 1rem;
    display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-bottom:.75rem; }
.filtros select, .filtros input { padding:.36rem .65rem; border:1px solid #cbd5e1; border-radius:8px;
    font-size:.8rem; outline:none; background:#fff; }
.filtros select:focus, .filtros input:focus { border-color:#3b82f6; }
.btn-filtrar { padding:.36rem .9rem; background:#1e40af; color:#fff; border:none; border-radius:8px;
    font-size:.8rem; font-weight:600; cursor:pointer; }
.btn-limpiar { padding:.36rem .8rem; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;
    border-radius:8px; font-size:.8rem; font-weight:600; text-decoration:none; }

/* Tabla */
.tbl-wrap { overflow-x:auto; border-radius:12px; border:1px solid #e2e8f0; background:#fff; }
.tbl-emp  { width:100%; border-collapse:collapse; font-size:.77rem; white-space:nowrap; }
.tbl-emp thead th {
    background:#0f172a; color:#fff; padding:.5rem .6rem;
    font-weight:600; font-size:.67rem; text-transform:uppercase; letter-spacing:.04em;
    position:sticky; top:0; z-index:2; text-align:center;
}
.tbl-emp thead th a { color:#cbd5e1; text-decoration:none; display:flex; align-items:center; gap:.2rem; justify-content:center; }
.tbl-emp thead th a:hover { color:#fff; }
.tbl-emp thead th a.sort-asc::after  { content:'\2191'; color:#3b82f6; margin-left:.15rem; }
.tbl-emp thead th a.sort-desc::after { content:'\2193'; color:#3b82f6; margin-left:.15rem; }
.tbl-emp tbody tr { border-bottom:1px solid #f1f5f9; transition:background .12s; }
.tbl-emp tbody tr:hover { background:#f8fafc; }
.tbl-emp td { padding:.45rem .55rem; vertical-align:middle; }

/* Números */
.num-cell { text-align:center; font-family:monospace; font-weight:700; font-size:.8rem; }
.num-0    { color:#94a3b8; }
.num-afil { color:#6d28d9; }
.num-ind  { color:#0369a1; }
.num-plan { color:#1e40af; }
.num-pend-total { color:#dc2626; font-size:.88rem; }
.num-pag  { color:#15803d; }

/* Empresa link */
.emp-nombre { font-weight:700; color:#1e3a5f; font-size:.8rem; }
.emp-nombre:hover { color:#2563eb; }
.emp-contacto { font-size:.7rem; color:#64748b; }

/* th-select override: mismo aspecto que los demás títulos */
.tbl-emp thead th .th-select {
    background:transparent !important;
    border:none !important;
    color:#cbd5e1;
    font-size:.67rem;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.04em;
    cursor:pointer;
    outline:none;
    appearance:none;
    -webkit-appearance:none;
    padding:0;
    width:100%;
}
.tbl-emp thead th .th-select:focus { color:#fff; }
.tbl-emp thead th .th-select.activo { color:#93c5fd; }
.tbl-emp thead th .th-select option { background:#0f172a; color:#fff; font-weight:600; text-transform:none; }

/* Encargado select */
.enc-select {
    font-size:.7rem; border:1px solid #e2e8f0; border-radius:6px; padding:.18rem .35rem;
    background:#f8fafc; color:#334155; outline:none; cursor:pointer; max-width:120px;
}
.enc-select:hover { border-color:#3b82f6; }
.enc-select.guardado { border-color:#15803d; background:#f0fdf4; }

/* Semáforo */
.sem-dot { display:inline-flex; align-items:center; gap:.3rem; font-size:.75rem; font-weight:600; }

/* Botón llamar */
.btn-llamar {
    padding:.25rem .6rem; border-radius:7px; font-size:.72rem; font-weight:700;
    cursor:pointer; border:none; transition:all .15s;
    background:linear-gradient(135deg,#1e40af,#2563eb); color:#fff;
}
.btn-llamar:hover { transform:translateY(-1px); box-shadow:0 3px 10px rgba(37,99,235,.3); }

/* Modal */
.modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.modal-bg.open { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:1.4rem; max-width:500px; width:95%; max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.22); animation:mIn .18s ease; }
@keyframes mIn { from{transform:translateY(-18px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-title  { font-size:.95rem; font-weight:800; color:#0f172a; margin-bottom:1rem; padding-bottom:.5rem; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
.modal-close  { background:none; border:none; font-size:1.2rem; cursor:pointer; color:#94a3b8; }
.modal-close:hover { color:#ef4444; }
.info-box { background:#f0f9ff; border-radius:9px; padding:.55rem .85rem; margin-bottom:.85rem; display:flex; flex-wrap:wrap; gap:.6rem; font-size:.77rem; }
.form-grp { display:flex; flex-direction:column; gap:.22rem; margin-bottom:.75rem; }
.form-grp label { font-size:.7rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
.form-grp select, .form-grp textarea { padding:.46rem .65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; outline:none; font-family:inherit; }
.form-grp select:focus, .form-grp textarea:focus { border-color:#3b82f6; }
.form-grp textarea { resize:vertical; min-height:75px; }
.btn-save { background:linear-gradient(135deg,#1e40af,#2563eb); color:#fff; border:none; border-radius:10px; padding:.55rem 1.4rem; font-size:.88rem; font-weight:700; cursor:pointer; width:100%; }
.timeline { padding-left:1.4rem; position:relative; }
.timeline::before { content:''; position:absolute; left:.45rem; top:0; bottom:0; width:2px; background:#e2e8f0; }
.tl-item { position:relative; margin-bottom:.8rem; }
.tl-item::before { content:''; position:absolute; left:-1.05rem; top:.28rem; width:9px; height:9px; border-radius:50%; border:2px solid #3b82f6; background:#fff; }
.tl-date { font-size:.66rem; color:#94a3b8; } .tl-user { font-size:.7rem; font-weight:700; color:#1e40af; }
.tl-res  { font-size:.68rem; font-weight:700; padding:.1rem .4rem; border-radius:5px; background:#f0fdf4; color:#15803d; display:inline-block; margin-top:.1rem; }
.tl-obs  { font-size:.77rem; color:#334155; margin-top:.15rem; }
.toast { position:fixed; bottom:1.2rem; right:1.2rem; z-index:9999; padding:.65rem 1.2rem; border-radius:10px; font-weight:600; font-size:.85rem; box-shadow:0 4px 16px rgba(0,0,0,.15); display:none; }
.toast.show { display:block; }
.toast.success { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.toast.error   { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
</style>



{{-- HEADER --}}
<form method="GET" action="{{ route('admin.cobros.empresas') }}" id="fHead">
<div class="cob-header">
    <div>
        <div class="cob-title">🏢 Cobros · Empresas</div>
        <div class="cob-sub">Resumen por empresa cliente · {{ $meses[$mes] }} {{ $anio }}</div>
    </div>
    <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
        {{-- Tabs navegación --}}
        <a href="{{ route('admin.cobros.index') }}"
           style="padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:700;text-decoration:none;background:rgba(255,255,255,.15);color:#cbd5e1;border:1px solid rgba(255,255,255,.15);">
            👤 Individuales
        </a>
        <a href="{{ route('admin.cobros.empresas') }}"
           style="padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:700;text-decoration:none;background:#ffffff;color:#0f172a;border:1px solid rgba(255,255,255,.3);">
            🏢 Empresas
        </a>
        <span style="width:1px;height:22px;background:rgba(255,255,255,.2);display:inline-block;"></span>
        <select name="mes" onchange="this.form.submit()" style="font-size:.8rem;padding:.3rem .5rem;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#e2e8f0;border-radius:6px;">
            @foreach($meses as $i => $m)
            @if($i) <option value="{{ $i }}" {{ $mes==$i?'selected':'' }} style="background:#1e3a5f;">{{ $m }}</option> @endif
            @endforeach
        </select>
        <select name="anio" onchange="this.form.submit()" style="font-size:.8rem;padding:.3rem .5rem;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#e2e8f0;border-radius:6px;">
            @for($y=date('Y');$y>=2023;$y--)
            <option value="{{ $y }}" {{ $anio==$y?'selected':'' }} style="background:#1e3a5f;">{{ $y }}</option>
            @endfor
        </select>
        <span style="width:1px;height:22px;background:rgba(255,255,255,.2);display:inline-block;"></span>
        {{-- Buscar empresa en el header --}}
        <div style="display:flex;align-items:center;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:7px;overflow:hidden;">
            <input type="text" name="buscar" value="{{ $buscar }}" placeholder="🔍 Empresa..."
                   style="background:transparent;border:none;outline:none;color:#fff;font-size:.78rem;padding:.3rem .55rem;width:145px;">
            <button type="submit" style="background:rgba(255,255,255,.15);border:none;color:#fff;padding:.3rem .55rem;cursor:pointer;font-size:.78rem;">&#9166;</button>
        </div>
        @if($buscar)
        <a href="{{ route('admin.cobros.empresas', ['mes'=>$mes,'anio'=>$anio]) }}"
           style="color:#fca5a5;font-size:.8rem;font-weight:700;text-decoration:none;" title="Limpiar búsqueda">×</a>
        @endif
        <span style="background:rgba(255,255,255,.15);color:#fff;font-size:.85rem;font-weight:800;padding:.3rem .7rem;border-radius:20px;">
            {{ $empresas->count() }} <span style="font-size:.7rem;opacity:.75;">empresas</span>
        </span>
    </div>
</div>
</form>

{{-- CARDS --}}
<div class="cards-row">
    <div class="card-item c-emp">
        <div class="ci-label">🏢 Empresas</div>
        <div class="ci-val">{{ $totalEmpresas }}</div>
        <div class="ci-sub">Con contratos activos</div>
    </div>
    <div class="card-item c-cont">
        <div class="ci-label">👥 Total contratos</div>
        <div class="ci-val">{{ $totalContratos }}</div>
        <div class="ci-sub">Acumulado del mes</div>
    </div>
    <div class="card-item c-pag">
        <div class="ci-label">✅ Pagados</div>
        <div class="ci-val" style="color:#15803d;">{{ $totalPagados }}</div>
        <div class="ci-sub">Con factura pagada</div>
    </div>
    <div class="card-item c-pend">
        <div class="ci-label">⏳ Pendientes</div>
        <div class="ci-val" style="color:#dc2626;">{{ $totalPendientes }}</div>
        <div class="ci-sub">Afil + Ind + Plan sin pagar</div>
    </div>
</div>



{{-- TABLA --}}
@if($empresas->isEmpty())
<div style="text-align:center;padding:3rem;color:#94a3b8;background:#fff;border-radius:12px;border:1px solid #e2e8f0;">
    <div style="font-size:3rem;">🏢</div>
    <div style="font-size:1rem;font-weight:600;margin-top:.5rem;">Sin empresas para este período</div>
    @if(!$esAdmin)<div style="font-size:.8rem;">No tienes empresas asignadas aún</div>@endif
</div>
@else
<div class="tbl-wrap">
<table class="tbl-emp">
<thead>
<tr>
    {{-- Encargado: filtro en th --}}
    @if($esAdmin)
    <th style="min-width:120px;">
        <form method="GET" action="{{ route('admin.cobros.empresas') }}" style="margin:0">
            @foreach(request()->except(['encargado_id','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="encargado_id" onchange="this.form.submit()" class="th-select {{ $encargadoFiltro ? 'activo' : '' }}">
                <option value="">↓ Encargado</option>
                @foreach($usuariosDisponibles as $u)
                <option value="{{ $u->id }}" {{ $encargadoFiltro==$u->id?'selected':'' }}>{{ $u->nombre ?? $u->name }}</option>
                @endforeach
            </select>
        </form>
    </th>
    @endif
    <th style="text-align:left;min-width:160px;"><a href="{{ $sortUrlE('empresa',$sort,$dir) }}" class="{{ $sortClassE('empresa',$sort,$dir) }}">Empresa</a></th>
    <th style="text-align:left;min-width:100px;"><a href="{{ $sortUrlE('contacto',$sort,$dir) }}" class="{{ $sortClassE('contacto',$sort,$dir) }}">Contacto / Tel</a></th>
    <th title="Total contratos activos del mes">Cant</th>
    <th title="Contratos pagados este mes" style="color:#34d399;">Pag.</th>
    <th title="Afiliaciones pendientes de pago">Afil</th>
    <th title="Independientes pendientes">Ind.</th>
    {{-- Plant.: filtro solo con pendientes --}}
    <th>
        <form method="GET" action="{{ route('admin.cobros.empresas') }}" style="margin:0">
            @foreach(request()->except(['solo_plant','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="solo_plant" onchange="this.form.submit()" class="th-select {{ request('solo_plant') ? 'activo' : '' }}">
                <option value="">↓ Plant.</option>
                <option value="1" {{ request('solo_plant')=='1'?'selected':'' }}>Solo pend.</option>
            </select>
        </form>
    </th>
    {{-- Total Pend.: filtro solo con pendientes --}}
    <th>
        <form method="GET" action="{{ route('admin.cobros.empresas') }}" style="margin:0">
            @foreach(request()->except(['solo_pend','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="solo_pend" onchange="this.form.submit()" class="th-select {{ request('solo_pend') ? 'activo' : '' }}">
                <option value="">↓ Total Pend.</option>
                <option value="1" {{ request('solo_pend')=='1'?'selected':'' }}>Solo pend.</option>
            </select>
        </form>
    </th>
    <th title="$ Administración de pendientes" style="color:#fbbf24;">Admon $</th>
    <th title="Mora estimada por pago tardío" style="color:#fde68a;">⚠️ Mora</th>
    {{-- Semáforo: filtro en th --}}
    <th style="min-width:100px;text-align:center;">
        <form method="GET" action="{{ route('admin.cobros.empresas') }}" style="margin:0">
            @foreach(request()->except(['semaforo','page']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="semaforo" onchange="this.form.submit()" class="th-select {{ request('semaforo') ? 'activo' : '' }}">
                <option value="">↓ Semáforo</option>
                <option value="gris"  {{ request('semaforo')=='gris'?'selected':''   }}>⬜ Sin llamadas</option>
                <option value="verde" {{ request('semaforo')=='verde'?'selected':''  }}>🟢 Al día</option>
                <option value="amarillo" {{ request('semaforo')=='amarillo'?'selected':'' }}>🟡 3–7 días</option>
                <option value="rojo"  {{ request('semaforo')=='rojo'?'selected':''   }}>🔴 +7 días</option>
            </select>
        </form>
    </th>
    <th style="min-width:130px;">&#218;ltima gestión</th>
    <th style="text-align:center;">📞</th>
    <th style="text-align:center;">🔗</th>
</tr>
</thead>
<tbody>
@foreach($empresas as $emp)
@php
[$semIco, $semColor, $semTip] = $semLabel($emp->semaforo);
$nombreEnc = $emp->encargado_id ? ($usuariosDisponibles->firstWhere('id', $emp->encargado_id)?->nombre ?? $usuariosDisponibles->firstWhere('id', $emp->encargado_id)?->name ?? '—') : '';
@endphp
<tr>
    {{-- Encargado (admin ve selector) --}}
    @if($esAdmin)
    <td>
        <select class="enc-select" data-empresa-id="{{ $emp->id }}" onchange="asignarEncargado(this)">
            <option value="">— Sin asignar —</option>
            @foreach($usuariosDisponibles as $u)
            <option value="{{ $u->id }}" {{ $emp->encargado_id == $u->id ? 'selected' : '' }}>
                {{ $u->nombre ?? $u->name }}
            </option>
            @endforeach
        </select>
    </td>
    @endif

    {{-- Empresa --}}
    <td>
        <a href="{{ route('admin.facturacion.empresa', ['id' => $emp->id, 'mes' => $mes, 'anio' => $anio]) }}"
           class="emp-nombre" target="_blank">
            {{ $emp->empresa ?? "Empresa #{$emp->id}" }}
        </a>
        @if($emp->nit)
        <div class="emp-contacto">NIT: {{ number_format($emp->nit, 0, '', '.') }}</div>
        @endif
    </td>

    {{-- Contacto --}}
    <td>
        <div class="emp-contacto" style="font-weight:600;color:#334155;">{{ $emp->contacto ?? '—' }}</div>
        @php $tel = $emp->telefono ?? $emp->celular ?? ''; $wa = $waUrl($tel); @endphp
        <div class="emp-contacto" style="display:flex;align-items:center;gap:.3rem;">
            {{ $tel }}
            @if($wa)
            <a href="{{ $wa }}" target="_blank" title="Abrir WhatsApp" style="text-decoration:none;line-height:1;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#25d366" width="14" height="14"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
            @endif
        </div>
    </td>

    {{-- Cant --}}
    <td class="num-cell">{{ $fmt($emp->cant) }}</td>

    {{-- Pagados --}}
    <td class="num-cell num-pag">{{ $fmt($emp->pagados) }}</td>

    {{-- Afiliaciones pendientes --}}
    <td class="num-cell {{ $emp->afil_pend > 0 ? 'num-afil' : 'num-0' }}">{{ $fmt($emp->afil_pend) }}</td>

    {{-- Independientes pendientes --}}
    <td class="num-cell {{ $emp->indep_pend > 0 ? 'num-ind' : 'num-0' }}">{{ $fmt($emp->indep_pend) }}</td>

    {{-- Planillas pendientes --}}
    <td class="num-cell {{ $emp->plan_pend > 0 ? 'num-plan' : 'num-0' }}">{{ $fmt($emp->plan_pend) }}</td>

    {{-- Total pendientes --}}
    <td class="num-cell {{ $emp->total_pend > 0 ? 'num-pend-total' : 'num-0' }}">
        <strong>{{ $fmt($emp->total_pend) }}</strong>
    </td>

    {{-- Admon pendientes --}}
    <td class="num-cell" style="{{ $emp->admon_pend > 0 ? 'color:#d97706;font-weight:800;' : 'color:#94a3b8;' }}">
        ${{ number_format($emp->admon_pend, 0, '', '.') }}
    </td>

    {{-- Mora estimada empresa --}}
    <td class="num-cell">
        @if(($emp->mora_estimada ?? 0) > 0)
            <span style="display:inline-block;padding:.1rem .4rem;border-radius:20px;font-size:.62rem;font-weight:700;background:#fef3c7;color:#92400e;" title="Mora estimada total de contratos pendientes">
                ${{ number_format($emp->mora_estimada, 0, '', '.') }}
            </span>
        @else
            <span style="color:#64748b;font-size:.7rem;">—</span>
        @endif
    </td>

    {{-- Semáforo --}}
    <td style="text-align:center;">
        <span class="sem-dot" style="color:{{ $semColor }};" title="{{ $semTip }}">
            {{ $semIco }}
            @if($emp->dias_sin_llamar !== null)
                <span style="font-size:.62rem;color:#94a3b8;">{{ $emp->dias_sin_llamar }}d</span>
            @endif
        </span>
    </td>

    {{-- Última gestión --}}
    <td>
        @if($emp->ultima_llamada)
            <div style="font-size:.7rem;font-weight:600;color:#334155;">
                {{ \App\Models\BitacoraCobro::RESULTADOS[$emp->ultima_llamada->resultado] ?? $emp->ultima_llamada->resultado }}
            </div>
            @if($emp->ultima_llamada->observacion)
            <div style="font-size:.66rem;color:#64748b;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                 title="{{ $emp->ultima_llamada->observacion }}">
                {{ $emp->ultima_llamada->observacion }}
            </div>
            @endif
        @else
            <span style="color:#cbd5e1;font-size:.7rem;">Sin gestiones</span>
        @endif
    </td>

    {{-- Botón llamar --}}
    <td style="text-align:center;">
        <button class="btn-llamar btn-llamar-emp"
            data-empresa-id="{{ $emp->id }}"
            data-nombre="{{ $emp->empresa ?? 'Empresa #'.$emp->id }}"
            data-contacto="{{ $emp->contacto ?? '—' }}"
            data-telefono="{{ $emp->telefono ?? $emp->celular ?? '—' }}"
            data-cant="{{ $emp->cant }}"
            data-pend="{{ $emp->total_pend }}"
            title="Registrar llamada">
            📞
        </button>
    </td>

    {{-- Link a facturación --}}
    <td style="text-align:center;">
        <a href="{{ route('admin.facturacion.empresa', ['id' => $emp->id, 'mes' => $mes, 'anio' => $anio]) }}"
           style="font-size:.8rem;text-decoration:none;padding:.22rem .55rem;background:#dbeafe;color:#1e40af;border-radius:7px;font-weight:700;"
           target="_blank" title="Ver contratos en facturación">
            📋
        </a>
    </td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr style="background:#0f172a;color:#fff;font-weight:700;font-size:.72rem;">
    <td colspan="{{ $esAdmin ? 3 : 2 }}" style="padding:.5rem .6rem;">TOTALES ({{ $empresas->count() }})</td>
    <td class="num-cell" style="color:#93c5fd;">{{ $fmt($totalContratos) }}</td>
    <td class="num-cell" style="color:#34d399;">{{ $fmt($totalPagados) }}</td>
    <td class="num-cell" style="color:#c4b5fd;">{{ $fmt($empresas->sum('afil_pend')) }}</td>
    <td class="num-cell" style="color:#7dd3fc;">{{ $fmt($empresas->sum('indep_pend')) }}</td>
    <td class="num-cell" style="color:#93c5fd;">{{ $fmt($empresas->sum('plan_pend')) }}</td>
    <td class="num-cell" style="color:#fca5a5;">{{ $fmt($totalPendientes) }}</td>
    <td class="num-cell" style="color:#fbbf24;font-weight:800;">${{ number_format($empresas->sum('admon_pend'), 0, '', '.') }}</td>
    <td class="num-cell" style="color:#fde68a;font-weight:800;">
        @if($empresas->sum('mora_estimada') > 0)
            ${{ number_format($empresas->sum('mora_estimada'), 0, '', '.') }}
        @else
            —
        @endif
    </td>
    <td colspan="4"></td>
</tr>
</tfoot>
</table>
</div>
@endif

{{-- MODAL LLAMADA EMPRESA --}}
<div class="modal-bg" id="modalLlamadaEmp">
<div class="modal-box">
    <div class="modal-title">
        <span>📞 Registrar Gestión · Empresa</span>
        <button class="modal-close" onclick="document.getElementById('modalLlamadaEmp').classList.remove('open')">✕</button>
    </div>
    <div class="info-box">
        <div><span style="color:#64748b;">🏢 Empresa:</span> <strong id="me-nombre"></strong></div>
        <div><span style="color:#64748b;">👤 Contacto:</span> <strong id="me-contacto"></strong></div>
        <div style="display:flex;align-items:center;gap:.4rem;">
            <span style="color:#64748b;">📞 Tel:</span>
            <strong id="me-telefono"></strong>
            <a id="me-wa-link" href="#" target="_blank" title="WhatsApp"
               style="text-decoration:none;display:none;line-height:1;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#25d366" width="16" height="16"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        </div>
        <div><span style="color:#64748b;">Contratos:</span> <strong id="me-cant"></strong> total / <strong id="me-pend" style="color:#dc2626;"></strong> pendientes</div>
    </div>
    <input type="hidden" id="me-empresa-id">
    <div class="form-grp">
        <label>Resultado de la gestión *</label>
        <select id="me-resultado">
            <option value="no_contesta">📵 No contesta</option>
            <option value="promesa_pago">🤝 Promesa de pago</option>
            <option value="pagado">✅ Ya pagaron</option>
            <option value="numero_errado">❌ Número errado</option>
            <option value="otro">📝 Otro</option>
        </select>
    </div>
    <div class="form-grp">
        <label>Observación — ¿Qué dijo el contacto?</label>
        <textarea id="me-observacion" placeholder="Ej: Doña Luz dice que consigna el viernes..."></textarea>
    </div>
    <button class="btn-save" id="btnGuardarEmp" onclick="guardarLlamadaEmp()">💾 Guardar Gestión</button>

    <div style="margin-top:1rem;padding-top:.9rem;border-top:1px solid #f1f5f9;">
        <div style="font-size:.7rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:.5rem;">Historial de gestiones</div>
        <div id="me-historial" style="font-size:.75rem;color:#94a3b8;">Cargando...</div>
    </div>
</div>
</div>

<div class="toast" id="toastMsg"></div>

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
const URL_EMP_LLAMADA  = '{{ route("admin.cobros.empresa.llamada.store", ["id" => "__ID__"]) }}';
const URL_EMP_LLAMADAS = '{{ route("admin.cobros.empresa.llamadas",     ["id" => "__ID__"]) }}';
const URL_EMP_ENCARGADO= '{{ route("admin.cobros.empresa.encargado",    ["id" => "__ID__"]) }}';

function mostrarToast(msg, tipo='success') {
    const t = document.getElementById('toastMsg');
    t.textContent = msg; t.className = `toast show ${tipo}`;
    setTimeout(() => t.classList.remove('show'), 3500);
}
document.getElementById('modalLlamadaEmp').addEventListener('click', e => {
    if(e.target === document.getElementById('modalLlamadaEmp'))
        document.getElementById('modalLlamadaEmp').classList.remove('open');
});
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') document.getElementById('modalLlamadaEmp').classList.remove('open');
});

// ── Abrir modal empresa ──
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-llamar-emp');
    if (!btn) return;
    document.getElementById('me-empresa-id').value  = btn.dataset.empresaId;
    document.getElementById('me-nombre').textContent    = btn.dataset.nombre;
    document.getElementById('me-contacto').textContent  = btn.dataset.contacto;
    document.getElementById('me-telefono').textContent  = btn.dataset.telefono;
    document.getElementById('me-cant').textContent      = btn.dataset.cant;
    document.getElementById('me-pend').textContent      = btn.dataset.pend;
    document.getElementById('me-resultado').value       = 'no_contesta';
    document.getElementById('me-observacion').value     = '';
    // WhatsApp link
    const waEl = document.getElementById('me-wa-link');
    const rawTel = (btn.dataset.telefono || '').replace(/\D/g, '');
    if (rawTel && rawTel !== '') {
        waEl.href = 'https://wa.me/57' + rawTel;
        waEl.style.display = 'inline';
    } else { waEl.style.display = 'none'; }
    cargarHistorialEmp(btn.dataset.empresaId);
    document.getElementById('modalLlamadaEmp').classList.add('open');
});

// ── Guardar gestión empresa ──
async function guardarLlamadaEmp() {
    const id         = document.getElementById('me-empresa-id').value;
    const resultado  = document.getElementById('me-resultado').value;
    const observacion= document.getElementById('me-observacion').value;
    const btn        = document.getElementById('btnGuardarEmp');
    btn.disabled = true; btn.textContent = 'Guardando...';
    try {
        const r = await fetch(URL_EMP_LLAMADA.replace('__ID__', id), {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body: JSON.stringify({ resultado, observacion })
        });
        const data = await r.json();
        if (!data.ok) throw new Error('Error');
        document.getElementById('modalLlamadaEmp').classList.remove('open');
        mostrarToast('✅ Gestión registrada correctamente');
        setTimeout(() => location.reload(), 600);
    } catch(err) {
        mostrarToast('❌ Error al guardar', 'error');
    } finally {
        btn.disabled = false; btn.textContent = '💾 Guardar Gestión';
    }
}

// ── Cargar historial empresa ──
async function cargarHistorialEmp(id) {
    const el = document.getElementById('me-historial');
    el.innerHTML = '<span style="color:#94a3b8;">Cargando...</span>';
    try {
        const r = await fetch(URL_EMP_LLAMADAS.replace('__ID__', id), {
            headers: {'Accept':'application/json','X-CSRF-TOKEN':CSRF}
        });
        const data = await r.json();
        if (!data.llamadas?.length) { el.innerHTML = '<span style="color:#94a3b8;">Sin gestiones previas</span>'; return; }
        el.innerHTML = '<div class="timeline">' +
            data.llamadas.map(l => `
                <div class="tl-item">
                    <div class="tl-date">${l.fecha} &nbsp; <span class="tl-user">${l.usuario}</span></div>
                    <div class="tl-res">${l.etiqueta}</div>
                    ${l.observacion ? `<div class="tl-obs">${l.observacion}</div>` : ''}
                </div>`).join('') + '</div>';
    } catch { el.innerHTML = '<span style="color:#94a3b8;">Error al cargar</span>'; }
}

// ── Asignar encargado ──
async function asignarEncargado(sel) {
    const id = sel.dataset.empresaId;
    const encargadoId = sel.value;
    try {
        const r = await fetch(URL_EMP_ENCARGADO.replace('__ID__', id), {
            method: 'PATCH',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
            body: JSON.stringify({ encargado_id: encargadoId || null })
        });
        const data = await r.json();
        if (data.ok) {
            sel.classList.add('guardado');
            mostrarToast('✅ Encargado asignado');
            setTimeout(() => sel.classList.remove('guardado'), 2000);
        }
    } catch { mostrarToast('❌ Error al asignar', 'error'); }
}
</script>
@endpush
@endsection
