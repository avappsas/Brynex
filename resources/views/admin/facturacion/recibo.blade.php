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
/* ─── PRINT: solo mostrar el recibo ─────────── */
@media print {
    body * { visibility: hidden !important; }
    #recibo-print-area, #recibo-print-area * { visibility: visible !important; }
    #recibo-print-area {
        position: fixed; inset: 0;
        padding: 8mm; background: #fff; z-index: 9999;
        box-shadow: none !important;
    }
    .no-print { display: none !important; }
}
/* ─── Estilos ───────────────────────────────── */
.recibo-wrap { max-width:960px;margin:0 auto;background:#fff;border-radius:12px;
    box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden }
.rec-hdr { background:linear-gradient(135deg,#0f172a,#1e3a5f);color:#fff;
    padding:1rem 1.4rem;display:flex;justify-content:space-between;align-items:flex-start }
.rec-num { font-size:1.55rem;font-weight:900;color:#fbbf24 }
.rec-body{ padding:1rem 1.4rem }
.badge   { display:inline-block;padding:.18rem .6rem;border-radius:20px;font-size:.72rem;font-weight:700 }
.badge-pago  { background:#dcfce7;color:#15803d }
.badge-pre   { background:#f1f5f9;color:#475569 }
.badge-prest { background:#ede9fe;color:#6d28d9 }
.badge-abono { background:#fef3c7;color:#92400e }
.alerta-prest{ background:#fdf4ff;border:2px solid #c4b5fd;border-radius:8px;
    padding:.6rem 1rem;margin:0 0 .7rem;display:flex;align-items:center;gap:.6rem }
.tbl { width:100%;border-collapse:collapse;font-size:.74rem }
.tbl th { background:#0f172a;color:#94a3b8;font-size:.61rem;text-transform:uppercase;
    padding:.38rem .42rem;white-space:nowrap;text-align:center }
.tbl td { padding:.28rem .42rem;border-bottom:1px solid #f1f5f9;vertical-align:top }
.tbl tbody tr:nth-child(even) td { background:#fafafa }
.tbl tfoot td { background:#0f172a;color:#fff;font-weight:700;padding:.4rem .42rem }
.n-r { text-align:right;font-family:monospace }
.tot-v { color:#34d399 }
.box2 { display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin:.6rem 0 }
.ibox { background:#f8fafc;border-radius:8px;padding:.5rem .75rem;font-size:.79rem }
.ilbl { font-size:.63rem;color:#94a3b8;text-transform:uppercase;font-weight:700;margin-bottom:.25rem }
.srow { display:flex;justify-content:space-between;padding:.09rem 0 }
.total-bx { background:#0f172a;color:#fff;border-radius:8px;padding:.7rem 1rem;
    display:flex;justify-content:space-between;align-items:center;margin-top:.5rem }
.total-v { font-size:1.4rem;font-weight:900;color:#fbbf24 }
.btn-a { padding:.4rem .9rem;border-radius:7px;border:none;font-weight:600;
    cursor:pointer;font-size:.82rem;text-decoration:none }
/* Vista simplificada: columnas .dc desaparecen, .simp-only aparece */
.simp .dc { display:none }
/* Bloque de entidades: visible solo en modo simplificado */
.simp-only { display:none }
.simp .simp-only { display:block }
</style>

{{-- Botonera (no se imprime) --}}
<div class="no-print" style="max-width:960px;margin:0 auto .65rem;display:flex;gap:.5rem;justify-content:flex-end;flex-wrap:wrap;">
    <button class="btn-a" style="background:#f1f5f9;color:#475569" onclick="toggleSimp()">👁 Vista simplificada</button>
    <button class="btn-a" style="background:#0f172a;color:#fff" onclick="window.print()">🖨 Imprimir</button>
    @if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('superadmin'))
    <button class="btn-a" style="background:#dc2626;color:#fff" onclick="abrirAnular()">🗑 Anular</button>
    @endif
    @if(request()->boolean('modal'))
    {{-- En modal: botón para cerrar el modal padre --}}
    <button class="btn-a" style="background:#64748b;color:#fff"
            onclick="if(window.parent && window.parent.cerrarRecibo){ window.parent.cerrarRecibo(); } else { window.close(); }">✕ Cerrar</button>
    @else
    {{-- Fuera de modal: botón Volver normal --}}
    <a href="{{ $factura->empresa_id
            ? route('admin.facturacion.empresa', ['id' => $factura->empresa_id, 'mes' => $factura->mes, 'anio' => $factura->anio])
            : route('admin.facturacion.index') }}"
       class="btn-a" style="background:#f1f5f9;color:#475569">← Volver</a>
    @endif
</div>

<div id="recibo-print-area">
<div class="recibo-wrap" id="rw">

{{-- ══ ENCABEZADO ══════════════════════════════════════════════════════ --}}
<div class="rec-hdr">
    <div>
        <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.15rem">RECIBO DE PAGO</div>

        @if($esGrupo && $empresaObj)
            <div style="font-size:1.1rem;font-weight:800">{{ $empresaObj->empresa }}</div>
            <div style="font-size:.74rem;color:#94a3b8">NIT: {{ $empresaObj->nit ?? '—' }}</div>
        @elseif($esGrupo)
            <div style="font-size:1rem;font-weight:700">{{ $filas->first()->contrato?->razonSocial?->razon_social ?? 'Empresa' }}</div>
        @else
            @php
            $cli0  = $factura->contrato?->cliente;
            // Para otro_ingreso buscar cliente por cédula si no hay contrato
            if (!$cli0 && $factura->tipo === 'otro_ingreso') {
                $cli0 = \App\Models\Cliente::where('cedula', $factura->cedula)->first();
            }
            $nom0  = trim(($cli0?->primer_nombre ?? '').' '.($cli0?->primer_apellido ?? ''));
            $rs0   = $factura->contrato?->razonSocial;
            @endphp

            @if($factura->tipo === 'otro_ingreso')
                {{-- Otro ingreso: empresa si viene desde empresa, cliente si es individual --}}
                @if($factura->empresa)
                    <div style="font-size:1rem;font-weight:800">{{ $factura->empresa->empresa }}</div>
                    <div style="font-size:.74rem;color:#94a3b8">NIT: {{ $factura->empresa->nit ?? '—' }}</div>
                @else
                    <div style="font-size:1rem;font-weight:800">{{ $nom0 ?: 'CC '.$factura->cedula }}</div>
                    <div style="font-size:.74rem;color:#94a3b8">CC {{ number_format($factura->cedula,0,'','.') }}</div>
                @endif
            @elseif($rs0)
                {{-- Contrato de empresa: mostrar la empresa --}}
                <div style="font-size:1rem;font-weight:800">{{ $rs0->razon_social }}</div>
                <div style="font-size:.74rem;color:#94a3b8">NIT: {{ $rs0->nit ?? '—' }}</div>
                <div style="font-size:.72rem;color:#94a3b8;margin-top:.1rem">
                    Trabajador: {{ $nom0 ?: 'CC '.$factura->cedula }}
                </div>
            @else
                {{-- Contrato individual: mostrar el cliente --}}
                <div style="font-size:1rem;font-weight:800">{{ $nom0 ?: 'CC '.$factura->cedula }}</div>
                <div style="font-size:.74rem;color:#94a3b8">CC {{ number_format($factura->cedula,0,'','.') }}</div>
            @endif

        @endif

        <div style="font-size:.75rem;color:#94a3b8;margin-top:.2rem">
            Período: {{ $meses[$factura->mes-1] }} {{ $factura->anio }}
            @if($esGrupo)
                · NP: <strong style="color:#fbbf24">{{ $factura->np }}</strong>
                · {{ $filas->count() }} trabajadores
            @endif
            · {{ Carbon::parse($factura->fecha_pago)->format('d/m/Y') }}
        </div>

        <div style="margin-top:.35rem">
            <span class="badge {{ $estadoCls($factura->estado) }}">{{ $estadoLabel($factura->estado) }}</span>
            <span style="color:#94a3b8;font-size:.7rem;margin-left:.4rem">
                Facturó: {{ $factura->usuario?->nombre ?? $factura->usuario?->name ?? ('Usuario #'.($factura->usuario_id ?? '?')) }}
            </span>
        </div>
    </div>
    <div class="rec-num">
        @if($esGrupo)
            <div style="font-size:.65rem;color:#94a3b8;font-weight:500;text-align:right">N° Recibo</div>
            {{ str_pad($filas->first()?->numero_factura ?? $factura->numero_factura, 6, '0', STR_PAD_LEFT) }}
        @else
            <div style="font-size:.65rem;color:#94a3b8;font-weight:500;text-align:right">N° Recibo</div>
            {{ str_pad($factura->numero_factura,6,'0',STR_PAD_LEFT) }}
        @endif
    </div>
</div>

<div class="rec-body">

{{-- ══ ALERTA PRÉSTAMO ══════════════════════════════════════════════════ --}}
@if($totPrest > 0 || $factura->estado === 'prestamo')
<div class="alerta-prest">
    <span style="font-size:1.3rem">💳</span>
    <div>
        <div style="font-weight:700;color:#6d28d9;font-size:.84rem">Préstamo pendiente de cobro</div>
        <div style="font-size:.77rem;color:#7c3aed">
            Total: <strong>{{ $fmt($totTotal) }}</strong> ·
            Recibido: <strong>{{ $fmt($totTotal - $totPrest) }}</strong> ·
            Pendiente a cobrar: <strong>{{ $fmt($totPrest) }}</strong>
        </div>
    </div>
</div>
@endif

{{-- ══ TABLA EMPRESA (modo NP) ═════════════════════════════════════════ --}}
@if($esGrupo)
<div style="font-size:.67rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.28rem">Trabajadores</div>
<div style="overflow-x:auto">
<table class="tbl">
<thead>
<tr>
    <th style="width:24px">No</th>
    <th style="text-align:left">Nombre / CC</th>
    <th style="text-align:left">Razón Social</th>
    <th style="text-align:center">Días</th>
    <th class="dc n-r">EPS $</th>
    <th class="dc n-r">ARL $</th>
    <th class="dc n-r">Pensión $</th>
    <th class="dc n-r">Caja $</th>
    <th class="dc n-r">Admon $</th>
    <th style="text-align:left;font-size:.6rem">EPS</th>
    <th style="text-align:left;font-size:.6rem">ARL</th>
    <th style="text-align:left;font-size:.6rem">Pensión</th>
    <th style="text-align:left;font-size:.6rem">Caja</th>
    <th class="n-r">TOTAL</th>
</tr>
</thead>
<tbody>
@php $tEps=$tArl=$tPen=$tCaj=$tAdm=0; @endphp
@foreach($filas as $idx => $f)
@php
$cli  = $f->contrato?->cliente;
$nom  = trim(($cli?->primer_nombre ?? '').' '.($cli?->segundo_nombre ?? '').' '.($cli?->primer_apellido ?? '').' '.($cli?->segundo_apellido ?? ''));
$rs   = $f->contrato?->razonSocial?->razon_social ?? $f->razonSocial?->razon_social ?? '—';
$enEps = $f->contrato?->eps?->nombre ?? '—';
// ARL: primero de RS, luego del contrato
$enArlNom = null;
$enArlNit = $f->contrato?->razonSocial?->arl_nit ?? null;
if ($enArlNit) {
    $enArlNom = \App\Models\Arl::where('nit', $enArlNit)->value('nombre_arl') ?? $enArlNit;
}
if (!$enArlNom) {
    $enArlNom = $f->contrato?->arl?->nombre_arl ?? '—';
}
$enArlNivel = $f->contrato?->n_arl ?? '';
$enArl = $enArlNom . ($enArlNivel ? ' (N'.$enArlNivel.')' : '');
$enPen = $f->contrato?->pension?->razon_social ?? '—';
$enCaj = $f->contrato?->caja?->nombre ?? $f->contrato?->caja?->razon_social ?? '—';
$vEps  = (int)($f->v_eps  ?? 0);
$vArl  = (int)($f->v_arl  ?? 0);
$vPen  = (int)($f->v_afp  ?? 0);
$vCaj  = (int)($f->v_caja ?? 0);
$vAdm  = (int)($f->admon  ?? 0) + (int)($f->admin_asesor ?? 0);
$dias  = $f->dias_cotizados ?? 30;
$tEps += $vEps; $tArl += $vArl; $tPen += $vPen; $tCaj += $vCaj; $tAdm += $vAdm;
@endphp
<tr>
    <td style="text-align:center;color:#94a3b8;font-weight:700">{{ $idx+1 }}</td>
    <td>
        <div style="font-weight:600;font-size:.78rem">{{ $nom ?: '—' }}</div>
        <div style="font-size:.65rem;color:#94a3b8">CC {{ number_format($f->cedula,0,'','.') }}</div>
    </td>
    <td style="font-size:.72rem;color:#1d4ed8;font-weight:700">{{ $rs }}</td>
    <td style="text-align:center;font-weight:700;color:{{ $dias < 30 ? '#d97706' : '#0f172a' }}">{{ $dias }}</td>
    <td class="dc n-r">{{ $vEps  > 0 ? '$'.number_format($vEps, 0,',','.') : '—' }}</td>
    <td class="dc n-r">{{ $vArl  > 0 ? '$'.number_format($vArl, 0,',','.') : '—' }}</td>
    <td class="dc n-r">{{ $vPen  > 0 ? '$'.number_format($vPen, 0,',','.') : '—' }}</td>
    <td class="dc n-r">{{ $vCaj  > 0 ? '$'.number_format($vCaj, 0,',','.') : '—' }}</td>
    <td class="dc n-r">{{ $vAdm  > 0 ? '$'.number_format($vAdm, 0,',','.') : '—' }}</td>
    <td style="font-size:.68rem;color:#0369a1">{{ $enEps }}</td>
    <td style="font-size:.68rem;color:#7c3aed;white-space:nowrap">{{ $enArl }}</td>
    <td style="font-size:.68rem">{{ $enPen }}</td>
    <td style="font-size:.68rem">{{ $enCaj }}</td>
    <td class="n-r" style="font-weight:800">${{ number_format($f->total,0,',','.') }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr>
    <td colspan="3" style="font-size:.7rem">TOTALES ({{ $filas->count() }})</td>
    <td></td>
    <td class="dc n-r tot-v">${{ number_format($tEps,0,',','.') }}</td>
    <td class="dc n-r tot-v">${{ number_format($tArl,0,',','.') }}</td>
    <td class="dc n-r tot-v">${{ number_format($tPen,0,',','.') }}</td>
    <td class="dc n-r tot-v">${{ number_format($tCaj,0,',','.') }}</td>
    <td class="dc n-r tot-v">${{ number_format($tAdm,0,',','.') }}</td>
    <td colspan="4"></td>
    <td class="n-r tot-v">${{ number_format($totTotal,0,',','.') }}</td>
</tr>
</tfoot>
</table>
</div>

@else
{{-- ══ Individual ═════════════════════════════════════════════════════ --}}
@php
$cli1   = $factura->contrato?->cliente;
// Para otro_ingreso puede no haber contrato, buscar por cédula directamente
if (!$cli1 && $factura->tipo === 'otro_ingreso') {
    $cli1 = \App\Models\Cliente::where('cedula', $factura->cedula)->first();
}
$nom1   = trim(($cli1?->primer_nombre ?? '').' '.($cli1?->segundo_nombre ?? '').' '.($cli1?->primer_apellido ?? '').' '.($cli1?->segundo_apellido ?? ''));
$rs1    = $factura->contrato?->razonSocial?->razon_social ?? $factura->razonSocial?->razon_social ?? '—';
// ARL: primero del contrato (independiente), luego de la razón social (empresa)
$arlNom = $factura->contrato?->arl?->nombre_arl;
if (!$arlNom) {
    $arlNit = $factura->contrato?->razonSocial?->arl_nit;
    $arlNom = $arlNit ? (\App\Models\Arl::where('nit',$arlNit)->value('nombre_arl') ?? $arlNit) : '—';
}
$arlNivel = $factura->contrato?->n_arl ?? '';
$cajaNom  = $factura->contrato?->caja?->nombre ?? $factura->contrato?->caja?->razon_social ?? '—';
$vEps1  = (int)($factura->v_eps  ?? 0);
$vArl1  = (int)($factura->v_arl  ?? 0);
$vPen1  = (int)($factura->v_afp  ?? 0);
$vCaj1  = (int)($factura->v_caja ?? 0);
$vAdm1  = (int)($factura->admon  ?? 0) + (int)($factura->admin_asesor ?? 0);
$dias1  = $factura->dias_cotizados ?? 30;
@endphp

{{-- Bloque trabajador: NO mostrar en otro_ingreso de empresa --}}
@if($factura->tipo !== 'otro_ingreso' || !$factura->empresa_id)
<div class="ibox" style="margin-bottom:.6rem">
    <div class="ilbl">Trabajador</div>
    <div style="font-size:.95rem;font-weight:700">{{ $nom1 ?: 'CC '.$factura->cedula }}</div>
    <div style="font-size:.75rem;color:#64748b">CC {{ number_format($factura->cedula,0,'','.') }}</div>
    @if($factura->tipo !== 'otro_ingreso')
    <div style="font-size:.78rem;color:#1d4ed8;font-weight:700;margin-top:.15rem">{{ $rs1 }}</div>
    @endif
</div>
@endif

{{-- ══ OTRO INGRESO: sección dedicada (solo vista detallada) ═════════ --}}
@if($factura->tipo === 'otro_ingreso')
<div class="dc">
<div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:2px solid #6ee7b7;border-radius:12px;padding:.7rem 1rem;margin-bottom:.6rem;">
    <div style="font-size:.6rem;font-weight:800;color:#065f46;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem;">
        💼 Trámite / Servicio Adicional
    </div>
    <div style="font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:.5rem;">
        {{ $factura->descripcion_tramite ?? 'Trámite sin descripción' }}
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.79rem;">
        @if(($factura->admon ?? 0) > 0)
        <div style="background:#fff;border-radius:8px;padding:.4rem .65rem;">
            <div style="font-size:.6rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:.12rem;">🏢 Admon empresa</div>
            <div style="font-weight:800;font-family:monospace">{{ $fmt($factura->admon) }}</div>
        </div>
        @endif
        @if(($factura->admon_asesor_oi ?? 0) > 0)
        <div style="background:#fff;border-radius:8px;padding:.4rem .65rem;">
            <div style="font-size:.6rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:.12rem;">💼 Asesor</div>
            <div style="font-weight:800;font-family:monospace">{{ $fmt($factura->admon_asesor_oi) }}</div>
        </div>
        @endif
        @if(($factura->iva ?? 0) > 0)
        <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:.4rem .65rem;">
            <div style="font-size:.6rem;color:#92400e;font-weight:700;text-transform:uppercase;margin-bottom:.12rem;">🏷 IVA</div>
            <div style="font-weight:800;font-family:monospace;color:#92400e">{{ $fmt($factura->iva) }}</div>
        </div>
        @endif
    </div>
    @if($factura->empresa)
    <div style="font-size:.72rem;color:#065f46;margin-top:.45rem;font-weight:600;">
        🏢 Empresa: {{ $factura->empresa->empresa }}
    </div>
    @endif
    </div>
</div>{{-- /dc --}}
@else

<div class="dc">
<div class="box2">
@if($factura->tipo === 'afiliacion')
    {{-- ══ AFILIACIÓN: mostrar entidades + plan, sin precios SS ══ --}}
    <div class="ibox">
        <div class="ilbl">📋 Entidades a Afiliar</div>
        @php
        $planNom = $factura->contrato?->plan?->nombre ?? '—';
        @endphp
        <div style="font-size:.72rem;font-weight:700;color:#7c3aed;margin-bottom:.35rem;">
            Plan: {{ $planNom }}
        </div>
        @if($factura->contrato?->eps)
        <div class="srow"><span>EPS</span><strong style="color:#0369a1;font-size:.8rem">{{ $factura->contrato->eps->nombre }}</strong></div>
        @endif
        @if($arlNom && $arlNom !== '—')
        <div class="srow"><span>ARL{{ $arlNivel ? ' Riesgo '.$arlNivel : '' }}</span><strong style="color:#15803d;font-size:.8rem">{{ $arlNom }}</strong></div>
        @endif
        @if($factura->contrato?->pension)
        <div class="srow"><span>Pensión</span><strong style="color:#7c3aed;font-size:.8rem">{{ $factura->contrato->pension->razon_social }}</strong></div>
        @endif
        @if($factura->contrato?->caja)
        <div class="srow"><span>Caja</span><strong style="font-size:.8rem">{{ $cajaNom }}</strong></div>
        @endif
        @if($dias1 < 30)
        <div class="srow" style="color:#d97706;font-size:.76rem"><span>Días cotizados</span><strong>{{ $dias1 }}</strong></div>
        @endif
    </div>
    <div class="ibox">
        <div class="ilbl">💰 Distribución del cobro</div>
        @php
        $dAdmon   = (int)($factura->dist_admon   ?? 0);
        $dAsesor  = (int)($factura->dist_asesor  ?? 0);
        $dRetiro  = (int)($factura->dist_retiro  ?? 0);
        $dUtil    = (int)($factura->dist_utilidad ?? 0);
        @endphp
        @if($dAdmon > 0)
        <div class="srow"><span>🏢 Admon Empresa</span><strong>{{ $fmt($dAdmon) }}</strong></div>
        @endif
        @if($dAsesor > 0)
        <div class="srow"><span>👤 Asesor</span><strong>{{ $fmt($dAsesor) }}</strong></div>
        @endif
        @if($dRetiro > 0)
        <div class="srow"><span>🏦 Retiro/Novedad</span><strong>{{ $fmt($dRetiro) }}</strong></div>
        @endif
        @if($dUtil > 0)
        <div class="srow"><span>📈 Utilidad</span><strong>{{ $fmt($dUtil) }}</strong></div>
        @endif
        @if(($factura->seguro ?? 0) > 0)
        <div class="srow" style="border-top:1px solid #e2e8f0;padding-top:.2rem;margin-top:.2rem"><span>🛡️ Seguro</span><strong>{{ $fmt($factura->seguro) }}</strong></div>
        @endif
    </div>
@else
    {{-- ══ PLANILLA: desglose normal de SS ══ --}}
    <div class="ibox">
        <div class="ilbl">Seguridad Social</div>
        <div class="srow"><span>EPS &mdash; {{ $factura->contrato?->eps?->nombre ?? '—' }}</span><strong>${{ number_format($vEps1,0,',','.') }}</strong></div>
        <div class="srow"><span>ARL &mdash; {{ $arlNom }}{{ $arlNivel ? ' (Nivel '.$arlNivel.')' : '' }}</span><strong>${{ number_format($vArl1,0,',','.') }}</strong></div>
        <div class="srow"><span>Pensín &mdash; {{ $factura->contrato?->pension?->razon_social ?? '—' }}</span><strong>${{ number_format($vPen1,0,',','.') }}</strong></div>
        <div class="srow"><span>Caja &mdash; {{ $cajaNom }}</span><strong>${{ number_format($vCaj1,0,',','.') }}</strong></div>
        <div class="srow" style="border-top:1px solid #e2e8f0;margin-top:.25rem;padding-top:.2rem;font-weight:700">
            <span>Subtotal SS</span><strong>${{ number_format($vEps1+$vArl1+$vPen1+$vCaj1,0,',','.') }}</strong>
        </div>
        @if($dias1 < 30)
        <div class="srow" style="color:#d97706;font-size:.76rem"><span>Días cotizados</span><strong>{{ $dias1 }}</strong></div>
        @endif
    </div>
    <div class="ibox">
        <div class="ilbl">Otros cargos</div>

        @if(($factura->seguro ?? 0) > 0)
        <div class="srow"><span>Seguro</span><strong>{{ $fmt($factura->seguro) }}</strong></div>
        @endif

        @if(($factura->afiliacion ?? 0) > 0)
        <div class="srow"><span>Afiliación</span><strong>{{ $fmt($factura->afiliacion) }}</strong></div>
        @endif

        @if(($factura->otros ?? 0) > 0)
        <div class="srow"><span>Otros</span><strong>{{ $fmt($factura->otros) }}</strong></div>
        @endif

        @if(($factura->mensajeria ?? 0) > 0)
        <div class="srow"><span>Mensajería</span><strong>{{ $fmt($factura->mensajeria) }}</strong></div>
        @endif

        @if(($factura->iva ?? 0) > 0)
        <div class="srow"><span>IVA</span><strong>{{ $fmt($factura->iva) }}</strong></div>
        @endif
    </div>
@endif
</div>
</div>
@endif {{-- otro_ingreso --}}

@endif {{-- esGrupo --}}

{{-- ══ BLOQUE SIMPLIFICADO: Entidades + Pago + Extras (fuera de .dc) ═══════ --}}
@php
// Obtener primera fila para entidades (funciona en individual y grupo)
$fRef = $filas->first();
$epsSimp = $fRef->contrato?->eps?->nombre ?? '—';
$penSimp = $fRef->contrato?->pension?->razon_social ?? '—';
$cajSimp = $fRef->contrato?->caja?->nombre ?? $fRef->contrato?->caja?->razon_social ?? '—';
// ARL: primero de la RS, luego del contrato
$arlSimpNom = null;
$arlSimpNit = $fRef->contrato?->razonSocial?->arl_nit ?? null;
if ($arlSimpNit) {
    $arlSimpNom = \App\Models\Arl::where('nit', $arlSimpNit)->value('nombre_arl') ?? $arlSimpNit;
}
if (!$arlSimpNom) {
    $arlSimpNom = $fRef->contrato?->arl?->nombre_arl ?? '—';
}
$arlSimpNivel = $fRef->contrato?->n_arl ?? '';
// Extras
$totMens = $filas->sum(fn($f) => (int)($f->mensajeria ?? 0));
$totOtros = $filas->sum(fn($f) => (int)($f->otros ?? 0));
$totOtrosAdmon = $filas->sum(fn($f) => (int)($f->otros_admon ?? 0));
$totIvaSimp = $filas->sum(fn($f) => (int)($f->iva ?? 0));
$totSaldoFavor = $filas->sum(fn($f) => (int)($f->saldo_a_favor ?? 0));
$totSaldoPend = $filas->sum(fn($f) => (int)($f->saldo_pendiente ?? 0));
@endphp
<div class="simp-only">
@if($factura->tipo === 'otro_ingreso')
    {{-- OTRO INGRESO: vista simple — descripción + pago + total --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.45rem;margin-bottom:.45rem;">

        {{-- Descripción del trámite + pago --}}
        <div style="background:#f8fafc;border-radius:8px;padding:.5rem .85rem;font-size:.77rem;">
            @if($factura->descripcion_tramite)
            <div style="font-size:.62rem;font-weight:800;color:#065f46;text-transform:uppercase;margin-bottom:.2rem">💼 Trámite</div>
            <div style="font-weight:700;color:#0f172a;font-size:.85rem;margin-bottom:.4rem;">{{ $factura->descripcion_tramite }}</div>
            @endif
            <div style="font-size:.59rem;font-weight:800;color:#94a3b8;text-transform:uppercase;margin-bottom:.3rem">Forma de pago</div>
            <div class="srow"><span style="color:#64748b">Tipo</span><strong>{{ ucfirst(str_replace('_',' ',$factura->forma_pago ?? '')) }}</strong></div>
            @if($totEfect > 0)
            <div class="srow"><span style="color:#64748b">💵 Efectivo</span><strong style="color:#15803d">{{ $fmt($totEfect) }}</strong></div>
            @endif
            @foreach($factura->consignaciones as $csg)
            <div class="srow">
                <span style="color:#64748b">🏦 {{ $csg->bancoCuenta?->nombre ?? 'Banco' }}
                    <span style="font-size:.65rem;color:#94a3b8"> · {{ \Carbon\Carbon::parse($csg->fecha)->format('d/m/Y') }}</span>
                </span>
                <strong>{{ $fmt($csg->valor) }}</strong>
            </div>
            @endforeach
            @if($totPrest > 0)
            <div class="srow"><span style="color:#7c3aed">💳 Préstamo</span><strong style="color:#7c3aed">{{ $fmt($totPrest) }}</strong></div>
            @endif
        </div>

        {{-- Total --}}
        <div style="background:#0f172a;border-radius:8px;padding:.5rem .85rem;display:flex;flex-direction:column;align-items:center;justify-content:center;">
            <div style="font-size:.6rem;font-weight:800;color:#94a3b8;text-transform:uppercase;margin-bottom:.25rem">Total</div>
            <div style="font-size:1.6rem;font-weight:900;color:#fbbf24;font-family:monospace">{{ $fmt($totTotal) }}</div>
            @if($totPrest > 0)
            <div style="font-size:.68rem;color:#a78bfa;margin-top:.2rem">Préstamo: {{ $fmt($totPrest) }}</div>
            @endif
        </div>

    </div>
@else
    {{-- Grid adaptado: en individual 3 cols (entidades | pago | nota), en grupo 2 cols (pago | nota) --}}
    <div style="display:grid;grid-template-columns:{{ $esGrupo ? '1fr 1fr' : '1fr 1fr 1fr' }};gap:.45rem;margin-bottom:.45rem;">

        {{-- Bloque entidades: solo en factura individual (no otro_ingreso) --}}
        @if(!$esGrupo)
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:.5rem .85rem;font-size:.77rem;">
            <div style="font-size:.59rem;font-weight:800;color:#0369a1;text-transform:uppercase;margin-bottom:.3rem">
                {{ $factura->tipo === 'afiliacion' ? '📋 Entidades' : '🏥 Seg. Social' }}
            </div>
            @if($factura->tipo === 'afiliacion')
                {{-- Afiliación: solo nombres --}}
                @if($factura->contrato?->eps)
                <div class="srow"><span style="color:#64748b">EPS</span><strong style="color:#0369a1">{{ $factura->contrato->eps->nombre }}</strong></div>
                @endif
                @if($arlNom && $arlNom !== '—')
                <div class="srow"><span style="color:#64748b">ARL{{ $arlNivel ? ' (N'.$arlNivel.')' : '' }}</span><strong style="color:#15803d">{{ $arlNom }}</strong></div>
                @endif
                @if($factura->contrato?->pension)
                <div class="srow"><span style="color:#64748b">Pensión</span><strong style="color:#7c3aed">{{ $factura->contrato->pension->razon_social }}</strong></div>
                @endif
                @if($cajaNom && $cajaNom !== '—')
                <div class="srow"><span style="color:#64748b">Caja</span><strong>{{ $cajaNom }}</strong></div>
                @endif
                <div class="srow" style="border-top:1px solid #bae6fd;margin-top:.25rem;padding-top:.2rem;font-weight:800;color:#0f172a;">
                    <span>Total afiliación</span><strong style="color:#7c3aed">{{ $fmt($factura->afiliacion + $factura->seguro) }}</strong>
                </div>
            @else
                {{-- Planilla: nombres + valores --}}
                @if($vEps1 > 0)
                <div class="srow"><span style="color:#64748b">EPS &mdash; {{ $factura->contrato?->eps?->nombre ?? '—' }}</span><strong>{{ $fmt($vEps1) }}</strong></div>
                @endif
                @if($vArl1 > 0)
                <div class="srow"><span style="color:#64748b">ARL{{ $arlNivel ? ' (N'.$arlNivel.')' : '' }} &mdash; {{ $arlNom }}</span><strong>{{ $fmt($vArl1) }}</strong></div>
                @endif
                @if($vPen1 > 0)
                <div class="srow"><span style="color:#64748b">Pensión &mdash; {{ $factura->contrato?->pension?->razon_social ?? '—' }}</span><strong>{{ $fmt($vPen1) }}</strong></div>
                @endif
                @if($vCaj1 > 0)
                <div class="srow"><span style="color:#64748b">Caja &mdash; {{ $cajaNom }}</span><strong>{{ $fmt($vCaj1) }}</strong></div>
                @endif
                @if($vAdm1 > 0)
                <div class="srow" style="border-top:1px dashed #e2e8f0;margin-top:.2rem;padding-top:.2rem">
                    <span style="color:#64748b">Admon</span><strong>{{ $fmt($vAdm1) }}</strong>
                </div>
                @endif
                <div class="srow" style="border-top:1px solid #bae6fd;margin-top:.25rem;padding-top:.2rem;font-weight:800;color:#0f172a;">
                    <span>Total</span><strong style="color:#1d4ed8">{{ $fmt($factura->total) }}</strong>
                </div>
            @endif
        </div>
        @endif

        {{-- Forma de pago --}}
        <div style="background:#f8fafc;border-radius:8px;padding:.5rem .85rem;font-size:.77rem;">
            <div style="font-size:.59rem;font-weight:800;color:#94a3b8;text-transform:uppercase;margin-bottom:.3rem">Forma de pago</div>
            <div class="srow"><span style="color:#64748b">Tipo</span><strong>{{ ucfirst(str_replace('_',' ',$factura->forma_pago ?? '')) }}</strong></div>
            @if($totEfect > 0)
            <div class="srow"><span style="color:#64748b">💵 Efectivo</span><strong style="color:#15803d">{{ $fmt($totEfect) }}</strong></div>
            @endif
            @foreach($factura->consignaciones as $csg)
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin:.12rem 0;">
                <div>
                    <span style="color:#64748b;font-weight:600;">🏦 {{ $csg->bancoCuenta?->nombre ?? 'Banco' }}</span>
                    <span style="color:#94a3b8;font-size:.66rem;margin-left:.3rem">{{ \Carbon\Carbon::parse($csg->fecha)->format('d/m/Y') }}</span>
                    @if($csg->imagen_path)
                    <a href="#"
                       onclick="verSoporte('{{ route('admin.facturacion.consignacion.imagen.ver', $csg->id) }}');return false;"
                       title="Ver soporte de consignación"
                       style="margin-left:.3rem;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;border-radius:4px;padding:0 5px;font-size:.62rem;text-decoration:none;vertical-align:middle;cursor:pointer;">
                       🖼️ soporte
                    </a>
                    @endif
                    <div style="font-size:.65rem;color:#94a3b8;margin-top:.05rem">
                        {{ $csg->bancoCuenta?->tipo_cuenta }} {{ $csg->bancoCuenta?->numero_cuenta }}
                        @if($csg->referencia) · Ref: {{ $csg->referencia }} @endif
                        @if($csg->confirmado) <span style="color:#15803d;font-weight:700"> · ✓ Confirmado</span>@endif
                    </div>
                </div>
                <strong style="white-space:nowrap;margin-left:.5rem">{{ $fmt($csg->valor) }}</strong>
            </div>
            @endforeach
            @if($totPrest > 0)
            <div class="srow"><span style="color:#7c3aed">💳 Préstamo (pendiente cobro)</span><strong style="color:#7c3aed">{{ $fmt($totPrest) }}</strong></div>
            @endif
            {{-- Anticipo aplicado (mes anterior pagó de más) --}}
            @if($totSaldoFavor > 0)
            <div style="margin-top:.3rem;padding-top:.3rem;border-top:1px solid #d1fae5;">
                <div class="srow" style="color:#15803d;font-size:.74rem;">
                    <span>✅ Anticipo aplicado
                        @php
                        $mesPrevio = $factura->mes > 1 ? $factura->mes - 1 : 12;
                        $anioPrevio = $factura->mes > 1 ? $factura->anio : $factura->anio - 1;
                        $mesesN = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                        @endphp
                        <span style="font-size:.62rem;color:#64748b">(pago adelantado de {{ $mesesN[$mesPrevio-1] }}. {{ $anioPrevio }})</span>
                    </span>
                    <strong>−{{ $fmt($totSaldoFavor) }}</strong>
                </div>
            </div>
            @endif
            {{-- Deuda de mes anterior recuperada --}}
            @if($totSaldoPend > 0)
            <div style="margin-top:.2rem;padding-top:.2rem;border-top:1px solid #fee2e2;">
                <div class="srow" style="color:#dc2626;font-size:.74rem;">
                    <span>🔴 Recuperación préstamo
                        <span style="font-size:.62rem;color:#64748b">(adeudo mes anterior)</span>
                    </span>
                    <strong>+{{ $fmt($totSaldoPend) }}</strong>
                </div>
            </div>
            @endif
            @if($totMens > 0 || $totOtros > 0 || $totOtrosAdmon > 0 || $totIvaSimp > 0)
            <div style="margin-top:.3rem;padding-top:.3rem;border-top:1px solid #e2e8f0;font-size:.72rem;">
                @if($totMens > 0)<div class="srow"><span style="color:#64748b">Mensajería</span><strong>{{ $fmt($totMens) }}</strong></div>@endif
                @if($totOtros > 0)<div class="srow"><span style="color:#64748b">Otros planilla</span><strong>{{ $fmt($totOtros) }}</strong></div>@endif
                @if($totOtrosAdmon > 0)<div class="srow"><span style="color:#64748b">Otros admón</span><strong>{{ $fmt($totOtrosAdmon) }}</strong></div>@endif
                @if($totIvaSimp > 0)<div class="srow"><span style="color:#64748b">IVA / 4×mil</span><strong>{{ $fmt($totIvaSimp) }}</strong></div>@endif
            </div>
            @endif
        </div>

        {{-- Nota legal (solo planilla/afiliacion, no otro_ingreso) --}}
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.5rem .85rem;font-size:.69rem;color:#92400e;line-height:1.45;display:flex;align-items:flex-start;gap:.35rem;">
            <span style="font-size:1rem;flex-shrink:0">⚠️</span>
            <span><strong>IMPORTANTE —</strong> Las incapacidades por enfermedad común o accidente laboral serán reconocidas por la EPS y la ARL <strong>únicamente</strong> cuando los aportes al Sistema de Seguridad Social se hayan realizado de forma oportuna, es decir, <strong>a más tardar el décimo (10°) día hábil de cada mes</strong>. El incumplimiento puede generar el no reconocimiento de las prestaciones.</span>
        </div>

    </div>
@endif
</div>

{{-- ══ DESGLOSE PAGO ═══════════════════════════════════════════════════ --}}
<div class="dc">
<div class="box2">
    <div class="ibox">
        <div class="ilbl">Resumen financiero</div>
        @if($factura->tipo !== 'otro_ingreso')
        <div class="srow"><span>Seg. Social</span><strong>{{ $fmt($totSS) }}</strong></div>
        @endif
        <div class="srow"><span>Administración{{ $factura->tipo === 'otro_ingreso' ? ' / Trámite' : '' }}</span><strong>{{ $fmt($totAdmon + ($factura->admon_asesor_oi ?? 0)) }}</strong></div>

        @if($totAfil > 0)
        <div class="srow"><span>Afiliaciones</span><strong>{{ $fmt($totAfil) }}</strong></div>
        @endif

        @if($totSeg > 0)
        <div class="srow"><span>Seguros</span><strong>{{ $fmt($totSeg) }}</strong></div>
        @endif

        @if($totIva > 0)
        <div class="srow"><span>IVA</span><strong>{{ $fmt($totIva) }}</strong></div>
        @endif

        @php
        $mesAnt = $factura->mes > 1 ? $factura->mes - 1 : 12;
        $anioAnt = $factura->mes > 1 ? $factura->anio : $factura->anio - 1;
        $mesesArr = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        @endphp
        @if(($factura->saldo_a_favor ?? 0) > 0)
        <div class="srow" style="color:#15803d;border-top:1px solid #d1fae5;padding-top:.25rem;margin-top:.25rem">
            <span>✅ Anticipo aplicado <small style="font-size:.62rem;color:#64748b;">(pago adelantado {{ $mesesArr[$mesAnt-1] }} {{ $anioAnt }})</small></span>
            <strong>−{{ $fmt($factura->saldo_a_favor) }}</strong>
        </div>
        @endif
        @if(($factura->saldo_pendiente ?? 0) > 0)
        <div class="srow" style="color:#dc2626;border-top:1px solid #fee2e2;padding-top:.25rem;margin-top:.25rem">
            <span>🔴 Recuperación préstamo <small style="font-size:.62rem;color:#64748b;">(adeudo {{ $mesesArr[$mesAnt-1] }} {{ $anioAnt }})</small></span>
            <strong>+{{ $fmt($factura->saldo_pendiente) }}</strong>
        </div>
        @endif
    </div>
    <div class="ibox">
        <div class="ilbl">Forma de pago</div>
        <div class="srow"><span>Tipo</span><strong>{{ ucfirst(str_replace('_',' ',$factura->forma_pago ?? '')) }}</strong></div>

        @if($totEfect > 0)
        <div class="srow" style="color:#15803d"><span>💵 Efectivo</span><strong>{{ $fmt($totEfect) }}</strong></div>
        @endif

        @foreach($factura->consignaciones as $csg)
        <div class="srow">
            <span>🏦 {{ ($csg->bancoCuenta?->banco ? $csg->bancoCuenta->banco.' — ' : '') }}{{ $csg->bancoCuenta?->nombre ?? 'Banco' }}
                @if($csg->confirmado) <span style="color:#15803d;font-size:.67rem;font-weight:700">✓ Confirmado</span>@endif
            </span>
            <strong>{{ $fmt($csg->valor) }}</strong>
        </div>
        <div style="font-size:.68rem;color:#64748b;padding-left:.5rem">
            {{ $csg->bancoCuenta?->tipo_cuenta }} {{ $csg->bancoCuenta?->numero_cuenta }}
            &middot; {{ \Carbon\Carbon::parse($csg->fecha)->format('d/m/Y') }}
            @if($csg->referencia) &middot; Ref: {{ $csg->referencia }}@endif
        </div>
        @endforeach

        @if($totPrest > 0)
        <div class="srow" style="color:#7c3aed"><span>💳 Préstamo (pendiente)</span><strong>{{ $fmt($totPrest) }}</strong></div>
        @endif

        @if($factura->observacion)
        <div style="font-size:.71rem;color:#94a3b8;margin-top:.3rem">{{ $factura->observacion }}</div>
        @endif
    </div>
</div>
</div>

{{-- ══ TOTAL: para otro_ingreso solo en vista detallada (vista simple ya tiene el total en el grid) ══ --}}
@if($factura->tipo === 'otro_ingreso')
<div class="dc">
<div class="total-bx">
    <span style="font-size:.9rem;font-weight:700">TOTAL A PAGAR</span>
    <span class="total-v">{{ $fmt($totTotal) }}</span>
</div>
</div>
@else
<div class="total-bx">
    <span style="font-size:.9rem;font-weight:700">TOTAL A PAGAR</span>
    <span class="total-v">{{ $fmt($totTotal) }}</span>
</div>
@endif

@if($totPrest > 0)
<div style="display:flex;justify-content:space-between;background:#ede9fe;border-radius:6px;padding:.4rem .85rem;margin-top:.35rem;font-size:.8rem;color:#6d28d9">
    <span>Recibido: <strong>{{ $fmt($totTotal - $totPrest) }}</strong></span>
    <span>Préstamo a cobrar: <strong>{{ $fmt($totPrest) }}</strong></span>
</div>
@endif

{{-- ══ NOTA LEGAL: solo planilla/afiliación (no otro_ingreso) ══ --}}
@if($factura->tipo !== 'otro_ingreso')
<div class="dc" style="margin-top:.75rem;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;
            padding:.5rem .85rem;font-size:.7rem;color:#92400e;line-height:1.45;">
    <span style="font-weight:800;">⚠️ IMPORTANTE &mdash;</span>
    Las incapacidades por enfermedad común o accidente laboral serán reconocidas por la EPS y la ARL
    <strong>únicamente</strong> cuando los aportes al Sistema de Seguridad Social se hayan realizado de forma
    oportuna, es decir, <strong>a más tardar el décimo (<span style="text-decoration:underline">10º</span>) día hábil de cada mes</strong>.
    El incumplimiento en los pagos puede generar el no reconocimiento de las prestaciones económicas o asistenciales.
</div>
@endif

</div>{{-- /rec-body --}}
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
        <input type="checkbox" id="an_np" style="width:16px;height:16px">
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
// Aplicar modo simplificado al cargar
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('rw').classList.add('simp');
    const btn = document.querySelector('button[onclick="toggleSimp()"]');
    if (btn) btn.textContent = '📋 Vista detallada';
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
            alert(data.mensaje);
            window.location.href = URL_IDX;
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
