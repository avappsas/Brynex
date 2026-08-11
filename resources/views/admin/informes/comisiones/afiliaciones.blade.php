@extends('layouts.app')
@section('title', 'Distribución Afiliaciones')

@php
    /** Link de ordenamiento: alterna asc/desc y conserva el resto de filtros. */
    $sortUrl = function ($col) use ($sort, $dir) {
        $d = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        $q = request()->except(['sort', 'dir']);
        $q['sort'] = $col;
        $q['dir']  = $d;
        return url()->current() . '?' . http_build_query($q);
    };
    $sortClass = fn ($col) => $sort !== $col ? '' : ($dir === 'asc' ? 'sort-asc' : 'sort-desc');
@endphp

@push('styles')
<style>
    :root {
        --c-surface: #ffffff;
        --c-soft:    #f8fafc;
        --c-border:  #e2e8f0;
        --c-blue:    #2563eb;
        --c-green:   #10b981;
        --c-red:     #ef4444;
        --c-text:    #1e293b;
        --c-muted:   #64748b;
    }
    .af-wrap { max-width: 1400px; margin: 0 auto; }

    /* Filtros dentro de la card de cabecera */
    .filtros-head { display: flex; align-items: flex-end; gap: .65rem; flex-wrap: wrap; }
    .filtros-head label {
        display: block; font-size: .6rem; font-weight: 700; letter-spacing: .07em;
        text-transform: uppercase; color: rgba(191,219,254,.75); margin-bottom: .2rem;
    }
    .filtros-head select {
        background: rgba(255,255,255,.95); border: 1px solid rgba(255,255,255,.35);
        color: #0f172a; border-radius: 8px; padding: .38rem .6rem;
        font-size: .8rem; font-weight: 600; outline: none; cursor: pointer;
    }
    .filtros-head select:focus { border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(147,197,253,.25); }
    .btn-volver {
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.28); color: #dbeafe;
        border-radius: 8px; padding: .4rem 1rem; font-size: .8rem; font-weight: 600;
        text-decoration: none; white-space: nowrap;
    }
    .btn-volver:hover { background: rgba(255,255,255,.24); }

    /* Badges de estado */
    .badge-sin, .badge-ok {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .22rem .6rem; border-radius: 20px;
        font-size: .7rem; font-weight: 700; white-space: nowrap;
    }
    .badge-sin { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .badge-ok  { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

    /* Panel tabla */
    .panel {
        background: var(--c-surface); border: 1px solid var(--c-border);
        border-radius: 14px; overflow: hidden; box-shadow: 0 1px 8px rgba(0,0,0,.06);
        margin-bottom: 1rem;
    }
    /* El wrap scrollea en los dos ejes: así el thead sticky tiene contra qué
       fijarse (con overflow-x solo, el sticky no se activaba al bajar). */
    .tabla-wrap { overflow: auto; max-height: calc(100vh - 230px); }
    table.af-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1240px; }

    /* Encabezado oscuro, como en Cobros */
    .af-table thead th {
        position: sticky; top: 0; z-index: 2;
        background: #0f172a; color: #fff;
        padding: .5rem .55rem; text-align: left;
        font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
        white-space: nowrap;
    }
    .af-table thead th a {
        color: #cbd5e1; text-decoration: none;
        display: flex; align-items: center; gap: .2rem;
    }
    .af-table thead th a:hover { color: #fff; }
    .af-table thead th.num a { justify-content: flex-end; }
    .af-table thead th a.sort-asc::after  { content: '\2191'; color: #60a5fa; margin-left: .15rem; }
    .af-table thead th a.sort-desc::after { content: '\2193'; color: #60a5fa; margin-left: .15rem; }

    /* Filtro montado sobre el propio título de la columna */
    .th-select {
        width: 100%; background: transparent; border: none;
        border-bottom: 1px solid rgba(255,255,255,.15);
        color: #fff; font-size: .65rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .04em;
        padding: .2rem; cursor: pointer; outline: none;
        appearance: auto; -webkit-appearance: auto;
    }
    .th-select:hover { border-bottom-color: rgba(255,255,255,.5); }
    .th-select:focus { border-bottom-color: #3b82f6; }
    .th-select option, .th-select optgroup { background: #0f172a; color: #fff; font-weight: 600; text-transform: none; }
    .th-select.activo { border-bottom-color: #3b82f6; color: #93c5fd; }

    .af-table td {
        padding: .55rem .6rem; font-size: .8rem; color: var(--c-text);
        border-bottom: 1px solid #f1f5f9; vertical-align: top;
    }
    /* Sin distribuir: fondo rojizo + filo rojo a la izquierda. Reemplaza al
       badge que había en la última columna. */
    .af-table tr.sin-dist td { background: #fef8f8; }
    .af-table tr.sin-dist td:first-child { box-shadow: inset 3px 0 0 var(--c-red); }
    .af-table tbody tr:hover td { background: #f1f5f9; }
    .af-table tbody tr.sin-dist:hover td { background: #fef2f2; }
    .af-table tbody tr:last-child td { border-bottom: none; }

    /* Columna cliente: nombre + documento en dos renglones */
    .col-cliente { width: 230px; min-width: 230px; }
    .cli-nombre {
        line-height: 1.3;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden; word-break: break-word;
    }
    .cli-doc-nombre { font-weight: 600; }
    .cli-doc { color: var(--c-muted); white-space: nowrap; }

    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* Inputs de distribución */
    .dist-input {
        width: 88px; background: #fff;
        border: 1px solid #cbd5e1; color: var(--c-text);
        border-radius: 6px; padding: .28rem .45rem;
        font-size: .8rem; text-align: right; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .dist-input:focus { border-color: var(--c-blue); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    .dist-input.auto { background: #f0fdf4; border-color: #a7f3d0; color: #047857; font-weight: 700; }

    /* Botones de fila */
    .acciones-fila { display: flex; gap: .35rem; align-items: center; }
    .btn-edit {
        background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;
        border-radius: 6px; padding: .26rem .6rem; font-size: .73rem; font-weight: 600;
        cursor: pointer; transition: background .12s; white-space: nowrap;
    }
    .btn-edit:hover { background: #dbeafe; }
    .btn-save {
        background: var(--c-green); color: #fff; border: none; border-radius: 6px;
        padding: .26rem .6rem; font-size: .73rem; font-weight: 700;
        cursor: pointer; transition: opacity .12s; white-space: nowrap;
    }
    .btn-save:hover { opacity: .88; }
    .btn-cancel-row {
        background: #fff; color: var(--c-muted); border: 1px solid var(--c-border);
        border-radius: 6px; padding: .26rem .5rem; font-size: .72rem; cursor: pointer;
    }
    .btn-cancel-row:hover { border-color: #cbd5e1; color: #334155; }

    /* Suma residuo */
    .residuo { font-size: .7rem; margin-top: .3rem; }
    .residuo.ok  { color: #047857; font-weight: 600; }
    .residuo.err { color: #b91c1c; font-weight: 700; }

    .empty-state { padding: 3rem; text-align: center; color: var(--c-muted); font-size: .85rem; }
    .empty-state .icon { font-size: 2.5rem; margin-bottom: .5rem; }

    /* Tarjetas del resumen */
    .kpi-grid { display: grid; grid-template-columns: repeat(6,1fr); gap: .75rem; }
    @media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(3,1fr); } }
    @media (max-width: 640px)  { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
    .kpi-card { border-radius: 10px; padding: .7rem .85rem; border: 1px solid; }
    .kpi-label { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .3rem; }
    .kpi-val { font-size: 1rem; font-weight: 800; font-family: 'SFMono-Regular', Menlo, monospace; }
    .kpi-pct { font-size: .62rem; margin-top: .15rem; color: var(--c-muted); }
</style>
@endpush

@section('contenido')
<div class="af-wrap">

    {{-- ══ Cabecera con los filtros de período ══ --}}
    <div style="background:linear-gradient(135deg,#0d2550,#1e40af);border-radius:14px;padding:1.15rem 1.5rem;margin-bottom:1.1rem;box-shadow:0 4px 20px rgba(30,64,175,.25);">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap;">
            <div>
                <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;color:rgba(147,197,253,.8);letter-spacing:.1em;margin-bottom:.25rem;">Canal 2 — {{ \Carbon\Carbon::create()->month($mes)->locale('es')->monthName }} {{ $anio }}</div>
                <div style="font-size:1.4rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                    <span style="font-size:1.3rem;">📋</span> Distribución de Afiliaciones
                </div>
            </div>

            {{-- Filtros de período, en la misma fila del enlace a Comisiones.
                 Asesor, empresa y plan/modalidad viven en los títulos de la
                 tabla, pero viajan aquí como ocultos para no perderse. --}}
            <form method="GET" action="{{ route('admin.informes.comisiones.afiliaciones') }}" class="filtros-head">
                <input type="hidden" name="asesor_id" value="{{ $asesorId ?: '' }}">
                <input type="hidden" name="empresa"   value="{{ $empresa }}">
                <input type="hidden" name="plan_mod"  value="{{ $planMod }}">
                <input type="hidden" name="sort"      value="{{ $sort }}">
                <input type="hidden" name="dir"       value="{{ $dir }}">
                <div>
                    <label>Mes</label>
                    <select name="mes" onchange="this.form.submit()">
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->locale('es')->monthName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Año</label>
                    <select name="anio" onchange="this.form.submit()">
                        @foreach(range(2025, now()->year) as $y)
                            <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Mostrar</label>
                    <select name="filtro" onchange="this.form.submit()">
                        <option value="todas"           {{ $filtro === 'todas'          ? 'selected' : '' }}>Todas</option>
                        <option value="sin_distribuir"  {{ $filtro === 'sin_distribuir' ? 'selected' : '' }}>Sin distribuir</option>
                    </select>
                </div>
                @if($asesorId || $empresa !== '' || $planMod !== '' || $filtro !== 'todas')
                    <a href="{{ route('admin.informes.comisiones.afiliaciones', ['mes' => $mes, 'anio' => $anio]) }}"
                       class="btn-volver" style="padding:.4rem .8rem;">✕ Limpiar</a>
                @endif
                <a href="{{ route('admin.informes.comisiones.index', ['mes' => $mes, 'anio' => $anio]) }}" class="btn-volver">
                    ← Comisiones
                </a>
            </form>
        </div>
    </div>

    {{-- ══ Tabla ══ --}}
    <div class="panel">
        @if($facturas->isEmpty())
            <div class="empty-state">
                <div class="icon">📭</div>
                Sin afiliaciones para este período y filtros
            </div>
        @else
        <div class="tabla-wrap">
            <table class="af-table" id="tablaAfil">
                <thead>
                    <tr>
                        <th><a href="{{ $sortUrl('factura') }}" class="{{ $sortClass('factura') }}">#</a></th>
                        <th><a href="{{ $sortUrl('fecha') }}"   class="{{ $sortClass('fecha') }}">Fecha</a></th>
                        <th class="col-cliente"><a href="{{ $sortUrl('cliente') }}" class="{{ $sortClass('cliente') }}">Cliente</a></th>

                        {{-- Empresa: filtro desde el título --}}
                        <th>
                            <form method="GET" action="{{ route('admin.informes.comisiones.afiliaciones') }}" style="margin:0">
                                @foreach(request()->except(['empresa']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                                <select name="empresa" onchange="this.form.submit()" class="th-select {{ $empresa !== '' ? 'activo' : '' }}">
                                    <option value="">↓ Empresa</option>
                                    @foreach($empresasDisponibles as $em)
                                        <option value="{{ $em }}" {{ $empresa === $em ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($em, 22, '…') }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </th>

                        {{-- Asesor: filtro desde el título --}}
                        <th>
                            <form method="GET" action="{{ route('admin.informes.comisiones.afiliaciones') }}" style="margin:0">
                                @foreach(request()->except(['asesor_id']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                                <select name="asesor_id" onchange="this.form.submit()" class="th-select {{ $asesorId ? 'activo' : '' }}">
                                    <option value="">↓ Asesor</option>
                                    @foreach($asesores as $a)
                                        <option value="{{ $a->id }}" {{ $asesorId == $a->id ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($a->nombre, 22, '…') }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </th>

                        {{-- Plan y modalidad comparten columna, y también el filtro --}}
                        <th>
                            <form method="GET" action="{{ route('admin.informes.comisiones.afiliaciones') }}" style="margin:0">
                                @foreach(request()->except(['plan_mod']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                                <select name="plan_mod" onchange="this.form.submit()" class="th-select {{ $planMod !== '' ? 'activo' : '' }}">
                                    <option value="">↓ Plan / Modalidad</option>
                                    <optgroup label="Plan">
                                        @foreach($planesDisponibles as $p)
                                            <option value="plan:{{ $p->id }}" {{ $planMod === 'plan:'.$p->id ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($p->nombre, 24, '…') }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Modalidad">
                                        @foreach($modalidadesDisponibles as $mo)
                                            <option value="mod:{{ $mo->id }}" {{ $planMod === 'mod:'.$mo->id ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($mo->nombre, 24, '…') }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </form>
                        </th>

                        <th class="num"><a href="{{ $sortUrl('afiliacion') }}" class="{{ $sortClass('afiliacion') }}">Afiliación</a></th>
                        <th class="num"><a href="{{ $sortUrl('v_asesor') }}"   class="{{ $sortClass('v_asesor') }}">💼 Asesor</a></th>
                        <th class="num"><a href="{{ $sortUrl('v_retiro') }}"   class="{{ $sortClass('v_retiro') }}">🔒 Retiro</a></th>
                        <th class="num"><a href="{{ $sortUrl('v_encarg') }}"   class="{{ $sortClass('v_encarg') }}">👤 Encargado</a></th>
                        <th class="num" title="En contratos con tarifario es el rubro «otros» de la celda (Parámetros)"><a href="{{ $sortUrl('v_admon') }}"    class="{{ $sortClass('v_admon') }}">🏢 Gastos</a></th>
                        <th class="num" title="Lo que le queda al aliado: afiliación cobrada − retiro − otros − asesor"><a href="{{ $sortUrl('v_util') }}"     class="{{ $sortClass('v_util') }}">📊 Utilidad</a></th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($facturas as $f)
                    <tr class="{{ !$f->distribuida ? 'sin-dist' : '' }}" id="row-{{ $f->id }}" data-id="{{ $f->id }}" data-afil="{{ (int)$f->afiliacion }}">
                        <td><strong>#{{ $f->numero_factura }}</strong></td>
                        <td style="font-size:.78rem;white-space:nowrap;color:var(--c-muted)">
                            @if($f->fecha_pago)
                                {{ \Carbon\Carbon::parse($f->fecha_pago)->locale('es')->isoFormat('D-MMMM') }}
                            @else
                                —
                            @endif
                        </td>
                        @php
                            $nombreCli = trim($f->nombre_cliente);
                            $docCli    = ($f->tipo_doc ?? 'CC') . ' ' . $f->cedula;
                        @endphp
                        <td class="col-cliente">
                            {{-- Nombre y documento en un solo bloque: el documento
                                 sigue al nombre y el conjunto se parte en 2 renglones. --}}
                            <div class="cli-nombre" title="{{ $nombreCli ? $nombreCli . ' — ' . $docCli : $docCli }}">
                                @if($nombreCli)<span class="cli-doc-nombre">{{ $nombreCli }}</span> @endif<span class="cli-doc">{{ $docCli }}</span>
                            </div>
                        </td>
                        <td style="font-size:.78rem">{{ $f->empresa_nombre }}</td>
                        <td style="font-size:.78rem">{{ $f->asesor_nombre }}</td>
                        <td style="font-size:.72rem;white-space:nowrap;">
                            <div style="color:var(--c-text);font-weight:600;">{{ $f->plan_nombre }}</div>
                            <div style="color:var(--c-muted);font-size:.65rem;margin-top:.1rem;">{{ $f->modalidad_nombre }}</div>
                        </td>
                        <td class="num" style="font-weight:700">${{ number_format($f->afiliacion) }}</td>

                        {{-- Campos dist (modo lectura) --}}
                        <td class="num td-asesor">
                            <span class="val-asesor">${{ number_format($f->dist_asesor) }}</span>
                            <input type="number" class="dist-input" name="dist_asesor" value="{{ (int)$f->dist_asesor }}" min="0" style="display:none;">
                        </td>
                        <td class="num td-retiro">
                            <span class="val-retiro">${{ number_format($f->dist_retiro) }}</span>
                            <input type="number" class="dist-input" name="dist_retiro" value="{{ (int)$f->dist_retiro }}" min="0" style="display:none;">
                        </td>
                        <td class="num td-encargado">
                            <span class="val-encargado">${{ number_format($f->dist_encargado) }}</span>
                            <input type="number" class="dist-input" name="dist_encargado" value="{{ (int)$f->dist_encargado }}" min="0" style="display:none;">
                        </td>
                        <td class="num td-admon">
                            <span class="val-admon">${{ number_format($f->dist_admon) }}</span>
                            <input type="number" class="dist-input" name="dist_admon" value="{{ (int)$f->dist_admon }}" min="0" style="display:none;">
                        </td>
                        <td class="num td-util">
                            <span class="val-util">${{ number_format($f->dist_utilidad) }}</span>
                            {{-- La utilidad es el residuo: se recalcula sola con lo que
                                 se escriba en las demás columnas. --}}
                            <input type="number" class="dist-input auto" name="dist_utilidad" value="{{ (int)$f->dist_utilidad }}" min="0" style="display:none;" title="Se ajusta solo con el resto de la afiliación">
                        </td>

                        {{-- Solo la acción. Que la afiliación esté sin repartir se
                             ve por el fondo rojizo de la fila, no por un badge. --}}
                        <td>
                            {{-- Repartir es de admin y superadmin. El contable
                                 entra a consultar: sin el permiso no se pinta
                                 el lápiz, que si no guardaría contra un 403. --}}
                            @can('comisiones.gestionar')
                            <div class="acciones-fila">
                                <button class="btn-edit" onclick="editarFila({{ $f->id }})">✏️ Editar</button>
                                <button class="btn-save" id="save-{{ $f->id }}" onclick="guardarFila({{ $f->id }})" style="display:none">💾 Guardar</button>
                                <button class="btn-cancel-row" id="cancel-{{ $f->id }}" onclick="cancelarFila({{ $f->id }})" style="display:none">✕</button>
                            </div>
                            @endcan
                            <div class="residuo" id="residuo-{{ $f->id }}"></div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ══ Resumen (al final) ══ --}}
    @php
        $totAfil      = $facturas->sum(fn($f) => (int)$f->afiliacion);
        $totAsesor    = $facturas->sum(fn($f) => (int)$f->dist_asesor);
        $totRetiro    = $facturas->sum(fn($f) => (int)$f->dist_retiro);
        $totEncargado = $facturas->sum(fn($f) => (int)($f->dist_encargado ?? 0));
        $totAdmon     = $facturas->sum(fn($f) => (int)$f->dist_admon);
        $totUtilidad  = $facturas->sum(fn($f) => (int)$f->dist_utilidad);
        $totDist      = $totAsesor + $totRetiro + $totEncargado + $totAdmon + $totUtilidad;
        $totSinDist   = $totAfil - $totDist;
        $cntTotal     = $facturas->count();
        $cntSinDist   = $facturas->where('distribuida', false)->count();
        $fmt = fn($v) => '$ ' . number_format($v, 0, ',', '.');
    @endphp
    <div style="background:#fff;border:1px solid var(--c-border);border-radius:14px;padding:1rem 1.25rem;box-shadow:0 1px 8px rgba(0,0,0,.06);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;gap:.5rem;flex-wrap:wrap;">
            <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;color:var(--c-muted);letter-spacing:.07em;">
                Resumen — {{ \Carbon\Carbon::create()->month($mes)->locale('es')->monthName }} {{ $anio }}
                <span style="color:var(--c-blue);margin-left:.5rem;">{{ $cntTotal }} registros</span>
            </div>
            @if($cntSinDist > 0)
                <span class="badge-sin">⚠️ {{ $cntSinDist }} sin distribuir</span>
            @else
                <span class="badge-ok">✅ Todo distribuido</span>
            @endif
        </div>
        <div class="kpi-grid">
            <div class="kpi-card" style="background:#f5f3ff;border-color:#ddd6fe;">
                <div class="kpi-label" style="color:#7c3aed;">Total Afiliaciones</div>
                <div class="kpi-val" style="color:#6d28d9;">{{ $fmt($totAfil) }}</div>
            </div>
            <div class="kpi-card" style="background:#fffbeb;border-color:#fde68a;">
                <div class="kpi-label" style="color:#b45309;">💼 Asesor</div>
                <div class="kpi-val" style="color:#b45309;">{{ $fmt($totAsesor) }}</div>
                @if($totAfil > 0)<div class="kpi-pct">{{ number_format($totAsesor/$totAfil*100,1) }}%</div>@endif
            </div>
            <div class="kpi-card" style="background:#faf5ff;border-color:#e9d5ff;">
                <div class="kpi-label" style="color:#7e22ce;">👤 Encargado</div>
                <div class="kpi-val" style="color:#7e22ce;">{{ $fmt($totEncargado) }}</div>
                @if($totAfil > 0)<div class="kpi-pct">{{ number_format($totEncargado/$totAfil*100,1) }}%</div>@endif
            </div>
            <div class="kpi-card" style="background:#fef2f2;border-color:#fecaca;">
                <div class="kpi-label" style="color:#b91c1c;">🔒 Retiro</div>
                <div class="kpi-val" style="color:#b91c1c;">{{ $fmt($totRetiro) }}</div>
                @if($totAfil > 0)<div class="kpi-pct">{{ number_format($totRetiro/$totAfil*100,1) }}%</div>@endif
            </div>
            <div class="kpi-card" style="background:#eff6ff;border-color:#bfdbfe;">
                <div class="kpi-label" style="color:#1d4ed8;">🏢 Gastos</div>
                <div class="kpi-val" style="color:#1d4ed8;">{{ $fmt($totAdmon) }}</div>
                @if($totAfil > 0)<div class="kpi-pct">{{ number_format($totAdmon/$totAfil*100,1) }}%</div>@endif
            </div>
            <div class="kpi-card" style="background:#ecfdf5;border-color:#a7f3d0;">
                <div class="kpi-label" style="color:#047857;">📊 Utilidad</div>
                <div class="kpi-val" style="color:#047857;">{{ $fmt($totUtilidad) }}</div>
                @if($totAfil > 0)<div class="kpi-pct">{{ number_format($totUtilidad/$totAfil*100,1) }}%</div>@endif
            </div>
        </div>
        @if($totAfil > 0)
        <div style="margin-top:.85rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;">
                <span style="font-size:.66rem;color:var(--c-muted);font-weight:600;">Distribuido</span>
                <span style="font-size:.66rem;color:{{ $totSinDist <= 0 ? '#047857' : '#b91c1c' }};font-weight:700;">
                    {{ $fmt($totDist) }} / {{ $fmt($totAfil) }}
                    @if($totSinDist > 0) — Sin distribuir: {{ $fmt($totSinDist) }} @endif
                </span>
            </div>
            <div style="height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:{{ min(100, round($totDist/$totAfil*100)) }}%;background:{{ $totSinDist <= 0 ? 'linear-gradient(90deg,#10b981,#34d399)' : 'linear-gradient(90deg,#2563eb,#8b5cf6)' }};border-radius:3px;transition:width .4s;"></div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function editarFila(id) {
    const row = document.getElementById('row-' + id);
    // Mostrar inputs, ocultar spans
    row.querySelectorAll('.val-asesor,.val-retiro,.val-encargado,.val-admon,.val-util').forEach(s => s.style.display = 'none');
    row.querySelectorAll('.dist-input').forEach(i => i.style.display = 'inline-block');
    row.querySelector('.btn-edit').style.display = 'none';
    document.getElementById('save-' + id).style.display = '';
    document.getElementById('cancel-' + id).style.display = '';

    // Un solo enganche por fila, aunque se entre y salga de edición varias veces
    if (!row.dataset.bound) {
        row.querySelectorAll('.dist-input').forEach(input => {
            input.addEventListener('input', () => {
                const rawVal = input.value.replace(/[^0-9]/g, '');
                input.value = rawVal || '0';
                // Al mover asesor/retiro/encargado/admon, la utilidad absorbe la
                // diferencia para que la suma siempre dé el valor de afiliación.
                if (input.name !== 'dist_utilidad') ajustarUtilidad(id);
                calcularResiduo(id);
            });
        });
        row.dataset.bound = '1';
    }
    calcularResiduo(id);
}

function cancelarFila(id) {
    const row = document.getElementById('row-' + id);
    row.querySelectorAll('.val-asesor,.val-retiro,.val-encargado,.val-admon,.val-util').forEach(s => s.style.display = '');
    row.querySelectorAll('.dist-input').forEach(i => i.style.display = 'none');
    row.querySelector('.btn-edit').style.display = '';
    document.getElementById('save-' + id).style.display = 'none';
    document.getElementById('cancel-' + id).style.display = 'none';
    document.getElementById('residuo-' + id).textContent = '';
}

/** La utilidad es lo que sobra de la afiliación tras el resto de conceptos. */
function ajustarUtilidad(id) {
    const row  = document.getElementById('row-' + id);
    const afil = parseInt(row.dataset.afil) || 0;
    let otros = 0;
    row.querySelectorAll('.dist-input').forEach(i => {
        if (i.name === 'dist_utilidad') return;
        const val = parseInt(i.value) || 0;
        otros += val < 0 ? 0 : val;
    });
    const util = row.querySelector('input[name="dist_utilidad"]');
    util.value = Math.max(0, afil - otros);
}

function calcularResiduo(id) {
    const row   = document.getElementById('row-' + id);
    const afil  = parseInt(row.dataset.afil) || 0;
    const inputs = row.querySelectorAll('.dist-input');
    let suma = 0;
    inputs.forEach(i => {
        const val = parseInt(i.value) || 0;
        suma += val < 0 ? 0 : val;
    });
    const diff = afil - suma;
    const el   = document.getElementById('residuo-' + id);
    if (diff === 0) {
        el.textContent = '✅ Cuadra';
        el.className = 'residuo ok';
    } else {
        el.textContent = (diff > 0 ? `Falta $${diff.toLocaleString()}` : `Excede $${Math.abs(diff).toLocaleString()}`);
        el.className = 'residuo err';
    }
}

async function guardarFila(id) {
    const row     = document.getElementById('row-' + id);
    const afil    = parseInt(row.dataset.afil) || 0;
    const inputs  = {};

    let tieneNegativos = false;
    row.querySelectorAll('.dist-input').forEach(i => {
        const val = parseInt(i.value) || 0;
        if (val < 0) {
            tieneNegativos = true;
        }
        inputs[i.name] = val;
    });

    if (tieneNegativos) {
        alert('No se permiten valores negativos en la distribución.');
        return;
    }

    const suma = Object.values(inputs).reduce((a,b) => a+b, 0);

    if (suma !== afil) {
        alert(`La suma (${suma.toLocaleString()}) debe ser igual al valor de afiliación (${afil.toLocaleString()}).`);
        return;
    }

    const btn = document.getElementById('save-' + id);
    btn.disabled = true; btn.textContent = '⏳';

    try {
        const res = await fetch(`/admin/informes/comisiones/afiliaciones/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ...inputs, _method: 'POST' }),
        });
        const json = await res.json();
        if (json.ok) {
            // Actualizar spans con nuevos valores
            row.querySelector('.val-asesor').textContent = '$' + inputs.dist_asesor.toLocaleString();
            row.querySelector('.val-retiro').textContent = '$' + inputs.dist_retiro.toLocaleString();
            row.querySelector('.val-encargado').textContent = '$' + inputs.dist_encargado.toLocaleString();
            row.querySelector('.val-admon').textContent  = '$' + inputs.dist_admon.toLocaleString();
            row.querySelector('.val-util').textContent   = '$' + inputs.dist_utilidad.toLocaleString();
            // Ya repartida: se le quita el resaltado de "sin distribuir"
            row.classList.remove('sin-dist');
            cancelarFila(id);
            btn.disabled = false; btn.textContent = '💾 Guardar';
        } else {
            alert(json.error || 'Error al guardar.');
            btn.disabled = false; btn.textContent = '💾 Guardar';
        }
    } catch(e) {
        alert('Error de red.');
        btn.disabled = false; btn.textContent = '💾 Guardar';
    }
}
</script>
@endpush
