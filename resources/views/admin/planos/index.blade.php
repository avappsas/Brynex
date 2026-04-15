@extends('layouts.app')

@section('titulo', 'Planos SS')
@section('modulo', 'Pago Planillas Seguridad Social')

@push('styles')
<style>
/* ── Variables ─────────────────────────────────────────────────────── */
:root {
    --azul-oscuro:#0a1628; --azul-medio:#0d2550; --azul-vivo:#1e40af;
    --acento:#3b82f6; --verde:#10b981; --rojo:#ef4444; --amarillo:#f59e0b;
}

/* ── Cabecera del módulo ─────────────────────────────────────────────── */
.modulo-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:1rem; flex-wrap:wrap; gap:.5rem;
}
.modulo-titulo {
    font-size:1.15rem; font-weight:700; color:var(--azul-oscuro);
    display:flex; align-items:center; gap:.5rem;
}
.badge-plano {
    background:var(--amarillo); color:#fff; font-size:.72rem;
    font-weight:700; padding:.15rem .55rem; border-radius:999px;
    display:inline-flex; align-items:center; gap:.3rem;
}

/* ── Panel de filtros ─────────────────────────────────────────────────── */
.filtros-panel {
    background:#fff; border-radius:12px;
    border:1px solid #e2e8f0; padding:.5rem .85rem;
    margin-bottom:.75rem;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.filtros-grid {
    display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
}
/* Grupo inline: label pegado al control */
.filtro-inline {
    display:flex; align-items:center; gap:.3rem;
}
.filtro-inline .fi-label {
    font-size:.68rem; font-weight:700; color:#64748b;
    text-transform:uppercase; letter-spacing:.04em;
    white-space:nowrap;
}
.filtro-inline select,
.filtro-inline .multiselect-trigger {
    border:1px solid #cbd5e1; border-radius:7px;
    padding:.28rem .5rem; font-size:.8rem;
    background:#f8fafc; color:#1e293b;
    outline:none; transition:border .15s;
    text-align:center;
}
.filtro-inline select:focus { border-color:var(--acento); background:#fff; }
/* Separador vertical */
.filtro-sep { width:1px; height:24px; background:#e2e8f0; margin:0 .1rem; flex-shrink:0; }
/* Mantener compatibilidad con .filtro-grupo para el resto de la app */
.filtro-grupo { display:flex; flex-direction:column; gap:.25rem; }
.filtro-grupo label {
    font-size:.7rem; font-weight:600; color:#64748b;
    text-transform:uppercase; letter-spacing:.04em;
}
.filtro-grupo select,
.filtro-grupo input[type=number],
.filtro-grupo input[type=text] {
    border:1px solid #cbd5e1; border-radius:7px;
    padding:.32rem .6rem; font-size:.82rem;
    background:#f8fafc; color:#1e293b;
    outline:none; transition:border .15s;
    min-width:80px;
}
.filtro-grupo select:focus,
.filtro-grupo input:focus { border-color:var(--acento); background:#fff; }

/* N_plano con botón + */
.nplano-wrap {
    display:flex; align-items:center; gap:.3rem;
}
.nplano-wrap input { width:60px; text-align:center; }
.btn-plus {
    width:26px; height:26px; border-radius:6px;
    background:var(--acento); color:#fff; border:none;
    cursor:pointer; font-size:1rem; display:flex;
    align-items:center; justify-content:center;
    transition:background .15s;
}
.btn-plus:hover { background:var(--azul-vivo); }

/* Multiselect tipos_modalidad */
.multiselect-wrap { position:relative; }
.multiselect-trigger {
    border:1px solid #cbd5e1; border-radius:7px;
    padding:.32rem .6rem; font-size:.82rem;
    background:#f8fafc; color:#1e293b;
    cursor:pointer; min-width:140px;
    display:flex; align-items:center; justify-content:space-between;
    gap:.4rem; white-space:nowrap;
    user-select:none;
}
.multiselect-trigger:hover { border-color:var(--acento); }
.multiselect-dropdown {
    position:absolute; top:calc(100% + 4px); left:0; z-index:200;
    background:#fff; border:1px solid #e2e8f0; border-radius:10px;
    box-shadow:0 8px 24px rgba(0,0,0,.12);
    padding:.4rem; min-width:220px;
    display:none; max-height:240px; overflow-y:auto;
}
.multiselect-wrap.open .multiselect-dropdown { display:block; }
.ms-item {
    display:flex; align-items:center; gap:.5rem;
    padding:.3rem .5rem; border-radius:6px;
    cursor:pointer; font-size:.8rem; color:#334155;
}
.ms-item:hover { background:#f1f5f9; }
.ms-item input[type=checkbox] { accent-color:var(--acento); }
.ms-select-all {
    font-size:.7rem; font-weight:600; color:var(--acento);
    cursor:pointer; padding:.2rem .5rem;
    border-bottom:1px solid #f1f5f9; margin-bottom:.2rem;
    display:block;
}

/* ── Botones de acción ───────────────────────────────────────────────── */
.btn-accion {
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.38rem .85rem; border-radius:8px; font-size:.8rem;
    font-weight:600; cursor:pointer; border:none;
    text-decoration:none; transition:all .15s;
}
.btn-descargar {
    background:linear-gradient(135deg,#0d9488,#0f766e);
    color:#fff;
    box-shadow:0 2px 6px rgba(13,148,136,.3);
}
.btn-descargar:hover { background:linear-gradient(135deg,#0f766e,#134e4a); }
.btn-pagar {
    background:linear-gradient(135deg,#1d4ed8,#1e40af);
    color:#fff;
    box-shadow:0 2px 6px rgba(29,78,216,.3);
}
.btn-pagar:hover { background:linear-gradient(135deg,#1e40af,#1e3a8a); }
.btn-consultar {
    background:linear-gradient(135deg,#7c3aed,#6d28d9);
    color:#fff;
    box-shadow:0 2px 6px rgba(124,58,237,.3);
}
.btn-consultar:hover { background:linear-gradient(135deg,#6d28d9,#5b21b6); }
.btn-cancelar {
    background:#f1f5f9; color:#64748b;
    border:1px solid #e2e8f0;
}
.btn-cancelar:hover { background:#e2e8f0; }

/* ── Tabla ───────────────────────────────────────────────────────────── */
.tabla-wrap {
    background:#fff; border-radius:12px;
    border:1px solid #e2e8f0;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
    overflow:hidden;
}
.tabla-planos {
    width:100%; border-collapse:collapse; font-size:.76rem;
}
.tabla-planos thead tr {
    background:linear-gradient(135deg,var(--azul-oscuro),var(--azul-medio));
    color:#e2e8f0;
}
.tabla-planos thead th {
    padding:.5rem .45rem; text-align:left;
    font-weight:600; font-size:.68rem;
    text-transform:uppercase; letter-spacing:.04em;
    white-space:nowrap; border-right:1px solid rgba(255,255,255,.07);
}
.tabla-planos tbody tr { border-bottom:1px solid #f1f5f9; }
.tabla-planos tbody tr:hover { background:#f8fafc; }
.tabla-planos tbody td {
    padding:.4rem .45rem; color:#334155;
    white-space:nowrap; overflow:hidden;
    max-width:140px; text-overflow:ellipsis;
}
.tabla-planos tbody td.td-nombre { max-width:130px; }
.tabla-planos tbody td.td-empresa { max-width:90px; }
.tabla-planos tbody td.td-envio { max-width:70px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tabla-planos tfoot tr {
    background:linear-gradient(135deg,#0a1628,#0d2550);
    color:#e2e8f0;
}
.tabla-planos tfoot td {
    padding:.5rem .45rem; font-weight:700;
    font-size:.78rem; white-space:nowrap;
}
.chip-tipo {
    display:inline-block; padding:.1rem .4rem;
    border-radius:4px; font-size:.65rem; font-weight:700;
    background:#e0f2fe; color:#0369a1;
}
.chip-tipo.e   { background:#dcfce7; color:#15803d; }
.chip-tipo.i   { background:#fef9c3; color:#a16207; }
.chip-tipo.k   { background:#f3e8ff; color:#7e22ce; }
.chip-tipo.tp  { background:#ffe4e6; color:#be123c; }

/* ── Pie: resumen ────────────────────────────────────────────────────── */
.resumen-pie {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
    padding:.6rem 1rem; margin-top:.75rem;
    display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;
    font-size:.82rem;
}
.resumen-item { display:flex; flex-direction:column; }
.resumen-item .ri-label {
    font-size:.65rem; font-weight:600; color:#94a3b8;
    text-transform:uppercase; letter-spacing:.04em;
}
.resumen-item .ri-valor {
    font-size:.9rem; font-weight:700; color:var(--azul-oscuro);
}

/* ── Modales ─────────────────────────────────────────────────────────── */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(10,22,40,.6); z-index:1000;
    align-items:center; justify-content:center;
    backdrop-filter:blur(3px);
}
.modal-overlay.open { display:flex; }
.modal-box {
    background:#fff; border-radius:16px; width:100%;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
    animation:modalIn .2s ease;
    overflow-y:auto;
}
.modal-box.md { max-width:480px; max-height:90vh; }
.modal-box.lg { max-width:640px; max-height:90vh; }
@keyframes modalIn {
    from { opacity:0; transform:translateY(-16px) scale(.97); }
    to   { opacity:1; transform:none; }
}
.modal-head {
    padding:1rem 1.25rem .75rem;
    border-bottom:1px solid #f1f5f9;
    display:flex; align-items:center; justify-content:space-between;
}
.modal-head h3 { font-size:.95rem; font-weight:700; color:var(--azul-oscuro); }
.modal-close {
    background:none; border:none; cursor:pointer;
    font-size:1.2rem; color:#94a3b8;
    transition:color .15s;
}
.modal-close:hover { color:var(--rojo); }
.modal-body { padding:1.1rem 1.25rem; }
.modal-foot {
    padding:.75rem 1.25rem 1rem;
    border-top:1px solid #f1f5f9;
    display:flex; gap:.5rem; justify-content:flex-end; flex-wrap:wrap;
}

/* Campos del modal */
.form-row { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem; }
.form-grupo { display:flex; flex-direction:column; gap:.25rem; flex:1; min-width:120px; }
.form-grupo label {
    font-size:.7rem; font-weight:600; color:#64748b;
    text-transform:uppercase; letter-spacing:.04em;
}
.form-grupo input,
.form-grupo select,
.form-grupo textarea {
    border:1px solid #cbd5e1; border-radius:8px;
    padding:.4rem .65rem; font-size:.83rem;
    color:#1e293b; outline:none;
    transition:border .15s; background:#f8fafc;
}
.form-grupo input:focus,
.form-grupo select:focus,
.form-grupo textarea:focus {
    border-color:var(--acento); background:#fff;
}
.form-grupo textarea { resize:vertical; min-height:70px; }

.aviso-modal {
    background:#fef3c7; border:1px solid #fde68a;
    border-radius:8px; padding:.65rem .9rem;
    font-size:.78rem; color:#92400e; margin-bottom:.9rem;
    line-height:1.5;
}
.aviso-modal strong { display:block; margin-bottom:.2rem; }

.info-chip {
    background:#eff6ff; border:1px solid #bfdbfe;
    border-radius:6px; padding:.3rem .65rem;
    font-size:.75rem; color:#1d4ed8; font-weight:600;
    display:inline-block; margin-bottom:.75rem;
}

/* Toast */
.toast {
    position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
    background:#0f172a; color:#e2e8f0; border-radius:10px;
    padding:.65rem 1.1rem; font-size:.82rem; font-weight:500;
    box-shadow:0 8px 24px rgba(0,0,0,.3);
    transform:translateY(60px); opacity:0;
    transition:all .3s ease;
    max-width:340px;
}
.toast.show { transform:none; opacity:1; }
.toast.success { border-left:4px solid var(--verde); }
.toast.error   { border-left:4px solid var(--rojo); }

/* ── Vacío ───────────────────────────────────────────────────────────── */
.empty-state {
    text-align:center; padding:3rem 1rem;
    color:#94a3b8;
}
.empty-state .es-icon { font-size:2.5rem; margin-bottom:.5rem; }
.empty-state p { font-size:.85rem; }
</style>
@endpush

@section('contenido')
<div class="modulo-header">
    <div class="modulo-titulo">
        📄 Planos de Seguridad Social – Pago al Operador
    </div>
    @if($rsSeleccionada)
    <div style="display:flex;align-items:center;gap:.5rem">
        <span class="badge-plano">
            N_PLANO actual:
            <span id="badge-nplano-val">{{ $nPlanoActual }}</span>
        </span>
    </div>
    @endif
</div>

{{-- ── Panel de filtros (toolbar compacto) ───────────── --}}
<div class="filtros-panel">
    <form method="GET" action="{{ route('admin.planos.index') }}" id="frm-filtros">
        <div class="filtros-grid" style="display:flex;align-items:center;gap:.5rem">

            {{-- Año --}}
            <div class="filtro-inline">
                <span class="fi-label">Año</span>
                <select name="anio" onchange="autoSubmit()">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div class="filtro-sep"></div>

            {{-- Mes --}}
            <div class="filtro-inline">
                <span class="fi-label">Mes</span>
                <select name="mes" onchange="autoSubmit()">
                    @php
                    $meses = ['','Ene','Feb','Mar','Abr','May','Jun',
                              'Jul','Ago','Sep','Oct','Nov','Dic'];
                    @endphp
                    @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>{{ $meses[$m] }}</option>
                    @endfor
                </select>
            </div>

            <div class="filtro-sep"></div>

            {{-- Razón Social --}}
            <div class="filtro-inline">
                <span class="fi-label">RS</span>
                <select name="razon_social_id" id="sel-rs" style="min-width:200px" onchange="onRsChange(this)">
                    <option value="">— Todas —</option>
                    @foreach($razonesSociales as $rs)
                    @php $esActiva = in_array(strtolower($rs->estado ?? ''), ['activo','activa','1','si','yes']); @endphp
                    <option value="{{ $rs->id }}"
                        data-nplano="{{ $rs->n_plano }}"
                        {{ $razonSocialId == $rs->id ? 'selected' : '' }}
                        style="{{ !$esActiva ? 'color:#94a3b8' : '' }}">
                        {{ $esActiva ? '' : '▪ ' }}{{ $rs->razon_social }} | P={{ $rs->n_plano ?? '?' }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filtro-sep"></div>

            {{-- N° Plano --}}
            <div class="filtro-inline">
                <span class="fi-label">Plano</span>
                <select name="n_plano" id="sel-nplano" onchange="autoSubmit()" style="width:72px">
                    <option value="">Auto</option>
                    @php $maxPlano = max(12, (int)($nPlanoFiltro ?? 0), (int)($rsSeleccionada->n_plano ?? 0)); @endphp
                    @for($np = 1; $np <= $maxPlano; $np++)
                    <option value="{{ $np }}"
                        {{ (string)$nPlanoFiltro === (string)$np ? 'selected' : '' }}>
                        {{ $np }}{{ ($rsSeleccionada && $rsSeleccionada->n_plano == $np) ? ' ⭐' : '' }}
                    </option>
                    @endfor
                </select>
            </div>

            <div class="filtro-sep"></div>

            {{-- Modalidad (multiselect) --}}
            <div class="filtro-inline">
                <span class="fi-label">Modal.</span>
                <div class="multiselect-wrap" id="ms-wrap">
                    <div class="multiselect-trigger" id="ms-trigger" onclick="toggleMs()" style="min-width:80px">
                        <span id="ms-label">{{ count($modalidadesIds) ? count($modalidadesIds).' sel.' : 'Todos' }}</span>
                        <span style="font-size:.7rem">▼</span>
                    </div>
                    <div class="multiselect-dropdown" id="ms-dropdown">
                        <span class="ms-select-all" onclick="toggleAllMs()">&#9745; Todos</span>
                        @foreach($tiposModalidad as $tm)
                        <label class="ms-item">
                            <input type="checkbox" name="tipos_modalidad[]"
                                   value="{{ $tm->id }}"
                                   {{ in_array($tm->id, $modalidadesIds) ? 'checked' : '' }}
                                   onchange="updateMsLabel()">
                            {{ $tm->tipo_modalidad }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Botones --}}
            <div style="margin-left:auto;display:flex;gap:.4rem;align-items:center">
                <button type="button" class="btn-accion btn-descargar" onclick="abrirModalDescarga()"
                    @if($planos->count()==0) disabled style="opacity:.4;cursor:not-allowed" @endif>
                    📥 Descargar Plano
                </button>
                <button type="button" class="btn-accion btn-pagar" onclick="abrirModalPago()"
                    @if($planos->count()==0) disabled style="opacity:.4;cursor:not-allowed" @endif>
                    ✅ Confirmar Pago
                </button>
            </div>

        </div>
    </form>
</div>

{{-- ── Tabla ───────────────────────────────────────────────────────────── --}}
<div class="tabla-wrap">
@if($planos->count() > 0)
<table class="tabla-planos">
    <thead>
        <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>No. ID</th>
            <th>Nombre</th>
            <th>Fec. Ing</th>
            <th>Fec. Ret</th>
            <th>Días</th>
            <th>EPS</th>
            <th title="Valor EPS">V.EPS</th>
            <th>ARL</th>
            <th title="Valor ARL">V.ARL</th>
            <th>CAJA</th>
            <th title="Valor Caja">V.CAJA</th>
            <th>PENSION</th>
            <th title="Valor Pensión">V.AFP</th>
            <th title="Total Seguridad Social"><b>TOTAL SS</b></th>
            <th>Planilla</th>
            <th>Empresa</th>
            <th>Envío</th>
            <th>Edad</th>
        </tr>
    </thead>
    <tbody>
        @php $i = 1; @endphp
        @foreach($planos as $p)
        @php
            $tipoClass = match(strtoupper(substr($p->tipo_modal_nombre ?? '', 0, 1))) {
                'E' => 'e', 'I' => 'i', 'K' => 'k', 'T' => 'tp', default => ''
            };
        @endphp
        <tr>
            <td style="color:#94a3b8">{{ $i++ }}</td>
            <td><span class="chip-tipo {{ $tipoClass }}">{{ $p->tipo_modal_nombre ?? $p->tipo_p }}</span></td>
            <td>{{ $p->no_identifi }}</td>
            <td class="td-nombre" title="{{ $p->nombre_completo }}">{{ $p->primer_nombre }} {{ $p->primer_ape }}</td>
            <td>{{ $p->fecha_ing ? \Carbon\Carbon::parse($p->fecha_ing)->format('d-') . strtolower(\Carbon\Carbon::parse($p->fecha_ing)->locale('es')->isoFormat('MMM')) : '—' }}</td>
            <td>{{ $p->fecha_ret ? \Carbon\Carbon::parse($p->fecha_ret)->format('d-') . strtolower(\Carbon\Carbon::parse($p->fecha_ret)->locale('es')->isoFormat('MMM')) : '—' }}</td>
            <td>{{ $p->num_dias }}</td>
            <td title="{{ $p->cod_eps }}">{{ $p->nombre_eps ? \Illuminate\Support\Str::limit($p->nombre_eps,18,'…') : ($p->cod_eps ?? '—') }}</td>
            <td>{{ number_format($p->v_eps ?? 0,0,',','.') }}</td>
            <td title="{{ $p->cod_arl }}">{{ $p->nombre_arl ? \Illuminate\Support\Str::limit($p->nombre_arl,18,'…') : ($p->cod_arl ?? '—') }}</td>
            <td>{{ number_format($p->v_arl ?? 0,0,',','.') }}</td>
            <td title="{{ $p->cod_caja }}">{{ $p->nombre_caja ? \Illuminate\Support\Str::limit($p->nombre_caja,18,'…') : ($p->cod_caja ?? '—') }}</td>
            <td>{{ number_format($p->v_caja ?? 0,0,',','.') }}</td>
            <td title="{{ $p->cod_afp }}">{{ $p->nombre_afp ? \Illuminate\Support\Str::limit($p->nombre_afp,18,'…') : ($p->cod_afp ?? '—') }}</td>
            <td>{{ number_format($p->v_afp ?? 0,0,',','.') }}</td>
            <td style="font-weight:700;color:var(--azul-vivo)">
                {{ number_format($p->total_ss ?? 0,0,',','.') }}
            </td>
            <td>
                @if($p->numero_planilla)
                <span style="color:var(--verde);font-weight:600">{{ $p->numero_planilla }}</span>
                @else
                <span style="color:#cbd5e1">—</span>
                @endif
            </td>
            <td class="td-empresa" title="{{ $p->nombre_empresa }}">{{ $p->nombre_empresa ? \Illuminate\Support\Str::limit($p->nombre_empresa,14,'…') : '—' }}</td>
            <td class="td-envio" title="{{ $p->envio_planilla }}">{{ $p->envio_planilla ? 'Sí' : 'No' }}</td>
            <td>{{ $p->edad !== null ? $p->edad.'a' : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" style="text-align:right">TOTALES →</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_eps'),0,',','.') }}</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_arl'),0,',','.') }}</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_caja'),0,',','.') }}</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_afp'),0,',','.') }}</td>
            <td style="font-size:.88rem">$ {{ number_format($totalSS,0,',','.') }}</td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>
@else
<div class="empty-state">
    <div class="es-icon">📄</div>
    <p>No hay planos de planilla para el período y filtros seleccionados.</p>
    <p style="margin-top:.35rem;font-size:.75rem">Filtre por período, razón social y/o tipo de modalidad.</p>
</div>
@endif
</div>

{{-- ── Resumen pie ────────────────────────────────────────── --}}
@if($planos->count() > 0)
<div class="resumen-pie" style="justify-content:flex-end">
    @if($rsSeleccionada)
    <div class="resumen-item">
        <span class="ri-label">N_Plano</span>
        <span class="ri-valor" style="color:var(--amarillo)">{{ $nPlanoActual }}</span>
    </div>
    @endif
    <div class="resumen-item">
        <span class="ri-label">Personas</span>
        <span class="ri-valor">{{ $totalPersonas }}</span>
    </div>
    <div class="resumen-item">
        <span class="ri-label">Total SS</span>
        <span class="ri-valor" style="color:var(--azul-vivo)">$ {{ number_format($totalSS,0,',','.') }}</span>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL 1: Descargar Plano (TXT o XLSX) + pregunta N_PLANO
═══════════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modal-descarga">
    <div class="modal-box md">
        <div class="modal-head">
            <h3>📥 Descargar Plano</h3>
            <button class="modal-close" onclick="cerrarModal('modal-descarga')">✕</button>
        </div>
        <div class="modal-body">
            <div style="display:flex;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap">
                <button class="btn-accion btn-descargar" style="flex:1"
                        onclick="ejecutarDescarga('txt')">
                    📄 Descargar TXT
                </button>
                <button class="btn-accion btn-pagar" style="flex:1"
                        onclick="ejecutarDescarga('xlsx')">
                    📊 Descargar Excel
                </button>
            </div>

            <div style="border-top:1px solid #f1f5f9;padding-top:1rem;margin-top:.25rem">
                <div class="aviso-modal">
                    <strong>⚠️ Actualizar N° Plano</strong>
                    Si realizará el pago con este plano, actualice el N_PLANO de la razón social para que los pagos siguientes queden en un nuevo número separado.
                </div>

                @if($rsSeleccionada)
                <div class="info-chip">
                    NIT: {{ $rsSeleccionada->id }} · {{ $rsSeleccionada->razon_social }}
                </div>
                @endif

                <div class="form-row">
                    <div class="form-grupo">
                        <label>N_PLANO nuevo</label>
                        <div class="nplano-wrap">
                            <input type="number" id="inp-nplano-modal" min="1"
                                   value="{{ ($nPlanoActual ?? 0) + 1 }}" style="width:80px">
                            <button type="button" class="btn-plus"
                                    onclick="document.getElementById('inp-nplano-modal').stepUp()">+</button>
                        </div>
                    </div>
                </div>
                <button class="btn-accion btn-pagar" onclick="guardarNPlano()"
                        @if(!$rsSeleccionada) disabled @endif>
                    💾 Guardar N_PLANO
                </button>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-accion btn-cancelar" onclick="cerrarModal('modal-descarga')">Cerrar</button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL 2: Confirmar Pago al Operador
═══════════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modal-pago">
    <div class="modal-box lg">
        <div class="modal-head">
            <h3>✅ Confirmar Pago de Planilla al Operador</h3>
            <button class="modal-close" onclick="cerrarModal('modal-pago')">✕</button>
        </div>
        <div class="modal-body">
            <div class="aviso-modal">
                <strong>CONFIRMAR PAGO CON EL NÚMERO DE PLANILLA EXPEDIDO POR EL OPERADOR</strong>
                Al confirmar, <strong>todas las personas incluidas en este filtro</strong> quedarán marcadas con el número de planilla asignado. Si alguna persona no entró en este pago, cámbiela de número de plano antes de confirmar.
            </div>

            <div class="form-row">
                <div class="form-grupo">
                    <label>Operador</label>
                    <select id="pago-operador" required>
                        <option value="">— Seleccione —</option>
                        @foreach($operadores as $op)
                        <option value="{{ $op->nombre }}">{{ $op->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-grupo">
                    <label>Número de Planilla</label>
                    <input type="text" id="pago-numero" placeholder="Ej: SOI-2026-00123" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-grupo">
                    <label>Valor Pagado</label>
                    <input type="number" id="pago-valor"
                           value="{{ $totalSS }}" min="1" step="100">
                </div>
                <div class="form-grupo">
                    <label>Forma de Pago</label>
                    <select id="pago-forma">
                        <option value="consignacion">Consignación</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="pse">PSE</option>
                        <option value="efectivo">Efectivo</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-grupo">
                    <label>Cuenta Bancaria que Realizó el Pago</label>
                    <select id="pago-banco" required>
                        <option value="">— Seleccione banco —</option>
                        @foreach($bancos as $b)
                        <option value="{{ $b->id }}">{{ $b->banco }} · {{ $b->numero_cuenta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-grupo">
                    <label>Observación</label>
                    <textarea id="pago-obs" placeholder="Observaciones del pago..."></textarea>
                </div>
            </div>

            <div id="pago-resultado" style="display:none;margin-top:.5rem"></div>
        </div>
        <div class="modal-foot">
            <button class="btn-accion btn-cancelar" onclick="cerrarModal('modal-pago')">Cancelar</button>
            <button class="btn-accion btn-pagar" id="btn-confirmar-pago" onclick="ejecutarConfirmarPago()">
                ✅ CONFIRMAR PAGO PLANILLA
            </button>
        </div>
    </div>
</div>

{{-- Toast global --}}
<div class="toast" id="toast-msg"></div>
@endsection

@push('scripts')
<script>
// ── Datos del contexto Blade ──────────────────────────────────────────
const CTX = {
    razonSocialId : {{ $razonSocialId ?? 'null' }},
    nPlanoFiltro  : {{ $nPlanoFiltro  ?? 'null' }},
    mes           : {{ $mes }},
    anio          : {{ $anio }},
    modalidadesIds: {!! json_encode(array_map('intval', $modalidadesIds)) !!},
    totalSS       : {{ $totalSS }},
    csrfToken     : '{{ csrf_token() }}',
    routes: {
        descargar    : '{{ route('admin.planos.descargar') }}',
        nPlanoUpdate : '{{ route('admin.planos.n_plano.update') }}',
        confirmarPago: '{{ route('admin.planos.confirmar_pago') }}',
        apiRazon     : '/admin/planos/api/razon/',
    }
};

// ── Multiselect tipos modalidad ──────────────────────────────────────
function toggleMs() {
    document.getElementById('ms-wrap').classList.toggle('open');
}
document.addEventListener('click', e => {
    if (!e.target.closest('#ms-wrap')) {
        document.getElementById('ms-wrap').classList.remove('open');
    }
});
function updateMsLabel() {
    const checked = document.querySelectorAll('#ms-dropdown input[type=checkbox]:checked');
    document.getElementById('ms-label').textContent =
        checked.length ? checked.length + ' sel.' : 'Todos';
}
function toggleAllMs() {
    const boxes = document.querySelectorAll('#ms-dropdown input[type=checkbox]');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
    updateMsLabel();
}

// ── RS → N_PLANO automático + submit
function onRsChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    const nplano = opt.dataset.nplano || '';
    // Actualizar select de n_plano
    const selNp = document.getElementById('sel-nplano');
    if (selNp && nplano) {
        // Buscar opción coincidente
        let found = false;
        for (let o of selNp.options) {
            if (o.value === nplano) { o.selected = true; found = true; break; }
        }
        if (!found) { selNp.value = ''; }
    }
    // Actualizar modal nplano
    const modalInp = document.getElementById('inp-nplano-modal');
    if (modalInp && nplano) modalInp.value = parseInt(nplano) + 1;
    autoSubmit();
}

// Auto-submit en cambio de cualquier filtro
function autoSubmit() {
    document.getElementById('frm-filtros').submit();
}

// ── Modales ───────────────────────────────────────────────────────────
function abrirModalDescarga() {
    document.getElementById('modal-descarga').classList.add('open');
}
function abrirModalPago() {
    document.getElementById('pago-valor').value = CTX.totalSS;
    document.getElementById('modal-pago').classList.add('open');
}
function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
}
// Cerrar con ESC
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

// ── Descargar archivo ─────────────────────────────────────────────────
function ejecutarDescarga(formato) {
    const params = new URLSearchParams({
        formato,
        razon_social_id: CTX.razonSocialId ?? '',
        mes   : CTX.mes,
        anio  : CTX.anio,
        n_plano: CTX.nPlanoFiltro ?? '',
    });
    window.location.href = CTX.routes.descargar + '?' + params.toString();
}

// ── Guardar N_PLANO ────────────────────────────────────────────────────
async function guardarNPlano() {
    const nPlano = parseInt(document.getElementById('inp-nplano-modal').value);
    if (!nPlano || nPlano < 1) { mostrarToast('Ingrese un N_PLANO válido.','error'); return; }
    if (!CTX.razonSocialId)    { mostrarToast('Seleccione una razón social.','error'); return; }

    try {
        const resp = await fetch(CTX.routes.nPlanoUpdate, {
            method : 'PATCH',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CTX.csrfToken },
            body   : JSON.stringify({ razon_social_id: CTX.razonSocialId, n_plano: nPlano }),
        });
        const data = await resp.json();
        if (data.ok) {
            mostrarToast(data.mensaje, 'success');
            document.getElementById('badge-nplano-val')?.setAttribute('data-val', nPlano);
            if (document.getElementById('badge-nplano-val'))
                document.getElementById('badge-nplano-val').textContent = nPlano;
        } else {
            mostrarToast(data.mensaje || 'Error al guardar.', 'error');
        }
    } catch(e) {
        mostrarToast('Error de conexión.', 'error');
    }
}

// ── Confirmar Pago ────────────────────────────────────────────────────
async function ejecutarConfirmarPago() {
    const operador = document.getElementById('pago-operador').value;
    const numero   = document.getElementById('pago-numero').value.trim();
    const valor    = parseInt(document.getElementById('pago-valor').value);
    const forma    = document.getElementById('pago-forma').value;
    const banco    = document.getElementById('pago-banco').value;
    const obs      = document.getElementById('pago-obs').value.trim();

    if (!operador) { mostrarToast('Seleccione el operador.','error'); return; }
    if (!numero)   { mostrarToast('Ingrese el número de planilla.','error'); return; }
    if (!valor)    { mostrarToast('Ingrese el valor pagado.','error'); return; }
    if (!banco)    { mostrarToast('Seleccione la cuenta bancaria.','error'); return; }
    if (!CTX.razonSocialId) { mostrarToast('Seleccione una razón social.','error'); return; }

    const btn = document.getElementById('btn-confirmar-pago');
    btn.disabled = true;
    btn.textContent = '⏳ Procesando...';

    try {
        const resp = await fetch(CTX.routes.confirmarPago, {
            method : 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CTX.csrfToken },
            body   : JSON.stringify({
                razon_social_id : CTX.razonSocialId,
                mes_plano       : CTX.mes,
                anio_plano      : CTX.anio,
                n_plano         : CTX.nPlanoFiltro,
                tipos_modalidad : CTX.modalidadesIds,
                operador, numero_planilla: numero, valor, forma_pago: forma,
                banco_id: parseInt(banco), observacion: obs,
            }),
        });
        const data = await resp.json();
        const res = document.getElementById('pago-resultado');
        res.style.display = 'block';

        if (data.ok) {
            res.innerHTML = `<div style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:8px;padding:.65rem .9rem;color:#15803d;font-size:.82rem">
                ✅ ${data.mensaje}
            </div>`;
            mostrarToast(data.mensaje, 'success');
            setTimeout(() => location.reload(), 2500);
        } else {
            res.innerHTML = `<div style="background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:.65rem .9rem;color:#b91c1c;font-size:.82rem">
                ❌ ${data.mensaje}
            </div>`;
            btn.disabled = false;
            btn.textContent = '✅ CONFIRMAR PAGO PLANILLA';
        }
    } catch(e) {
        mostrarToast('Error de conexión. Intente de nuevo.','error');
        btn.disabled = false;
        btn.textContent = '✅ CONFIRMAR PAGO PLANILLA';
    }
}

// ── Toast ─────────────────────────────────────────────────────────────
function mostrarToast(msg, tipo='success') {
    const t = document.getElementById('toast-msg');
    t.textContent = msg;
    t.className = `toast ${tipo} show`;
    clearTimeout(t._tmr);
    t._tmr = setTimeout(() => t.classList.remove('show'), 4000);
}
</script>
@endpush
