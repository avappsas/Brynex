@extends('layouts.app')
@section('modulo', 'Gestión ARL')

@push('styles')
<style>
html,body{height:100%;overflow:hidden}
body{display:flex;flex-direction:column}
.header{flex-shrink:0}
.contenido{flex:1!important;min-height:0!important;overflow:hidden!important;display:flex!important;flex-direction:column!important;padding:.75rem 1rem!important;gap:.5rem}
</style>
@endpush

@section('contenido')
<style>
/* ── Header ── */
.garl-header{background:linear-gradient(135deg,#0f172a 0%,#1a3a5c 50%,#0e4d2f 100%);padding:.8rem 1.2rem;border-radius:12px;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;flex-shrink:0}
.garl-title{font-size:1.3rem;font-weight:800;letter-spacing:.02em}
.garl-sub{font-size:.78rem;color:#94a3b8;margin-top:.15rem}

/* ── Tabla ── */
.tbl-wrap{overflow-x:auto;overflow-y:auto;border-radius:12px;border:1px solid #e2e8f0;background:#fff;flex:1;min-height:0}
.tbl-arl{width:100%;border-collapse:collapse;font-size:.78rem;white-space:nowrap}
.tbl-arl thead th{background:#0f172a;color:#fff;padding:.55rem .6rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;position:sticky;top:0;z-index:2}
.tbl-arl tbody tr{border-bottom:1px solid #f1f5f9;transition:background .12s}
.tbl-arl tbody tr:hover{background:#f8fafc}
.tbl-arl td{padding:.45rem .55rem;vertical-align:middle}

/* ── Semáforo ── */
.sem-dot{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:.7rem;font-weight:800;flex-shrink:0;box-shadow:0 2px 6px rgba(0,0,0,.15)}
.sem-verde   {background:#16a34a;color:#fff}
.sem-amarillo{background:#d97706;color:#fff}
.sem-rojo    {background:#dc2626;color:#fff;animation:pulse-red 1.5s ease-in-out infinite}
.sem-sin_fecha{background:#94a3b8;color:#fff}
@keyframes pulse-red{0%,100%{box-shadow:0 2px 6px rgba(220,38,38,.3)}50%{box-shadow:0 2px 16px rgba(220,38,38,.7)}}

.dias-badge{font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;display:inline-block;margin-left:.3rem}
.dias-verde   {background:#dcfce7;color:#15803d}
.dias-amarillo{background:#fef3c7;color:#b45309}
.dias-rojo    {background:#fee2e2;color:#b91c1c}
.dias-sin_fecha{background:#f1f5f9;color:#64748b}

/* ── Razon badge ── */
.razon-badge{font-weight:700;font-size:.75rem;padding:.2rem .6rem;border-radius:6px;background:#dbeafe;color:#1e40af;display:inline-block;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ── Fact badge ── */
.fact-badge{background:#f0fdf4;color:#15803d;font-size:.68rem;font-weight:700;padding:.15rem .45rem;border-radius:6px;border:1px solid #86efac;white-space:nowrap}
.fact-none{color:#cbd5e1;font-size:.68rem}

/* ── Primer mes badge ── */
.primer-mes{background:#ede9fe;color:#7c3aed;font-size:.6rem;font-weight:700;padding:.1rem .35rem;border-radius:10px;margin-left:.3rem}

/* ── Botones ── */
.btn-accion{border:none;border-radius:7px;padding:.28rem .6rem;font-size:.68rem;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap;display:inline-flex;align-items:center;gap:.25rem}
.btn-renovar {background:#0d9488;color:#fff}.btn-renovar:hover{background:#0f766e}
.btn-facturar{background:#1e40af;color:#fff}.btn-facturar:hover{background:#1d4ed8}
.btn-retirar {background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0}.btn-retirar:hover{background:#fee2e2;color:#b91c1c}

/* ── Select en th ── */
.th-select{width:100%;background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,.15);color:#fff;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:.22rem .2rem;cursor:pointer;outline:none;appearance:auto}
.th-select.activo{border-bottom-color:#3b82f6;color:#93c5fd}
.th-select option{background:#0f172a;color:#fff}

/* ── Sort links en th ── */
.th-sort{color:#94a3b8;text-decoration:none;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;display:block;white-space:nowrap;transition:color .15s}
.th-sort:hover{color:#e2e8f0}
.th-sort.activo{color:#93c5fd}

/* ── Modales ── */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-bg.open{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:1.5rem;max-width:480px;width:94%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:modalIn .2s ease}
.modal-box.wide{max-width:680px}
@keyframes modalIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-title{font-size:1rem;font-weight:800;color:#0f172a;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.modal-close{background:none;border:none;font-size:1.2rem;cursor:pointer;color:#94a3b8}
.modal-close:hover{color:#ef4444}
.form-group{display:flex;flex-direction:column;gap:.25rem;margin-bottom:.8rem}
.form-group label{font-size:.72rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em}
.form-group input,.form-group select,.form-group textarea{padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;outline:none;font-family:inherit}
.form-group input:focus,.form-group select:focus{border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,.1)}
.btn-save{background:linear-gradient(135deg,#1e40af,#2563eb);color:#fff;border:none;border-radius:10px;padding:.6rem 1.5rem;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(37,99,235,.3);transition:all .15s;width:100%}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 5px 15px rgba(37,99,235,.4)}

/* ── Alerta vencidos ── */
.banner-alerta{background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fff;border-radius:10px;padding:.6rem 1rem;font-size:.82rem;font-weight:600;flex-shrink:0;display:flex;align-items:center;gap:.5rem}
</style>

{{-- ══ HEADER + FILTROS ══ --}}
<form method="GET" action="{{ route('admin.gestion-arl.index') }}" id="formFiltros" style="flex-shrink:0;">
<div class="garl-header">
    <div>
        <div class="garl-title">🛡️ Gestión ARL</div>
        <div class="garl-sub">Seguimiento de contratos ARL — {{ now()->format('d/m/Y') }}</div>
    </div>
    <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-left:auto;">

        {{-- Aliado (solo BryNex) --}}
        @if($user->es_brynex && count($alidosDisponibles) > 1)
        <select name="aliado_id" onchange="this.form.submit()" style="font-size:.78rem;padding:.3rem .5rem;border:1px solid #334155;background:#1a3a5c;color:#e2e8f0;border-radius:6px;font-weight:700;">
            @foreach($alidosDisponibles as $al)
            <option value="{{ $al->id }}" {{ $alidoId == $al->id ? 'selected' : '' }}>{{ $al->nombre }}</option>
            @endforeach
        </select>
        <span style="color:#4b6a8b;">|</span>
        @endif

        {{-- Encargado --}}
        <select name="encargado_id" onchange="this.form.submit()" style="font-size:.78rem;padding:.3rem .5rem;border:1px solid #334155;background:#1a3a5c;color:#e2e8f0;border-radius:6px;">
            <option value="">— Todos —</option>
            @foreach($encargados as $enc)
            <option value="{{ $enc->id }}" {{ $encId == $enc->id ? 'selected' : '' }}>{{ $enc->nombre }}</option>
            @endforeach
        </select>

        <span style="color:#4b6a8b;">|</span>

        {{-- Buscador por nombre / cédula --}}
        <div style="display:flex;align-items:center;background:#1a3a5c;border:1px solid #334155;border-radius:6px;overflow:hidden;">
            <span style="padding:0 .4rem;color:#64748b;font-size:.85rem;">🔍</span>
            <input type="text" name="buscar" value="{{ $buscar }}"
                   placeholder="Nombre o cédula…"
                   style="background:transparent;border:none;outline:none;color:#e2e8f0;font-size:.78rem;padding:.3rem .4rem .3rem 0;width:160px;"
                   onkeydown="if(event.key==='Enter'){this.form.submit();}">
            @if($buscar)
            <a href="{{ request()->fullUrlWithQuery(['buscar'=>'']) }}"
               style="padding:0 .4rem;color:#94a3b8;font-size:.75rem;text-decoration:none;" title="Limpiar búsqueda">✕</a>
            @endif
        </div>

        <span style="background:rgba(255,255,255,.15);color:#fff;font-size:.88rem;font-weight:800;padding:.3rem .7rem;border-radius:20px;white-space:nowrap;">
            {{ $contratos->count() }} <span style="font-size:.7rem;font-weight:500;opacity:.75;">vigentes</span>
        </span>
    </div>
</div>
</form>

{{-- ── Banner si hay vencidos ── --}}
@php $totalRojos = $contratos->where('semaforo','rojo')->count(); @endphp
@if($totalRojos > 0)
<div class="banner-alerta" style="flex-shrink:0;">
    🚨 <strong>{{ $totalRojos }} ARL {{ $totalRojos == 1 ? 'vencida' : 'vencidas' }}</strong> — Requiere atención inmediata. El retiro ya no puede ser informativo.
</div>
@endif

{{-- ══ TABLA ══ --}}
@if($contratos->isEmpty())
<div style="flex:1;display:flex;align-items:center;justify-content:center;">
<div style="text-align:center;padding:3rem;color:#94a3b8;background:#fff;border-radius:12px;border:1px solid #e2e8f0;max-width:420px;">
    <div style="font-size:3rem;">🛡️</div>
    <div style="font-size:1rem;font-weight:600;margin-top:.5rem;">Sin contratos ARL vigentes</div>
    <div style="font-size:.8rem;margin-top:.25rem;">No hay contratos de Gestión ARL activos.</div>
</div>
</div>
@else
<div class="tbl-wrap">
<table class="tbl-arl">
    @php
        // Helpers de ordenamiento
        $si = fn($col) => $sort === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
        $su = fn($col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc']);
        $sc = fn($col) => 'th-sort' . ($sort === $col ? ' activo' : '');
    @endphp
    <thead>
        <tr>
            <th style="width:36px;text-align:center;">
                <a href="{{ $su('semaforo') }}" class="{{ $sc('semaforo') }}" title="Ordenar por urgencia">⚡{{ $si('semaforo') }}</a>
            </th>
            <th style="max-width:130px;">
                <form method="GET" action="{{ route('admin.gestion-arl.index') }}" style="margin:0;">
                    @foreach(request()->except(['razon_social_id']) as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="razon_social_id" onchange="this.form.submit()" class="th-select {{ $rsId ? 'activo' : '' }}" style="max-width:125px;">
                        <option value="">↓ Razón Social</option>
                        @foreach($razonesDisponibles as $rs)<option value="{{ $rs->id }}" {{ $rsId == $rs->id ? 'selected' : '' }}>{{ $rs->razon_social }}</option>@endforeach
                    </select>
                </form>
            </th>
            <th>
                <a href="{{ $su('fecha_arl') }}" class="{{ $sc('fecha_arl') }}" title="Ordenar por fecha ARL">Fecha ARL{{ $si('fecha_arl') }}</a>
            </th>
            <th style="white-space:nowrap;">
                <a href="{{ $su('semaforo') }}" class="{{ $sc('semaforo') }}" title="Ordenar por días restantes">D. Rest. 📅{{ $si('semaforo') }}</a>
            </th>
            <th>
                <a href="{{ $su('dias_fact') }}" class="{{ $sc('dias_fact') }}" title="Ordenar por última factura">Fact.{{ $si('dias_fact') }}</a>
            </th>
            <th style="white-space:nowrap;">
                <a href="{{ $su('dias_fact') }}" class="{{ $sc('dias_fact') }}" title="Ordenar por días sin facturar">⏱ Sin fact.{{ $si('dias_fact') }}</a>
            </th>
            <th>
                <a href="{{ $su('cedula') }}" class="{{ $sc('cedula') }}" title="Ordenar por cédula">Cédula{{ $si('cedula') }}</a>
            </th>
            <th>
                <a href="{{ $su('nombre') }}" class="{{ $sc('nombre') }}" title="Ordenar por nombre">Nombres{{ $si('nombre') }}</a>
            </th>
            <th>
                <form method="GET" action="{{ route('admin.gestion-arl.index') }}" style="margin:0;">
                    @foreach(request()->except(['arl_id']) as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="arl_id" onchange="this.form.submit()" class="th-select {{ $arlF ? 'activo' : '' }}">
                        <option value="">↓ ARL</option>
                        @foreach($arlDisponibles as $a)<option value="{{ $a->id }}" {{ $arlF == $a->id ? 'selected' : '' }}>{{ $a->nombre_arl }}</option>@endforeach
                    </select>
                </form>
            </th>
            <th>N.ARL</th>
            <th>
                <form method="GET" action="{{ route('admin.gestion-arl.index') }}" style="margin:0;">
                    @foreach(request()->except(['empresa_id']) as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <select name="empresa_id" onchange="this.form.submit()" class="th-select {{ $empresaId ? 'activo' : '' }}" style="max-width:120px;">
                        <option value="">↓ Empresa</option>
                        @foreach($empresasDisponibles as $emp)<option value="{{ $emp->id }}" {{ $empresaId == $emp->id ? 'selected' : '' }}>{{ $emp->empresa }}</option>@endforeach
                    </select>
                </form>
            </th>
            <th style="min-width:160px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
    @foreach($contratos as $c)
    @php
        $nombre = trim(collect([$c->cliente?->primer_nombre,$c->cliente?->primer_apellido])->filter()->implode(' '));
        $nombreCompleto = trim(collect([$c->cliente?->primer_nombre,$c->cliente?->segundo_nombre,$c->cliente?->primer_apellido,$c->cliente?->segundo_apellido])->filter()->implode(' '));
        $empresa = $c->cliente?->empresa?->empresa ?? '—';
        // Última factura display
        $uf = $c->ultima_factura;
        $fechaUf   = $uf ? ($uf->fecha_pago ? \Carbon\Carbon::parse($uf->fecha_pago) : \Carbon\Carbon::create($uf->anio, $uf->mes, 1)) : null;
        $ufDisplay = $uf ? $uf->numero_factura . ' (' . $fechaUf->format('d/m') . ')' : null;
        // Semáforo
        $sem = $c->semaforo;
        $diasR = $c->dias_restantes;
        $diasText = $diasR !== null ? ($diasR >= 0 ? "{$diasR}d" : 'VEN') : '—';
        // Fecha ARL display (fallback a fecha_ingreso con indicador visual)
        $esFechaIngreso  = !$c->fecha_arl && $c->fecha_ingreso;
        $fechaArlDisplay = $c->fecha_arl
            ? $c->fecha_arl->format('d/m/Y')
            : ($c->fecha_ingreso ? $c->fecha_ingreso->format('d/m/Y') : '—');
        // Data para modales
        $ctx = json_encode([
            'id'            => $c->id,
            'cedula'        => $c->cedula,
            'nombre'        => $nombre,
            'nombre_completo'=> $nombreCompleto,
            'razon_social'  => $c->razonSocial?->razon_social ?? '—',
            'fecha_arl'     => $c->fecha_arl?->format('Y-m-d') ?? '',
            'fecha_ingreso' => $c->fecha_ingreso?->format('d/m/Y') ?? '—',
            'arl'           => $c->arl_efectiva_nombre,
            'n_arl'         => $c->n_arl ?? 1,
            'semaforo'      => $sem,
            'dias_restantes'=> $diasR,
        ]);
    @endphp
    <tr id="row-{{ $c->id }}">
        {{-- Semáforo --}}
        <td style="text-align:center;">
            <span class="sem-dot sem-{{ $sem }}" title="{{ $sem === 'verde' ? 'Vigente' : ($sem === 'amarillo' ? 'Por vencer' : ($sem === 'rojo' ? 'VENCIDO' : 'Sin fecha')) }}">
                {{ $sem === 'verde' ? '✓' : ($sem === 'amarillo' ? '!' : ($sem === 'rojo' ? '✗' : '?')) }}
            </span>
        </td>

        {{-- Razón Social --}}
        <td><span class="razon-badge" title="{{ $c->razonSocial?->razon_social }}">{{ $c->razonSocial?->razon_social ?? '—' }}</span></td>

        {{-- Fecha ARL --}}
        <td style="font-size:.75rem;font-weight:700;color:{{ $esFechaIngreso ? '#64748b' : ($sem === 'rojo' ? '#dc2626' : ($sem === 'amarillo' ? '#b45309' : '#15803d')) }};">
            {{ $fechaArlDisplay }}
            @if($esFechaIngreso)
                <span style="font-size:.58rem;font-weight:600;background:#f1f5f9;color:#64748b;padding:.1rem .35rem;border-radius:8px;margin-left:.2rem;border:1px solid #e2e8f0;">ing.</span>
            @elseif($c->es_primer_mes)
                <span class="primer-mes">1er mes</span>
            @endif
        </td>

        {{-- Días restantes --}}
        <td>
            <span class="dias-badge dias-{{ $sem }}">{{ $diasText }}</span>
        </td>

        {{-- Última factura --}}
        <td>
            @if($ufDisplay)<span class="fact-badge">{{ $ufDisplay }}</span>
            @else<span class="fact-none">—</span>@endif
        </td>

        {{-- Días desde última factura --}}
        <td style="text-align:center;">
            @if($c->dias_desde_factura !== null)
                @php $df = $c->dias_desde_factura; @endphp
                <span class="dias-badge {{ $df <= 10 ? 'dias-verde' : ($df <= 20 ? 'dias-amarillo' : 'dias-rojo') }}"
                      title="{{ $df }} días desde la última factura">
                    {{ $df }}d
                </span>
            @else
                <span class="fact-none">—</span>
            @endif
        </td>

        {{-- Cédula --}}
        <td style="font-family:monospace;font-size:.77rem;font-weight:700;color:#3b82f6;">{{ $c->cedula }}</td>

        {{-- Nombres --}}
        <td style="font-weight:600;color:#1e3a5f;max-width:140px;overflow:hidden;text-overflow:ellipsis;" title="{{ $nombreCompleto }}">
            {{ $nombre }}
        </td>

        {{-- ARL --}}
        <td style="font-size:.7rem;color:#475569;max-width:80px;overflow:hidden;text-overflow:ellipsis;" title="{{ $c->arl_efectiva_nombre }}">
            {{ $c->arl_efectiva_nombre }}
        </td>

        {{-- N.ARL --}}
        <td style="text-align:center;">
            @php $nivel = (int)($c->n_arl ?? 1); @endphp
            <span style="background:#f0fdf4;color:#15803d;font-size:.68rem;font-weight:800;padding:.15rem .45rem;border-radius:6px;border:1px solid #86efac;">N{{ $nivel }}</span>
        </td>

        {{-- Empresa --}}
        <td style="font-size:.65rem;color:#475569;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $empresa }}">{{ $empresa }}</td>

        {{-- Acciones --}}
        <td style="white-space:nowrap;">
            <button class="btn-accion btn-renovar" onclick="abrirRenovar({{ $ctx }})" title="Registrar renovación en portal ARL">
                📅 Renovar
            </button>
            <button class="btn-accion btn-facturar" onclick="abrirFacturar({{ $ctx }})" title="Facturar afiliación ARL">
                💳 Facturar
            </button>
            <button class="btn-accion btn-retirar" onclick="abrirRetirar({{ $ctx->id ?? $c->id }}, '{{ addslashes($nombre) }}')" title="Retiro informativo">
                ❌
            </button>
        </td>
    </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif

{{-- ══ MODAL RENOVAR FECHA ARL ══ --}}
<div class="modal-bg" id="modalRenovar">
<div class="modal-box">
    <div class="modal-title">
        <span>📅 Renovar ARL en Portal</span>
        <button class="modal-close" onclick="cerrarModal('modalRenovar')">✕</button>
    </div>
    <div id="renovar-ctx" style="background:#f0fdf4;border-radius:8px;padding:.5rem .75rem;margin-bottom:.75rem;font-size:.78rem;">
        <strong id="renovar-nombre"></strong> — <span id="renovar-rs" style="color:#475569;"></span>
    </div>
    <div style="background:#fef3c7;border-radius:8px;padding:.5rem .75rem;margin-bottom:.75rem;font-size:.75rem;color:#92400e;border:1px solid #fcd34d;">
        💡 Registra aquí la nueva fecha de afiliación en el portal de la ARL. El semáforo se reinicia desde ese día (28 días de vigencia).
    </div>
    <div class="form-group">
        <label>Nueva Fecha de Afiliación ARL *</label>
        <input type="date" id="renovar-fecha" required>
        <span style="font-size:.7rem;color:#94a3b8;">Coloca el día en que aparece la afiliación en el portal.</span>
    </div>
    <input type="hidden" id="renovar-contrato-id">
    <button class="btn-save" onclick="guardarRenovacion()">✅ Registrar Fecha ARL</button>
</div>
</div>

{{-- ══ MODAL RETIRO (iframe contrato form) ══ --}}
<div class="modal-bg" id="modalRetirar">
<div class="modal-box wide" style="max-width:1200px;padding:0;overflow:hidden;">
    <div style="background:#0f172a;color:#fff;padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between;">
        <strong style="font-size:.9rem;">❌ Retiro Informativo — <span id="retirar-nombre"></span></strong>
        <button class="modal-close" onclick="cerrarModal('modalRetirar')" style="color:#94a3b8;font-size:1.2rem;">✕</button>
    </div>
    <iframe id="retirar-iframe" src="" style="width:100%;height:750px;border:none;"></iframe>
</div>
</div>

{{-- ══ MODAL FACTURAR (iframe partial) ══ --}}
<div class="modal-bg" id="modalFacturar">
<div class="modal-box wide" style="max-width:780px;padding:0;overflow:hidden;">
    <div style="background:#1e40af;color:#fff;padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between;">
        <strong style="font-size:.9rem;">💳 Facturar ARL — <span id="facturar-nombre"></span></strong>
        <button class="modal-close" onclick="cerrarModal('modalFacturar')" style="color:#bfdbfe;font-size:1.2rem;">✕</button>
    </div>
    <iframe id="facturar-iframe" src="" style="width:100%;height:600px;border:none;"></iframe>
</div>
</div>

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

/* ── Helpers modales ── */
function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
    const iframe = document.querySelector(`#${id} iframe`);
    if (iframe) iframe.src = '';
}

/* ── Modal Renovar ── */
function abrirRenovar(ctx) {
    document.getElementById('renovar-nombre').textContent = ctx.nombre_completo || ctx.nombre;
    document.getElementById('renovar-rs').textContent     = ctx.razon_social;
    document.getElementById('renovar-contrato-id').value  = ctx.id;
    // Sugerir el día siguiente a fecha_arl actual, o mañana si sin fecha
    const base = ctx.fecha_arl
        ? new Date(ctx.fecha_arl + 'T00:00:00')
        : new Date();
    const sugerida = new Date(base);
    if (ctx.fecha_arl) sugerida.setDate(sugerida.getDate() + 29);
    document.getElementById('renovar-fecha').value = sugerida.toISOString().split('T')[0];
    document.getElementById('modalRenovar').classList.add('open');
}

async function guardarRenovacion() {
    const id     = document.getElementById('renovar-contrato-id').value;
    const fecha  = document.getElementById('renovar-fecha').value;
    if (!fecha) { alert('Selecciona la fecha de afiliación ARL.'); return; }

    const btn = document.querySelector('#modalRenovar .btn-save');
    btn.disabled = true; btn.textContent = '⏳ Guardando...';

    const res = await fetch(`/admin/gestion-arl/${id}/renovar`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ fecha_arl: fecha }),
    });
    const data = await res.json();
    btn.disabled = false; btn.textContent = '✅ Registrar Fecha ARL';

    if (data.ok) {
        cerrarModal('modalRenovar');
        // Recargar página para actualizar semáforos
        location.reload();
    } else {
        alert(data.message || 'Error al guardar.');
    }
}

/* ── Modal Facturar (iframe hacia la vista de facturación individual) ── */
function abrirFacturar(ctx) {
    document.getElementById('facturar-nombre').textContent = ctx.nombre;
    // Abre el contrato en la vista de facturación con iframe
    const url = `/admin/facturacion?cedula=${ctx.cedula}&contrato_id=${ctx.id}&tipo=afiliacion&iframe=1`;
    document.getElementById('facturar-iframe').src = url;
    document.getElementById('modalFacturar').classList.add('open');
}

/* ── Modal Retiro Informativo ── */
function abrirRetirar(id, nombre) {
    document.getElementById('retirar-nombre').textContent = nombre;
    // Abre el formulario de contrato con el modal de retiro pre-abierto, solo modo informativo
    const url = `/admin/contratos/${id}/edit?iframe=1&abrir_retiro=informativo`;
    document.getElementById('retirar-iframe').src = url;
    document.getElementById('modalRetirar').classList.add('open');
}

/* ── Cerrar al click fuera ── */
document.querySelectorAll('.modal-bg').forEach(bg => {
    bg.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
            const iframe = this.querySelector('iframe');
            if (iframe) iframe.src = '';
        }
    });
});
</script>
@endpush
@endsection
