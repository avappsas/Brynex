@extends('layouts.app')

@section('titulo', 'Cotizaciones y Prospectos')
@section('modulo', 'Cotizaciones')

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
                <h1 class="cl-title">Gestión de Prospectos y Cotizaciones</h1>
                <p class="cl-subtitle">
                    <span class="cl-badge-count">{{ number_format($prospectos->total()) }}</span>
                    cotizaciones / prospectos registrados
                </p>
            </div>
        </div>
        <a href="{{ route('admin.cotizaciones.create') }}" class="cl-btn-new">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nueva Cotización
        </a>
    </div>

    {{-- ══ FLASH ════════════════════════════════════════════════════════ --}}
    @if(session('success'))
        <div class="cl-flash" style="margin-bottom: 0.5rem;">
            <span>✅ {{ session('success') }}</span>
        </div>
    @endif

    {{-- ══ FILTROS ══════════════════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('admin.cotizaciones.index') }}" class="cl-filters">
        <div class="cl-filter-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#64748b" stroke-width="2" style="position: absolute; left: .75rem; pointer-events: none;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="buscar" class="cl-search-input" value="{{ $buscar }}" placeholder="Buscar por Nombre, Cédula o Celular...">
        </div>
        
        <select name="estado" class="cl-select">
            <option value="">— Estado (Todos) —</option>
            @foreach($estados as $key => $val)
                <option value="{{ $key }}" {{ $estado == $key ? 'selected' : '' }}>{{ $val }}</option>
            @endforeach
        </select>

        <select name="canal" class="cl-select">
            <option value="">— Canal (Todos) —</option>
            @foreach($canales as $key => $val)
                <option value="{{ $key }}" {{ $canal == $key ? 'selected' : '' }}>{{ $val }}</option>
            @endforeach
        </select>

        <select name="asesor_id" class="cl-select select2">
            <option value="">— Asesor (Todos) —</option>
            @foreach($asesores as $id => $nombre)
                <option value="{{ $id }}" {{ $asesorId == $id ? 'selected' : '' }}>{{ $nombre }}</option>
            @endforeach
        </select>

        <button type="submit" class="cl-btn-search">Buscar</button>
        <a href="{{ route('admin.cotizaciones.index') }}" class="cl-btn-clear">Limpiar</a>
    </form>

    {{-- ══ TABLA ════════════════════════════════════════════════════════ --}}
    <div class="cl-table-wrap">
        <table class="cl-table">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th class="cl-th">Fecha</th>
                    <th class="cl-th">Prospecto</th>
                    <th class="cl-th">Contacto</th>
                    <th class="cl-th">Canal</th>
                    <th class="cl-th">Asesor</th>
                    <th class="cl-th">Estado</th>
                    <th class="cl-th">Próx. Llamada</th>
                    <th class="cl-th" style="text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($prospectos as $prospecto)
                <tr class="cl-tr">
                    <td class="cl-td" style="white-space:nowrap; color:#475569;">
                        {{ $prospecto->fecha_cotizacion ? $prospecto->fecha_cotizacion->format('d/m/Y') : '' }}
                    </td>
                    <td class="cl-td">
                        <div class="cl-client-cell">
                            <div class="cl-avatar {{ $prospecto->estado == 'convertido' ? 'av-vigente' : ($prospecto->estado == 'no_interesado' ? 'av-retirado' : 'av-sin') }}">
                                {{ substr($prospecto->primer_nombre ?: 'P', 0, 1) }}{{ substr($prospecto->primer_apellido ?: 'R', 0, 1) }}
                            </div>
                            <div>
                                <a href="{{ route('admin.cotizaciones.show', $prospecto->id) }}" class="cl-name">
                                    {{ $prospecto->nombre_completo ?: 'Sin Nombre' }}
                                </a>
                                <div class="cl-cedula">{{ $prospecto->cedula }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="cl-td" style="white-space:nowrap;">
                        <div style="font-weight: 500; color:#0f172a; display:flex; align-items:center; gap: 0.35rem;">
                            📞 {{ $prospecto->celular ?: 'N/A' }}
                            @if($prospecto->celular)
                                <a href="https://wa.me/57{{ $prospecto->celular }}" class="cl-wa-inline" target="_blank" title="Enviar WhatsApp">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.948h.003c4.368 0 7.927-3.559 7.931-7.928a7.86 7.86 0 0 0-2.327-5.594ZM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592Zm3.69-4.98c-.202-.101-1.202-.594-1.392-.66-.189-.07-.327-.101-.466.101-.139.2-.538.66-.66.8-.12.138-.241.156-.443.055-.202-.101-.85-.313-1.62-.998-.6-.535-1.005-1.197-1.123-1.401-.118-.202-.012-.311.089-.412.091-.09.202-.236.302-.354.101-.118.135-.2.203-.332.067-.134.034-.251-.017-.352-.05-.101-.466-1.123-.638-1.54-.168-.403-.34-.348-.466-.354-.121-.006-.26-.008-.399-.008-.14 0-.368.052-.56.26-.192.208-.733.717-.733 1.748 0 1.03.75 2.023.854 2.163.104.14 1.478 2.256 3.58 3.162.5.216.89.345 1.196.443.502.16 1.037.137 1.429.078.437-.066 1.202-.492 1.371-.963.17-.472.17-.878.118-.963-.05-.084-.191-.133-.393-.234Z"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </td>
                    <td class="cl-td">
                        @if($prospecto->canal_origen)
                            <span class="cl-emp-chip">{{ $canales[$prospecto->canal_origen] ?? $prospecto->canal_origen }}</span>
                        @else
                            <span class="cl-emp-ind">-</span>
                        @endif
                    </td>
                    <td class="cl-td" style="white-space:nowrap; color:#475569;">
                        {{ $prospecto->asesor->nombre ?? 'Sin Asignar' }}
                    </td>
                    <td class="cl-td">
                        @php
                            $badgeClass = match($prospecto->estado) {
                                'interesado' => 'cl-badge-vigente',
                                'sin_respuesta' => 'cl-badge-otro',
                                'pendiente_resp' => 'cl-badge-otro',
                                'no_interesado' => 'cl-badge-retirado',
                                'convertido' => 'cl-badge-vigente',
                                default => 'cl-badge-otro'
                            };
                            
                            $nombreEstado = match($prospecto->estado) {
                                'interesado' => 'Interesado',
                                'sin_respuesta' => 'Sin Respuesta',
                                'pendiente_resp' => 'Pendiente Respuesta',
                                'no_interesado' => 'No Interesado',
                                'convertido' => 'Afiliado / Convertido',
                                default => $prospecto->estado
                            };
                        @endphp
                        <span class="cl-estado-badge {{ $badgeClass }}">
                            @if($prospecto->estado == 'convertido' || $prospecto->estado == 'interesado')
                                <span class="cl-dot"></span>
                            @elseif($prospecto->estado == 'no_interesado')
                                <span class="cl-dot-ret"></span>
                            @endif
                            {{ $nombreEstado }}
                        </span>
                    </td>
                    <td class="cl-td" style="white-space:nowrap;">
                        @if($prospecto->proxima_llamada)
                            @if($prospecto->proxima_llamada->isPast() && $prospecto->estado != 'convertido' && $prospecto->estado != 'no_interesado')
                                <span style="color:#ef4444;font-weight:700;"><i class="fas fa-exclamation-circle"></i> {{ $prospecto->proxima_llamada->format('d/m/Y') }}</span>
                            @else
                                <span style="color:#2563eb; font-weight: 500;">{{ $prospecto->proxima_llamada->format('d/m/Y') }}</span>
                            @endif
                        @else
                            <span style="color:#94a3b8; font-style: italic;">-</span>
                        @endif
                    </td>
                    <td class="cl-td" style="text-align:center; white-space:nowrap;">
                        <a href="{{ route('admin.cotizaciones.show', $prospecto->id) }}" class="cl-btn-abrir">
                            👁️ Detalle / Gestionar
                        </a>
                    </td>
                </tr>
                @empty
                <tr class="cl-tr">
                    <td colspan="8" class="cl-td cl-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p>No se encontraron prospectos con los filtros actuales.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ══ PAGINACIÓN ══════════════════════════════════════════════════════ --}}
    <div class="cl-pagination">
        <div class="cl-page-info">
            Mostrando <strong>{{ $prospectos->firstItem() ?: 0 }}</strong> a <strong>{{ $prospectos->lastItem() ?: 0 }}</strong> de <strong>{{ $prospectos->total() }}</strong> prospectos
        </div>
        <div class="cl-page-controls">
            @if($prospectos->onFirstPage())
                <span class="cl-page-btn cl-page-btn--disabled">« Anterior</span>
            @else
                <a href="{{ $prospectos->appends(request()->query())->previousPageUrl() }}" class="cl-page-btn">« Anterior</a>
            @endif

            @php
                $currentPage = $prospectos->currentPage();
                $lastPage = $prospectos->lastPage();
                $start = max(1, $currentPage - 2);
                $end = min($lastPage, $currentPage + 2);
            @endphp

            @if($start > 1)
                <a href="{{ $prospectos->appends(request()->query())->url(1) }}" class="cl-page-num">1</a>
                @if($start > 2)<span class="cl-page-dots">…</span>@endif
            @endif

            @for($p = $start; $p <= $end; $p++)
                @if($p == $currentPage)
                    <span class="cl-page-num cl-page-num--active">{{ $p }}</span>
                @else
                    <a href="{{ $prospectos->appends(request()->query())->url($p) }}" class="cl-page-num">{{ $p }}</a>
                @endif
            @endfor

            @if($end < $lastPage)
                @if($end < $lastPage - 1)<span class="cl-page-dots">…</span>@endif
                <a href="{{ $prospectos->appends(request()->query())->url($lastPage) }}" class="cl-page-num">{{ $lastPage }}</a>
            @endif

            @if($prospectos->hasMorePages())
                <a href="{{ $prospectos->appends(request()->query())->nextPageUrl() }}" class="cl-page-btn">Siguiente »</a>
            @else
                <span class="cl-page-btn cl-page-btn--disabled">Siguiente »</span>
            @endif
        </div>
    </div>

</div>

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
.cl-search-input {
    width: 100%; padding: .5rem .9rem .5rem 2.2rem;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: .85rem; color: #0f172a; background: #f8fafc;
    transition: border-color .2s, box-shadow .2s;
}
.cl-search-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
.cl-select {
    padding: .5rem .85rem; border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: .85rem; color: #334155; background: #f8fafc; min-width: 160px;
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

/* ── Celda cliente ── */
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

/* Chips y Badges */
.cl-emp-ind { font-size: .77rem; color: #94a3b8; font-style: italic; }
.cl-emp-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    background: #eff6ff; color: #1d4ed8;
    padding: .22rem .6rem; border-radius: 7px;
    font-size: .76rem; font-weight: 600; white-space: nowrap;
}

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

/* Acciones */
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

.cl-wa-inline {
    display: inline-flex; align-items: center; justify-content: center;
    width: 20px; height: 20px; border-radius: 5px;
    background: #dcfce7; color: #16a34a;
    text-decoration: none; flex-shrink: 0;
    transition: all .15s;
    border: none;
}
.cl-wa-inline:hover { background: #bbf7d0; transform: scale(1.15); }

/* Empty state */
.cl-empty {
    text-align: center; padding: 3rem 1rem;
    color: #94a3b8; font-size: .9rem;
}
.cl-empty svg { display: block; margin: 0 auto .75rem; }
.cl-empty p { margin: 0; }

/* Paginación */
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

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2').select2({
            width: '200px'
        });
    });
</script>
@endpush
