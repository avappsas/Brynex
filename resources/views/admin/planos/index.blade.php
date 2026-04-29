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

/* ── Planilla icon tooltip ────────────────────────────────────── */
.pla-ico {
    cursor:pointer; font-size:1rem; position:relative;
    display:inline-block; transition:transform .15s;
}
.pla-ico:hover { transform:scale(1.25); }
.pla-ico::after {
    content:attr(data-num);
    position:absolute; bottom:calc(100% + 5px); left:50%;
    transform:translateX(-50%);
    background:#1e293b; color:#fff;
    padding:.2rem .5rem; border-radius:5px;
    font-size:.7rem; white-space:nowrap; font-family:monospace;
    opacity:0; pointer-events:none; transition:opacity .15s; z-index:10;
}
.pla-ico:hover::after { opacity:1; }

/* ── Custom RS Dropdown ────────────────────────────────────────────── */
.rs-wrap { position:relative; }
.rs-btn {
    display:flex; align-items:center; gap:.4rem;
    min-width:280px; padding:.28rem .6rem;
    border:1px solid #cbd5e1; border-radius:7px;
    background:#f8fafc; color:#1e293b;
    font-size:.8rem; cursor:pointer; text-align:left;
    white-space:nowrap; overflow:hidden; transition:border .15s;
}
.rs-btn:hover { border-color:var(--acento); }
.rs-btn-txt { flex:1; overflow:hidden; text-overflow:ellipsis; }
.rs-btn-arr { font-size:.6rem; color:#94a3b8; flex-shrink:0; transition:transform .2s; }
.rs-wrap.open .rs-btn-arr { transform:rotate(180deg); }
.rs-panel {
    position:absolute; top:calc(100% + 5px); left:0; z-index:400;
    background:#fff; border:1px solid #e2e8f0; border-radius:10px;
    box-shadow:0 10px 30px rgba(0,0,0,.15);
    width:440px; max-height:380px;
    flex-direction:column; display:none;
}
.rs-wrap.open .rs-panel { display:flex; }
.rs-search-box { padding:.4rem .5rem; border-bottom:1px solid #f1f5f9; flex-shrink:0; }
.rs-search-box input {
    width:100%; padding:.3rem .65rem; border:1px solid #e2e8f0;
    border-radius:6px; font-size:.8rem; outline:none; background:#f8fafc;
    transition:border .15s;
}
.rs-search-box input:focus { border-color:var(--acento); background:#fff; }
.rs-list { overflow-y:auto; flex:1; padding:.15rem 0; }
.rs-glabel {
    padding:.3rem .85rem .15rem; font-size:.62rem; font-weight:700;
    color:#94a3b8; text-transform:uppercase; letter-spacing:.06em;
    background:#fff; position:sticky; top:0;
}
.rs-row {
    display:grid;
    grid-template-columns:18px 1fr 38px 58px;
    align-items:center; gap:0 6px;
    padding:.3rem .85rem; cursor:pointer;
    font-size:.79rem; color:#334155; transition:background .1s;
}
.rs-row:hover { background:#f1f5f9; }
.rs-row.sel { background:#eff6ff; }
.rs-row .ri { font-size:.75rem; text-align:center; }
.rs-row .ri.g { color:#16a34a; }
.rs-row .ri.s { color:#94a3b8; }
.rs-row .rn { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500; }
.rs-row.sel .rn { color:#1d4ed8; font-weight:700; }
.rs-row .rp { text-align:right; font-size:.71rem; color:#64748b; font-weight:600; white-space:nowrap; }
.rs-row.sel .rp { color:#1d4ed8; }
.rs-row .rc { text-align:right; font-size:.68rem; color:#16a34a; white-space:nowrap; }
.rs-row.sel .rc { color:#1d4ed8; }
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

            {{-- Razon Social — Custom Dropdown --}}
            <div class="filtro-inline">
                <span class="fi-label">RS</span>
                <input type="hidden" name="razon_social_id" id="sel-rs-val" value="{{ $razonSocialId ?? '' }}">
                <div class="rs-wrap" id="rs-wrap">
                    <button type="button" class="rs-btn" onclick="toggleRs()">
                        <span class="rs-btn-txt" id="rs-btn-txt">{{ $rsSeleccionada ? mb_strtoupper($rsSeleccionada->razon_social) : '— Todas —' }}</span>
                        <span class="rs-btn-arr">▼</span>
                    </button>
                    <div class="rs-panel">
                        <div class="rs-search-box">
                            <input type="text" id="rs-search" placeholder="🔍 Buscar..." oninput="filtrarRs(this.value)" autocomplete="off">
                        </div>
                        <div class="rs-list" id="rs-list">
                            <div class="rs-row {{ !$razonSocialId ? 'sel':'' }}" data-lbl="" onclick="selRs('','','— Todas —')">
                                <span class="ri s">—</span>
                                <span class="rn" style="color:#64748b">— Todas —</span>
                                <span class="rp"></span><span class="rc"></span>
                            </div>
                            @php
                                $rsConPlanos = $razonesSociales->filter(fn($r) => isset($cantPorRs[$r->id]) && $cantPorRs[$r->id] > 0);
                                $activas = ['activo','activa','1','si','yes'];
                                $rsSinPlanosActivas = $razonesSociales
                                    ->filter(fn($r) => !isset($cantPorRs[$r->id]) || $cantPorRs[$r->id] == 0)
                                    ->filter(fn($r) => in_array(strtolower($r->estado ?? ''), $activas));
                            @endphp
                            @if($rsConPlanos->count())
                            <div class="rs-glabel">● Con planos — {{ $rsConPlanos->count() }} RS</div>
                            @foreach($rsConPlanos as $rs)
                            @php $cant = $cantPorRs[$rs->id] ?? 0; $nom = mb_strtoupper($rs->razon_social); @endphp
                            <div class="rs-row {{ $razonSocialId == $rs->id ? 'sel':'' }}"
                                 data-lbl="{{ strtolower($rs->razon_social) }}"
                                 onclick="selRs('{{ $rs->id }}','{{ $rs->n_plano }}','{{ addslashes($nom) }}')">
                                <span class="ri g">●</span>
                                <span class="rn">{{ $nom }}</span>
                                <span class="rp">P{{ $rs->n_plano }}</span>
                                <span class="rc">{{ $cant }} p.</span>
                            </div>
                            @endforeach
                            @endif
                            @if($rsSinPlanosActivas->count())
                            <div class="rs-glabel" style="margin-top:.2rem">○ Sin planos — {{ $rsSinPlanosActivas->count() }} RS</div>
                            @foreach($rsSinPlanosActivas as $rs)
                            @php $nom = mb_strtoupper($rs->razon_social); @endphp
                            <div class="rs-row {{ $razonSocialId == $rs->id ? 'sel':'' }}"
                                 data-lbl="{{ strtolower($rs->razon_social) }}"
                                 onclick="selRs('{{ $rs->id }}','{{ $rs->n_plano }}','{{ addslashes($nom) }}')">
                                <span class="ri s">○</span>
                                <span class="rn" style="color:#64748b">{{ $nom }}</span>
                                <span class="rp">P{{ $rs->n_plano }}</span>
                                <span class="rc"></span>
                            </div>
                            @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="filtro-sep"></div>


            {{-- N° Plano --}}
            <div class="filtro-inline">
                <span class="fi-label">Plano</span>
                <select name="n_plano" id="sel-nplano" onchange="autoSubmit()" style="width:80px">
                    <option value="">Todos</option>
                    @php $maxPlano = max(12, (int)($nPlanoFiltro ?? 0), (int)($rsSeleccionada->n_plano ?? 0)); @endphp
                    @for($np = 1; $np <= $maxPlano; $np++)
                    <option value="{{ $np }}"
                        {{ (string)$nPlanoFiltro === (string)$np ? 'selected' : '' }}>
                        P{{ $np }}{{ ($rsSeleccionada && $rsSeleccionada->n_plano == $np) ? ' ⭐' : '' }}
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
                        @php
                            // Si hay RS seleccionada mostramos solo las modalidades presentes en el periodo+RS;
                            // si no hay RS, mostramos todas las activas.
                            $modalidadesParaFiltro = $razonSocialId && $modalidadesDispon->count()
                                ? $modalidadesDispon
                                : $tiposModalidad;
                        @endphp
                        @foreach($modalidadesParaFiltro as $tm)
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
                @if(!$esIndependiente)
                <button type="button" class="btn-accion btn-pagar" onclick="abrirModalPago()"
                    @if($planos->count()==0) disabled style="opacity:.4;cursor:not-allowed" @endif>
                    ✅ Confirmar Pago
                </button>
                @endif
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
            <th title="Acciones">⋯</th>
            @if($esIndependiente)<th>Operador</th><th>Pago</th>@endif
        </tr>
    </thead>
    <tbody>
        @php $i = 1; @endphp
        @foreach($planos as $p)
        @php
            $tipoClass = match(strtoupper(substr($p->tipo_modal_nombre ?? '', 0, 1))) {
                'E' => 'e', 'I' => 'i', 'K' => 'k', 'T' => 'tp', default => ''
            };
            $clienteNombre = trim(($p->primer_nombre ?? '').' '.($p->primer_ape ?? ''));
        @endphp
        <tr id="fila-plano-{{ $p->id }}">
            <td style="color:#94a3b8">{{ $i++ }}</td>
            <td>
                @if($p->contrato_id ?? null)
                <a href="{{ url('/admin/contratos/'.$p->contrato_id.'/edit') }}" style="text-decoration:none" title="Ver contrato">
                    <span class="chip-tipo {{ $tipoClass }}">{{ $p->tipo_modal_nombre ?? $p->tipo_p }}</span>
                </a>
                @else
                <span class="chip-tipo {{ $tipoClass }}">{{ $p->tipo_modal_nombre ?? $p->tipo_p }}</span>
                @endif
            </td>
            <td>{{ $p->no_identifi }}</td>
            <td class="td-nombre" title="{{ $p->nombre_completo ?? $clienteNombre }}">
                <a href="{{ ($p->cliente_id ?? null) ? url('/admin/clientes/'.$p->cliente_id.'/edit') : '#' }}"
                   style="color:#1d4ed8;text-decoration:none;font-weight:600" title="Ver cliente">
                    {{ $p->primer_nombre }} {{ $p->primer_ape }}
                </a>
            </td>
            <td>{{ $p->fecha_ing ? sqldate($p->fecha_ing)->format('d-') . strtolower(sqldate($p->fecha_ing)->locale('es')->isoFormat('MMM')) : '—' }}</td>
            <td>{{ $p->fecha_ret ? sqldate($p->fecha_ret)->format('d-') . strtolower(sqldate($p->fecha_ret)->locale('es')->isoFormat('MMM')) : '—' }}</td>
            <td>{{ $p->num_dias }}</td>
            <td title="{{ $p->nombre_eps ?? $p->cod_eps }}" style="font-size:.72rem;white-space:nowrap">
                {{ $p->nombre_eps ? \Illuminate\Support\Str::limit($p->nombre_eps, 9, '…') : ($p->cod_eps ?? '—') }}
            </td>
            <td>{{ number_format($p->v_eps ?? 0,0,',','.') }}</td>
            <td title="{{ $p->nombre_arl ?? $p->cod_arl }}" style="font-size:.72rem;white-space:nowrap">
                {{ $p->nombre_arl ? \Illuminate\Support\Str::limit($p->nombre_arl, 9, '…') : ($p->cod_arl ?? '—') }}
            </td>
            <td>{{ number_format($p->v_arl ?? 0,0,',','.') }}</td>
            <td title="{{ $p->nombre_caja ?? $p->cod_caja }}" style="font-size:.72rem;white-space:nowrap">
                {{ $p->nombre_caja ? \Illuminate\Support\Str::limit($p->nombre_caja, 9, '…') : ($p->cod_caja ? \Illuminate\Support\Str::limit($p->cod_caja,9,'…') : '—') }}
            </td>
            <td>{{ number_format($p->v_caja ?? 0,0,',','.') }}</td>
            <td title="{{ $p->nombre_afp ?? $p->cod_afp }}" style="font-size:.72rem;white-space:nowrap">
                {{ $p->nombre_afp ? \Illuminate\Support\Str::limit($p->nombre_afp, 9, '…') : ($p->cod_afp ?? '—') }}
            </td>
            <td>{{ number_format($p->v_afp ?? 0,0,',','.') }}</td>
            <td style="font-weight:700;color:var(--azul-vivo)">
                {{ number_format($p->total_ss ?? 0,0,',','.') }}
            </td>
            <td id="planilla-{{ $p->id }}" style="text-align:center">
                @if($p->numero_planilla)
                <span class="pla-ico" data-num="{{ $p->numero_planilla }}"
                      onclick="copiarPlanilla(this)" title="">✅</span>
                @else
                <span style="color:#cbd5e1">—</span>
                @endif
            </td>
            <td class="td-empresa" title="{{ $p->nombre_empresa }}">{{ $p->nombre_empresa ? \Illuminate\Support\Str::limit($p->nombre_empresa,14,'…') : '—' }}</td>
            <td class="td-envio" title="{{ $p->envio_planilla }}">{{ $p->envio_planilla ? 'Sí' : 'No' }}</td>
            <td style="text-align:center">
                <button type="button"
                    onclick="abrirModalMover({{ $p->id }}, {{ $p->n_plano }})"
                    style="padding:.18rem .45rem;border-radius:5px;font-size:.75rem;border:1px solid #e2e8f0;background:#f8fafc;color:#475569;cursor:pointer;line-height:1"
                    title="Mover a otro plano">🔄</button>
            </td>
            @if($esIndependiente)
            {{-- Columna Operador (texto informativo) --}}
            <td style="font-size:.72rem;color:#374151;white-space:nowrap">
                @if($p->operador_cliente_nombre ?? null)
                <span style="display:inline-flex;align-items:center;gap:.2rem;background:#f0f9ff;color:#0284c7;border:1px solid #bae6fd;border-radius:6px;padding:.1rem .4rem;font-size:.67rem;font-weight:600">
                    🏦 {{ $p->operador_cliente_nombre }}
                </span>
                @else
                <span style="color:#cbd5e1;font-size:.67rem">— sin asignar—</span>
                @endif
            </td>
            {{-- Columna Acción: Pagar / Pagado --}}
            <td id="accion-{{ $p->id }}">
                @if($p->numero_planilla)
                <span style="display:inline-flex;align-items:center;gap:.25rem;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;border-radius:20px;padding:.15rem .55rem;font-size:.67rem;font-weight:700;font-family:monospace;white-space:nowrap"
                      title="Planilla: {{ $p->numero_planilla }}">✅ {{ $p->numero_planilla }}</span>
                @else
                <button type="button"
                    onclick="abrirModalPagoIndividual({{ $p->id }}, {{ $p->total_ss ?? 0 }}, '{{ addslashes($clienteNombre) }}', {{ $p->operador_cliente_id ?? 'null' }})"
                    style="padding:.2rem .55rem;border-radius:6px;font-size:.68rem;font-weight:700;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;cursor:pointer;white-space:nowrap;transition:all .15s"
                    onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                    💳 Pagar
                </button>
                @endif
            </td>
            @endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" style="text-align:right">TOTALES &rarr;</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_eps'),0,',','.') }}</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_arl'),0,',','.') }}</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_caja'),0,',','.') }}</td>
            <td></td>
            <td>{{ number_format($planos->sum('v_afp'),0,',','.') }}</td>
            <td></td>{{-- TOTAL SS: se muestra en el resumen inferior --}}
            <td colspan="4"></td>
            @if($esIndependiente)
            <td></td>
            <td></td>
            @endif
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
            <h3>📥 Descargar Plano Excel</h3>
            <button class="modal-close" onclick="cerrarModal('modal-descarga')">✕</button>
        </div>
        <div class="modal-body">

            {{-- Operadores activos del aliado --}}
            @if($operadores->count())
            <div style="margin-bottom:1rem">
                <div style="font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.4rem">🏦 Operadores activos del aliado</div>
                <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                    @foreach($operadores as $op)
                    <span style="display:inline-flex;align-items:center;gap:.3rem;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:20px;padding:.2rem .65rem;font-size:.75rem;font-weight:600">
                        🏦 {{ $op->nombre }}
                    </span>
                    @endforeach
                </div>
                <div style="font-size:.72rem;color:#94a3b8;margin-top:.35rem">Descargue el formato Excel correspondiente al operador con el que realizará el pago.</div>
            </div>
            @endif

            {{-- Botón único: Excel --}}
            <button class="btn-accion btn-pagar" style="width:100%;justify-content:center;padding:.55rem"
                    onclick="ejecutarDescarga('xlsx')">
                📊 Descargar Excel
            </button>

            <div style="border-top:1px solid #f1f5f9;padding-top:1rem;margin-top:1rem">
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
                    {{-- N_PLANO actual (solo lectura) --}}
                    <div class="form-grupo" style="max-width:110px">
                        <label>N_PLANO actual</label>
                        <input type="number" value="{{ $nPlanoActual ?? 0 }}" readonly
                               style="background:#f1f5f9;color:#64748b;font-weight:700;text-align:center">
                    </div>
                    {{-- N_PLANO nuevo --}}
                    <div class="form-grupo" style="max-width:110px">
                        <label>N_PLANO nuevo</label>
                        <div class="nplano-wrap">
                            <input type="number" id="inp-nplano-modal" min="1"
                                   value="{{ ($nPlanoActual ?? 0) + 1 }}" style="width:70px;text-align:center">
                            <button type="button" class="btn-plus"
                                    onclick="document.getElementById('inp-nplano-modal').stepUp()">+</button>
                        </div>
                    </div>
                </div>
                <button class="btn-accion btn-pagar" style="width:100%;justify-content:center" onclick="guardarNPlano()"
                        @if(!$rsSeleccionada) disabled @endif>
                    💾 Guardar N_PLANO
                </button>
            </div>
        </div>
    </div>
</div>
{{-- ══════════════════════════════════════════════════════════════════════
     MODAL: Mover registro a otro n_plano
════════════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modal-mover">
    <div class="modal-box" style="max-width:360px">
        <div class="modal-head">
            <h3>🔄 Mover a otro Plano</h3>
            <button class="modal-close" onclick="cerrarModal('modal-mover')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="mover-plano-id">
            <p style="font-size:.82rem;color:#64748b;margin-bottom:.75rem">
                Cambia el número de plano de este registro. El registro se moverá al nuevo plano al guardar.
            </p>
            <div class="form-row">
                <div class="form-grupo" style="max-width:110px">
                    <label>Plano actual</label>
                    <input type="number" id="mover-plano-actual" readonly
                           style="background:#f1f5f9;color:#64748b;font-weight:700;text-align:center">
                </div>
                <div class="form-grupo" style="max-width:110px">
                    <label>Nuevo plano</label>
                    <div class="nplano-wrap">
                        <input type="number" id="mover-plano-nuevo" min="1"
                               style="width:70px;text-align:center">
                        <button type="button" class="btn-plus"
                                onclick="document.getElementById('mover-plano-nuevo').stepUp()">+</button>
                    </div>
                </div>
            </div>
            <button class="btn-accion btn-pagar" style="width:100%;justify-content:center;margin-top:.5rem"
                    onclick="guardarMover()">
                💾 Mover Plano
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL 2: Confirmar Pago al Operador
═══════════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="modal-pago">
    <div class="modal-box lg">
        <div class="modal-head">
            <h3 id="modal-pago-titulo">✅ Confirmar Pago de Planilla al Operador</h3>
            <button class="modal-close" onclick="cerrarModal('modal-pago')">✕</button>
        </div>
        <div class="modal-body">
            <div class="aviso-modal" id="modal-pago-aviso">
                <strong>CONFIRMAR PAGO CON EL NÚMERO DE PLANILLA EXPEDIDO POR EL OPERADOR</strong>
                Al confirmar, <strong>todas las personas incluidas en este filtro</strong> quedarán marcadas con el número de planilla asignado. Si alguna persona no entró en este pago, cámbiela de número de plano antes de confirmar.
            </div>

            <div class="form-row">
                <div class="form-grupo">
                    <label>Operador</label>
                    <select id="pago-operador" required>
                        <option value="">— Seleccione —</option>
                        @foreach($operadores as $op)
                        <option value="{{ $op->nombre }}" data-op-id="{{ $op->id }}">{{ $op->nombre }}</option>
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
                    <label>Valor Pagado <span style="color:#ef4444">*</span></label>
                    <input type="number" id="pago-valor"
                           value="{{ $totalSS }}" min="1" step="100">
                </div>
                <div class="form-grupo">
                    <label>Forma de Pago <span style="color:#ef4444">*</span></label>
                    <select id="pago-forma" onchange="toggleBanco()">
                        <option value="">— Seleccione —</option>
                        <option value="transferencia">🏦 Banco (transferencia/consignación)</option>
                        <option value="efectivo">💵 Efectivo</option>
                    </select>
                </div>
            </div>

            <div class="form-row" id="fila-banco">
                <div class="form-grupo">
                    <label>Cuenta Bancaria que Realizó el Pago <span style="color:#ef4444">*</span></label>
                    <select id="pago-banco">
                        <option value="">— Seleccione banco —</option>
                        @foreach($bancos as $b)
                        <option value="{{ $b->id }}">{{ $b->nombre }}</option>
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

            {{-- Soporte del pago (imagen o PDF) --}}
            <div class="form-row">
                <div class="form-grupo">
                    <label>📎 Soporte del Pago <span style="font-weight:400;color:#94a3b8">(imagen o PDF, máx. 5 MB)</span></label>
                    <div id="soporte-drop-area" style="border:2px dashed #cbd5e1;border-radius:10px;padding:.85rem 1rem;background:#f8fafc;cursor:pointer;transition:border-color .15s;text-align:center;color:#64748b;font-size:.8rem"
                         onclick="document.getElementById('pago-soporte').click()"
                         ondragover="event.preventDefault();this.style.borderColor='var(--acento)'"
                         ondragleave="this.style.borderColor='#cbd5e1'"
                         ondrop="handleSoporteDrop(event)">
                        <span id="soporte-label">🖼️ Haz clic o arrastra aquí el comprobante de pago</span>
                    </div>
                    <input type="file" id="pago-soporte" accept="image/*,.pdf" style="display:none"
                           onchange="previewSoporte(this.files[0])">
                    <div id="soporte-preview" style="display:none;margin-top:.5rem"></div>
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
    esIndependiente: {{ $esIndependiente ? 'true' : 'false' }},
    csrfToken     : '{{ csrf_token() }}',
    routes: {
        descargar    : '{{ route('admin.planos.descargar') }}',
        nPlanoUpdate : '{{ route('admin.planos.n_plano.update') }}',
        confirmarPago: '{{ route('admin.planos.confirmar_pago') }}',
        apiRazon     : '/admin/planos/api/razon/',
    }
};
let _planoIdActual    = null;
let _operadorClienteId = null;

// ── Multiselect tipos modalidad ──────────────────────────────────────
function toggleMs() {
    document.getElementById('ms-wrap').classList.toggle('open');
}
document.addEventListener('click', e => {
    if (!e.target.closest('#ms-wrap'))  document.getElementById('ms-wrap')?.classList.remove('open');
    if (!e.target.closest('#rs-wrap'))  document.getElementById('rs-wrap')?.classList.remove('open');
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

// ── RS Custom Dropdown ────────────────────────────────────────────────
function toggleRs() {
    const w = document.getElementById('rs-wrap');
    w.classList.toggle('open');
    if (w.classList.contains('open')) setTimeout(() => document.getElementById('rs-search')?.focus(), 40);
}
function selRs(val, nplano, label) {
    document.getElementById('sel-rs-val').value = val;
    document.getElementById('rs-btn-txt').textContent = label || '— Todas —';
    document.getElementById('rs-wrap').classList.remove('open');
    document.querySelectorAll('#rs-list .rs-row').forEach(r => r.classList.remove('sel'));
    const hit = [...document.querySelectorAll('#rs-list .rs-row')].find(r => r.getAttribute('onclick')?.includes("'" + val + "'"));
    if (hit) hit.classList.add('sel'); else document.querySelector('#rs-list .rs-row')?.classList.add('sel');
    const selNp = document.getElementById('sel-nplano');
    if (selNp && nplano) {
        let found = false;
        for (let o of selNp.options) { if (o.value === String(nplano)) { o.selected = true; found = true; break; } }
        if (!found) selNp.value = '';
    }
    const mi = document.getElementById('inp-nplano-modal');
    if (mi && nplano) mi.value = parseInt(nplano) + 1;
    autoSubmit();
}
function filtrarRs(q) {
    q = q.trim().toLowerCase();
    let grp = null, grpVis = false;
    document.querySelectorAll('#rs-list .rs-row, #rs-list .rs-glabel').forEach(el => {
        if (el.classList.contains('rs-glabel')) {
            if (grp) grp.style.display = grpVis ? '' : 'none';
            grp = el; grpVis = false;
        } else {
            const show = !q || (el.dataset.lbl || '').includes(q);
            el.style.display = show ? '' : 'none';
            if (show) grpVis = true;
        }
    });
    if (grp) grp.style.display = grpVis ? '' : 'none';
}

// Auto-submit en cambio de cualquier filtro
function autoSubmit() {
    document.getElementById('frm-filtros').submit();
}

// ── Modales ───────────────────────────────────────────────────────────
function abrirModalDescarga() {
    document.getElementById('modal-descarga').classList.add('open');
}
function resetModalPago() {
    document.getElementById('pago-numero').value  = '';
    document.getElementById('pago-obs').value     = '';
    document.getElementById('pago-banco').value   = '';
    document.getElementById('pago-forma').value   = '';
    document.getElementById('pago-operador').value = '';
    document.getElementById('pago-resultado').style.display = 'none';
    limpiarSoporte();
    toggleBanco();
}
function toggleBanco() {
    const forma  = document.getElementById('pago-forma').value;
    const filaBanco = document.getElementById('fila-banco');
    if (forma === 'efectivo') {
        filaBanco.style.display = 'none';
        document.getElementById('pago-banco').value = ''; // limpiar selección
    } else {
        filaBanco.style.display = '';
    }
}
function abrirModalPago() {
    _planoIdActual    = null;
    _operadorClienteId = null;
    document.getElementById('modal-pago-titulo').textContent = '✅ Confirmar Pago de Planilla al Operador';
    document.getElementById('modal-pago-aviso').style.display = '';
    document.getElementById('pago-valor').value = CTX.totalSS;
    resetModalPago();
    const btn = document.getElementById('btn-confirmar-pago');
    btn.disabled = false; btn.textContent = '✅ CONFIRMAR PAGO PLANILLA';
    document.getElementById('modal-pago').classList.add('open');
}
function abrirModalPagoIndividual(planoId, totalSS, clienteNombre, operadorId) {
    _planoIdActual    = planoId;
    _operadorClienteId = operadorId;
    document.getElementById('modal-pago-titulo').textContent = '💳 Pagar: ' + clienteNombre;
    document.getElementById('modal-pago-aviso').style.display = 'none';
    document.getElementById('pago-valor').value = totalSS;
    resetModalPago();
    const btn = document.getElementById('btn-confirmar-pago');
    btn.disabled = false; btn.textContent = '✅ CONFIRMAR PAGO';
    // Pre-seleccionar operador del cliente
    const sel = document.getElementById('pago-operador');
    if (operadorId) {
        let found = false;
        for (let opt of sel.options) {
            if (parseInt(opt.dataset.opId) === operadorId) { opt.selected = true; found = true; break; }
        }
        if (!found) sel.value = '';
    } else {
        sel.value = '';
    }
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
            // Actualizar badge visible en la cabecera
            if (document.getElementById('badge-nplano-val'))
                document.getElementById('badge-nplano-val').textContent = nPlano;
            // Cerrar el modal al guardar exitosamente
            cerrarModal('modal-descarga');
        } else {
            mostrarToast(data.mensaje || 'Error al guardar.', 'error');
        }
    } catch(e) {
        mostrarToast('Error de conexión.', 'error');
    }
}

// ── Mover registro a otro n_plano ─────────────────────────────────────────
function abrirModalMover(id, nPlanoActual) {
    document.getElementById('mover-plano-id').value      = id;
    document.getElementById('mover-plano-actual').value  = nPlanoActual;
    document.getElementById('mover-plano-nuevo').value   = nPlanoActual + 1;
    document.getElementById('modal-mover').classList.add('open');
}
async function guardarMover() {
    const id     = document.getElementById('mover-plano-id').value;
    const nPlano = parseInt(document.getElementById('mover-plano-nuevo').value);
    if (!nPlano || nPlano < 1) { mostrarToast('N_PLANO inválido.', 'error'); return; }
    try {
        const resp = await fetch(`/admin/planos/${id}/mover`, {
            method : 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CTX.csrfToken },
            body   : JSON.stringify({ n_plano: nPlano }),
        });
        const data = await resp.json();
        if (data.ok) {
            mostrarToast(data.mensaje, 'success');
            cerrarModal('modal-mover');
            setTimeout(() => location.reload(), 700);
        } else {
            mostrarToast(data.mensaje || 'Error al mover.', 'error');
        }
    } catch(e) {
        mostrarToast('Error de conexión.', 'error');
    }
}

// ── Copiar número de planilla al portapapeles ────────────────────────
function copiarPlanilla(el) {
    const num = el.dataset.num;
    navigator.clipboard.writeText(num)
        .then(() => mostrarToast('📋 Planilla ' + num + ' copiada.', 'success'))
        .catch(() => {
            // Fallback para navegadores sin clipboard API
            const ta = document.createElement('textarea');
            ta.value = num; document.body.appendChild(ta);
            ta.select(); document.execCommand('copy');
            document.body.removeChild(ta);
            mostrarToast('📋 Planilla ' + num + ' copiada.', 'success');
        });
}


function previewSoporte(file) {
    if (!file) return;
    const label   = document.getElementById('soporte-label');
    const preview = document.getElementById('soporte-preview');
    const drop    = document.getElementById('soporte-drop-area');
    label.textContent = '✅ ' + file.name;
    drop.style.borderColor = 'var(--verde)';
    drop.style.background  = '#f0fdf4';

    if (file.type === 'application/pdf') {
        preview.innerHTML = `<div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:.55rem .9rem;font-size:.78rem;color:#92400e;display:flex;align-items:center;gap:.5rem">📄 <strong>${file.name}</strong> — PDF adjunto (${(file.size/1024).toFixed(0)} KB)</div>`;
    } else {
        const url = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${url}" alt="preview soporte" style="max-height:140px;border-radius:8px;border:1px solid #e2e8f0;object-fit:contain;display:block">`;
    }
    preview.style.display = 'block';
}
function handleSoporteDrop(e) {
    e.preventDefault();
    document.getElementById('soporte-drop-area').style.borderColor = '#cbd5e1';
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('pago-soporte').files = e.dataTransfer.files;
        previewSoporte(file);
    }
}
function limpiarSoporte() {
    document.getElementById('pago-soporte').value = '';
    document.getElementById('soporte-label').textContent = '🖼️ Haz clic o arrastra aquí el comprobante de pago';
    document.getElementById('soporte-preview').style.display = 'none';
    document.getElementById('soporte-preview').innerHTML = '';
    const drop = document.getElementById('soporte-drop-area');
    drop.style.borderColor = '#cbd5e1';
    drop.style.background  = '#f8fafc';
}

// ── Confirmar Pago ────────────────────────────────────────────────────
async function ejecutarConfirmarPago() {
    const operador = document.getElementById('pago-operador').value;
    const numero   = document.getElementById('pago-numero').value.trim();
    const valor    = parseInt(document.getElementById('pago-valor').value);
    const forma    = document.getElementById('pago-forma').value;
    const banco    = document.getElementById('pago-banco').value;
    const obs      = document.getElementById('pago-obs').value.trim();
    const soporteInput = document.getElementById('pago-soporte');
    const soporteFile  = soporteInput.files[0] || null;
    const esEfectivo   = forma === 'efectivo';

    // Validaciones obligatorias
    if (!operador) { resaltarError('pago-operador', 'Seleccione el operador.'); return; }
    if (!numero)   { resaltarError('pago-numero',   'Ingrese el número de planilla.'); return; }
    if (!valor || valor < 1) { resaltarError('pago-valor', 'Ingrese un valor pagado válido.'); return; }
    if (!forma)    { resaltarError('pago-forma',    'Seleccione la forma de pago.'); return; }
    if (!esEfectivo && !banco) { resaltarError('pago-banco', 'Seleccione la cuenta bancaria.'); return; }
    if (!_planoIdActual && !CTX.razonSocialId) { mostrarToast('Seleccione una razón social.','error'); return; }

    const btn = document.getElementById('btn-confirmar-pago');
    btn.disabled = true;
    btn.textContent = '⏳ Procesando...';

    // Usar FormData para soportar el archivo adjunto (multipart/form-data)
    const fd = new FormData();
    fd.append('_token', CTX.csrfToken);
    fd.append('operador', operador);
    fd.append('numero_planilla', numero);
    fd.append('valor', valor);
    fd.append('forma_pago', forma);
    if (!esEfectivo && banco) fd.append('banco_id', banco);
    fd.append('observacion', obs);
    if (soporteFile) fd.append('soporte', soporteFile);

    if (_planoIdActual) {
        fd.append('plano_id', _planoIdActual);
        // En modo individual se requiere también razon_social_id, mes, anio, n_plano (el controlador los necesita si no hay plano_id masivo)
        // El controlador en modo individual no los usa, pero los valida como required
        // → usamos valores del contexto
        fd.append('razon_social_id', CTX.razonSocialId);
        fd.append('mes_plano', CTX.mes);
        fd.append('anio_plano', CTX.anio);
        fd.append('n_plano', CTX.nPlanoFiltro ?? 1);
    } else {
        fd.append('razon_social_id', CTX.razonSocialId);
        fd.append('mes_plano', CTX.mes);
        fd.append('anio_plano', CTX.anio);
        fd.append('n_plano', CTX.nPlanoFiltro ?? 1);
        CTX.modalidadesIds.forEach(id => fd.append('tipos_modalidad[]', id));
    }

    try {
        const resp = await fetch(CTX.routes.confirmarPago, {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CTX.csrfToken }, // SIN Content-Type: el browser lo pone con boundary
            body   : fd,
        });
        const data = await resp.json();
        const res = document.getElementById('pago-resultado');
        res.style.display = 'block';

        if (data.ok) {
            let soporteHtml = '';
            if (data.soporte_url) {
                soporteHtml = ` <a href="${data.soporte_url}" target="_blank" style="font-size:.75rem;color:#1d4ed8;text-decoration:underline">Ver soporte 📎</a>`;
            }
            res.innerHTML = `<div style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:8px;padding:.65rem .9rem;color:#15803d;font-size:.82rem">✅ ${data.mensaje}${soporteHtml}</div>`;
            mostrarToast(data.mensaje, 'success');
            if (_planoIdActual) {
                // Modo individual: actualizar visualmente la fila sin recargar
                const chip = `<span style="display:inline-flex;align-items:center;gap:.25rem;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;border-radius:20px;padding:.15rem .55rem;font-size:.67rem;font-weight:700;font-family:monospace;white-space:nowrap" title="Planilla: ${numero}">✅ ${numero}</span>`;
                const tdPlanilla = document.getElementById('planilla-' + _planoIdActual);
                const tdAccion   = document.getElementById('accion-'   + _planoIdActual);
                if (tdPlanilla) tdPlanilla.innerHTML = chip;
                if (tdAccion)   tdAccion.innerHTML   = chip;
                setTimeout(() => cerrarModal('modal-pago'), 1800);
            } else {
                setTimeout(() => location.reload(), 2500);
            }
        } else {
            res.innerHTML = `<div style="background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:.65rem .9rem;color:#b91c1c;font-size:.82rem">❌ ${data.mensaje}</div>`;
            btn.disabled = false;
            btn.textContent = _planoIdActual ? '✅ CONFIRMAR PAGO' : '✅ CONFIRMAR PAGO PLANILLA';
        }
    } catch(e) {
        mostrarToast('Error de conexión. Intente de nuevo.','error');
        btn.disabled = false;
        btn.textContent = _planoIdActual ? '✅ CONFIRMAR PAGO' : '✅ CONFIRMAR PAGO PLANILLA';
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
// ── Resaltar campo con error ──────────────────────────────────────────
function resaltarError(fieldId, msg) {
    const el = document.getElementById(fieldId);
    if (el) {
        el.style.borderColor = '#ef4444';
        el.style.boxShadow   = '0 0 0 3px rgba(239,68,68,.18)';
        const limpiar = () => {
            el.style.borderColor = '';
            el.style.boxShadow   = '';
            el.removeEventListener('change', limpiar);
            el.removeEventListener('input',  limpiar);
        };
        el.addEventListener('change', limpiar);
        el.addEventListener('input',  limpiar);
        el.focus();
    }
    mostrarToast(msg, 'error');
}
</script>
@endpush
