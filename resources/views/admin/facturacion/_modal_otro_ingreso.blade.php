{{--
    ╔══════════════════════════════════════════════════════════╗
    ║  PARTIAL: _modal_otro_ingreso.blade.php  —  BryNex      ║
    ║  Modal para facturar trámites / otros ingresos          ║
    ║                                                          ║
    ║  Variables via @include:                                 ║
    ║    $bancos   → colección BancoCuenta::paraFacturacion()   ║
    ║    $oiMes    → mes por defecto (opcional)               ║
    ║    $oiAnio   → año por defecto (opcional)               ║
    ║    $oiCedula → cédula del cliente (opcional, si es fijo)║
    ║    $oiEmpresaId → empresa_id (opcional)                 ║
    ╚══════════════════════════════════════════════════════════╝
--}}
@php
    $oiBancos   = $bancos    ?? collect();
    $oiMesD     = $oiMes     ?? now()->month;
    $oiAnioD    = $oiAnio    ?? now()->year;
    $oiCedulaD  = $oiCedula  ?? null;
    $oiEmpIdD   = $oiEmpresaId ?? null;
    $meses_oi   = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
@endphp

<style>
/* ═══════════════════════════════════════════════════════════════
   Modal Otro Ingreso — BryNex
═══════════════════════════════════════════════════════════════ */
#oi-overlay {
    position:fixed; inset:0;
    background:rgba(10,10,20,.65);
    backdrop-filter:blur(4px);
    z-index:2100;
    display:flex; align-items:center; justify-content:center;
    padding:.75rem;
}
#oi-box {
    background:#fff; border-radius:18px;
    width:min(820px,98vw); max-height:96vh;
    overflow:hidden; display:flex; flex-direction:column;
    box-shadow:0 32px 100px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.06);
}
/* ── HEADER ── */
#oi-header {
    background:linear-gradient(135deg,#064e3b 0%,#065f46 100%);
    padding:.8rem 1.2rem .7rem;
    display:flex; align-items:center; justify-content:space-between;
    flex-shrink:0;
}
#oi-header-icon {
    width:34px; height:34px; border-radius:9px;
    background:rgba(255,255,255,.12);
    display:flex; align-items:center; justify-content:center; font-size:1.1rem;
}
#oi-header-text h2 { font-size:.92rem; font-weight:800; color:#fff; margin:0; }
#oi-header-text p  { font-size:.65rem; color:rgba(255,255,255,.55); margin:0; }
#oi-close-btn {
    width:28px; height:28px; border-radius:7px; border:none; cursor:pointer;
    background:rgba(255,255,255,.1); color:rgba(255,255,255,.7); font-size:.95rem;
    display:flex; align-items:center; justify-content:center; transition:background .18s;
}
#oi-close-btn:hover { background:rgba(255,255,255,.2); color:#fff; }
/* ── CONTROLES ── */
#oi-controls {
    background:#f0fdf4; border-bottom:1px solid #d1fae5;
    padding:.6rem 1.2rem;
    display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; flex-shrink:0;
}
.oi-ctrl-group { display:flex; flex-direction:column; gap:.12rem; min-width:0; }
.oi-ctrl-label { font-size:.57rem; font-weight:800; color:#065f46; text-transform:uppercase; letter-spacing:.05em; }
.oi-ctrl-sel, .oi-ctrl-inp {
    padding:.3rem .55rem; border:1.5px solid #a7f3d0;
    border-radius:7px; font-size:.78rem; background:#fff; cursor:pointer;
    font-family:inherit; color:#0f172a; outline:none; transition:border-color .15s;
    min-width:90px;
}
.oi-ctrl-sel:focus, .oi-ctrl-inp:focus { border-color:#10b981; }
/* ── BODY 2 COL ── */
#oi-body {
    display:grid; grid-template-columns:1.1fr 1fr;
    gap:0; overflow:hidden; flex:1; min-height:0;
}
/* ── COL IZQ — Valores ── */
#oi-col-valores {
    padding:.9rem 1rem .9rem 1.2rem;
    overflow-y:auto; border-right:1px solid #f0fdf4; background:#fff;
}
.oi-col-title {
    font-size:.6rem; font-weight:800; color:#065f46;
    text-transform:uppercase; letter-spacing:.08em;
    margin-bottom:.6rem; padding-bottom:.3rem;
    border-bottom:1px solid #d1fae5;
    display:flex; align-items:center; gap:.4rem;
}
/* Descripción */
#oi-desc-wrap { margin-bottom:.75rem; }
#oi-desc {
    width:100%; padding:.42rem .55rem;
    border:1.5px solid #d1fae5; border-radius:8px;
    font-size:.82rem; font-family:inherit; outline:none; background:#fff;
    transition:border-color .15s; box-sizing:border-box; resize:vertical;
    min-height:56px;
}
#oi-desc:focus { border-color:#10b981; }
/* Filas editable */
.oi-edit-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:.22rem 0;
}
.oi-edit-lbl { font-size:.73rem; color:#374151; font-weight:600; }
.oi-edit-inp {
    text-align:right; font-size:.75rem; font-weight:700; font-family:monospace;
    border:1.5px solid #d1fae5; border-radius:6px;
    padding:.2rem .42rem; background:#f0fdf4; width:100px;
    transition:border-color .15s; outline:none;
}
.oi-edit-inp:focus { border-color:#10b981; background:#fff; }
.oi-divider { border:none; border-top:1px dashed #d1fae5; margin:.35rem 0; }
/* IVA badge */
.oi-iva-badge {
    font-size:.62rem; background:#fef3c7; color:#92400e;
    border-radius:4px; padding:.04rem .3rem; font-weight:800;
}
/* Total box */
.oi-total-box {
    margin-top:.55rem;
    background:linear-gradient(135deg,#064e3b,#065f46);
    border-radius:10px; padding:.55rem .8rem;
    display:flex; justify-content:space-between; align-items:center;
}
.oi-total-label { font-size:.72rem; font-weight:700; color:rgba(255,255,255,.7); text-transform:uppercase; }
.oi-total-val   { font-size:1.15rem; font-weight:900; color:#fff; font-family:monospace; }
/* ── COL DER — Pago ── */
#oi-col-pagos {
    padding:.9rem 1.2rem .9rem 1rem;
    overflow-y:auto; background:#f9fafb;
    display:flex; flex-direction:column; gap:.55rem;
}
/* Pendiente box */
.oi-pendiente-box {
    background:linear-gradient(135deg,#ecfdf5,#d1fae5);
    border:2px solid #6ee7b7; border-radius:12px;
    padding:.55rem .85rem;
    display:flex; align-items:center; justify-content:space-between;
}
.oi-pendiente-label { font-size:.65rem; font-weight:800; color:#065f46; text-transform:uppercase; letter-spacing:.06em; }
#oi-pendiente { font-size:1.15rem; font-weight:900; font-family:monospace; transition:color .2s; }
/* Consig */
.oi-pagos-sec { background:#fff; border:1.5px solid #e2e8f0; border-radius:11px; overflow:hidden; }
.oi-pagos-sec-hdr {
    background:#f8fafc; padding:.38rem .75rem;
    font-size:.6rem; font-weight:800; color:#64748b;
    text-transform:uppercase; letter-spacing:.06em;
    border-bottom:1px solid #e2e8f0;
    display:flex; align-items:center; justify-content:space-between;
}
.oi-consig-add-btn {
    padding:.18rem .52rem; font-size:.65rem; border-radius:5px;
    border:1.5px solid #10b981; background:#ecfdf5; color:#065f46;
    cursor:pointer; font-weight:700; transition:background .15s;
}
.oi-consig-add-btn:hover { background:#d1fae5; }
#oi-consig-list { padding:.3rem .4rem; display:flex; flex-direction:column; gap:.22rem; max-height:130px; overflow-y:auto; }
.oi-consig-row { display:grid; grid-template-columns:minmax(0,1fr) 62px 92px 26px 20px; gap:.22rem; align-items:center; }
.oi-consig-row > * { min-width:0; }
.oi-consig-img-lbl {
    display:flex; align-items:center; justify-content:center;
    height:23px; border-radius:5px; border:1px solid #bae6fd;
    background:#f0f9ff; cursor:pointer; font-size:.78rem; transition:all .15s;
}
.oi-consig-img-lbl:hover { background:#e0f2fe; border-color:#38bdf8; }
.oi-consig-img-lbl.tiene { background:#dcfce7; border-color:#86efac; }
.oi-consig-sel, .oi-consig-monto, .oi-consig-fecha {
    padding:.25rem .2rem; border:1px solid #e2e8f0; border-radius:5px;
    font-size:.71rem; font-family:inherit; outline:none; background:#fff;
    transition:border-color .15s;
}
.oi-consig-sel:focus, .oi-consig-monto:focus, .oi-consig-fecha:focus { border-color:#10b981; }
.oi-consig-monto { text-align:right; font-weight:700; font-family:monospace; }
.oi-consig-fecha { font-size:.66rem; }
.oi-consig-del {
    padding:2px 5px; border:none; background:#fee2e2; color:#dc2626;
    border-radius:5px; cursor:pointer; font-size:.85rem;
}
.oi-consig-del:hover { background:#fecaca; }
/* Efectivo */
.oi-pago-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:.4rem .75rem; border-top:1px solid #f1f5f9;
}
.oi-pago-lbl { font-size:.73rem; font-weight:700; color:#475569; display:flex; align-items:center; gap:.3rem; }
.oi-pago-inp {
    width:100px; text-align:right; padding:.27rem .42rem;
    border:1.5px solid #e2e8f0; border-radius:6px; font-size:.76rem;
    font-family:monospace; font-weight:700; background:#fff; outline:none;
    transition:border-color .15s;
}
.oi-pago-inp:focus { border-color:#10b981; }
/* obs */
.oi-obs-inp {
    width:100%; padding:.35rem .55rem; border:1.5px solid #e2e8f0;
    border-radius:8px; font-size:.77rem; font-family:inherit; outline:none;
    background:#fff; transition:border-color .15s; box-sizing:border-box;
}
.oi-obs-inp:focus { border-color:#10b981; }
/* ── FOOTER ── */
#oi-footer {
    background:#f0fdf4; border-top:1px solid #d1fae5;
    padding:.6rem 1.2rem;
    display:flex; align-items:center; justify-content:flex-end; gap:.5rem;
    flex-shrink:0;
}
.oi-btn-cancel {
    padding:.42rem 1.1rem; background:#fff; color:#475569;
    border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer;
    font-size:.8rem; font-weight:600; transition:all .15s;
}
.oi-btn-cancel:hover { background:#f1f5f9; }
.oi-btn-guardar {
    padding:.44rem 1.4rem;
    background:linear-gradient(135deg,#065f46,#047857);
    color:#fff; border:none; border-radius:8px; cursor:pointer;
    font-size:.83rem; font-weight:800;
    box-shadow:0 2px 10px rgba(6,95,70,.3);
    transition:all .18s; display:flex; align-items:center; gap:.4rem;
}
.oi-btn-guardar:hover { background:linear-gradient(135deg,#064e3b,#065f46); transform:translateY(-1px); }
.oi-btn-guardar:disabled { opacity:.6; transform:none; cursor:not-allowed; }
/* IVA alerta */
#oi-iva-aviso {
    display:none; padding:.3rem .8rem;
    background:#fef9c3; border-bottom:1px solid #fde68a;
    font-size:.7rem; font-weight:700; color:#92400e;
    flex-shrink:0;
}
/* scrollbar */
#oi-col-valores::-webkit-scrollbar,
#oi-col-pagos::-webkit-scrollbar,
#oi-consig-list::-webkit-scrollbar { width:4px; }
#oi-col-valores::-webkit-scrollbar-thumb,
#oi-col-pagos::-webkit-scrollbar-thumb,
#oi-consig-list::-webkit-scrollbar-thumb { background:#d1fae5; border-radius:4px; }
@media (max-width:600px) {
    #oi-body { grid-template-columns:1fr; }
    #oi-col-valores { border-right:none; border-bottom:1px solid #d1fae5; }
}
/* ── Mini-modal: soporte de pago ── */
#oiadj-overlay {
    position:fixed; inset:0; z-index:2300;
    background:rgba(10,10,20,.7); backdrop-filter:blur(4px);
    display:none; align-items:center; justify-content:center; padding:1rem;
}
#oiadj-box {
    background:#fff; border-radius:16px; width:min(440px,96vw);
    box-shadow:0 28px 80px rgba(0,0,0,.45); overflow:hidden;
    display:flex; flex-direction:column;
}
#oiadj-hdr {
    background:linear-gradient(135deg,#064e3b,#065f46);
    padding:.7rem 1rem; display:flex; align-items:center; justify-content:space-between;
}
#oiadj-hdr h3 { margin:0; font-size:.85rem; font-weight:800; color:#fff; }
#oiadj-hdr p  { margin:0; font-size:.62rem; color:rgba(255,255,255,.6); }
#oiadj-close {
    width:26px; height:26px; border:none; border-radius:6px; cursor:pointer;
    background:rgba(255,255,255,.12); color:#fff; font-size:.85rem;
}
#oiadj-body { padding:.9rem 1rem; display:flex; flex-direction:column; gap:.6rem; }
#oiadj-zona {
    border:2px dashed #a7f3d0; border-radius:12px; background:#f0fdf4;
    min-height:120px; display:flex; flex-direction:column; align-items:center;
    justify-content:center; gap:.25rem; cursor:pointer; outline:none;
    transition:all .15s; text-align:center; padding:.8rem;
}
#oiadj-zona:focus, #oiadj-zona.drag { border-color:#10b981; background:#dcfce7; }
#oiadj-zona-t1 { font-size:.82rem; font-weight:800; color:#065f46; }
#oiadj-zona-t2 { font-size:.68rem; color:#059669; }
#oiadj-file-btn {
    padding:.4rem .9rem; border:1.5px solid #a7f3d0; border-radius:8px;
    background:#fff; color:#065f46; font-weight:700; font-size:.76rem; cursor:pointer;
}
#oiadj-file-btn:hover { background:#ecfdf5; }
#oiadj-prev { display:none; text-align:center; }
#oiadj-prev-img { max-width:100%; max-height:220px; border-radius:10px; border:1px solid #e2e8f0; }
#oiadj-prev-name { font-size:.72rem; font-weight:700; color:#065f46; margin-top:.35rem; word-break:break-all; }
#oiadj-footer {
    background:#f8fafc; border-top:1px solid #e2e8f0; padding:.6rem 1rem;
    display:flex; gap:.45rem; justify-content:flex-end; align-items:center;
}
#oiadj-quitar {
    margin-right:auto; padding:.38rem .8rem; border:1.5px solid #fecaca;
    background:#fff1f2; color:#be123c; border-radius:8px; cursor:pointer;
    font-size:.75rem; font-weight:700;
}
#oiadj-cancel {
    padding:.38rem .9rem; border:1.5px solid #e2e8f0; background:#fff;
    color:#475569; border-radius:8px; cursor:pointer; font-size:.77rem; font-weight:600;
}
#oiadj-ok {
    padding:.4rem 1.1rem; border:none; border-radius:8px; cursor:pointer;
    background:linear-gradient(135deg,#065f46,#047857); color:#fff;
    font-size:.79rem; font-weight:800;
}
#oiadj-ok:disabled { opacity:.5; cursor:not-allowed; }
</style>

{{-- ════════════════════ OVERLAY ════════════════════ --}}
<div id="oi-overlay" style="display:none;" onclick="if(event.target===this)OI.cerrar()">
<div id="oi-box" onclick="event.stopPropagation()">

    {{-- HEADER --}}
    <div id="oi-header">
        <div style="display:flex;align-items:center;gap:.65rem;">
            <div id="oi-header-icon">💼</div>
            <div id="oi-header-text">
                <h2>Otro Ingreso / Trámite</h2>
                <p id="oi-subtitle">Registrar trámite o servicio adicional</p>
            </div>
        </div>
        <button id="oi-close-btn" onclick="OI.cerrar()" title="Cerrar">✕</button>
    </div>

    {{-- IVA aviso --}}
    <div id="oi-iva-aviso">🏷️ Este cliente o empresa aplica IVA — se calculará automáticamente sobre admon + asesor.</div>

    {{-- CONTROLES —  Estado / Mes / Año --}}
    <div id="oi-controls">
        <div class="oi-ctrl-group">
            <span class="oi-ctrl-label">Estado</span>
            <select id="oi-estado" class="oi-ctrl-sel" onchange="OI.onEstado()">
                <option value="pagada">✅ Pagado</option>
                <option value="pre_factura">📋 Pre-factura</option>
                <option value="prestamo">💳 Préstamo</option>
            </select>
        </div>
        <div class="oi-ctrl-group">
            <span class="oi-ctrl-label">Mes</span>
            <select id="oi-mes" class="oi-ctrl-sel">
                @foreach($meses_oi as $i => $m)
                <option value="{{ $i+1 }}" {{ ($i+1) == $oiMesD ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="oi-ctrl-group">
            <span class="oi-ctrl-label">Año</span>
            <select id="oi-anio" class="oi-ctrl-sel">
                @for($y = now()->year - 1; $y <= now()->year + 2; $y++)
                <option value="{{ $y }}" {{ $y == $oiAnioD ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
        {{-- Cédula del cliente (visible solo cuando no hay cédula fija) --}}
        <div class="oi-ctrl-group" id="oi-cedula-wrap" style="display:none;">
            <span class="oi-ctrl-label">Cédula cliente *</span>
            <input type="text" id="oi-cedula-inp" class="oi-ctrl-inp"
                placeholder="Ej: 1234567890" style="width:140px;font-family:monospace;font-weight:700;">
        </div>
    </div>

    {{-- BODY 2 COLUMNAS --}}
    <div id="oi-body">

        {{-- COL IZQ — Descripción + Valores --}}
        <div id="oi-col-valores">
            <div class="oi-col-title">📋 Detalle del trámite</div>

            {{-- Descripción --}}
            <div id="oi-desc-wrap">
                <label style="font-size:.62rem;font-weight:800;color:#065f46;text-transform:uppercase;display:block;margin-bottom:.2rem;">
                    Descripción del trámite *
                </label>
                <textarea id="oi-desc" placeholder="Ej: Traslado EPS, Inclusión de beneficiario..."></textarea>
            </div>

            <hr class="oi-divider">

            {{-- Admon Empresa --}}
            <div class="oi-edit-row">
                <label class="oi-edit-lbl" for="oi-admon">🏢 Admon empresa</label>
                <input type="text" id="oi-admon" class="oi-edit-inp" value="0" oninput="OI.recalc()">
            </div>
            {{-- Admon Asesor --}}
            <div class="oi-edit-row" style="margin-top:.12rem;">
                <label class="oi-edit-lbl" for="oi-asesor">💼 Asesor
                    <span id="oi-asesor-nombre" style="font-size:.65rem;color:#6b7280;font-weight:400;"></span>
                </label>
                <input type="text" id="oi-asesor" class="oi-edit-inp" value="0" oninput="OI.recalc()">
            </div>
            {{-- IVA (read-only) --}}
            <div class="oi-edit-row" style="margin-top:.12rem;">
                <span class="oi-edit-lbl">
                    IVA <span class="oi-iva-badge" id="oi-pct-iva"></span>
                </span>
                <span id="oi-iva-val" style="font-size:.75rem;font-weight:700;color:#92400e;font-family:monospace;">$0</span>
            </div>
            <hr class="oi-divider">
            {{-- Total --}}
            <div class="oi-total-box">
                <span class="oi-total-label">Total a pagar</span>
                <span id="oi-total" class="oi-total-val">$0</span>
            </div>
        </div>

        {{-- COL DER — Pagos --}}
        <div id="oi-col-pagos">

            {{-- Pendiente --}}
            <div class="oi-pendiente-box">
                <div>
                    <div class="oi-pendiente-label">Saldo pendiente</div>
                    <div style="font-size:.62rem;color:#10b981;margin-top:.08rem;">Total − pagos recibidos</div>
                </div>
                <span id="oi-pendiente" style="color:#dc2626;">$0</span>
            </div>

            {{-- Consignaciones --}}
            <div class="oi-pagos-sec">
                <div class="oi-pagos-sec-hdr">
                    <span>🏦 Consignaciones</span>
                    <button class="oi-consig-add-btn" onclick="OI.addConsig()">＋ Agregar</button>
                </div>
                <div style="display:grid;grid-template-columns:minmax(0,1fr) 62px 92px 26px 20px;gap:.22rem;padding:.22rem .5rem .15rem;background:#f8fafc;border-bottom:1px solid #f1f5f9;">
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Banco</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:right;">Valor</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:center;">Fecha</span>
                    <span style="font-size:.57rem;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:center;">Adj.</span>
                    <span></span>
                </div>
                <div id="oi-consig-list"></div>
            </div>

            {{-- Efectivo --}}
            <div class="oi-pagos-sec" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #3b82f6;border-radius:10px;">
                <div class="oi-pagos-sec-hdr" style="color:#1d4ed8;font-weight:800;font-size:.82rem;">💵 Efectivo</div>
                <div style="padding:.4rem .7rem .55rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                    <span style="font-size:.78rem;font-weight:700;color:#1e40af;">Valor en efectivo</span>
                    <input type="text" id="oi-efectivo" value="0" oninput="OI.recalc()"
                        style="width:130px;padding:.45rem .65rem;border:2px solid #3b82f6;border-radius:8px;
                               font-size:1rem;font-weight:800;color:#1d4ed8;text-align:right;
                               background:#fff;outline:none;box-shadow:0 2px 8px rgba(59,130,246,.15);">
                </div>
            </div>

            {{-- Préstamo (solo si estado=prestamo) --}}
            <div id="oi-prest-wrap" style="display:none;" class="oi-pagos-sec">
                <div class="oi-pagos-sec-hdr"><span>💳 Valor Préstamo</span></div>
                <div class="oi-pago-row">
                    <span class="oi-pago-lbl">Monto del préstamo</span>
                    <input type="text" id="oi-prestamo" value="0" oninput="OI.recalc()" class="oi-pago-inp">
                </div>
            </div>

            {{-- Observación --}}
            <div>
                <div style="font-size:.6rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.25rem;">
                    🗒 Observación
                </div>
                <input type="text" id="oi-obs" class="oi-obs-inp" placeholder="Nota opcional...">
            </div>

        </div>{{-- /oi-col-pagos --}}
    </div>{{-- /oi-body --}}

    {{-- FOOTER --}}
    <div id="oi-footer">
        <button class="oi-btn-cancel" onclick="OI.cerrar()">Cancelar</button>
        <button class="oi-btn-guardar" id="oi-btn-guardar" onclick="OI.guardar()">💼 Registrar trámite</button>
    </div>

</div>
</div>

{{-- ═══════════ MINI-MODAL: soporte de pago de la consignación ═══════════ --}}
<div id="oiadj-overlay" onclick="if(event.target===this)OI._cerrarAdjunto()">
<div id="oiadj-box" onclick="event.stopPropagation()">
    <div id="oiadj-hdr">
        <div>
            <h3>📎 Soporte de pago</h3>
            <p>Comprobante de la consignación</p>
        </div>
        <button id="oiadj-close" type="button" onclick="OI._cerrarAdjunto()">✕</button>
    </div>
    <div id="oiadj-body">
        <div id="oiadj-zona" tabindex="0">
            <div style="font-size:1.6rem;line-height:1">📋</div>
            <div id="oiadj-zona-t1">Pega aquí con Ctrl+V</div>
            <div id="oiadj-zona-t2">o arrastra la imagen / PDF del comprobante</div>
        </div>
        <button id="oiadj-file-btn" type="button">📁 Elegir archivo</button>
        <input type="file" id="oiadj-file-inp" style="display:none"
               accept="image/jpeg,image/png,image/webp,application/pdf">
        <div id="oiadj-prev">
            <img id="oiadj-prev-img" alt="Soporte">
            <div id="oiadj-prev-name"></div>
        </div>
    </div>
    <div id="oiadj-footer">
        <button id="oiadj-quitar" type="button" onclick="OI._quitarAdjunto()">Quitar</button>
        <button id="oiadj-cancel" type="button" onclick="OI._cerrarAdjunto()">Cancelar</button>
        <button id="oiadj-ok" type="button" disabled onclick="OI._confirmarAdjunto()">✅ Confirmar soporte</button>
    </div>
</div>
</div>

<script>
// Bancos disponibles
window._OI_BANCOS = [
    {id:'', label:'-- Seleccionar banco --'},
    @foreach($oiBancos as $b)
    {id:{{ $b->id }}, label:{!! json_encode($b->nombre . ' — ' . $b->tipo_cuenta) !!}},
    @endforeach
];

const OI = (() => {
    const fmt      = v => '$' + Math.round(v||0).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    const parse    = s => parseInt((s||'0').replace(/[^0-9]/g,''))||0;

    // Estado interno
    let _cedula    = null;
    let _empresaId = null;
    let _aplicaIva = false;
    let _pctIva    = 0;   // porcentaje IVA (ej: 19)
    let _urlStore  = '{{ route("admin.facturacion.otro_ingreso.store") }}';
    let _csrf      = document.querySelector('meta[name="csrf-token"]').content;
    let _urlConsigImg = '{{ route("admin.facturacion.consignacion.imagen.subir", ["id"=>"__ID__"]) }}';

    function _el(id)  { return document.getElementById(id); }
    function _val(id) { return parse(_el(id)?.value); }

    // ── Abrir modal ─────────────────────────────────────────────────
    function abrir({ cedula, empresaId, subtitulo, aplicaIva, pctIva, mes, anio, asesorNombre }) {
        _cedula    = cedula    || null;
        _empresaId = empresaId || null;
        _aplicaIva = !!aplicaIva;
        _pctIva    = pctIva || 19;

        // Resetear campos
        _el('oi-desc').value     = '';
        _el('oi-admon').value    = '0';
        _el('oi-asesor').value   = '0';
        _el('oi-efectivo').value = '0';
        _el('oi-prestamo').value = '0';
        _el('oi-obs').value      = '';
        _el('oi-consig-list').innerHTML = '';

        // Campo cédula: visible solo si no viene pre-cargada
        const cedulaWrap = _el('oi-cedula-wrap');
        const cedulaInp  = _el('oi-cedula-inp');
        if (_cedula) {
            cedulaWrap.style.display = 'none';
            cedulaInp.value = _cedula;
        } else {
            cedulaWrap.style.display = 'flex';
            cedulaInp.value = '';
        }

        // Asesor
        _el('oi-asesor-nombre').textContent = asesorNombre ? '— ' + asesorNombre : '';

        // IVA
        _el('oi-pct-iva').textContent = _aplicaIva ? _pctIva + '%' : '';
        _el('oi-iva-aviso').style.display = _aplicaIva ? 'block' : 'none';

        // Subtítulo
        if (subtitulo) _el('oi-subtitle').textContent = subtitulo;

        // Periodo
        if (mes)  _el('oi-mes').value  = mes;
        if (anio) _el('oi-anio').value = anio;

        recalc();
        _el('oi-overlay').style.display = 'flex';
        _initPaste();
    }

    function cerrar() {
        _el('oi-overlay').style.display = 'none';
        _cerrarAdjunto();
        if (document._oiPasteHandler) {
            document.removeEventListener('paste', document._oiPasteHandler);
            document._oiPasteHandler = null;
        }
    }

    // ── Recalc totales ───────────────────────────────────────────────
    function recalc() {
        const admon   = _val('oi-admon');
        const asesor  = _val('oi-asesor');
        const base    = admon + asesor;

        let iva = 0;
        if (_aplicaIva && base > 0) {
            iva = Math.ceil(base * _pctIva / 100 / 100) * 100;
        }
        const total = base + iva;

        _el('oi-iva-val').textContent = fmt(iva);

        // consignaciones
        const consigSum = [..._el('oi-consig-list').querySelectorAll('.oi-monto-inp')]
            .reduce((s, i) => s + parse(i.value), 0);
        const efectivo = _val('oi-efectivo');
        const prestamo = _val('oi-prestamo');
        const pagado   = consigSum + efectivo + prestamo;
        const pendiente = total - pagado;

        _el('oi-total').textContent    = fmt(total);
        _el('oi-pendiente').textContent = fmt(Math.max(0, pendiente));
        _el('oi-pendiente').style.color = pendiente <= 0 ? '#16a34a' : '#dc2626';
    }

    // ── Estado → mostrar/ocultar préstamo ───────────────────────────
    function onEstado() {
        const est = _el('oi-estado').value;
        _el('oi-prest-wrap').style.display = est === 'prestamo' ? 'block' : 'none';
    }

    // ── Agregar fila consignación ────────────────────────────────────
    function addConsig() {
        const list = _el('oi-consig-list');
        const row  = document.createElement('div');
        row.className = 'oi-consig-row';

        const opts = window._OI_BANCOS.map(b =>
            `<option value="${b.id}">${b.label}</option>`
        ).join('');

        row.innerHTML = `
            <select class="oi-consig-sel">${opts}</select>
            <input type="text"  class="oi-consig-monto oi-monto-inp" placeholder="0" oninput="OI.recalc()">
            <input type="date"  class="oi-consig-fecha" value="${new Date().toISOString().slice(0,10)}">
            <label class="oi-consig-img-lbl" tabindex="0" title="📎 Adjuntar soporte de pago">
                <span class="oi-consig-img-icon">📎</span>
                <input type="file" class="oi-consig-img-inp"
                       accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none">
            </label>
            <button class="oi-consig-del" onclick="this.closest('.oi-consig-row').remove();OI.recalc()">✕</button>
        `;
        // Click en el 📎 → mini-modal de adjunto (no el file picker nativo)
        row.querySelector('.oi-consig-img-lbl').addEventListener('click', function (e) {
            e.preventDefault();
            _abrirAdjunto(row);
        });
        list.appendChild(row);
        recalc();
        return row;
    }

    // ── Soporte de pago por consignación ────────────────────────────
    // Guarda el File en el input de la fila (o en _pastedFile si el
    // navegador no soporta DataTransfer) y marca el 📎 en verde.
    function _setFile(row, file) {
        if (!row || !file) return;
        const inp  = row.querySelector('.oi-consig-img-inp');
        const icon = row.querySelector('.oi-consig-img-icon');
        const lbl  = row.querySelector('.oi-consig-img-lbl');
        if (!inp) return;
        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            inp.files = dt.files;
            inp._pastedFile = null;
        } catch (_) {
            inp._pastedFile = file;
        }
        if (icon) icon.textContent = '🖼️';
        if (lbl) {
            lbl.classList.add('tiene');
            lbl.title = '✅ ' + (file.name || 'soporte') + ' — Click para cambiar';
        }
    }

    function _getFile(row) {
        const inp = row ? row.querySelector('.oi-consig-img-inp') : null;
        if (!inp) return null;
        if (inp.files && inp.files[0]) return inp.files[0];
        return inp._pastedFile || null;
    }

    function _clearFile(row) {
        const inp  = row.querySelector('.oi-consig-img-inp');
        const icon = row.querySelector('.oi-consig-img-icon');
        const lbl  = row.querySelector('.oi-consig-img-lbl');
        if (inp) { inp.value = ''; inp._pastedFile = null; }
        if (icon) icon.textContent = '📎';
        if (lbl) { lbl.classList.remove('tiene'); lbl.title = '📎 Adjuntar soporte de pago'; }
    }

    // Mini-modal: pegar (Ctrl+V), arrastrar o elegir archivo
    let _adjRow = null, _adjFile = null;

    function _abrirAdjunto(row) {
        _adjRow  = row;
        _adjFile = _getFile(row);
        const zona = _el('oiadj-zona');
        const prev = _el('oiadj-prev');
        const img  = _el('oiadj-prev-img');
        const nom  = _el('oiadj-prev-name');
        const ok   = _el('oiadj-ok');

        if (_adjFile) {
            _pintarPrev(_adjFile);
        } else {
            zona.style.display = 'flex';
            prev.style.display = 'none';
            img.src = ''; nom.textContent = '';
            ok.disabled = true;
        }
        _el('oiadj-overlay').style.display = 'flex';
        setTimeout(() => zona.focus(), 80);
    }

    function _pintarPrev(file) {
        _adjFile = file;
        const img = _el('oiadj-prev-img');
        const nom = _el('oiadj-prev-name');
        if (file.type === 'application/pdf') {
            img.style.display = 'none';
            nom.textContent = '📄 ' + file.name;
        } else {
            img.src = URL.createObjectURL(file);
            img.style.display = 'block';
            nom.textContent = '✅ ' + (file.name || 'imagen_pegada.png');
        }
        _el('oiadj-zona').style.display = 'none';
        _el('oiadj-prev').style.display = 'block';
        _el('oiadj-ok').disabled = false;
    }

    function _cerrarAdjunto() {
        _el('oiadj-overlay').style.display = 'none';
        _adjRow = null; _adjFile = null;
    }

    function _confirmarAdjunto() {
        if (_adjRow && _adjFile) _setFile(_adjRow, _adjFile);
        _cerrarAdjunto();
    }

    function _quitarAdjunto() {
        if (_adjRow) _clearFile(_adjRow);
        _cerrarAdjunto();
    }

    // Ctrl+V global mientras el modal de trámite está abierto:
    // la imagen va a la última consignación (creando una si no hay).
    function _initPaste() {
        if (document._oiPasteHandler) {
            document.removeEventListener('paste', document._oiPasteHandler);
        }
        document._oiPasteHandler = function (e) {
            const ov = _el('oi-overlay');
            if (!ov || ov.style.display === 'none') return;

            const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image/'));
            if (!item) return;

            // Si el mini-modal de adjunto está abierto, la imagen es para él
            const adjOv = _el('oiadj-overlay');
            if (adjOv && adjOv.style.display === 'flex') {
                e.preventDefault();
                _pintarPrev(item.getAsFile());
                return;
            }

            // No interferir si se está escribiendo en un campo
            const tag  = document.activeElement?.tagName?.toLowerCase();
            const tipo = document.activeElement?.type?.toLowerCase();
            if ((tag === 'input' && tipo !== 'file') || tag === 'textarea' || tag === 'select') return;

            e.preventDefault();
            const rows = [..._el('oi-consig-list').querySelectorAll('.oi-consig-row')];
            const row  = rows[rows.length - 1] || addConsig();
            if (!row) return;
            _setFile(row, item.getAsFile());
            row.scrollIntoView({ behavior:'smooth', block:'nearest' });
        };
        document.addEventListener('paste', document._oiPasteHandler);
    }

    // ── Subir los soportes ya creada la factura ─────────────────────
    async function _subirSoportes(consigIds, rows) {
        const subidas = [];
        rows.forEach((row, idx) => {
            const file = _getFile(row);
            const id   = consigIds && consigIds[idx];
            if (!file || !id) return;
            const fd = new FormData();
            fd.append('imagen', file);
            fd.append('_token', _csrf);
            subidas.push(fetch(_urlConsigImg.replace('__ID__', id), { method:'POST', body: fd }));
        });
        if (subidas.length) await Promise.all(subidas);
    }

    // ── Guardar ─────────────────────────────────────────────────────
    async function guardar() {
        // Obtener cédula: preestablecida o del input
        const cedulaFinal = _cedula || parseInt(_el('oi-cedula-inp')?.value.replace(/\D/g,''));
        if (!cedulaFinal) { alert('Ingrese la cédula del cliente.'); _el('oi-cedula-inp')?.focus(); return; }

        const desc = _el('oi-desc').value.trim();
        if (!desc) { alert('La descripción del trámite es obligatoria.'); _el('oi-desc').focus(); return; }

        const admon  = _val('oi-admon');
        const asesor = _val('oi-asesor');
        if (admon <= 0 && asesor <= 0) { alert('Ingrese al menos el valor de admon o asesor.'); return; }

        // Consignaciones — se guardan también las filas para poder subir el
        // soporte de cada una: el backend crea una consignación por elemento
        // de `consigs`, en ese mismo orden.
        const consigs    = [];
        const consigRows = [];
        _el('oi-consig-list').querySelectorAll('.oi-consig-row').forEach(row => {
            const banco = row.querySelector('.oi-consig-sel').value;
            const valor = parse(row.querySelector('.oi-monto-inp').value);
            const fecha = row.querySelector('.oi-consig-fecha').value;
            if (banco && valor > 0) { consigs.push({ banco_cuenta_id: banco, valor, fecha }); consigRows.push(row); }
        });

        // Soporte de pago: se pide, no se obliga (igual que en facturas)
        const sinSoporte = consigRows.filter(r => !_getFile(r)).length;
        if (sinSoporte > 0) {
            const msg = sinSoporte === 1
                ? 'Hay 1 consignación sin soporte de pago adjunto.\n\n¿Registrar el trámite de todas formas?'
                : `Hay ${sinSoporte} consignaciones sin soporte de pago adjunto.\n\n¿Registrar el trámite de todas formas?`;
            if (!confirm(msg)) return;
        }

        const body = {
            cedula:              cedulaFinal,
            descripcion_tramite: desc,
            mes:                 parseInt(_el('oi-mes').value),
            anio:                parseInt(_el('oi-anio').value),
            valor_admon:         admon,
            valor_asesor:        asesor,
            forma_pago:          consigs.length > 0 && _val('oi-efectivo') > 0 ? 'mixto'
                               : consigs.length > 0 ? 'consignacion'
                               : _el('oi-estado').value === 'prestamo' ? 'prestamo'
                               : 'efectivo',
            estado:              _el('oi-estado').value,
            valor_efectivo:      _val('oi-efectivo'),
            valor_prestamo:      _val('oi-prestamo'),
            consignaciones:      consigs,
            empresa_id:          _empresaId,
            observacion:         _el('oi-obs').value.trim() || null,
        };

        const btn = _el('oi-btn-guardar');
        btn.disabled = true;
        btn.textContent = '⏳ Guardando...';

        try {
            const res  = await fetch(_urlStore, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': _csrf },
                body: JSON.stringify(body),
            });
            const data = await res.json();

            if (data.ok) {
                // Subir los soportes de pago adjuntos a cada consignación
                try {
                    await _subirSoportes(data.consignacion_ids, consigRows);
                } catch (e) {
                    console.error(e);
                    alert('El trámite se registró, pero falló la subida de algún soporte de pago.\nPuedes adjuntarlo después desde el historial.');
                }
                cerrar();
                if (data.recibo_url) {
                    if (typeof abrirRecibo === 'function') {
                        // Abrir recibo en el modal iframe de la misma pestaña.
                        // Al cerrar el modal de recibo se recargará la página.
                        abrirRecibo(data.recibo_url + '?modal=1');
                        // Parchar el botón de cerrar del modal recibo para recargar al salir
                        const _orig = window.cerrarRecibo;
                        window.cerrarRecibo = function() {
                            if (typeof _orig === 'function') _orig();
                            window.cerrarRecibo = _orig; // restaurar
                            location.reload();
                        };
                    } else {
                        window.open(data.recibo_url, '_blank');
                        location.reload();
                    }
                } else {
                    location.reload();
                }
            } else {
                alert(data.mensaje || data.message || 'Error al registrar el trámite.');
            }
        } catch(e) {
            console.error(e);
            alert('Error de conexión al guardar el trámite.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '💼 Registrar trámite';
        }
    }

    // ── Eventos del mini-modal de adjunto (su HTML es estático) ─────
    (function _bindAdjunto() {
        const zona = _el('oiadj-zona');
        const inp  = _el('oiadj-file-inp');
        const btn  = _el('oiadj-file-btn');
        if (!zona || !inp || !btn) return;

        zona.addEventListener('click', () => zona.focus());
        zona.addEventListener('paste', (e) => {
            const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image/'));
            if (item) { e.preventDefault(); e.stopPropagation(); _pintarPrev(item.getAsFile()); }
        });
        zona.addEventListener('dragover',  (e) => { e.preventDefault(); zona.classList.add('drag'); });
        zona.addEventListener('dragleave', () => zona.classList.remove('drag'));
        zona.addEventListener('drop', (e) => {
            e.preventDefault(); zona.classList.remove('drag');
            const f = e.dataTransfer?.files?.[0];
            if (f) _pintarPrev(f);
        });

        btn.addEventListener('click', () => inp.click());
        inp.addEventListener('change', function () {
            if (this.files && this.files[0]) _pintarPrev(this.files[0]);
            this.value = '';
        });
    })();

    return {
        abrir, cerrar, recalc, onEstado, addConsig, guardar,
        _cerrarAdjunto, _confirmarAdjunto, _quitarAdjunto,
    };
})();
</script>
