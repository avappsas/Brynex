@extends('layouts.app')
@section('modulo','Clientes')

@section('contenido')
<div class="cl-page" style="max-width:1100px;margin:0 auto;">

    {{-- ══ HEADER ══════════════════════════════════════════════════════ --}}
    <div class="cl-header">
        <div class="cl-header-left">
            <div class="cl-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="cl-title">Base de Datos — Clientes</h1>
                <p class="cl-subtitle">
                    <span class="cl-badge-count">{{ number_format($clientes->total()) }}</span>
                    clientes registrados
                    @if($buscar || $filtroEmpresa)
                        <span class="cl-badge-filter">· Filtros activos</span>
                    @endif
                </p>
            </div>
        </div>
        <a href="{{ route('admin.clientes.create') }}" class="cl-btn-new">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo Cliente
        </a>
    </div>

    {{-- ══ FLASH ════════════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div class="cl-flash">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- ══ FILTROS ══════════════════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('admin.clientes.index') }}" class="cl-filters">
        <div class="cl-filter-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            <input type="text" name="buscar" value="{{ $buscar ?? '' }}"
                   placeholder="Buscar por cédula, nombre o celular…"
                   class="cl-search-input">
        </div>

        <select name="empresa" class="cl-select">
            <option value="">— Todas las empresas —</option>
            <option value="1" {{ ($filtroEmpresa ?? '') == '1' ? 'selected' : '' }}>Individual</option>
            @foreach($empresas as $emp)
                @if($emp->id != 1)
                <option value="{{ $emp->id }}" {{ ($filtroEmpresa ?? '') == $emp->id ? 'selected' : '' }}>
                    {{ $emp->empresa }}
                </option>
                @endif
            @endforeach
        </select>

        <button type="submit" class="cl-btn-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            Buscar
        </button>
        @if($buscar || $filtroEmpresa)
        <a href="{{ route('admin.clientes.index') }}" class="cl-btn-clear">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            Limpiar
        </a>
        @endif
    </form>

    {{-- ══ TABLA ════════════════════════════════════════════════════════ --}}
    <div class="cl-table-wrap">
        <table class="cl-table">
            <thead>
                <tr>
                    <th class="cl-th">Cliente</th>
                    <th class="cl-th">Empresa</th>
                    <th class="cl-th" style="text-align:center;">Estado</th>
                    <th class="cl-th" style="text-align:center;">Vigente desde</th>
                    <th class="cl-th" style="text-align:center;">Modalidad</th>
                    <th class="cl-th">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clientes as $c)
                @php
                    $contrato = $ultimosContratos[$c->cedula] ?? null;
                    $vigente  = $contrato && strtolower($contrato->estado ?? '') === 'vigente';
                    $retirado = $contrato && strtolower($contrato->estado ?? '') === 'retirado';
                    $nombre   = trim(
                        ($c->primer_nombre ?? '') . ' ' .
                        ($c->segundo_nombre ?? '') . ' ' .
                        ($c->primer_apellido ?? '') . ' ' .
                        ($c->segundo_apellido ?? '')
                    ) ?: '—';
                @endphp
                <tr class="cl-tr">
                    {{-- Nombre + cédula + WA --}}
                    <td class="cl-td">
                        <div class="cl-client-cell">
                            <div class="cl-avatar {{ $vigente ? 'av-vigente' : ($retirado ? 'av-retirado' : 'av-sin') }}">
                                {{ strtoupper(substr($c->primer_nombre ?? 'C', 0, 1)) }}{{ strtoupper(substr($c->primer_apellido ?? '', 0, 1)) }}
                            </div>
                            <div>
                                <div style="display:flex;align-items:center;gap:.4rem;">
                                    <a href="{{ route('admin.clientes.edit', $c->id) }}" class="cl-name">{{ $nombre }}</a>
                                    @if($c->celular)
                                    <a href="https://wa.me/57{{ preg_replace('/[^0-9]/', '', $c->celular) }}" target="_blank" class="cl-wa-inline" title="WhatsApp {{ $c->celular }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    </a>
                                    @endif
                                </div>
                                <div class="cl-cedula">{{ $c->cedula ? number_format($c->cedula, 0, '', '.') : '—' }}</div>
                            </div>
                        </div>
                    </td>

                    {{-- Empresa --}}
                    <td class="cl-td">
                        @if(!$c->cod_empresa || (int)$c->cod_empresa === 1)
                            <span class="cl-emp-ind">Individual</span>
                        @else
                            @php $empNombre = $c->empresa?->empresa ?: "Empresa #{$c->cod_empresa}"; @endphp
                            <span class="cl-emp-chip" title="{{ $empNombre }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
                                {{ \Illuminate\Support\Str::limit($empNombre, 26) }}
                            </span>
                        @endif
                    </td>

                    {{-- Columna: Estado --}}
                    <td class="cl-td" style="text-align:center;">
                        @if($contrato)
                            @if($vigente)
                                <span class="cl-estado-badge cl-badge-vigente">
                                    <span class="cl-dot"></span>
                                    Vigente
                                </span>
                            @elseif($retirado)
                                <span class="cl-estado-badge cl-badge-retirado">
                                    <span class="cl-dot-ret"></span>
                                    Retirado
                                </span>
                            @else
                                <span class="cl-estado-badge cl-badge-otro">{{ ucfirst($contrato->estado) }}</span>
                            @endif
                        @else
                            <span class="cl-sin-contrato">—</span>
                        @endif
                    </td>

                    {{-- Columna: Fecha --}}
                    @php
                        $mesesEs = [
                            1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',
                            5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',
                            9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
                        ];
                    @endphp
                    <td class="cl-td" style="text-align:center;">
                        @if($contrato && $contrato->fecha_ingreso)
                            @php
                                $fi = sqldate($contrato->fecha_ingreso);
                                $fechaFmt = $fi->format('d') . '-' . $mesesEs[(int)$fi->format('n')] . '-' . $fi->format('y');
                            @endphp
                            <div class="cl-contrato-fecha">
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/></svg>
                                {{ $fechaFmt }}
                            </div>
                        @else
                            <span class="cl-sin-contrato">—</span>
                        @endif
                    </td>

                    {{-- Columna: Modalidad --}}
                    <td class="cl-td" style="text-align:center;">
                        @if($contrato && $contrato->modalidad)
                            <div class="cl-modalidad" style="font-style:normal;">{{ \Illuminate\Support\Str::limit($contrato->modalidad, 26) }}</div>
                        @else
                            <span class="cl-sin-contrato">—</span>
                        @endif
                    </td>

                    {{-- Acciones --}}
                    <td class="cl-td">
                        <a href="{{ route('admin.clientes.edit', $c->id) }}" class="cl-btn-abrir" title="Abrir ficha del cliente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            Abrir
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="cl-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#cbd5e1" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        <p>
                            @if($buscar || $filtroEmpresa)
                                No se encontraron clientes con los filtros aplicados.
                            @else
                                No hay clientes registrados.
                            @endif
                        </p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ══ PAGINACIÓN ══════════════════════════════════════════════════ --}}
    @if($clientes->hasPages())
    <div class="cl-pagination">
        {{-- Info --}}
        <span class="cl-page-info">
            Mostrando <strong>{{ $clientes->firstItem() }}</strong> — <strong>{{ $clientes->lastItem() }}</strong>
            de <strong>{{ number_format($clientes->total()) }}</strong> clientes
        </span>

        {{-- Controles --}}
        <div class="cl-page-controls">
            {{-- Anterior --}}
            @if($clientes->onFirstPage())
                <span class="cl-page-btn cl-page-btn--disabled">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    Anterior
                </span>
            @else
                <a href="{{ $clientes->appends(['buscar' => $buscar, 'empresa' => $filtroEmpresa])->previousPageUrl() }}" class="cl-page-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    Anterior
                </a>
            @endif

            {{-- Números de página --}}
            @php
                $currentPage = $clientes->currentPage();
                $lastPage    = $clientes->lastPage();
                $start = max(1, $currentPage - 2);
                $end   = min($lastPage, $currentPage + 2);
            @endphp

            @if($start > 1)
                <a href="{{ $clientes->appends(['buscar' => $buscar, 'empresa' => $filtroEmpresa])->url(1) }}" class="cl-page-num">1</a>
                @if($start > 2)<span class="cl-page-dots">…</span>@endif
            @endif

            @for($p = $start; $p <= $end; $p++)
                @if($p === $currentPage)
                    <span class="cl-page-num cl-page-num--active">{{ $p }}</span>
                @else
                    <a href="{{ $clientes->appends(['buscar' => $buscar, 'empresa' => $filtroEmpresa])->url($p) }}" class="cl-page-num">{{ $p }}</a>
                @endif
            @endfor

            @if($end < $lastPage)
                @if($end < $lastPage - 1)<span class="cl-page-dots">…</span>@endif
                <a href="{{ $clientes->appends(['buscar' => $buscar, 'empresa' => $filtroEmpresa])->url($lastPage) }}" class="cl-page-num">{{ $lastPage }}</a>
            @endif

            {{-- Siguiente --}}
            @if($clientes->hasMorePages())
                <a href="{{ $clientes->appends(['buscar' => $buscar, 'empresa' => $filtroEmpresa])->nextPageUrl() }}" class="cl-page-btn">
                    Siguiente
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                </a>
            @else
                <span class="cl-page-btn cl-page-btn--disabled">
                    Siguiente
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                </span>
            @endif
        </div>
    </div>
    @endif
</div>

{{-- ══ ESTILOS ══════════════════════════════════════════════════════════ --}}
<style>
/* Fuente */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* ── Contenedor principal ── */
.cl-page {
    font-family: 'Inter', sans-serif;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

/* ── Header ── */
.cl-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 60%, #3b82f6 100%);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 20px rgba(37,99,235,.3);
    gap: 1rem;
    flex-wrap: wrap;
}
.cl-header-left { display: flex; align-items: center; gap: 1rem; }
.cl-header-icon {
    width: 48px; height: 48px;
    background: rgba(255,255,255,.18);
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    backdrop-filter: blur(4px);
}
.cl-title    { margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff; }
.cl-subtitle { margin: .2rem 0 0; font-size: .8rem; color: rgba(255,255,255,.8); display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.cl-badge-count  { background: rgba(255,255,255,.2); color: #fff; padding: .1rem .55rem; border-radius: 99px; font-weight: 700; font-size: .78rem; }
.cl-badge-filter { color: rgba(255,255,255,.65); font-size: .75rem; }
.cl-btn-new {
    display: inline-flex; align-items: center; gap: .45rem;
    background: rgba(255,255,255,.18);
    border: 1.5px solid rgba(255,255,255,.35);
    color: #fff; font-weight: 600; font-size: .83rem; text-decoration: none;
    padding: .55rem 1.1rem; border-radius: 10px;
    transition: all .2s; white-space: nowrap;
    backdrop-filter: blur(4px);
}
.cl-btn-new:hover { background: rgba(255,255,255,.28); transform: translateY(-1px); }

/* ── Flash ── */
.cl-flash {
    display: flex; align-items: center; gap: .6rem;
    background: rgba(16,185,129,.08);
    border: 1px solid rgba(16,185,129,.25);
    border-left: 3px solid #10b981;
    border-radius: 10px;
    color: #065f46; padding: .65rem 1rem; font-size: .83rem; font-weight: 500;
}

/* ── Filtros ── */
.cl-filters {
    display: flex; gap: .6rem; flex-wrap: wrap; align-items: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: .75rem 1rem;
    box-shadow: 0 1px 6px rgba(0,0,0,.04);
}
.cl-filter-search {
    position: relative; flex: 1; min-width: 200px;
    display: flex; align-items: center;
}
.cl-filter-search svg { position: absolute; left: .75rem; pointer-events: none; }
.cl-search-input {
    width: 100%; padding: .5rem .9rem .5rem 2.2rem;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: .85rem; color: #0f172a; background: #f8fafc;
    transition: border-color .2s, box-shadow .2s;
}
.cl-search-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
.cl-select {
    padding: .5rem .85rem; border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: .85rem; color: #334155; background: #f8fafc; min-width: 180px;
    cursor: pointer; transition: border-color .2s;
}
.cl-select:focus { outline: none; border-color: #2563eb; }
.cl-btn-search {
    display: inline-flex; align-items: center; gap: .4rem;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; border: none; padding: .52rem 1.1rem;
    border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer;
    box-shadow: 0 2px 8px rgba(37,99,235,.3); transition: all .2s; white-space: nowrap;
}
.cl-btn-search:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,99,235,.35); }
.cl-btn-clear {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .52rem .9rem; border: 1.5px solid #e2e8f0;
    border-radius: 8px; color: #64748b; text-decoration: none;
    font-size: .83rem; font-weight: 500; transition: all .15s; white-space: nowrap;
    background: #fff;
}
.cl-btn-clear:hover { background: #f1f5f9; border-color: #cbd5e1; }

/* ── Tabla ── */
.cl-table-wrap {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 8px rgba(0,0,0,.05);
    overflow-x: auto;
}
.cl-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.cl-th {
    padding: .75rem 1.1rem;
    text-align: left;
    font-size: .72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .06em;
    background: #f8fafc;
    border-bottom: 1.5px solid #e2e8f0;
    white-space: nowrap;
}
.cl-tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
}
.cl-tr:last-child { border-bottom: none; }
.cl-tr:hover { background: #fafbff; }
.cl-td { padding: .7rem 1.1rem; vertical-align: middle; }

/* ── Celda cliente (nombre + cédula) ── */
.cl-client-cell { display: flex; align-items: center; gap: .75rem; }
.cl-avatar {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; font-weight: 800; flex-shrink: 0;
    letter-spacing: -.03em;
}
.av-vigente  { background: #dbeafe; color: #1d4ed8; }
.av-retirado { background: #fee2e2; color: #b91c1c; }
.av-sin      { background: #f1f5f9; color: #64748b; }
.cl-name {
    display: block;
    color: #0f172a; font-weight: 600; font-size: .87rem;
    text-decoration: none; line-height: 1.3;
    transition: color .15s;
}
.cl-name:hover { color: #2563eb; }
.cl-cedula {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: .73rem; color: #94a3b8; font-weight: 600;
    margin-top: .15rem; letter-spacing: .02em;
}

/* ── Empresa ── */
.cl-emp-ind { font-size: .77rem; color: #94a3b8; font-style: italic; }
.cl-emp-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    background: #eff6ff; color: #1d4ed8;
    padding: .22rem .6rem; border-radius: 7px;
    font-size: .76rem; font-weight: 600; white-space: nowrap;
    max-width: 200px; overflow: hidden; text-overflow: ellipsis;
}

/* ── Estado del contrato ── */
.cl-estado-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .25rem .7rem; border-radius: 99px;
    font-size: .73rem; font-weight: 700; letter-spacing: .03em;
    white-space: nowrap;
}
.cl-badge-vigente  { background: #dcfce7; color: #15803d; }
.cl-badge-retirado { background: #fee2e2; color: #b91c1c; }
.cl-badge-otro     { background: #f1f5f9; color: #475569; }

.cl-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #22c55e;
    animation: cl-pulse 2s infinite;
}
.cl-dot-ret {
    width: 7px; height: 7px; border-radius: 50%;
    background: #ef4444;
}
@keyframes cl-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .4; }
}
.cl-contrato-fecha {
    display: inline-flex; align-items: center; gap: .25rem;
    color: #475569; font-size: .76rem; font-weight: 500;
    white-space: nowrap;
}
.cl-modalidad {
    font-size: .68rem; color: #64748b; margin-top: .2rem; font-style: italic;
}
.cl-sin-contrato { font-size: .76rem; color: #cbd5e1; font-style: italic; }

/* ── Botones acción ── */
.cl-btn-abrir {
    display: inline-flex; align-items: center; gap: .38rem;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    padding: .35rem .85rem; border-radius: 8px;
    font-size: .76rem; font-weight: 700; text-decoration: none;
    box-shadow: 0 2px 8px rgba(99,102,241,.35);
    transition: all .2s; white-space: nowrap;
    letter-spacing: .01em;
}
.cl-btn-abrir:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    box-shadow: 0 4px 14px rgba(99,102,241,.45);
    transform: translateY(-1px);
    color: #fff;
}

/* WA inline junto al nombre */
.cl-wa-inline {
    display: inline-flex; align-items: center; justify-content: center;
    width: 20px; height: 20px; border-radius: 5px;
    background: #dcfce7; color: #16a34a;
    text-decoration: none; flex-shrink: 0;
    transition: all .15s;
}
.cl-wa-inline:hover { background: #bbf7d0; transform: scale(1.15); }

/* ── Empty ── */
.cl-empty {
    text-align: center; padding: 3rem 1rem;
    color: #94a3b8; font-size: .9rem;
}
.cl-empty svg { display: block; margin: 0 auto .75rem; }
.cl-empty p { margin: 0; }

/* ── Paginación ── */
.cl-pagination {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: .75rem;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 12px; padding: .75rem 1.1rem;
    box-shadow: 0 1px 6px rgba(0,0,0,.04);
}
.cl-page-info { font-size: .8rem; color: #64748b; }
.cl-page-info strong { color: #0f172a; font-weight: 600; }
.cl-page-controls { display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; }

.cl-page-btn {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .38rem .75rem; border-radius: 8px;
    border: 1.5px solid #e2e8f0; background: #fff;
    color: #334155; font-size: .8rem; font-weight: 600;
    text-decoration: none; transition: all .15s; white-space: nowrap;
}
.cl-page-btn:hover { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
.cl-page-btn--disabled {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .38rem .75rem; border-radius: 8px;
    border: 1.5px solid #f1f5f9; background: #f8fafc;
    color: #cbd5e1; font-size: .8rem; font-weight: 600;
    white-space: nowrap; cursor: default;
}

.cl-page-num {
    width: 34px; height: 34px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px; border: 1.5px solid #e2e8f0;
    background: #fff; color: #334155; font-size: .8rem; font-weight: 600;
    text-decoration: none; transition: all .15s;
}
.cl-page-num:hover { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
.cl-page-num--active {
    width: 34px; height: 34px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px; font-size: .8rem; font-weight: 700;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; border: none;
    box-shadow: 0 2px 8px rgba(37,99,235,.35);
}
.cl-page-dots { color: #94a3b8; font-size: .8rem; padding: 0 .1rem; }

@media (max-width: 640px) {
    .cl-pagination { flex-direction: column; align-items: flex-start; }
    .cl-page-info { display: none; }
}
</style>
@endsection
