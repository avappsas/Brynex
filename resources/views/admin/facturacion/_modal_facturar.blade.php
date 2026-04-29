{{--
    ╔══════════════════════════════════════════════════════════════════╗
    ║  PARTIAL: _modal_facturar.blade.php  —  BryNex                  ║
    ║  Modal UNIFICADO de Facturación — layout 2 columnas             ║
    ║                                                                  ║
    ║  Variables via @include:                                         ║
    ║    $bancos   → colección BancoCuenta::activas() (requerida)     ║
    ║    $mfMes    → mes por defecto (opcional)                        ║
    ║    $mfAnio   → año por defecto (opcional)                        ║
    ╚══════════════════════════════════════════════════════════════════╝
--}}
@php
    $mfBancos = $bancos ?? collect();
    $mfMesD   = $mfMes  ?? now()->month;
    $mfAnioD  = $mfAnio ?? now()->year;
    $meses_nombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
@endphp

<style>
/* ════════════════════════════════════════════════════════════════
   Modal Unificado de Facturación — BryNex  (diseño 2 columnas)
════════════════════════════════════════════════════════════════ */
#mf-overlay {
    position: fixed; inset: 0;
    background: rgba(10, 10, 20, .65);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: flex; align-items: center; justify-content: center;
    padding: .75rem;
}
#mf-box {
    background: #ffffff;
    border-radius: 18px;
    width: min(920px, 98vw);
    max-height: 96vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 32px 100px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.06);
}

/* ── HEADER ── */
#mf-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
    padding: .8rem 1.2rem .7rem;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
#mf-header-left { display: flex; align-items: center; gap: .65rem; }
#mf-header-icon {
    width: 34px; height: 34px; border-radius: 9px;
    background: rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
}
#mf-header-text h2 {
    font-size: .92rem; font-weight: 800; color: #fff; margin: 0; line-height: 1.2;
}
#mf-header-text p {
    font-size: .65rem; color: rgba(255,255,255,.55); margin: 0;
}
#mf-close-btn {
    width: 28px; height: 28px; border-radius: 7px; border: none; cursor: pointer;
    background: rgba(255,255,255,.1); color: rgba(255,255,255,.7);
    font-size: .95rem; display: flex; align-items: center; justify-content: center;
    transition: background .18s;
}
#mf-close-btn:hover { background: rgba(255,255,255,.2); color: #fff; }

/* ── CONTROLES SUPERIORES (tipo/estado/mes/año) ── */
#mf-controls {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: .6rem 1.2rem;
    display: flex; align-items: center; gap: .7rem; flex-wrap: wrap;
    flex-shrink: 0;
}
.mf-ctrl-group { display: flex; flex-direction: column; gap: .12rem; min-width: 0; }
.mf-ctrl-label {
    font-size: .57rem; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: .05em;
}
.mf-ctrl-sel {
    padding: .3rem .55rem; border: 1.5px solid #e2e8f0;
    border-radius: 7px; font-size: .78rem; background: #fff; cursor: pointer;
    font-family: inherit; color: #0f172a; outline: none; transition: border-color .15s;
    min-width: 90px;
}
.mf-ctrl-sel:focus { border-color: #3b82f6; }

/* ── ALERTAS ── */
#mf-alerts { flex-shrink: 0; }
.mf-alert {
    padding: .4rem 1.2rem; font-size: .73rem; font-weight: 700;
    display: flex; align-items: center; gap: .45rem; border-bottom: 1px solid transparent;
}
.mf-alert-warn  { background: #fefce8; color: #78350f; border-color: #fde68a; }
.mf-alert-purple{ background: #faf5ff; color: #6d28d9; border-color: #e9d5ff; }
.mf-alert-blue  { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
/* saldos dentro de col-pagos */
#mf-saldos-panel {
    display: none; flex-direction: column; gap: .3rem;
    border-radius: 8px; overflow: hidden;
}
.mf-badge-favor    { display:flex;align-items:center;gap:.4rem;background: #dcfce7; color: #15803d; padding: .3rem .7rem; border-radius: 7px; font-size: .72rem; font-weight: 700; }
.mf-badge-pendiente{ display:flex;align-items:center;gap:.4rem;background: #fee2e2; color: #dc2626; padding: .3rem .7rem; border-radius: 7px; font-size: .72rem; font-weight: 700; }

/* ── OPCIONES INDEPENDIENTE ── */
#mf-indep-opts {
    display: none;
    padding: .4rem 1.2rem; background: #faf5ff; border-bottom: 2px solid #e9d5ff;
    flex-shrink: 0;
}
.mf-radio-row { display: flex; gap: .9rem; margin-top: .22rem; }
.mf-radio-lbl {
    display: flex; align-items: center; gap: .35rem;
    font-size: .78rem; font-weight: 700; color: #6d28d9; cursor: pointer;
}
.mf-radio-lbl input { accent-color: #7c3aed; }

/* ── BODY 2 COLUMNAS ── */
#mf-body {
    display: grid;
    grid-template-columns: 1.5fr 3.7fr;
    gap: 0;
    overflow: hidden;
    flex: 1;
    min-height: 0;
}

/* ── COLUMNA IZQUIERDA: detalle a cobrar ── */
#mf-col-desglose {
    padding: .9rem 1rem .9rem 1.2rem;
    overflow-y: auto;
    border-right: 1px solid #f1f5f9;
    background: #fff;
}
.mf-col-title {
    font-size: .6rem; font-weight: 800; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .08em;
    margin-bottom: .6rem; padding-bottom: .3rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: .4rem; justify-content: space-between;
}
/* Tabla SS */
.mf-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: .25rem 0; border-bottom: 1px solid #f8fafc;
}
.mf-detail-row:last-child { border-bottom: none; }
.mf-detail-lbl { font-size: .75rem; color: #475569; font-weight: 600; display: flex; align-items: center; gap: .3rem; }
.mf-detail-val { font-size: .76rem; font-weight: 800; color: #1e293b; font-family: 'JetBrains Mono', monospace, monospace; }
.mf-divider { border: none; border-top: 1px dashed #e2e8f0; margin: .3rem 0; }
/* SS total highlighted */
.mf-ss-total .mf-detail-lbl { font-size: .8rem; font-weight: 800; color: #1d4ed8; }
.mf-ss-total .mf-detail-val { font-size: .84rem; color: #1d4ed8; }
/* Total box */
.mf-total-box {
    margin-top: .55rem;
    background: linear-gradient(135deg, #0f172a, #1e3a5f);
    border-radius: 10px;
    padding: .55rem .8rem;
    display: flex; justify-content: space-between; align-items: center;
}
.mf-total-label { font-size: .72rem; font-weight: 700; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: .04em; }
.mf-total-val   { font-size: 1.15rem; font-weight: 900; color: #fff; font-family: monospace; }
/* Inputs editables en desglose */
.mf-edit-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: .2rem 0;
}
.mf-edit-lbl { font-size: .73rem; color: #64748b; font-weight: 600; }
.mf-edit-inp {
    text-align: right; font-size: .74rem; font-weight: 700; font-family: monospace;
    border: 1.5px solid #e2e8f0; border-radius: 6px;
    padding: .18rem .4rem; background: #f8fafc; width: 90px;
    transition: border-color .15s; outline: none;
}
.mf-edit-inp:focus { border-color: #3b82f6; background: #fff; }
/* Distribución afiliación */
.mf-dist-card {
    background: linear-gradient(135deg, #faf5ff, #f5f3ff);
    border: 1.5px solid #ddd6fe;
    border-radius: 10px; padding: .6rem .75rem; margin-top: .5rem;
}
.mf-dist-card-title { font-size: .62rem; font-weight: 800; color: #7c3aed; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .4rem; }
.mf-dist-grid { display: grid; grid-template-columns: 1fr auto; gap: .22rem .5rem; align-items: center; }
.mf-dist-lbl { font-size: .71rem; font-weight: 700; color: #5b21b6; }
.mf-dist-inp {
    text-align: right; font-size: .73rem; font-family: monospace; font-weight: 700;
    border: 1.5px solid #ddd6fe; border-radius: 5px; padding: .16rem .35rem;
    background: #fff; width: 82px; outline: none;
}
.mf-dist-inp:focus { border-color: #7c3aed; }
.mf-dist-total-row { display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #ddd6fe; padding-top: .28rem; margin-top: .18rem; }
.mf-dist-total-lbl { font-size: .71rem; font-weight: 700; color: #4c1d95; }
#mf-dist-admon { font-size: .82rem; font-weight: 900; color: #1d4ed8; font-family: monospace; }
/* Afiliacion label */
#mf-detalle-afil { font-size: .8rem; font-weight: 700; color: #7c3aed; padding: .35rem 0; }

/* ── COLUMNA DERECHA: pagos ── */
#mf-col-pagos {
    padding: .9rem 1.2rem .9rem 1rem;
    overflow-y: auto;
    background: #fafafa;
    display: flex; flex-direction: column; gap: .55rem;
}
/* Pendiente badge grande */
.mf-pendiente-box {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 2px solid #bfdbfe;
    border-radius: 12px; padding: .55rem .85rem;
    display: flex; align-items: center; justify-content: space-between;
}
.mf-pendiente-label { font-size: .65rem; font-weight: 800; color: #1e40af; text-transform: uppercase; letter-spacing: .06em; }
#mf-pendiente { font-size: 1.15rem; font-weight: 900; font-family: monospace; transition: color .2s; }
/* Sección consignaciones */
.mf-pagos-sec {
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 11px;
    overflow: hidden;
}
.mf-pagos-sec-hdr {
    background: #f8fafc; padding: .38rem .75rem;
    font-size: .6rem; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: space-between;
}
.mf-consig-add-btn {
    padding: .18rem .52rem; font-size: .65rem; border-radius: 5px;
    border: 1.5px solid #3b82f6; background: #eff6ff; color: #1d4ed8;
    cursor: pointer; font-weight: 700; transition: background .15s;
}
.mf-consig-add-btn:hover { background: #dbeafe; }
#mf-consig-list {
    padding: .3rem .5rem; display: flex; flex-direction: column; gap: .22rem;
    max-height: 140px; overflow-y: auto;
}
.mf-consig-row {
    display: grid; grid-template-columns: 2fr 90px 100px 22px;
    gap: .25rem; align-items: center;
}
.mf-consig-sel, .mf-consig-monto-inp, .mf-consig-fecha-inp {
    padding: .25rem .35rem; border: 1px solid #e2e8f0; border-radius: 5px;
    font-size: .71rem; font-family: inherit; outline: none; background: #fff;
    transition: border-color .15s;
}
.mf-consig-sel:focus, .mf-consig-monto-inp:focus, .mf-consig-fecha-inp:focus { border-color: #3b82f6; }
.mf-consig-monto-inp { text-align: right; font-weight: 700; font-family: monospace; }
.mf-consig-del {
    padding: 2px 5px; border: none; background: #fee2e2; color: #dc2626;
    border-radius: 5px; cursor: pointer; font-size: .85rem;
    transition: background .15s;
}
.mf-consig-del:hover { background: #fecaca; }
.mf-consig-img-lbl {
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 5px;
    background: #f0f9ff; border: 1px solid #bae6fd; cursor: pointer;
    font-size: .9rem; transition: background .15s;
}
.mf-consig-img-lbl:hover { background: #e0f2fe; }
/* Efectivo / Préstamo */
.mf-pago-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: .4rem .75rem; border-top: 1px solid #f1f5f9;
}
.mf-pago-lbl { font-size: .73rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: .3rem; }
.mf-pago-inp {
    width: 88px; text-align: right; padding: .27rem .42rem;
    border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: .76rem;
    font-family: monospace; font-weight: 700; background: #fff; outline: none;
    transition: border-color .15s;
}
.mf-pago-inp:focus { border-color: #3b82f6; }
/* Observación */
.mf-obs-wrap { flex-shrink: 0; }
.mf-obs-inp {
    width: 100%; padding: .35rem .55rem; border: 1.5px solid #e2e8f0;
    border-radius: 8px; font-size: .77rem; font-family: inherit; outline: none;
    background: #fff; transition: border-color .15s; box-sizing: border-box;
}
.mf-obs-inp:focus { border-color: #3b82f6; }

/* ── NP plano ── */
.mf-np-wrap { display: none; }
.mf-np-inp {
    width: 100%; padding: .3rem .5rem; border: 1.5px solid #e2e8f0;
    border-radius: 7px; font-size: .8rem; outline: none; background: #fff;
    transition: border-color .15s; box-sizing: border-box;
}
.mf-np-inp:focus { border-color: #3b82f6; }

/* ── Retiro ── */
#mf-retiro-card {
    background: linear-gradient(135deg, #fff5f5, #fef2f2);
    border: 1.5px solid #fecaca;
    border-radius: 11px; overflow: hidden;
    transition: border-color .18s;
}
#mf-retiro-card.activo {
    border-color: #ef4444;
    box-shadow: 0 2px 10px rgba(239,68,68,.12);
}
#mf-retiro-hdr {
    display: flex; align-items: center; gap: .5rem;
    padding: .42rem .72rem;
    cursor: pointer; user-select: none;
    background: transparent;
    border: none; width: 100%; text-align: left;
}
#mf-retiro-hdr:hover { background: rgba(239,68,68,.05); }
.mf-retiro-check {
    width: 15px; height: 15px; accent-color: #ef4444; cursor: pointer; flex-shrink: 0;
}
#mf-retiro-hdr-label {
    font-size: .75rem; font-weight: 700; color: #dc2626;
    display: flex; align-items: center; gap: .35rem;
}
#mf-retiro-body {
    display: none;
    padding: .45rem .72rem .6rem;
    border-top: 1px solid #fecaca;
    flex-direction: column; gap: .4rem;
}
#mf-retiro-body.visible { display: flex; }
.mf-retiro-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; align-items: end;
}
.mf-retiro-lbl {
    font-size: .57rem; font-weight: 800; color: #991b1b;
    text-transform: uppercase; letter-spacing: .05em;
    display: block; margin-bottom: .18rem;
}
.mf-retiro-date {
    padding: .3rem .5rem; border: 1.5px solid #fca5a5; border-radius: 7px;
    font-size: .78rem; font-family: inherit; outline: none;
    background: #fff; width: 100%; box-sizing: border-box;
    transition: border-color .15s;
}
.mf-retiro-date:focus { border-color: #ef4444; }
#mf-retiro-dias-box {
    background: #fff; border: 1.5px solid #fca5a5; border-radius: 7px;
    padding: .3rem .55rem;
    display: flex; align-items: center; justify-content: space-between;
}
#mf-retiro-dias-num {
    font-size: 1.05rem; font-weight: 900; color: #dc2626; font-family: monospace;
}
.mf-retiro-aviso {
    font-size: .67rem; color: #92400e; background: #fef3c7;
    border-radius: 5px; padding: .25rem .5rem; border: 1px solid #fde68a;
}

/* ── FOOTER ── */
#mf-footer {
    background: #f8fafc; border-top: 1px solid #e2e8f0;
    padding: .6rem 1.2rem;
    display: flex; align-items: center; justify-content: flex-end; gap: .5rem;
    flex-shrink: 0;
}
.mf-btn-cancel {
    padding: .42rem 1.1rem; background: #fff; color: #475569;
    border: 1.5px solid #e2e8f0; border-radius: 8px; cursor: pointer;
    font-size: .8rem; font-weight: 600; transition: all .15s;
}
.mf-btn-cancel:hover { background: #f1f5f9; border-color: #cbd5e1; }
.mf-btn-guardar {
    padding: .44rem 1.4rem;
    background: linear-gradient(135deg, #166534, #15803d);
    color: #fff; border: none; border-radius: 8px; cursor: pointer;
    font-size: .83rem; font-weight: 800; letter-spacing: .01em;
    box-shadow: 0 2px 10px rgba(21, 128, 61, .3);
    transition: all .18s; display: flex; align-items: center; gap: .4rem;
}
.mf-btn-guardar:hover { background: linear-gradient(135deg, #14532d, #166534); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(21, 128, 61, .35); }
.mf-btn-guardar:disabled { opacity: .6; transform: none; cursor: not-allowed; }

/* ── Badge nivel ARL ── */
.mf-arl-badge {
    font-size: .58rem; background: #e0f2fe; color: #0369a1;
    border-radius: 4px; padding: .03rem .28rem; font-weight: 800;
}

/* ── Scrollbar fina ── */
#mf-col-desglose::-webkit-scrollbar,
#mf-col-pagos::-webkit-scrollbar,
#mf-consig-list::-webkit-scrollbar { width: 4px; }
#mf-col-desglose::-webkit-scrollbar-thumb,
#mf-col-pagos::-webkit-scrollbar-thumb,
#mf-consig-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* ── Responsive (< 600px → 1 col) ── */
@media (max-width: 600px) {
    #mf-body { grid-template-columns: 1fr; }
    #mf-col-desglose { border-right: none; border-bottom: 1px solid #f1f5f9; }
}
</style>

{{-- ════════════════════════════════════════════════════════ --}}
{{--  OVERLAY + BOX                                          --}}
{{-- ════════════════════════════════════════════════════════ --}}
<div id="mf-overlay" style="display:none;">
<div id="mf-box" onclick="event.stopPropagation()">

    {{-- ── HEADER ── --}}
    <div id="mf-header">
        <div id="mf-header-left">
            <div id="mf-header-icon">🧾</div>
            <div id="mf-header-text">
                <h2>Facturar</h2>
                <p id="mf-subtitle">Seleccione los datos de pago</p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.6rem;">
            <span id="mf-badge-dias" style="background:rgba(255,255,255,.12);color:rgba(255,255,255,.8);font-size:.63rem;font-weight:700;padding:.18rem .55rem;border-radius:5px;"></span>
            <button id="mf-close-btn" onclick="MF.cerrar()" title="Cerrar">✕</button>
        </div>
    </div>

    {{-- ── CONTROLES: indep-opts a la izquierda | Tipo/Mes/Año a la derecha ── --}}
    <div id="mf-controls">
        {{-- IZQUIERDA: opciones independiente (primer mes) --}}
        <div id="mf-indep-opts" style="display:none;">
            <div style="font-size:.57rem;font-weight:800;color:#7c3aed;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;">⚡ Primer mes — ¿Qué se cobra?</div>
            <div style="display:flex;gap:.75rem;align-items:center;">
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:700;color:#6d28d9;cursor:pointer;white-space:nowrap;">
                    <input type="radio" name="mf_indep_modo" value="afiliacion" checked onchange="MF.actualizarTipo()" style="accent-color:#7c3aed;"> Solo Afiliación
                </label>
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.78rem;font-weight:700;color:#6d28d9;cursor:pointer;white-space:nowrap;">
                    <input type="radio" name="mf_indep_modo" value="ambos" onchange="MF.actualizarTipo()" style="accent-color:#7c3aed;"> Planilla + Afiliación
                </label>
            </div>
        </div>

        {{-- DERECHA: controles estándar --}}
        <div style="display:flex;align-items:center;gap:.7rem;margin-left:auto;flex-wrap:wrap;">
            <div class="mf-ctrl-group">
                <span class="mf-ctrl-label">Tipo</span>
                <select id="mf-estado" class="mf-ctrl-sel" onchange="MF.onEstado()">
                    <option value="pagada">🧳 Facturar</option>
                    <option value="pre_factura">📋 Pre-factura</option>
                    <option value="prestamo">💳 Préstamo</option>
                </select>
            </div>
            <div class="mf-ctrl-group">
                <span class="mf-ctrl-label">Mes</span>
                <select id="mf-mes" class="mf-ctrl-sel" onchange="MF.cambiarPeriodo()">
                    @foreach($meses_nombres as $i => $m)
                    <option value="{{ $i+1 }}" {{ ($i+1) == $mfMesD ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mf-ctrl-group">
                <span class="mf-ctrl-label">Año</span>
                <select id="mf-anio" class="mf-ctrl-sel" onchange="MF.cambiarPeriodo()">
                    @for($y = now()->year - 1; $y <= now()->year + 2; $y++)
                    <option value="{{ $y }}" {{ $y == $mfAnioD ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="mf-ctrl-group mf-np-wrap" id="mf-nplano-wrap">
                <span class="mf-ctrl-label">N° Plano (NP)</span>
                <input type="number" id="mf-nplano" class="mf-np-inp" placeholder="Auto" min="1" style="width:88px;">
            </div>
        </div>
        <input type="hidden" id="mf-tipo" value="planilla">
    </div>

    {{-- ── ALERTAS ── --}}
    <div id="mf-alerts">
        <div id="mf-aviso-mes"  style="display:none;" class="mf-alert mf-alert-warn">⚠️ <span id="mf-aviso-mes-txt"></span></div>
        <div id="mf-aviso-tipo" style="display:none;" class="mf-alert mf-alert-warn"></div>
    </div>

    {{-- ══════════════════════  BODY 2 COLUMNAS  ══════════════════════ --}}
    <div id="mf-body">

        {{-- ╔══════════════════════════════════════════════╗ --}}
        {{-- ║  COL IZQUIERDA — DETALLE A COBRAR           ║ --}}
        {{-- ╚══════════════════════════════════════════════╝ --}}
        <div id="mf-col-desglose">
            <div class="mf-col-title">
                <span>📋 Detalle a cobrar</span>
            </div>

            {{-- Desglose SS (visible en planilla) --}}
            <div id="mf-detalle-ss">
                {{-- EPS --}}
                <div class="mf-detail-row">
                    <span class="mf-detail-lbl">EPS <span id="mf-eps-pct" style="font-size:.6rem;color:#94a3b8;font-weight:500;"></span></span>
                    <span id="mf-v-eps" class="mf-detail-val">$0</span>
                </div>
                {{-- ARL --}}
                <div class="mf-detail-row">
                    <span class="mf-detail-lbl">ARL <span id="mf-arl-badge" class="mf-arl-badge"></span></span>
                    <span id="mf-v-arl" class="mf-detail-val">$0</span>
                </div>
                {{-- Pensión --}}
                <div class="mf-detail-row">
                    <span class="mf-detail-lbl">Pensión</span>
                    <span id="mf-v-afp" class="mf-detail-val">$0</span>
                </div>
                {{-- Caja --}}
                <div class="mf-detail-row">
                    <span class="mf-detail-lbl">Caja Comp.</span>
                    <span id="mf-v-caja" class="mf-detail-val">$0</span>
                </div>
                {{-- SS Total --}}
                <hr class="mf-divider">
                <div class="mf-detail-row mf-ss-total">
                    <span class="mf-detail-lbl" style="color:#1d4ed8;">Seg. Social</span>
                    <span id="mf-v-ss" class="mf-detail-val" style="color:#1d4ed8;">$0</span>
                </div>
                <hr class="mf-divider">
                {{-- Admin / Seguro / IVA --}}
                <div class="mf-detail-row">
                    <span class="mf-detail-lbl">Admon empresa</span>
                    <span id="mf-v-admon" class="mf-detail-val">$0</span>
                </div>
                <div class="mf-detail-row">
                    <span class="mf-detail-lbl">Seguro</span>
                    <span id="mf-v-seg" class="mf-detail-val">$0</span>
                </div>
                <div class="mf-detail-row">
                    <span class="mf-detail-lbl">IVA</span>
                    <span id="mf-v-iva" class="mf-detail-val">$0</span>
                </div>
                {{-- Afiliación (visible cuando hay contratos en tipo afiliación) --}}
                <div class="mf-detail-row" id="mf-row-afil" style="display:none;">
                    <span class="mf-detail-lbl" style="color:#16a34a;font-weight:700;">📌 Afiliación</span>
                    <span id="mf-v-afil" class="mf-detail-val" style="color:#16a34a;font-weight:700;">$0</span>
                </div>
                <hr class="mf-divider">
                {{-- Otros editables --}}
                <div class="mf-edit-row">
                    <label class="mf-edit-lbl" for="mf-otros">Otros planilla</label>
                    <input type="text" id="mf-otros" class="mf-edit-inp" value="0" oninput="MF.recalc()">
                </div>
                <div class="mf-edit-row" style="margin-top:.18rem;">
                    <label class="mf-edit-lbl" for="mf-otros-admon">Otros admon</label>
                    <input type="text" id="mf-otros-admon" class="mf-edit-inp" value="0" oninput="MF.recalc()">
                </div>
            </div>
            {{-- Detalle afiliación (legacy) --}}
            <div id="mf-detalle-afil" style="display:none;"></div>

            {{-- Total bruto -- al fondo de la columna izquierda --}}
            <div class="mf-total-box" style="margin-top:.5rem;">
                <span class="mf-total-label">Total bruto</span>
                <span id="mf-total" class="mf-total-val">$0</span>
            </div>

            {{-- Distribución afiliación (solo en modo afiliación) --}}
            <div id="mf-dist-sec" style="display:none;" class="mf-dist-card">
                <div class="mf-dist-card-title">📊 Distribución Afiliación <span style="font-weight:400;opacity:.7;">(interno)</span></div>
                <div class="mf-dist-grid">
                    <span class="mf-dist-lbl">💼 Asesor</span>
                    <input type="text" id="mf-dist-asesor" value="0" oninput="MF.distRecalc()" class="mf-dist-inp">

                    <span class="mf-dist-lbl">🔒 Retiro/Novedad</span>
                    <input type="text" id="mf-dist-retiro" value="0" oninput="MF.distRecalc()" class="mf-dist-inp">

                    <span class="mf-dist-lbl">👤 Encargado</span>
                    <input type="text" id="mf-dist-encargado" value="0" oninput="MF.distRecalc()" class="mf-dist-inp">
                </div>
                <div class="mf-dist-total-row">
                    <span class="mf-dist-total-lbl">🏢 Empresa/Admon</span>
                    <span id="mf-dist-admon">$0</span>
                </div>
                <div id="mf-dist-aviso" style="display:none;margin-top:.3rem;font-size:.67rem;color:#dc2626;font-weight:700;"></div>
            </div>

        </div>{{-- /mf-col-desglose --}}

        {{-- ╔══════════════════════════════════════════════╗ --}}
        {{-- ║  COL DERECHA — PAGOS                        ║ --}}
        {{-- ╚══════════════════════════════════════════════╝ --}}
        <div id="mf-col-pagos">

            {{-- Saldos a favor / pendientes (aparece si los hay) — ARRIBA del pendiente --}}
            <div id="mf-saldos-panel" style="display:none;"></div>

            {{-- Saldo a pagar (columna derecha) --}}
            <div class="mf-pendiente-box">
                <div>
                    <div class="mf-pendiente-label">Saldo a pagar</div>
                    <div style="font-size:.62rem;color:#3b82f6;margin-top:.08rem;">Total bruto − anticipo − pagos recibidos</div>
                </div>
                <span id="mf-pendiente" style="color:#dc2626;">$0</span>
            </div>

            {{-- Consignaciones --}}
            <div class="mf-pagos-sec">
                <div class="mf-pagos-sec-hdr">
                    <span>🏦 Consignaciones</span>
                    <button class="mf-consig-add-btn" onclick="MF.addConsig()">＋ Agregar</button>
                </div>
                {{-- Cabecera --}}
                <div style="display:grid;grid-template-columns:2fr 90px 100px 100px 34px 22px;gap:.25rem;padding:.22rem .6rem .15rem;background:#f8fafc;border-bottom:1px solid #f1f5f9;">
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Banco</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:right;">Valor</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:center;">Fecha</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:center;">Referencia</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:center;">Adj.</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:center;">Del.</span>
                </div>
                <div id="mf-consig-list"></div>
            </div>

            {{-- Efectivo --}}
            <div class="mf-pagos-sec" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #3b82f6;border-radius:10px;margin-top:.4rem;">
                <div class="mf-pagos-sec-hdr" style="color:#1d4ed8;font-weight:800;font-size:.82rem;">💵 Efectivo</div>
                <div style="padding:.4rem .7rem .55rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                    <span style="font-size:.78rem;font-weight:700;color:#1e40af;">Valor en efectivo</span>
                    <input type="text" id="mf-efectivo" value="0" oninput="MF.recalc()"
                        style="width:130px;padding:.45rem .65rem;border:2px solid #3b82f6;border-radius:8px;
                               font-size:1rem;font-weight:800;color:#1d4ed8;text-align:right;
                               background:#fff;outline:none;box-shadow:0 2px 8px rgba(59,130,246,.15);">
                </div>
            </div>

            {{-- Préstamo (solo si estado=prestamo) --}}
            <div id="mf-prest-wrap" style="display:none;" class="mf-pagos-sec">
                <div class="mf-pagos-sec-hdr"><span>💳 Valor Préstamo</span></div>
                <div class="mf-pago-row">
                    <span class="mf-pago-lbl">Monto del préstamo</span>
                    <input type="text" id="mf-prestamo" value="0" oninput="MF.recalc()" class="mf-pago-inp">
                </div>
            </div>

            {{-- Retiro --}}
            <div id="mf-retiro-card">
                <button type="button" id="mf-retiro-hdr" onclick="MF.toggleRetiro()">
                    <input type="checkbox" id="mf-retiro-check" class="mf-retiro-check"
                           onclick="event.stopPropagation();MF.toggleRetiro()">
                    <span id="mf-retiro-hdr-label">🚪 Marcar Retiro en este período</span>
                </button>
                <div id="mf-retiro-body" style="display:none;">
                    <div class="mf-retiro-row">
                        <div>
                            <label class="mf-retiro-lbl" for="mf-retiro-fecha">Fecha de Retiro</label>
                            <input type="date" id="mf-retiro-fecha" class="mf-retiro-date"
                                   oninput="MF.onRetiroFecha()">
                        </div>
                        <div>
                            <label class="mf-retiro-lbl">Días a pagar</label>
                            <div id="mf-retiro-dias-box">
                                <span style="font-size:.67rem;color:#991b1b;font-weight:600;">días</span>
                                <span id="mf-retiro-dias-num">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="mf-retiro-aviso">
                        ⚠️ Los días calculados actualizan el cotizador para que el SS sea proporcional al retiro.
                    </div>
                </div>
            </div>

            {{-- Observación --}}
            <div class="mf-obs-wrap">
                <div class="mf-col-title" style="margin-bottom:.25rem;">
                    <span>🗒 Observación</span>
                </div>
                <input type="text" id="mf-obs" class="mf-obs-inp" placeholder="Agrega una nota opcional...">
            </div>

        </div>{{-- /mf-col-pagos --}}
    </div>{{-- /mf-body --}}

    {{-- ── FOOTER ── --}}
    <div id="mf-footer">
        <button type="button" class="mf-btn-cancel" onclick="MF.cerrar()">Cancelar</button>
        <button type="button" class="mf-btn-guardar" id="mf-btn-guardar" onclick="MF.guardar()">🧾 Facturar ahora</button>
    </div>

</div>
</div>

{{-- Datos bancos para JS --}}
<script>
window._MF_BANCOS = [
    {id:'', label:'-- Seleccionar banco --'},
    @foreach($mfBancos as $b)
    {id:{{ $b->id }}, label:{!! json_encode($b->nombre . ' — ' . $b->tipo_cuenta) !!}},
    @endforeach
];
</script>
