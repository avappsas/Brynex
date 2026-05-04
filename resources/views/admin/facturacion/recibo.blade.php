@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.app')
@section('modulo','Recibo de Pago')

@php
use Carbon\Carbon;
$meses=['Enero','Febrero','Marzo','Abril','Mayo','Junio',
        'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fmt   = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
$esGrupo = $grupoNp && $grupoNp->count() > 0;
$filas   = $esGrupo ? $grupoNp : collect([$factura]);

// Empresa (factura siempre a nombre de la empresa, no del trabajador)
$empresaObj = null;
if ($esGrupo) {
    $codEmp = $filas->first()->contrato?->cliente?->cod_empresa;
    if ($codEmp) $empresaObj = \App\Models\Empresa::find($codEmp);
}

// Totales del grupo
$totSS=$totAdmon=$totSeg=$totAfil=$totIva=$totTotal=$totPrest=0;
$totEfect=$totConsig=$totBanco2=0;
foreach ($filas as $f) {
    $totSS    += (int)($f->total_ss ?? 0);
    $totAdmon += (int)($f->admon ?? 0) + (int)($f->admin_asesor ?? 0);
    $totSeg   += (int)($f->seguro ?? 0);
    $totAfil  += (int)($f->afiliacion ?? 0);
    $totIva   += (int)($f->iva ?? 0);
    $totTotal += (int)($f->total ?? 0);
    $totPrest += (int)($f->valor_prestamo ?? 0);
    $totEfect += (int)($f->valor_efectivo ?? 0);
    $totConsig+= (int)($f->valor_consignado ?? 0);
    $totBanco2+= (int)($f->valor_banco2 ?? 0);
}
// Si estado es préstamo y valor_prestamo=0, calcularlo como total - lo recibido
if ($factura->estado === 'prestamo' && $totPrest === 0) {
    $totPrest = max(0, $totTotal - $totEfect - $totConsig);
}

$estadoLabel = fn($e) => match($e) {
    'pagada'      => 'PAGO',
    'pre_factura' => 'PRE-FACTURA',
    'prestamo'    => 'PRÉSTAMO',
    'abono'       => 'ABONO',
    default       => strtoupper($e)
};
$estadoCls = fn($e) => match($e) {
    'pagada'  => 'badge-pago',
    'prestamo'=> 'badge-prest',
    'abono'   => 'badge-abono',
    default   => 'badge-pre'
};
@endphp

@section('contenido')
<style>
/* ─── Google Fonts ───────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

/* ─── PRINT ──────────────────────────────────── */
@page {
    size: A4 landscape;
    margin: 8mm 10mm;
}
@media print {
    body * { visibility: hidden !important; }
    #recibo-print-area, #recibo-print-area * { visibility: visible !important; }
    #recibo-print-area {
        position: fixed; inset: 0;
        padding: 3mm 5mm; background: #fff; z-index: 9999;
        box-shadow: none !important;
    }
    .no-print { display: none !important; }
    .recibo-wrap { box-shadow: none !important; border-radius: 0 !important; border: none !important; overflow: visible !important; }
    .recibo-inner { margin: 0 !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; overflow: visible !important; }
    .recibo-inner-wrap { margin: 0 !important; overflow: visible !important; }
    .fact-header { border: none !important; border-radius: 0 !important; }
    .fact-sello { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .hoja-fondo { background: #fff !important; padding: 0 !important; }
    /* Colores de fondo se imprimen */
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    /* Vista simple: ocultar resumen/detalle al imprimir */
    .bloque-resumen { display: none !important; }
    .g-adm-row { display: none !important; }
    .g-adm-footer { display: none !important; }
    .g-val { display: none !important; }
    /* Vista detallada: mostrar todo si el wrapper tiene .det */
    #rw.det .bloque-resumen { display: grid !important; }
    #rw.det .g-adm-row { display: table-row !important; }
    #rw.det .g-adm-footer { display: table-row !important; }
    #rw.det .g-val { display: block !important; }
    #rw.det .col-valor-det { display: table-cell !important; }
    /* Tabla: auto-layout con fuente compacta para que entre en A4 landscape */
    .fact-table {
        table-layout: auto !important;
        width: 100% !important;
        font-size: .58rem !important;
    }
    .fact-table td, .fact-table th {
        overflow: visible !important;
        white-space: normal !important;
        word-break: normal !important;
        padding: .25rem .4rem !important;
    }
    .fact-table td.right, .fact-table tfoot td.right {
        white-space: nowrap !important;
    }
    /* Padding interno de la tabla no se corte */
    .fact-section-title + div[style*="padding"] {
        padding: 0 !important;
    }
}

/* ─── Fondo tipo hoja ─────────────────────────── */
.hoja-fondo {
    background: #e8edf2;
    padding: 1.5rem 1.2rem;
    min-height: 100vh;
}

/* ─── Base ───────────────────────────────────── */
#recibo-print-area, #recibo-print-area * { font-family: 'Inter', sans-serif; }
.recibo-wrap {
    max-width: 1150px; margin: 0 auto; background: #fff;
    border-radius: 6px;
    box-shadow:
        0 1px 3px rgba(0,0,0,.14),
        0 4px 14px rgba(0,0,0,.10),
        0 10px 40px rgba(0,0,0,.09),
        0 0 0 1px rgba(0,0,0,.06);
    overflow: hidden;
    border: 1px solid #c9d2dc;
    position: relative;
}

/* Padding interno: separa el contenido de los bordes del recuadro */
.recibo-inner {
    margin: 1rem 1.2rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(30,58,95,.07);
}

/* El header y bottom-bar del individual van dentro del recibo-inner-wrap con margen top/bot */
.recibo-inner-wrap {
    margin: 1rem 1.2rem 0;
    border-radius: 6px 6px 0 0;
    overflow: hidden;
}
/* El recibo-inner de datos se conecta sin brecha con el wrap del header */
.recibo-inner-wrap + .recibo-inner {
    margin-top: 0;
    border-top: none;
    border-radius: 0 0 6px 6px;
}

/* ─── BOTONES ─────────────────────────────────── */
.btn-a {
    padding: .4rem .9rem; border-radius: 7px; border: none;
    font-weight: 600; cursor: pointer; font-size: .82rem;
    text-decoration: none; font-family: 'Inter', sans-serif;
}

/* ─── BADGE ESTADO ───────────────────────────── */
.badge {
    display: inline-block; padding: .18rem .6rem;
    border-radius: 20px; font-size: .72rem; font-weight: 700;
}
.badge-pago  { background: #dcfce7; color: #15803d; }
.badge-pre   { background: #f1f5f9; color: #475569; }
.badge-prest { background: #ede9fe; color: #6d28d9; }
.badge-abono { background: #fef3c7; color: #92400e; }

/* ─── SELLO DIAGONAL (individual) ───────────── */
.fact-sello-wrap {
    position: absolute; top: 0; right: 0;
    width: 160px; height: 160px; overflow: hidden;
    pointer-events: none; z-index: 10;
}
.fact-sello {
    position: absolute; top: 32px; right: -32px;
    width: 170px; text-align: center;
    padding: 7px 0; font-size: .72rem; font-weight: 900;
    letter-spacing: .12em; text-transform: uppercase;
    transform: rotate(45deg);
    box-shadow: 0 3px 10px rgba(0,0,0,.25);
    border-radius: 3px;
}
.sello-pagado  { background: #15803d; color: #fff; }
.sello-pre     { background: #64748b; color: #fff; }
.sello-prest   { background: #7c3aed; color: #fff; }
.sello-abono   { background: #d97706; color: #fff; }

/* ─── CABECERA FACTURA ───────────────────────── */
.fact-header {
    display: grid;
    grid-template-columns: 1fr auto 220px;
    gap: 0;
    border-bottom: 3px solid #1e3a5f;
    padding: 0;
}
.fact-h-empresa {
    padding: 1rem 1.2rem;
    border-right: 1.5px solid #e2e8f0;
}
.fact-h-recibo {
    background: linear-gradient(135deg,#1e3a5f,#0f172a);
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.6rem 1.2rem;
    min-width: 200px;
    text-align: center;
}
.fact-h-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-left: 1.5px solid #e2e8f0;
    position: relative;
}

/* ─── DATOS CLIENTE ──────────────────────────── */
.fact-cliente {
    background: linear-gradient(to right, #f0f7ff, #fff);
    border-bottom: 1.5px solid #e2e8f0;
    padding: .65rem 1.2rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .2rem .6rem;
    font-size: .78rem;
}
.fact-cliente-row {
    display: flex;
    gap: .4rem;
    align-items: baseline;
    padding: .12rem 0;
    border-bottom: .5px solid #e9f0f8;
}
.fact-cliente-lbl {
    font-weight: 700;
    color: #1e3a5f;
    min-width: 90px;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    flex-shrink: 0;
}
.fact-cliente-val {
    color: #0f172a;
    font-weight: 600;
    font-size: .8rem;
}

/* ─── TABLA ENTIDADES (estilo factura) ───────── */
.fact-body { padding: 0; }
.fact-section-title {
    background: linear-gradient(90deg, #1e3a5f, #2563eb);
    color: #fff;
    font-size: .65rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: .35rem 1.2rem;
}
.fact-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .79rem;
}
.fact-table th {
    background: #e8f0fe;
    color: #1e3a5f;
    font-size: .62rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: .32rem .55rem;
    letter-spacing: .05em;
    border-bottom: 2px solid #c7d7f5;
    text-align: left;
    overflow: hidden;
    white-space: nowrap;
}
.fact-table th.right { text-align: right; }
.fact-table td {
    padding: .35rem .55rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    overflow: hidden;
    word-break: break-word;
}
.fact-table tbody tr:nth-child(odd) td  { background: #f8fafc; }
.fact-table tbody tr:nth-child(even) td { background: #ffffff; }
.fact-table tbody tr:hover td { background: #eff6ff; transition: background .15s; }
.fact-table td.right { text-align: right; font-family: monospace; font-weight: 700; white-space: nowrap; }
.fact-table td.entidad {
    font-weight: 700;
    color: #1d4ed8;
    font-size: .78rem;
}
.fact-table td.concepto {
    color: #334155;
    font-weight: 600;
}
.fact-table td.tag {
    font-size: .62rem;
    color: #64748b;
}
.fact-table tfoot td {
    background: #1e3a5f;
    color: #fff;
    font-weight: 800;
    padding: .45rem .55rem;
    font-size: .78rem;
}
.fact-table tfoot td.right {
    color: #93c5fd;
    font-family: monospace;
    font-size: .82rem;
    white-space: nowrap;
}

/* ─── PIE: Nota + Total ──────────────────────── */
.fact-footer-area {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0;
    border-top: 2px solid #1e3a5f;
    min-height: 64px;
}
.fact-nota {
    padding: .65rem 1rem;
    font-size: .68rem;
    color: #92400e;
    background: #fffbeb;
    border-right: 1.5px solid #fde68a;
    line-height: 1.55;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.fact-total-bloque {
    background: linear-gradient(135deg, #1e3a5f, #0f172a);
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: .75rem 1.5rem;
    min-width: 220px;
    gap: .1rem;
}
.fact-total-label {
    font-size: .63rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #93c5fd;
}
.fact-total-valor {
    font-size: 1.6rem;
    font-weight: 900;
    color: #fbbf24;
    font-family: monospace;
    letter-spacing: -.02em;
}

/* ─── DATOS PAGO (bajo tabla) ────────────────── */
.fact-pago-area {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border-top: 1.5px solid #e2e8f0;
}
.fact-pago-col {
    padding: .6rem 1.1rem;
    font-size: .76rem;
}
.fact-pago-col:first-child {
    border-right: 1.5px solid #e2e8f0;
}
.fact-pago-hdr {
    font-size: .61rem;
    font-weight: 800;
    color: #1e3a5f;
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: .35rem;
    padding-bottom: .2rem;
    border-bottom: 1.5px solid #bfdbfe;
}
.fact-pago-row {
    display: flex;
    justify-content: space-between;
    padding: .14rem 0;
    border-bottom: .5px solid #f1f5f9;
    color: #374151;
}
.fact-pago-row span:first-child { color: #64748b; }
.fact-pago-row strong { color: #0f172a; font-weight: 700; }
.fact-bottom-bar {
    background: #0f172a;
    color: #94a3b8;
    font-size: .65rem;
    padding: .45rem 1.2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ─── ALERTA PRÉSTAMO ────────────────────────── */
.alerta-prest {
    background: #fdf4ff; border: 2px solid #c4b5fd; border-radius: 0;
    padding: .55rem 1.2rem; display: flex; align-items: center; gap: .6rem;
    border-left: 4px solid #7c3aed; margin: 0;
}

/* ─── TABLA GRUPO (NP) ───────────────────────── */
.tbl { width:100%;border-collapse:collapse;font-size:.74rem }
.tbl th { background:#0f172a;color:#94a3b8;font-size:.61rem;text-transform:uppercase;
    padding:.38rem .42rem;white-space:nowrap;text-align:center }
.tbl td { padding:.28rem .42rem;border-bottom:1px solid #f1f5f9;vertical-align:top }
.tbl tbody tr:nth-child(even) td { background:#fafafa }
.tbl tfoot td { background:#0f172a;color:#fff;font-weight:700;padding:.4rem .42rem }
.n-r { text-align:right;font-family:monospace }
.tot-v { color:#34d399 }
.rec-body{ padding:1rem 1.4rem }
.box2 { display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin:.6rem 0 }
.ibox { background:#f8fafc;border-radius:8px;padding:.5rem .75rem;font-size:.79rem }
.ilbl { font-size:.63rem;color:#94a3b8;text-transform:uppercase;font-weight:700;margin-bottom:.25rem }
.srow { display:flex;justify-content:space-between;padding:.09rem 0 }
.total-bx { background:#0f172a;color:#fff;border-radius:8px;padding:.7rem 1rem;
    display:flex;justify-content:space-between;align-items:center;margin-top:.5rem }
.total-v { font-size:1.4rem;font-weight:900;color:#fbbf24 }

/* ─── MODO GRUPO: encabezado tipo rec original ── */
.rec-hdr { background:linear-gradient(135deg,#0f172a,#1e3a5f);color:#fff;
    padding:1rem 1.4rem;display:flex;justify-content:space-between;align-items:flex-start }
.rec-num { font-size:1.55rem;font-weight:900;color:#fbbf24 }

/* ─── Vista simple / detallada (individual y grupo) ─ */
/* Por defecto = vista simple */
.col-valor-det { display:none }
.bloque-resumen { display:none }
/* ── Grupo: valores bajo entidad ───────────── */
.g-val { display:none; font-size:.6rem; color:#64748b; font-style:italic; margin-top:.08rem; }
.g-adm-row { display:none }
.g-adm-footer { display:none }
/* Con clase .det: todo visible */
.det .col-valor-det { display:table-cell }
.det .bloque-resumen { display:grid }
.det .g-val { display:block }
.det .g-adm-row { display:table-row }
.det .g-adm-footer { display:table-row }
</style>

{{-- Botonera (no se imprime) --}}
<div class="no-print" style="max-width:1150px;margin:0 auto .65rem;display:flex;gap:.5rem;justify-content:flex-end;flex-wrap:wrap;">
    <button class="btn-a" id="btnToggleVista" style="background:#f1f5f9;color:#475569" onclick="toggleVistaDet()">📋 Vista detallada</button>
    <button class="btn-a" style="background:#0f172a;color:#fff" onclick="window.print()">🖨 Imprimir</button>
    @if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('superadmin'))
    <button class="btn-a" style="background:#dc2626;color:#fff" onclick="abrirAnular()">🗑 Anular</button>
    @endif
    @if(request()->boolean('modal'))
    <button class="btn-a" style="background:#64748b;color:#fff"
            onclick="if(window.parent && window.parent.cerrarRecibo){ window.parent.cerrarRecibo(); } else { window.close(); }">✕ Cerrar</button>
    @else
    <a href="{{ $factura->empresa_id
            ? route('admin.facturacion.empresa', ['id' => $factura->empresa_id, 'mes' => $factura->mes, 'anio' => $factura->anio])
            : route('admin.facturacion.index') }}"
       class="btn-a" style="background:#f1f5f9;color:#475569">← Volver</a>
    @endif
</div>

<div id="recibo-print-area" class="hoja-fondo">
<div class="recibo-wrap" id="rw">

{{-- ══ RECIBO EMPRESARIAL (modo NP) ═════════════════════════════════════ --}}
@if($esGrupo)
@php
$aliadoGObj  = \App\Models\Aliado::find($factura->aliado_id);
$logoAliadoG = $aliadoGObj?->logo ? asset('storage/'.$aliadoGObj->logo) : null;
$nomAliadoG  = $aliadoGObj?->nombre ?? $aliadoGObj?->razon_social ?? 'BryNex';
$numGrupo    = str_pad($filas->first()?->numero_factura ?? $factura->numero_factura, 6, '0', STR_PAD_LEFT);
@endphp

{{-- HEADER 3 COLUMNAS (igual que individual) --}}
<div class="recibo-inner-wrap">
<div class="fact-header" style="position:relative;overflow:hidden;border-radius:6px 6px 0 0;border:1px solid #e2e8f0;">

    {{-- Sello diagonal --}}
    <div class="fact-sello-wrap">
        <div class="fact-sello {{ $estadoCls($factura->estado) === 'badge-pago' ? 'sello-pagado' : ($estadoCls($factura->estado) === 'badge-prest' ? 'sello-prest' : ($estadoCls($factura->estado) === 'badge-abono' ? 'sello-abono' : 'sello-pre')) }}">
            {{ $estadoLabel($factura->estado) }}
        </div>
    </div>

    {{-- Col 1: Empresa --}}
    <div class="fact-h-empresa">
        @if($empresaObj)
            <div style="font-size:.55rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.04rem">Empresa</div>
            <div style="font-size:1.4rem;font-weight:900;color:#0f172a;line-height:1.15;letter-spacing:-.02em">{{ $empresaObj->empresa }}</div>
            <div style="font-size:.65rem;color:#64748b;margin-top:.05rem">NIT: {{ $empresaObj->nit ?? '—' }}</div>
        @else
            <div style="font-size:.55rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.04rem">Empresa</div>
            <div style="font-size:1.4rem;font-weight:900;color:#0f172a;line-height:1.15">{{ $filas->first()->contrato?->razonSocial?->razon_social ?? 'Empresa' }}</div>
        @endif
        <div style="font-size:.68rem;color:#64748b;margin-top:.3rem;display:flex;gap:.8rem;align-items:center">
            <span>Fecha: <strong style="color:#0f172a">{{ sqldate($factura->fecha_pago)->format('d/m/Y') }}</strong></span>
            <span style="background:#1e3a5f;color:#93c5fd;font-size:.6rem;font-weight:800;padding:.1rem .45rem;border-radius:20px">NP {{ $factura->np }}</span>
        </div>
    </div>

    {{-- Col 2: Número de recibo --}}
    <div class="fact-h-recibo">
        <div style="font-size:.58rem;font-weight:700;letter-spacing:.15em;color:#93c5fd;text-transform:uppercase;margin-bottom:.18rem">Recibo de Pago</div>
        <div style="font-size:2rem;font-weight:900;color:#fbbf24;letter-spacing:-.03em;line-height:1">
            {{ $numGrupo }}
        </div>
        <div style="margin-top:.35rem">
            <span class="badge {{ $estadoCls($factura->estado) }}">{{ $estadoLabel($factura->estado) }}</span>
        </div>
    </div>

    {{-- Col 3: Logo aliado --}}
    <div class="fact-h-logo">
        @if($logoAliadoG)
        <img src="{{ $logoAliadoG }}" alt="{{ $nomAliadoG }}" style="max-width:140px;max-height:70px;object-fit:contain">
        @else
        <img src="{{ asset('img/logo-brynex.png') }}" alt="BryNex" style="max-width:140px;max-height:70px;object-fit:contain">
        @endif
        <div style="font-size:.55rem;color:#64748b;text-align:center;margin-top:.35rem;font-weight:600;letter-spacing:.04em">
            {{ strtoupper($nomAliadoG) }}
        </div>
    </div>

</div>{{-- /fact-header --}}
</div>{{-- /recibo-inner-wrap --}}

{{-- CUERPO --}}
<div class="recibo-inner">

{{-- ALERTA PRÉSTAMO --}}
@if($totPrest > 0 || $factura->estado === 'prestamo')
<div class="alerta-prest">
    <span style="font-size:1.3rem">💳</span>
    <div>
        <div style="font-weight:700;color:#6d28d9;font-size:.84rem">Préstamo pendiente de cobro</div>
        <div style="font-size:.77rem;color:#7c3aed">
            Total: <strong>{{ $fmt($totTotal) }}</strong> &middot;
            Recibido: <strong>{{ $fmt($totTotal - $totPrest) }}</strong> &middot;
            Pendiente: <strong>{{ $fmt($totPrest) }}</strong>
        </div>
    </div>
</div>
@endif

{{-- TABLA TRABAJADORES --}}
<div class="fact-section-title">TRABAJADORES &mdash; {{ $filas->count() }} registros</div>
<div style="padding:0 .85rem">
<table class="fact-table" style="font-size:.72rem;table-layout:auto;width:100%">
<thead>
<tr>
    <th style="width:22px;text-align:center">No</th>
    <th style="width:18%">Nombre / CC</th>
    <th style="width:16%">Razón Social</th>
    <th style="width:36px;text-align:center">Días</th>
    <th style="width:11%">EPS</th>
    <th style="width:11%">ARL</th>
    <th style="width:13%">Pensión</th>
    <th style="width:11%">Caja</th>
    <th class="right" style="width:88px;white-space:nowrap">TOTAL</th>
</tr>
</thead>
<tbody>
@php $tEps=$tArl=$tPen=$tCaj=$tAdm=$tIva=$tOtros=0; @endphp
@foreach($filas as $idx => $f)
@php
$cli  = $f->contrato?->cliente;
$nom  = trim(($cli?->primer_nombre ?? '').' '.($cli?->primer_apellido ?? ''));
$rsG  = $f->contrato?->razonSocial?->razon_social ?? $f->razonSocial?->razon_social ?? null;
$enEpsG = $f->contrato?->eps?->nombre ?? '—';
$enArlNomG = null;
$enArlNitG = $f->contrato?->razonSocial?->arl_nit ?? null;
if ($enArlNitG) {
    $enArlNomG = \App\Models\Arl::where('nit', $enArlNitG)->value('nombre_arl') ?? $enArlNitG;
}
if (!$enArlNomG) { $enArlNomG = $f->contrato?->arl?->nombre_arl ?? '—'; }
$enArlNivelG = $f->contrato?->n_arl ?? '';
$enArlG = $enArlNomG . ($enArlNivelG ? ' N'.$enArlNivelG : '');
$enPenG = $f->contrato?->pension?->razon_social ?? '—';
$enCajG = $f->contrato?->caja?->nombre ?? $f->contrato?->caja?->razon_social ?? '—';
$vEpsG  = (int)($f->v_eps  ?? 0);
$vArlG  = (int)($f->v_arl  ?? 0);
$vPenG  = (int)($f->v_afp  ?? 0);
$vCajG  = (int)($f->v_caja ?? 0);
$vAdmG  = (int)($f->admon  ?? 0) + (int)($f->admin_asesor ?? 0);
$vIvaG  = (int)($f->iva    ?? 0);
$vOtrG  = (int)($f->mensajeria ?? 0) + (int)($f->otros ?? 0);
$diasG  = $f->dias_cotizados ?? 30;
$tEps += $vEpsG; $tArl += $vArlG; $tPen += $vPenG; $tCaj += $vCajG; $tAdm += $vAdmG;
$tIva += $vIvaG; $tOtros += $vOtrG;
@endphp
<tr>
    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:.72rem">{{ $idx+1 }}</td>
    <td>
        <div style="font-weight:700;font-size:.78rem;color:#0f172a">{{ $nom ?: '—' }}</div>
        <div style="font-size:.63rem;color:#94a3b8">CC {{ number_format($f->cedula,0,'','.') }}</div>
    </td>
    <td>
        @if($rsG)
            <span style="font-size:.7rem;font-weight:700;color:#1d4ed8">{{ $rsG }}</span>
        @else
            <span style="font-size:.65rem;font-weight:800;color:#15803d;text-transform:uppercase;letter-spacing:.05em">Independiente</span>
        @endif
    </td>
    <td style="text-align:center;font-weight:700;color:{{ $diasG < 30 ? '#d97706' : '#0f172a' }}">{{ $diasG }}</td>
    <td class="entidad" style="font-size:.72rem">
        {{ $enEpsG }}
        <div class="g-val">{{ $vEpsG > 0 ? $fmt($vEpsG) : '' }}</div>
    </td>
    <td class="entidad" style="font-size:.72rem;color:#15803d">
        {{ $enArlG }}
        <div class="g-val">{{ $vArlG > 0 ? $fmt($vArlG) : '' }}</div>
    </td>
    <td class="entidad" style="font-size:.72rem;color:#7c3aed">
        {{ $enPenG }}
        <div class="g-val">{{ $vPenG > 0 ? $fmt($vPenG) : '' }}</div>
    </td>
    <td class="entidad" style="font-size:.72rem;color:#0369a1">
        {{ $enCajG !== '—' ? $enCajG : 'Ninguna' }}
        <div class="g-val">{{ $vCajG > 0 ? $fmt($vCajG) : '' }}</div>
    </td>
    <td class="right" style="font-weight:800;color:#0f172a">${{ number_format($f->total,0,',','.') }}</td>
</tr>
@endforeach
</tbody>
@php
$tSS = $tEps + $tArl + $tPen + $tCaj;
@endphp
<tfoot>
{{-- Fila: TOTAL FACTURA (siempre visible) --}}
<tr style="background:#0f172a">
    <td colspan="8" style="font-size:.78rem;font-weight:800;color:#93c5fd;letter-spacing:.07em;padding:.7rem .55rem">TOTAL &mdash; {{ $filas->count() }} trabajadores</td>
    <td class="right" style="font-size:1.3rem;font-weight:900;color:#fbbf24;font-family:monospace;white-space:nowrap;padding:.7rem .55rem">${{ number_format($totTotal,0,',','.') }}</td>
</tr>
</tfoot>
</table>
</div>
</div>{{-- cierre div padding --}}
{{-- RESUMEN FINANCIERO + FORMA DE PAGO (solo visible en detallado) --}}
<div class="fact-pago-area bloque-resumen" style="margin:.75rem .85rem 0">

    {{-- Columna izquierda: Resumen Financiero --}}
    <div class="fact-pago-col">
        <div class="fact-pago-hdr">Resumen Financiero</div>
        @if($totSS > 0)
        <div class="fact-pago-row">
            <span>Seguridad Social</span>
            <strong>{{ $fmt($totSS) }}</strong>
        </div>
        @endif
        @if($totAdmon > 0)
        <div class="fact-pago-row">
            <span>Administración</span><strong>{{ $fmt($totAdmon) }}</strong>
        </div>
        @endif
        @if($totAfil > 0)
        <div class="fact-pago-row">
            <span>Afiliación</span><strong>{{ $fmt($totAfil) }}</strong>
        </div>
        @endif
        @if($totSeg > 0)
        <div class="fact-pago-row">
            <span>Seguro</span><strong>{{ $fmt($totSeg) }}</strong>
        </div>
        @endif
        @if($totIva > 0)
        <div class="fact-pago-row" style="color:#92400e">
            <span>IVA / 4×mil</span><strong>{{ $fmt($totIva) }}</strong>
        </div>
        @endif
        @php
        // saldo_proximo de la primera factura del grupo (anticipo o pendiente aplicado)
        $spGrupo = (int)(collect($filas)->sum(fn($f) => (int)($f->saldo_proximo ?? 0)));
        $saldoFavorG = $spGrupo < 0 ? abs($spGrupo) : 0; // consumió anticipo previo
        @endphp
        @if($saldoFavorG > 0)
        @php
        $mesAntG  = $factura->mes > 1 ? $factura->mes - 1 : 12;
        $anioAntG = $factura->mes > 1 ? $factura->anio : $factura->anio - 1;
        @endphp
        <div class="fact-pago-row" style="color:#15803d;border-top:1px solid #d1fae5;padding-top:.2rem;margin-top:.2rem">
            <span>✅ Anticipo aplicado <small style="font-size:.62rem">{{ $meses[$mesAntG-1] }} {{ $anioAntG }}</small></span>
            <strong>−{{ $fmt($saldoFavorG) }}</strong>
        </div>
        @endif
        @if($totPrest > 0)
        <div class="fact-pago-row" style="color:#dc2626;border-top:1px solid #fee2e2;padding-top:.2rem;margin-top:.2rem">
            <span>🔴 Recuper. préstamo</span>
            <strong>+{{ $fmt($totPrest) }}</strong>
        </div>
        @endif
        @if($factura->observacion)
        <div style="margin-top:.4rem;font-size:.68rem;color:#94a3b8;font-style:italic">{{ $factura->observacion }}</div>
        @endif
    </div>

    {{-- Columna derecha: Forma de Pago --}}
    <div class="fact-pago-col">
        <div class="fact-pago-hdr">Forma de Pago</div>
        <div class="fact-pago-row">
            <span>Tipo</span>
            <strong>{{ ucfirst(str_replace('_', ' ', $factura->forma_pago ?? '—')) }}</strong>
        </div>
        @if($totEfect > 0)
        <div class="fact-pago-row" style="color:#15803d">
            <span>💵 Efectivo</span><strong>{{ $fmt($totEfect) }}</strong>
        </div>
        @endif
        @foreach($factura->consignaciones as $csg)
        <div style="padding:.14rem 0;border-bottom:.5px solid #f1f5f9">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                    <span style="color:#1d4ed8;font-weight:600;font-size:.76rem">
                        🏦 {{ $csg->bancoCuenta?->nombre ?? 'Banco' }}
                        @if($csg->confirmado) <span style="color:#15803d;font-size:.62rem;font-weight:700">✓</span> @endif
                    </span>
                    @if($csg->imagen_path)
                    <a href="#"
                       onclick="verSoporte('{{ route('admin.facturacion.consignacion.imagen.ver', $csg->id) }}');return false;"
                       style="margin-left:.3rem;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;border-radius:4px;padding:0 5px;font-size:.58rem;text-decoration:none;vertical-align:middle">🖼️ soporte</a>
                    @endif
                    <div style="font-size:.62rem;color:#94a3b8">
                        {{ $csg->bancoCuenta?->tipo_cuenta }} {{ $csg->bancoCuenta?->numero_cuenta }}
                        · {{ sqldate($csg->fecha)->format('d/m/Y') }}
                        @if($csg->referencia) · Ref: {{ $csg->referencia }} @endif
                    </div>
                </div>
                <strong style="white-space:nowrap;font-size:.78rem">{{ $fmt($csg->valor) }}</strong>
            </div>
        </div>
        @endforeach
        @if($totPrest > 0)
        <div class="fact-pago-row" style="color:#7c3aed">
            <span>💳 Préstamo (pendiente)</span><strong>{{ $fmt($totPrest) }}</strong>
        </div>
        @endif
    </div>

</div>
{{-- NOTA LEGAL --}}
<div style="margin:.7rem .85rem 0;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:.5rem .85rem;font-size:.68rem;color:#92400e;line-height:1.5">
    <span style="font-weight:800">⚠️ IMPORTANTE &mdash;</span>
    Las incapacidades por enfermedad común o accidente laboral serán reconocidas por la EPS y ARL
    <strong>únicamente</strong> cuando los aportes se hayan realizado oportunamente,
    <strong>antes del décimo (10º) día hábil de cada mes</strong>.
</div>
<div style="height:.9rem"></div>
{{-- BARRA INFERIOR dentro del cuadro --}}
<div class="fact-bottom-bar" style="border-top:1px solid rgba(255,255,255,.07);margin: 0 0 0; border-radius:0">
    <span>{{ $nomAliadoG }} — Asesoría en Seguridad Social</span>
    <span style="font-size:.65rem;color:#94a3b8">Facturó: {{ $factura->usuario?->nombre ?? $factura->usuario?->name ?? 'Usuario' }} &nbsp;&middot;&nbsp; Impreso: {{ now()->format('d/m/Y H:i') }}</span>
</div>
</div>{{-- /recibo-inner --}}
@else
{{-- ══════════════════════════════════════════════════════════════════════
     VISTA INDIVIDUAL — Diseño Tipo Factura Premium
══════════════════════════════════════════════════════════════════════════ --}}
@php
$cli1   = $factura->contrato?->cliente;
if (!$cli1 && $factura->tipo === 'otro_ingreso') {
    $cli1 = \App\Models\Cliente::where('aliado_id', $factura->aliado_id)
        ->where('cedula', $factura->cedula)->first();
}
$nom1     = trim(($cli1?->primer_nombre ?? '').' '.($cli1?->segundo_nombre ?? '').' '.($cli1?->primer_apellido ?? '').' '.($cli1?->segundo_apellido ?? ''));
$rs1      = $factura->contrato?->razonSocial?->razon_social ?? $factura->razonSocial?->razon_social ?? null;
$arlNom   = $factura->contrato?->arl?->nombre_arl;
if (!$arlNom) {
    $arlNit = $factura->contrato?->razonSocial?->arl_nit;
    $arlNom = $arlNit ? (\App\Models\Arl::where('nit',$arlNit)->value('nombre_arl') ?? $arlNit) : null;
}
$arlNivel = $factura->contrato?->n_arl ?? '';
$cajaNom  = $factura->contrato?->caja?->nombre ?? $factura->contrato?->caja?->razon_social ?? null;
$penNom   = $factura->contrato?->pension?->razon_social ?? null;
$epsNom   = $factura->contrato?->eps?->nombre ?? null;
$vEps1    = (int)($factura->v_eps  ?? 0);
$vArl1    = (int)($factura->v_arl  ?? 0);
$vPen1    = (int)($factura->v_afp  ?? 0);
$vCaj1    = (int)($factura->v_caja ?? 0);
$vAdm1    = (int)($factura->admon  ?? 0) + (int)($factura->admin_asesor ?? 0);
$vSeg1    = (int)($factura->seguro ?? 0);
$vAfil1   = (int)($factura->afiliacion ?? 0);
$vMens1   = (int)($factura->mensajeria ?? 0);
$vOtros1  = (int)($factura->otros ?? 0);
$vIva1    = (int)($factura->iva ?? 0);
$dias1    = $factura->dias_cotizados ?? 30;

// Sello de estado
$selloTxt = match($factura->estado) {
    'pagada'      => 'PAGADO',
    'pre_factura' => 'PRE-FACT',
    'prestamo'    => 'PRÉSTAMO',
    'abono'       => 'ABONO',
    default       => strtoupper($factura->estado ?? '')
};
$selloCls = match($factura->estado) {
    'pagada'   => 'sello-pagado',
    'prestamo' => 'sello-prest',
    'abono'    => 'sello-abono',
    default    => 'sello-pre'
};

// Dirección/contacto del cliente
$dir1 = trim(($cli1?->direccion ?? ''));
$tel1 = trim(($cli1?->telefono ?? '') ?: ($cli1?->celular ?? ''));
$sal1 = (int)($factura->contrato?->salario ?? 0);

// Logo del aliado
$aliadoObj  = \App\Models\Aliado::find($factura->aliado_id);
$logoAliado = $aliadoObj?->logo ? asset('storage/'.$aliadoObj->logo) : null;
$nomAliado  = $aliadoObj?->nombre ?? $aliadoObj?->razon_social ?? 'BryNex';
@endphp

{{-- HEADER TIPO FACTURA (con margen superior) --}}
<div class="recibo-inner-wrap">
<div class="fact-header" style="position:relative;overflow:hidden;border-radius:6px 6px 0 0;border:1px solid #e2e8f0;">

    {{-- Sello diagonal --}}
    <div class="fact-sello-wrap">
        <div class="fact-sello {{ $selloCls }}">{{ $selloTxt }}</div>
    </div>

    {{-- Col 1: Afiliado / Trabaj. / Empresa --}}
    <div class="fact-h-empresa">
        @if($factura->tipo === 'otro_ingreso' && $factura->empresa)
            {{-- Otro ingreso de empresa --}}
            <div style="font-size:1.15rem;font-weight:900;color:#0f172a;line-height:1.1">{{ $factura->empresa->empresa }}</div>
            <div style="font-size:.68rem;color:#64748b;margin-top:.15rem">NIT: {{ $factura->empresa->nit ?? '—' }}</div>
        @elseif($rs1)
            {{-- Con razón social → DEPENDIENTE --}}
            <div style="font-size:1.1rem;font-weight:900;color:#0f172a;line-height:1.1">{{ $nom1 ?: 'CC '.$factura->cedula }}</div>
            <div style="font-size:.68rem;color:#64748b;margin-top:.12rem">C.C. {{ number_format($factura->cedula, 0, '', '.') }}</div>
            <div style="font-size:.65rem;font-weight:800;color:#1d4ed8;text-transform:uppercase;letter-spacing:.07em;margin-top:.2rem">Dependiente</div>
        @else
            {{-- Sin razón social → INDEPENDIENTE --}}
            <div style="font-size:1.1rem;font-weight:900;color:#0f172a;line-height:1.1">{{ $nom1 ?: 'CC '.$factura->cedula }}</div>
            <div style="font-size:.68rem;color:#64748b;margin-top:.12rem">C.C. {{ number_format($factura->cedula, 0, '', '.') }}</div>
            <div style="font-size:.65rem;font-weight:800;color:#15803d;text-transform:uppercase;letter-spacing:.07em;margin-top:.2rem">Independiente</div>
        @endif
    </div>

    {{-- Col 2: Solo Número de recibo (centrado) --}}
    <div class="fact-h-recibo">
        <div style="font-size:.58rem;font-weight:700;letter-spacing:.15em;color:#93c5fd;text-transform:uppercase;margin-bottom:.18rem">Recibo de Pago</div>
        <div style="font-size:2rem;font-weight:900;color:#fbbf24;letter-spacing:-.03em;line-height:1">
            {{ str_pad($factura->numero_factura, 6, '0', STR_PAD_LEFT) }}
        </div>
        <div style="margin-top:.35rem">
            <span class="badge {{ $estadoCls($factura->estado) }}">{{ $estadoLabel($factura->estado) }}</span>
        </div>
    </div>

    {{-- Col 3: Logo del aliado --}}
    <div class="fact-h-logo">
        @if($logoAliado)
        <img src="{{ $logoAliado }}" alt="{{ $nomAliado }}" style="max-width:140px;max-height:70px;object-fit:contain">
        @else
        <img src="{{ asset('img/logo-brynex.png') }}" alt="BryNex" style="max-width:140px;max-height:70px;object-fit:contain">
        @endif
        <div style="font-size:.55rem;color:#64748b;text-align:center;margin-top:.35rem;font-weight:600;letter-spacing:.04em">
            {{ strtoupper($nomAliado) }}
        </div>
    </div>

</div>{{-- /fact-header --}}
</div>{{-- /recibo-inner-wrap --}}

{{-- DATOS DEL CLIENTE (con margen interior) --}}
<div class="recibo-inner">
<div class="fact-cliente">
    @if($nom1 && $rs1)
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Trabajador</span>
        <span class="fact-cliente-val">{{ $nom1 }}</span>
    </div>
    @elseif($nom1)
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Nombres</span>
        <span class="fact-cliente-val">{{ $nom1 }}</span>
    </div>
    @endif

    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Cédula</span>
        <span class="fact-cliente-val">{{ number_format($factura->cedula, 0, '', '.') }}</span>
    </div>
    @if($tel1)
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Teléfono</span>
        <span class="fact-cliente-val">{{ $tel1 }}</span>
    </div>
    @endif
    @if($dir1)
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Dirección</span>
        <span class="fact-cliente-val">{{ $dir1 }}</span>
    </div>
    @endif
    @if($sal1 > 0)
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Salario IBC</span>
        <span class="fact-cliente-val" style="color:#1d4ed8">{{ $fmt($sal1) }}</span>
    </div>
    @endif
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Mes Liquidado</span>
        <span class="fact-cliente-val" style="color:#1d4ed8;font-weight:800">
            {{ $meses[$factura->mes-1] }}&nbsp;&nbsp;AÑO: {{ $factura->anio }}
        </span>
    </div>
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Período</span>
        <span class="fact-cliente-val" style="color:#0f172a;font-weight:700">
            {{ $meses[$factura->mes-1] }} {{ $factura->anio }}
        </span>
    </div>
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Facturó</span>
        <span class="fact-cliente-val" style="color:#64748b;font-size:.73rem">
            {{ $factura->usuario?->nombre ?? $factura->usuario?->name ?? ('Usuario #'.($factura->usuario_id ?? '?')) }}
        </span>
    </div>
    <div class="fact-cliente-row">
        <span class="fact-cliente-lbl">Fecha</span>
        <span class="fact-cliente-val" style="color:#64748b;font-size:.73rem">
            {{ sqldate($factura->fecha_pago)->format('d/m/Y') }}
        </span>
    </div>
</div>

{{-- ALERTA PRÉSTAMO --}}
@if($totPrest > 0 || $factura->estado === 'prestamo')
<div class="alerta-prest">
    <span style="font-size:1.3rem">💳</span>
    <div>
        <div style="font-weight:700;color:#6d28d9;font-size:.84rem">Préstamo pendiente de cobro</div>
        <div style="font-size:.77rem;color:#7c3aed">
            Total: <strong>{{ $fmt($totTotal) }}</strong> ·
            Recibido: <strong>{{ $fmt($totTotal - $totPrest) }}</strong> ·
            Pendiente: <strong>{{ $fmt($totPrest) }}</strong>
        </div>
    </div>
</div>
@endif

{{-- TABLA ENTIDADES --}}
<div class="fact-body">
<div class="fact-section-title">DESCRIPCIÓN DE SERVICIOS</div>

@if($factura->tipo === 'otro_ingreso')
{{-- ── Otro Ingreso ─────────────────────────────── --}}
<table class="fact-table">
<thead>
    <tr>
        <th style="width:40%">Concepto / Trámite</th>
        <th>Detalle</th>
        <th class="right" style="width:140px">Valor</th>
    </tr>
</thead>
<tbody>
    <tr>
        <td class="concepto" style="font-weight:800;color:#065f46">
            💼 {{ $factura->descripcion_tramite ?? 'Trámite / Servicio' }}
        </td>
        <td class="tag">
            @if($factura->empresa) Empresa: {{ $factura->empresa->empresa }} @endif
        </td>
        <td class="right" style="color:#0f172a">
            @if(($factura->admon ?? 0) > 0) {{ $fmt($factura->admon) }} @endif
        </td>
    </tr>
    @if(($factura->admon_asesor_oi ?? 0) > 0)
    <tr>
        <td class="concepto">Honorarios asesor</td>
        <td></td>
        <td class="right">{{ $fmt($factura->admon_asesor_oi) }}</td>
    </tr>
    @endif
    @if($vIva1 > 0)
    <tr>
        <td class="concepto">IVA</td>
        <td class="tag">Impuesto al valor agregado</td>
        <td class="right" style="color:#92400e">{{ $fmt($vIva1) }}</td>
    </tr>
    @endif
</tbody>
<tfoot>
    <tr>
        <td colspan="2" style="font-size:.75rem;letter-spacing:.06em">SUBTOTAL</td>
        <td class="right">{{ $fmt($totTotal) }}</td>
    </tr>
</tfoot>
</table>

@elseif($factura->tipo === 'afiliacion')
{{-- ── Afiliación ───────────────────────────────── --}}
<table class="fact-table">
<thead>
    <tr>
        <th style="width:30%">Descripción</th>
        <th>Entidad</th>
        <th class="right" style="width:140px">Valor</th>
    </tr>
</thead>
<tbody>
    @if($epsNom)
    <tr>
        <td class="concepto">EPS</td>
        <td class="entidad">{{ $epsNom }}</td>
        <td class="right">—</td>
    </tr>
    @endif
    @if($arlNom)
    <tr>
        <td class="concepto">ARL{{ $arlNivel ? ' · Riesgo '.$arlNivel : '' }}</td>
        <td class="entidad" style="color:#15803d">{{ $arlNom }}</td>
        <td class="right">—</td>
    </tr>
    @endif
    @if($penNom)
    <tr>
        <td class="concepto">PENSIÓN</td>
        <td class="entidad" style="color:#7c3aed">{{ $penNom }}</td>
        <td class="right">—</td>
    </tr>
    @endif
    @if($cajaNom)
    <tr>
        <td class="concepto">CAJA COMPENSACIÓN</td>
        <td class="entidad" style="color:#0369a1">{{ $cajaNom }}</td>
        <td class="right">—</td>
    </tr>
    @endif
    @if($vAdm1 > 0)
    <tr>
        <td class="concepto">ADMINISTRACIÓN</td>
        <td class="tag">Honorarios gestión BryNex</td>
        <td class="right">{{ $fmt($vAdm1) }}</td>
    </tr>
    @endif
    @if($vSeg1 > 0)
    <tr>
        <td class="concepto">SEGURO</td>
        <td class="tag"></td>
        <td class="right">{{ $fmt($vSeg1) }}</td>
    </tr>
    @endif
    @if($vIva1 > 0)
    <tr>
        <td class="concepto">IVA</td>
        <td class="tag">Impuesto al valor agregado</td>
        <td class="right" style="color:#92400e">{{ $fmt($vIva1) }}</td>
    </tr>
    @endif
    <tr style="background:#f0fdf4 !important">
        <td class="concepto" style="font-size:.7rem;color:#64748b;font-style:italic" colspan="3">
            ⚡ Trámite de afiliación ante entidades del Sistema de Seguridad Social
            @if($dias1 < 30) — <span style="color:#d97706;font-weight:700">{{ $dias1 }} días cotizados</span> @endif
        </td>
    </tr>
</tbody>
<tfoot>
    <tr>
        <td colspan="2" style="font-size:.75rem;letter-spacing:.06em">TOTAL AFILIACIÓN</td>
        <td class="right">{{ $fmt($totTotal) }}</td>
    </tr>
</tfoot>
</table>

@else
{{-- ── Planilla (Seguridad Social) ─────────────── --}}
<table class="fact-table">
<thead>
    <tr>
        <th style="width:26%">Descripción</th>
        <th>Entidad</th>
        <th style="width:70px;text-align:center">Días</th>
        <th class="right col-valor-det" style="width:130px">Valor</th>
    </tr>
</thead>
<tbody>
    @if($epsNom)
    <tr>
        <td class="concepto">EPS</td>
        <td class="entidad">{{ $epsNom }}</td>
        <td style="text-align:center;font-weight:700;color:{{ $dias1 < 30 ? '#d97706' : '#0f172a' }}">{{ $dias1 }}</td>
        <td class="right col-valor-det">{{ $vEps1 > 0 ? $fmt($vEps1) : '—' }}</td>
    </tr>
    @endif
    @if($arlNom)
    <tr>
        <td class="concepto">ARL{{ $arlNivel ? ' · Riesgo '.$arlNivel : '' }}</td>
        <td class="entidad" style="color:#15803d">{{ $arlNom }}</td>
        <td style="text-align:center;font-weight:700;color:{{ $dias1 < 30 ? '#d97706' : '#0f172a' }}">{{ $dias1 }}</td>
        <td class="right col-valor-det">{{ $vArl1 > 0 ? $fmt($vArl1) : '—' }}</td>
    </tr>
    @endif
    @if($penNom)
    <tr>
        <td class="concepto">PENSIÓN</td>
        <td class="entidad" style="color:#7c3aed">{{ $penNom }}</td>
        <td style="text-align:center;font-weight:700;color:{{ $dias1 < 30 ? '#d97706' : '#0f172a' }}">{{ $dias1 }}</td>
        <td class="right col-valor-det">{{ $vPen1 > 0 ? $fmt($vPen1) : '—' }}</td>
    </tr>
    @endif
    @if($cajaNom)
    <tr>
        <td class="concepto">CAJA COMPENSACIÓN</td>
        <td class="entidad" style="color:#0369a1">{{ $cajaNom !== '—' ? $cajaNom : 'NINGUNA' }}</td>
        <td style="text-align:center;color:#94a3b8">{{ $dias1 }}</td>
        <td class="right col-valor-det">{{ $vCaj1 > 0 ? $fmt($vCaj1) : '—' }}</td>
    </tr>
    @endif
    @if($vAdm1 > 0)
    <tr>
        <td class="concepto">ADMINISTRACIÓN</td>
        <td class="tag">Honorarios gestión {{ $nomAliado }}</td>
        <td></td>
        <td class="right col-valor-det">{{ $fmt($vAdm1) }}</td>
    </tr>
    @endif
    @if($vSeg1 > 0)
    <tr>
        <td class="concepto">SEGURO</td>
        <td class="tag"></td>
        <td></td>
        <td class="right col-valor-det">{{ $fmt($vSeg1) }}</td>
    </tr>
    @endif
    @if($vAfil1 > 0)
    <tr style="background:#f0fdf4 !important">
        <td class="concepto" style="color:#065f46">AFILIACIÓN</td>
        <td class="tag">Trámite de afiliación incluido</td>
        <td></td>
        <td class="right col-valor-det" style="color:#15803d">{{ $fmt($vAfil1) }}</td>
    </tr>
    @endif
    @if($vMens1 > 0)
    <tr>
        <td class="concepto">MENSAJERÍA</td>
        <td class="tag"></td>
        <td></td>
        <td class="right col-valor-det">{{ $fmt($vMens1) }}</td>
    </tr>
    @endif
    @if($vOtros1 > 0)
    <tr>
        <td class="concepto">OTROS</td>
        <td class="tag"></td>
        <td></td>
        <td class="right col-valor-det">{{ $fmt($vOtros1) }}</td>
    </tr>
    @endif
    @if($vIva1 > 0)
    <tr>
        <td class="concepto">IVA / 4×MIL</td>
        <td class="tag">Impuesto al valor agregado</td>
        <td></td>
        <td class="right col-valor-det" style="color:#92400e">{{ $fmt($vIva1) }}</td>
    </tr>
    @endif
</tbody>
<tfoot>
    <tr>
        <td colspan="2" style="font-size:.75rem;letter-spacing:.06em">SUBTOTAL</td>
        <td style="text-align:center;font-size:.7rem;color:#93c5fd;font-weight:600">
            @if($dias1 < 30)<span style="color:#fbbf24">{{ $dias1 }}d</span>@endif
        </td>
        <td class="right col-valor-det">{{ $fmt($totTotal) }}</td>
    </tr>
</tfoot>
</table>
@endif

{{-- BLOQUE PAGO (solo en vista detallada) --}}
<div class="fact-pago-area bloque-resumen">
    <div class="fact-pago-col">
        <div class="fact-pago-hdr">Resumen Financiero</div>
        @if($factura->tipo !== 'otro_ingreso')
        @if($vEps1+$vArl1+$vPen1+$vCaj1 > 0)
        <div class="fact-pago-row">
            <span>Seguridad Social</span>
            <strong>{{ $fmt($vEps1+$vArl1+$vPen1+$vCaj1) }}</strong>
        </div>
        @endif
        @endif
        @if($vAdm1 > 0)
        <div class="fact-pago-row">
            <span>Administración</span><strong>{{ $fmt($vAdm1) }}</strong>
        </div>
        @endif
        @if($vAfil1 > 0)
        <div class="fact-pago-row">
            <span>Afiliación</span><strong>{{ $fmt($vAfil1) }}</strong>
        </div>
        @endif
        @if($vSeg1 > 0)
        <div class="fact-pago-row">
            <span>Seguro</span><strong>{{ $fmt($vSeg1) }}</strong>
        </div>
        @endif
        @if($vIva1 > 0)
        <div class="fact-pago-row" style="color:#92400e">
            <span>IVA / 4×mil</span><strong>{{ $fmt($vIva1) }}</strong>
        </div>
        @endif
        @php
        // saldo_proximo: negativo = consumió anticipo (aplico a favor), positivo = generó anticipo
        $spIndiv = (int)($factura->saldo_proximo ?? 0);
        $saldoFavorMostrar = $spIndiv < 0 ? abs($spIndiv) : 0;
        @endphp
        @if($saldoFavorMostrar > 0)
        @php
        $mesAnt2  = $factura->mes > 1 ? $factura->mes - 1 : 12;
        $anioAnt2 = $factura->mes > 1 ? $factura->anio : $factura->anio - 1;
        @endphp
        <div class="fact-pago-row" style="color:#15803d;border-top:1px solid #d1fae5;padding-top:.2rem;margin-top:.2rem">
            <span>✅ Anticipo aplicado <small style="font-size:.62rem">{{ $meses[$mesAnt2-1] }} {{ $anioAnt2 }}</small></span>
            <strong>−{{ $fmt($saldoFavorMostrar) }}</strong>
        </div>
        @endif
        {{-- Saldo pendiente heredado ya no se almacena -- se omite esta fila --}}
        @if($factura->observacion)
        <div style="margin-top:.4rem;font-size:.68rem;color:#94a3b8;font-style:italic">{{ $factura->observacion }}</div>
        @endif
    </div>

    <div class="fact-pago-col">
        <div class="fact-pago-hdr">Forma de Pago</div>
        <div class="fact-pago-row">
            <span>Tipo</span>
            <strong>{{ ucfirst(str_replace('_', ' ', $factura->forma_pago ?? '—')) }}</strong>
        </div>
        @if($totEfect > 0)
        <div class="fact-pago-row" style="color:#15803d">
            <span>💵 Efectivo</span><strong>{{ $fmt($totEfect) }}</strong>
        </div>
        @endif
        @foreach($factura->consignaciones as $csg)
        <div style="padding:.14rem 0;border-bottom:.5px solid #f1f5f9">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                    <span style="color:#1d4ed8;font-weight:600;font-size:.76rem">
                        🏦 {{ $csg->bancoCuenta?->nombre ?? 'Banco' }}
                        @if($csg->confirmado) <span style="color:#15803d;font-size:.62rem;font-weight:700">✓</span> @endif
                    </span>
                    @if($csg->imagen_path)
                    <a href="#"
                       onclick="verSoporte('{{ route('admin.facturacion.consignacion.imagen.ver', $csg->id) }}');return false;"
                       style="margin-left:.3rem;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;border-radius:4px;padding:0 5px;font-size:.58rem;text-decoration:none;vertical-align:middle">🖼️ soporte</a>
                    @endif
                    <div style="font-size:.62rem;color:#94a3b8">
                        {{ $csg->bancoCuenta?->tipo_cuenta }} {{ $csg->bancoCuenta?->numero_cuenta }}
                        · {{ sqldate($csg->fecha)->format('d/m/Y') }}
                        @if($csg->referencia) · Ref: {{ $csg->referencia }} @endif
                    </div>
                </div>
                <strong style="white-space:nowrap;font-size:.78rem">{{ $fmt($csg->valor) }}</strong>
            </div>
        </div>
        @endforeach
        @if($totPrest > 0)
        <div class="fact-pago-row" style="color:#7c3aed">
            <span>💳 Préstamo (pendiente)</span><strong>{{ $fmt($totPrest) }}</strong>
        </div>
        @endif
    </div>
</div>

</div>{{-- /fact-body --}}

{{-- ══ FORMA DE PAGO — siempre visible (vista simple y detallada) ══ --}}
@php
$fpLabel = match($factura->forma_pago ?? '') {
    'consignacion' => '🏦 Consignación',
    'efectivo'     => '💵 Efectivo',
    'mixto'        => '💰 Mixto (efectivo + consignación)',
    'prestamo'     => '💳 Préstamo',
    default        => ucfirst(str_replace('_',' ', $factura->forma_pago ?? '—')),
};
@endphp
<div style="border-top:1.5px solid #e2e8f0;padding:.55rem 1.2rem;background:#f8fafc;">
    <div style="font-size:.6rem;font-weight:800;color:#1e3a5f;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem;padding-bottom:.2rem;border-bottom:1.5px solid #bfdbfe;">
        Forma de Pago
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;align-items:flex-start;">

        {{-- Tipo --}}
        <div style="display:flex;align-items:center;gap:.35rem;font-size:.77rem;">
            <span style="color:#64748b;font-weight:600;">Tipo:</span>
            <span style="font-weight:800;color:#0f172a;">{{ $fpLabel }}</span>
        </div>

        {{-- Efectivo --}}
        @if($totEfect > 0)
        <div style="display:flex;align-items:center;gap:.35rem;font-size:.77rem;color:#15803d;">
            <span style="font-weight:600;">💵 Efectivo:</span>
            <strong>{{ $fmt($totEfect) }}</strong>
        </div>
        @endif

        {{-- Consignaciones --}}
        @foreach($factura->consignaciones as $csg)
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:7px;padding:.3rem .65rem;font-size:.75rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
            <span style="color:#1d4ed8;font-weight:700;">
                🏦 {{ $csg->bancoCuenta?->nombre ?? 'Banco' }}
                @if($csg->confirmado) <span style="color:#15803d;font-size:.65rem;">✓ Confirmado</span> @endif
            </span>
            <span style="color:#475569;font-weight:800;">{{ $fmt($csg->valor) }}</span>
            <span style="color:#94a3b8;font-size:.68rem;">
                {{ sqldate($csg->fecha)->format('d/m/Y') }}
                @if($csg->referencia) · Ref: {{ $csg->referencia }} @endif
                @if($csg->bancoCuenta?->tipo_cuenta) · {{ $csg->bancoCuenta->tipo_cuenta }} @endif
            </span>
            @if($csg->imagen_path)
            <a href="#"
               onclick="verSoporte('{{ route('admin.facturacion.consignacion.imagen.ver', $csg->id) }}');return false;"
               style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;border-radius:4px;padding:0 6px;font-size:.62rem;text-decoration:none;font-weight:700;">
                🖼️ Ver soporte
            </a>
            @endif
        </div>
        @endforeach

        {{-- Préstamo --}}
        @if($totPrest > 0)
        <div style="display:flex;align-items:center;gap:.35rem;font-size:.77rem;color:#7c3aed;">
            <span style="font-weight:600;">💳 Préstamo pendiente:</span>
            <strong>{{ $fmt($totPrest) }}</strong>
        </div>
        @endif

    </div>
    {{-- Observación --}}
    @if($factura->observacion)
    <div style="margin-top:.35rem;font-size:.68rem;color:#94a3b8;font-style:italic;">
        📝 {{ $factura->observacion }}
    </div>
    @endif
</div>

{{-- PIE: Nota Legal + Total --}}
<div class="fact-footer-area">
    <div class="fact-nota">
        <span style="font-size:1.15rem;flex-shrink:0">⚠️</span>
        <span>
            <strong>NOTA:</strong> Las incapacidades por enfermedades y/o accidentes laborales serán reconocidas por la
            EPS y ARL solo si los aportes se han realizado oportunamente <strong>antes del décimo día hábil de cada mes</strong>.
        </span>
    </div>
    <div class="fact-total-bloque">
        <span class="fact-total-label">Total a Pagar</span>
        <span class="fact-total-valor">{{ $fmt($totTotal) }}</span>
        @if($totPrest > 0)
        <div style="font-size:.65rem;color:#a78bfa;margin-top:.2rem">Préstamo: {{ $fmt($totPrest) }}</div>
        @endif
    </div>
</div>
</div>{{-- /recibo-inner --}}

{{-- BARRA INFERIOR (con margen inferior) --}}
<div style="margin: 0 1.2rem 1rem; border-radius: 0 0 6px 6px; overflow:hidden; border: 1px solid #e2e8f0; border-top: none;">
<div class="fact-bottom-bar" style="border-radius:0">
    <span>{{ $nomAliado }} — Asesoría en Seguridad Social</span>
    <span>Impreso: {{ now()->format('d/m/Y H:i') }}</span>
</div>{{-- /fact-bottom-bar --}}
</div>{{-- /bottom-wrapper --}}

@endif {{-- esGrupo --}}


</div>{{-- /recibo-wrap --}}
</div>{{-- /recibo-print-area --}}
{{-- Modal Anular (solo admin) --}}
@if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('superadmin'))
<div id="modalAnular" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
<div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:460px;width:95%;box-shadow:0 8px 32px rgba(0,0,0,.2)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem">
        <h3 style="margin:0;color:#dc2626;font-size:1rem">⚠️ Anular Factura</h3>
        <button onclick="document.getElementById('modalAnular').style.display='none'" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:#6b7280">&times;</button>
    </div>
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:.6rem .8rem;margin-bottom:.9rem;font-size:.81rem;color:#991b1b">
        Esta acción es <strong>irreversible</strong>. Se eliminará la factura con sus abonos y plano. Todo quedará en la bitácora de auditoría.
    </div>
    <div style="margin-bottom:.75rem">
        <label style="font-size:.78rem;font-weight:700;color:#374151;display:block;margin-bottom:.3rem">Motivo de anulación <span style="color:#dc2626">*</span></label>
        <textarea id="an_motivo" rows="3" placeholder="Motivo obligatorio..." style="width:100%;border:1px solid #d1d5db;border-radius:6px;padding:.42rem .6rem;font-size:.82rem;resize:vertical;box-sizing:border-box"></textarea>
    </div>
    @if($factura->np)
    <label style="display:flex;align-items:center;gap:.45rem;font-size:.81rem;margin-bottom:.75rem;cursor:pointer">
        <input type="checkbox" id="an_np" style="width:16px;height:16px" checked>
        Anular <strong>todas las {{ $filas->count() }} facturas</strong> del NP {{ $factura->np }}
    </label>
    @endif
    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <button class="btn-a" style="background:#f1f5f9;color:#475569" onclick="document.getElementById('modalAnular').style.display='none'">Cancelar</button>
        <button class="btn-a" style="background:#dc2626;color:#fff" id="btnAnular" onclick="confirmarAnulacion()">🗑 Confirmar Anulación</button>
    </div>
</div>
</div>
@endif

<script>
const CSRF_REC  = document.querySelector('meta[name="csrf-token"]').content;
const URL_ANUL  = '{{ route('admin.facturacion.anular', $factura->id) }}';
const URL_IDX   = '{{ route('admin.facturacion.index') }}';

let simp = true;  // Vista simplificada por defecto al abrir
function toggleSimp() {
    simp = !simp;
    document.getElementById('rw').classList.toggle('simp', simp);
    // Actualizar texto del botón
    const btn = document.querySelector('button[onclick="toggleSimp()"]');
    if (btn) btn.textContent = simp ? '📋 Vista detallada' : '👁 Vista simplificada';
}
// ── Vista detallada / simple para recibo individual ─────────
let _modoDetallado = false;
function toggleVistaDet() {
    _modoDetallado = !_modoDetallado;
    const rw  = document.getElementById('rw');
    const btn = document.getElementById('btnToggleVista');
    if (_modoDetallado) {
        rw.classList.add('det');
        if (btn) { btn.textContent = '📄 Vista simple'; btn.style.background = '#1e3a5f'; btn.style.color = '#fff'; }
    } else {
        rw.classList.remove('det');
        if (btn) { btn.textContent = '📋 Vista detallada'; btn.style.background = '#f1f5f9'; btn.style.color = '#475569'; }
    }
}
// Aplicar modo simplificado al cargar
document.addEventListener('DOMContentLoaded', () => {
    // Grupo: inicia en modo simplificado
    const rw = document.getElementById('rw');
    if (rw && rw.querySelector('.simp-only') !== null) {
        // es grupo: aplicar simp
        rw.classList.add('simp');
        const btn = document.querySelector('button[onclick="toggleSimp()"]');
        if (btn) btn.textContent = '📋 Vista detallada';
    }
    // Individual: modo simple por defecto (sin .det)
    // rw ya no tiene .det, las columnas .col-valor-det están ocultas
});
function abrirAnular() {
    document.getElementById('modalAnular').style.display = 'flex';
}
async function confirmarAnulacion() {
    const motivo = (document.getElementById('an_motivo')?.value ?? '').trim();
    const todoNp = document.getElementById('an_np')?.checked ?? false;
    if (!motivo) { alert('Ingrese el motivo de anulación.'); return; }
    const btn = document.getElementById('btnAnular');
    btn.disabled = true; btn.textContent = '⏳ Anulando...';
    try {
        const res  = await fetch(URL_ANUL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_REC },
            body: JSON.stringify({ motivo, todo_np: todoNp })
        });
        const data = await res.json();
        if (data.ok) {
            // Cerrar el modal de anulación
            document.getElementById('modalAnular').style.display = 'none';
            setTimeout(() => {
                if (window.parent && window.parent !== window && typeof window.parent.cerrarRecibo === 'function') {
                    // Estamos dentro del iframe del modal recibo → cerrar y recargar la página padre
                    window.parent.cerrarRecibo();
                } else if (window.opener) {
                    // Popup independiente → recargar abridor y cerrarse
                    window.opener.location.reload();
                    window.close();
                } else {
                    // Página directa → ir al índice (comportamiento original)
                    window.location.href = URL_IDX;
                }
            }, 300);
        } else {
            alert(data.message || 'Error al anular.');
            btn.disabled = false;
            btn.textContent = '🗑 Confirmar Anulación';
        }
    } catch(e) {
        alert('Error de conexión.');
        btn.disabled = false;
    }
}
// ── Soporte consignación ─────────────────────────────────
function verSoporte(url) {
    const ov   = document.getElementById('soporte-ov');
    const img  = document.getElementById('soporte-img');
    const fr   = document.getElementById('soporte-frame');
    const isPdf = url.match(/\.pdf(\?|$)/i) || url.endsWith('/imagen'); // redirige, asumir PDF posible
    // Usamos iframe para cualquier contenido (funciona para imágenes y PDFs)
    img.style.display = 'none';
    fr.src = url;
    fr.style.display = 'block';
    ov.style.display = 'flex';
}
</script>

{{-- Modal soporte consignación --}}
<div id="soporte-ov" onclick="if(event.target===this)document.getElementById('soporte-ov').style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.77);z-index:99999;align-items:center;justify-content:center;">
    <div style="position:relative;max-width:92vw;max-height:92vh;background:#1e293b;border-radius:12px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.6);">
        <button onclick="document.getElementById('soporte-ov').style.display='none'"
                style="position:absolute;top:8px;right:10px;background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:50%;width:28px;height:28px;font-size:1.1rem;cursor:pointer;z-index:1;line-height:1;">×</button>
        <img  id="soporte-img"  style="display:none;max-width:88vw;max-height:88vh;object-fit:contain;padding:8px;" alt="Soporte">
        <iframe id="soporte-frame" src="" style="display:block;width:82vw;height:88vh;border:none;"></iframe>
    </div>
</div>
@endsection
