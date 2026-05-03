@extends('layouts.app')
@section('modulo', 'Facturación')

@php
use App\Models\BancoCuenta;
$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fmt   = fn($v) => '$' . number_format($v ?? 0, 0, ',', '.');
$aliadoId = session('aliado_id_activo');
$r100 = fn($v) => (int)(ceil(($v ?? 0) / 100) * 100); // redondeo al centena superior

$estadoLabel = fn($e) => match($e) {
    'pagada'      => 'Pago',
    'abono'       => 'Abono',
    'pre_factura' => 'Pre-factura',
    'prestamo'    => 'Préstamo',
    default       => ucfirst($e)
};
$estadoBg = fn($e) => match($e) {
    'pagada'      => ['#dcfce7','#15803d'],
    'abono'       => ['#fef3c7','#92400e'],
    'prestamo'    => ['#ede9fe','#6d28d9'],
    default       => ['#f1f5f9','#64748b'],
};
@endphp

@section('contenido')
<style>
.fac-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
.fac-h-top{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.fac-h-nom{font-size:1.3rem;font-weight:800}
.fac-h-meta{font-size:.78rem;color:#94a3b8;display:flex;gap:1.2rem;margin-top:.3rem;flex-wrap:wrap}
.periodo-sel{display:flex;align-items:center;gap:.5rem}
.periodo-sel select{padding:.35rem .6rem;border-radius:7px;border:1px solid #334155;background:#1e293b;color:#fff;font-size:.85rem}
.btn-act{padding:.4rem 1rem;font-size:.82rem;font-weight:600;border-radius:7px;border:none;cursor:pointer;transition:all .15s}
.btn-fac{background:#2563eb;color:#fff}.btn-fac:hover{background:#1d4ed8}
.btn-exp{background:#475569;color:#fff}.btn-exp:hover{background:#334155}
.btn-sm{padding:.25rem .65rem;font-size:.72rem;border-radius:5px;border:none;cursor:pointer;font-weight:600}
.fil-btn{padding:.3rem .8rem;border-radius:20px;font-size:.75rem;font-weight:600;border:1.5px solid #e2e8f0;background:#f8fafc;cursor:pointer;transition:all .15s}
.fil-btn.active{border-color:#2563eb;background:#eff6ff;color:#1d4ed8}
/* Tabla */
.tbl-wrap{overflow-x:auto}
table.fac-tbl{width:100%;border-collapse:collapse;font-size:.78rem}
.fac-tbl th{background:#0f172a;color:#94a3b8;font-size:.63rem;text-transform:uppercase;letter-spacing:.05em;padding:.4rem .45rem;white-space:nowrap;position:sticky;top:0;z-index:2}
.fac-tbl td{padding:.32rem .45rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}
.fac-tbl tr:hover td{background:#f8fafc}
.fac-tbl tr.ya-pago td{background:#f0fdf4}
.fac-tbl input[type=checkbox]{width:1.1rem;height:1.1rem;cursor:pointer}
.num-col{font-family:monospace;text-align:right}
.totales{background:#0f172a;color:#fff;font-weight:700}
.tot-val{color:#34d399}
/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;display:flex;align-items:center;justify-content:center}
.modal-box{background:#fff;border-radius:14px;padding:1.4rem;width:min(600px,96vw);max-height:92vh;overflow-y:auto}
.modal-title{font-size:1rem;font-weight:800;margin-bottom:.9rem;color:#0f172a;border-bottom:2px solid #e2e8f0;padding-bottom:.45rem}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin-bottom:.55rem}
.form-full{grid-column:1/-1}
.flb{display:block;font-size:.67rem;font-weight:700;color:#475569;margin-bottom:.15rem;text-transform:uppercase}
.finp{width:100%;padding:.36rem .48rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.82rem;box-sizing:border-box}
.finp:focus{outline:none;border-color:#3b82f6}
.resumen-box{background:#f8fafc;border-radius:8px;padding:.65rem .85rem;margin:.7rem 0;font-size:.82rem}
.resumen-row{display:flex;justify-content:space-between;padding:.15rem 0}
.resumen-row.total{font-weight:700;border-top:1px solid #e2e8f0;margin-top:.3rem;padding-top:.38rem;font-size:.95rem;color:#0f172a}
.btn-guardar{width:100%;padding:.65rem;background:#2563eb;color:#fff;font-size:.92rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;margin-top:.45rem}
.btn-guardar:hover{background:#1d4ed8}
.btn-cancelar{margin-right:.5rem;padding:.48rem 1.1rem;background:#f1f5f9;color:#475569;border:none;border-radius:7px;cursor:pointer;font-weight:600}
</style>

{{-- Header empresa --}}
<div class="fac-header">
    <div class="fac-h-top">
        {{-- Info empresa --}}
        <div>
            <a href="{{ route('admin.facturacion.index') }}" style="color:#94a3b8;text-decoration:none;font-size:.8rem;">← Empresas</a>
            <div class="fac-h-nom" style="margin-top:.2rem;">🏢 {{ $empresa->empresa }}</div>
            <div class="fac-h-meta">
                @if($empresa->nit)<span>NIT: {{ $empresa->nit }}</span>@endif
                @if($empresa->asesor)<span>👤 {{ $empresa->asesor->nombre }}</span>
                @elseif($empresa->contacto)<span>👤 {{ $empresa->contacto }}</span>@endif
                @if($empresa->celular)<span>📞 {{ $empresa->celular }}</span>@endif
{{-- IVA oculto del encabezado --}}
            </div>
        </div>

        {{-- Botones del header: Historial + Editar --}}
        <div style="display:flex;align-items:center;gap:.45rem;">
            <a href="{{ route('admin.facturacion.empresa.historial', $empresa->id) }}"
               style="display:inline-flex;align-items:center;gap:.35rem;padding:.38rem .85rem;font-size:.8rem;font-weight:600;border-radius:7px;background:rgba(255,255,255,.1);color:#cbd5e1;text-decoration:none;transition:background .15s;"
               onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'"
               title="Historial de facturación">📋 Historial</a>
            <button type="button" onclick="abrirClavesEmpresa()"
               style="display:inline-flex;align-items:center;gap:.35rem;padding:.38rem .85rem;font-size:.8rem;font-weight:600;border-radius:7px;background:#fef9c3;color:#92400e;border:1px solid #fde68a;cursor:pointer;transition:background .15s;"
               onmouseover="this.style.background='#fde68a'" onmouseout="this.style.background='#fef9c3'"
               title="Claves y accesos de la empresa">🔑 Claves</button>
            <a href="{{ route('admin.facturacion.empresa.edit', $empresa->id) }}"
               style="display:inline-flex;align-items:center;gap:.35rem;padding:.38rem .85rem;font-size:.8rem;font-weight:600;border-radius:7px;background:#f59e0b;color:#fff;text-decoration:none;transition:background .15s;"
               onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'"
               title="Editar empresa">✏️ Editar</a>
        </div>
    </div>
</div>

{{-- Filtros + Acciones --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:.6rem 1rem;margin-bottom:.8rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.55rem;">
    {{-- Izquierda: filtros --}}
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;">
        <span class="fil-btn active" onclick="filtrar(this,'todos')">Todos ({{ $contratos->count() }})</span>
        <span class="fil-btn" onclick="filtrar(this,'pendiente')">⏳ Pendientes</span>
        <span class="fil-btn" onclick="filtrar(this,'pago')">✅ Pagados</span>
        <span class="fil-btn" onclick="filtrar(this,'prestamo')">💳 Préstamo</span>
    </div>
    <div style="display:flex;gap:.45rem;align-items:center;flex-wrap:wrap;">


        <button class="btn-act btn-exp" onclick="exportarExcel()">📊 Excel</button>

        <button class="btn-act" onclick="OI_abrirEmpresa()"
            style="background:linear-gradient(135deg,#065f46,#047857);color:#fff;"
            title="Registrar trámite / otro ingreso para esta empresa"
            data-empresa-id="{{ $empresa->id }}"
            data-empresa-asesor-id="{{ $empresa->asesor_id ?? '' }}"
            data-empresa-asesor-nombre="{{ $empresa->asesor?->nombre ?? '' }}">
            💼 Otro Ingreso
        </button>

        <button class="btn-act" id="btnCuentaCobro" onclick="abrirCuentaCobro('simple')" disabled
            style="background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;"
            title="Generar Cuenta de Cobro">
            📄 Cuenta Cobro
        </button>

        <button class="btn-act btn-fac" id="btnFacturarSel" onclick="abrirModalFacturar()" disabled>
            🧾 Facturar
        </button>

        {{-- Contador seleccionados (separado) --}}
        <span id="ctSelecBadge" style="
            display:inline-flex;align-items:center;justify-content:center;
            min-width:26px;height:26px;padding:0 .45rem;
            background:#1e3a5f;color:#fff;border-radius:20px;
            font-size:.78rem;font-weight:800;font-family:monospace;
            transition:background .2s;
        " title="Seleccionados"><span id="ctSelec">0</span></span>

        <div style="width:1px;height:24px;background:#e2e8f0;"></div>

        {{-- Selector periodo (mes / año) --}}
        <form method="GET" id="formPeriodo" style="display:flex;align-items:center;gap:.3rem;">
            <select name="mes" onchange="this.form.submit()"
                    style="padding:.28rem .5rem;border-radius:6px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.8rem;cursor:pointer;color:#334155;">
                @foreach($meses as $i=>$nm)
                <option value="{{ $i+1 }}" {{ ($i+1)===$mes?'selected':'' }}>{{ $nm }}</option>
                @endforeach
            </select>
            <select name="anio" onchange="this.form.submit()"
                    style="padding:.28rem .4rem;border-radius:6px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.8rem;cursor:pointer;color:#334155;">
                @for($y=now()->year+1;$y>=2020;$y--)
                <option value="{{ $y }}" {{ $y===$anio?'selected':'' }}>{{ $y }}</option>
                @endfor
            </select>
        </form>
    </div>
</div>


{{-- Tabla --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
<div class="tbl-wrap">
<table class="fac-tbl" id="tblTrab">
<thead><tr>
    <th>TIPO</th><th>CÉDULA</th><th>NOMBRE</th><th>RAZÓN SOCIAL</th>
    <th style="text-align:center">INGRESO / RETIRO</th><th style="text-align:center">DÍAS</th>
    <th class="num-col">EPS</th><th class="num-col">ARL</th>
    <th class="num-col">CAJA</th><th class="num-col">PENSIÓN</th>
    <th class="num-col">ADMON</th><th class="num-col" style="display:none">IVA</th>
    <th class="num-col" style="color:#34d399">TOTAL</th>
    <th class="num-col" title="Mora estimada al cliente" style="color:#fbbf24">⚠️ MORA</th>
    <th style="text-align:center">ESTADO</th><th style="text-align:center">NP</th>
    <th style="text-align:center">
        <input type="checkbox" id="chkAll" onchange="toggleAll(this)" title="Seleccionar todos"
               style="width:1rem;height:1rem;cursor:pointer;vertical-align:middle;"> SEL
    </th>
</tr></thead>
<tbody>
@php
$totEps=$totArl=$totCaja=$totPen=$totAdmon=$totIva=$totTotal=$totMora=0;
$totAFavor=$totPendiente=0;
@endphp
@forelse($contratos as $c)
@php
$fact  = $c->factura_exist;
$yaP   = $fact && in_array($fact->estado,['pagada','prestamo']);
// Nombre: solo primer nombre + primer apellido
$nombre = trim(($c->cliente?->primer_nombre ?? '') . ' ' . ($c->cliente?->primer_apellido ?? ''));
if(!$nombre) $nombre = $c->cliente?->nombre_completo ?? '—';
// Tipo: campo tipo_modalidad directo (ej: 'E', 'I')
$tipoMod    = $c->tipoModalidad?->tipo_modalidad ?? '—';
$tipoNom    = $c->tipoModalidad?->nombre ?? '—';  // tooltip
$rs         = $c->razonSocial?->razon_social ?? '—';
$esRetirado = $c->estado === 'retirado';
$fIng       = $c->fecha_ingreso ? $c->fecha_ingreso->format('d/m/Y') : '—';
$fRet       = ($esRetirado && $c->fecha_retiro) ? $c->fecha_retiro->format('d/m/Y') : null;
$dias       = $c->dias_cotizar ?? 30;
// Detectar si este período debe ser afiliación pura (I VENC, empresa)
// vs I ACT primer mes (viene del controlador como es_ind_act_primer_mes)
$esIndep          = $c->tipoModalidad?->esIndependiente() ?? false;
$esIndActPrimerMes = $c->es_ind_act_primer_mes ?? false; // flag del controller
$esAfil = false;
if ($c->fecha_ingreso) {
    $fIngC = $c->fecha_ingreso;
    if ((int)$fIngC->month === $mes && (int)$fIngC->year === $anio) {
        // I ACT: NO es afiliación pura (cobra SS también)
        // I VENC y empresa: sí es afiliación pura
        if (!$esIndActPrimerMes) {
            $esAfil = true;
        }
    }
}
// Si ya hay factura, usar su tipo (planilla o afiliacion)
if ($fact) {
    // I ACT primer mes puede tener tipo='planilla' con afiliación incluida
    $esAfil = $fact->tipo === 'afiliacion' && !($fact->afiliacion > 0 && $fact->total_ss > 0);
}
// Tiempo Parcial: detectar y obtener días por entidad
$esTP     = $c->tipoModalidad?->esTiempoParcial() ?? false;
$diasTP   = $esTP ? $c->tipoModalidad->diasPorEntidad() : null;
// Valores: si hay factura usar los reales; si es retirado sin factura → 0; si es activo sin factura → estimar
$vEps  = $fact ? $r100($fact->v_eps)  : 0;
$vArl  = $fact ? $r100($fact->v_arl)  : 0;
$vCaja = $fact ? $r100($fact->v_caja) : 0;
$vPen  = $fact ? $r100($fact->v_afp)  : 0;
$vAdm  = $fact ? (int)($fact->admon + $fact->admin_asesor) : ($esRetirado ? 0 : (int)(($c->administracion??0) + ($c->admon_asesor??0)));
$vIva  = $fact ? $r100($fact->iva)    : 0;
// Total y SS
$cotiz = $c->calcularCotizacion($dias);
if (!$fact) {
    if ($esRetirado) {
        // Retirado sin factura aún → mostrar todo en 0
        $vEps = $vArl = $vPen = $vCaja = $vIva = $vAdm = $vSS = 0;
        $vTot = 0;
    } elseif ($esIndActPrimerMes) {
        // I ACT primer mes: SS reales (días del mes) + afiliación + admon
        $vEps  = $r100($cotiz['eps']??0);
        $vArl  = $r100($cotiz['arl']??0);
        $vPen  = $r100($cotiz['pen']??0);
        $vCaja = $r100($cotiz['caja']??0);
        $vIva  = $r100($cotiz['iva']??0);
        $vSS   = $r100($cotiz['ss']);
        // admon ya calculado arriba desde contrato
        $vTot  = $vSS + $vAdm + $vIva + (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
    } elseif ($esAfil) {
        // Afiliación pura (I VENC, empresa): SS=0, admon=0
        $vEps  = 0; $vArl  = 0; $vPen  = 0; $vCaja = 0;
        $vSS   = 0; $vIva  = 0; $vAdm  = 0;
        $vTot  = (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
    } else {
        $vEps  = $r100($cotiz['eps']??0);
        $vArl  = $r100($cotiz['arl']??0);
        $vPen  = $r100($cotiz['pen']??0);
        $vCaja = $r100($cotiz['caja']??0);
        $vIva  = $r100($cotiz['iva']??0);
        $vSS   = $r100($cotiz['ss']);
        $vTot  = $vSS + $vAdm + $vIva;
    }
} else {
    $vSS = $r100($fact->total_ss);
    $vTot = (int)$fact->total;
}
// Mora: si ya tiene factura usar facturas.mora; si no, estimar
$vMora = 0;
try {
    $aliadoIdEmp = session('aliado_id_activo');
    if ($fact && ($fact->mora ?? 0) > 0) {
        $vMora = (int)$fact->mora;
    } elseif (!$fact && !$esRetirado && !$esAfil && $vSS > 0) {
        $rsEmp   = $c->razonSocial;
        $rsNitE  = $rsEmp ? (int)($rsEmp->nit ?: $rsEmp->id) : 0;
        $rsDiaHE = $rsEmp ? ($rsEmp->dia_habil ?? null) : null;
        if ($rsNitE) {
            $mi = \App\Services\MoraClienteService::calcular($aliadoIdEmp, $rsNitE, $rsDiaHE, $vSS, $mes, $anio);
            $vMora = $mi['mora'];
        }
    }
} catch (\Throwable) {}
// Costo de afiliación para data-* (lo necesita el modal)
$vAfiliacion = ($esAfil || $esIndActPrimerMes) ? (int)($c->costo_afiliacion ?? 0) : 0;
$totEps+=$vEps;$totArl+=$vArl;$totCaja+=$vCaja;$totPen+=$vPen;
$totAdmon+=$vAdm;$totIva+=$vIva;$totTotal+=$vTot;$totMora+=$vMora;
@endphp
<tr class="{{ $yaP?'ya-pago':'' }}"
    data-estado="{{ $fact?->estado ?? 'sin_factura' }}"
    data-cedula="{{ $c->cedula }}"
    data-contrato="{{ $c->id }}"
    data-dias="{{ $dias }}"
    data-veps="{{ $vEps }}" data-varl="{{ $vArl }}"
    data-vpen="{{ $vPen }}" data-vcaja="{{ $vCaja }}"
    data-vadmon="{{ $vAdm }}" data-viva="{{ $vIva }}"
    data-vtot="{{ $vTot }}"
    data-seguro="{{ (int)($c->seguro??0) }}"
    data-nombre="{{ $nombre }}"
    data-esafil="{{ $esAfil ? '1' : '0' }}"
    data-esindact="{{ $esIndActPrimerMes ? '1' : '0' }}"
    data-afiliacion="{{ $vAfiliacion }}"
    data-tipo="{{ ($esAfil && !$esIndActPrimerMes) ? 'afiliacion' : 'planilla' }}">

    <td style="font-size:.75rem;font-weight:700;text-align:center;white-space:nowrap;" title="{{ $tipoNom }}{{ $esIndActPrimerMes ? ' · Afiliación + Planilla' : '' }}{{ $esRetirado ? ' · RETIRADO' : '' }}">
        <span style="display:inline-flex;align-items:center;gap:3px;flex-direction:column;">
            <span style="display:inline-flex;align-items:center;gap:3px;">
                {{ $tipoMod }}
                <a href="{{ route('admin.contratos.edit', $c->id) }}?back={{ urlencode(url()->current()) }}"
                   title="Abrir contrato · {{ $tipoNom }}"
                   style="color:{{ $esIndActPrimerMes ? '#7c3aed' : ($esRetirado ? '#dc2626' : '#64748b') }};text-decoration:none;line-height:1;font-size:.85rem;"
                   onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='{{ $esIndActPrimerMes ? '#7c3aed' : ($esRetirado ? '#dc2626' : '#64748b') }}'">
                    @if($esIndActPrimerMes)&#9889;@elseif($esAfil)&#128204;@elseif($esRetirado)&#128683;@else&#9741;@endif
                </a>
            </span>
            @if($esRetirado)
            <span style="font-size:.55rem;background:#fee2e2;color:#dc2626;border-radius:4px;padding:.05rem .3rem;font-weight:800;letter-spacing:.03em;">RETIRO</span>
            @endif
        </span>
    </td>
    <td style="font-family:monospace;font-size:.75rem">{{ number_format($c->cedula,0,'','.') }}</td>
    <td style="max-width:170px;overflow:hidden;text-overflow:ellipsis;font-weight:500">
        @if($c->cliente?->id)
        <a href="{{ route('admin.clientes.edit', $c->cliente->id) }}"
           style="color:#1d4ed8;text-decoration:none;font-weight:600;"
           title="Ver cliente"
           onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
            {{ $nombre }}
        </a>
        @else
            {{ $nombre }}
        @endif
    </td>
    <td style="font-size:.7rem;color:#64748b;max-width:130px;overflow:hidden;text-overflow:ellipsis" title="{{ $rs }}">{{ Str::limit($rs,16) }}</td>
    <td style="text-align:center;font-size:.75rem;">
        @if($esRetirado && $fRet)
            <span style="color:#dc2626;font-weight:700;">{{ $fRet }}</span>
        @else
            <span style="color:#64748b;">{{ $fIng }}</span>
        @endif
    </td>
    <td style="text-align:center;font-weight:700;color:{{ $dias===0?'#9333ea':($dias<30?'#d97706':'#0f172a') }}">
        @if($esTP && $diasTP)
            <span title="T. Parcial: ARL {{ $diasTP['arl'] }}d · AFP {{ $diasTP['afp'] }}d · CAJA {{ $diasTP['caja'] }}d"
                  style="font-size:.62rem;background:#fef3c7;color:#78350f;border-radius:6px;padding:.1rem .35rem;font-weight:700;cursor:help;white-space:nowrap;">
                ⏱TP
            </span>
        @else
            {{ $dias === 0 ? '—' : $dias }}
        @endif
    </td>
    <td class="num-col">{{ (!$esTP && $vEps>0)?'$'.number_format($vEps,0,',','.'):'—' }}</td>
    <td class="num-col">{{ $vArl>0?'$'.number_format($vArl,0,',','.'):'—' }}</td>
    <td class="num-col">{{ $vCaja>0?'$'.number_format($vCaja,0,',','.'):'—' }}</td>
    <td class="num-col">{{ $vPen>0?'$'.number_format($vPen,0,',','.'):'—' }}</td>
    <td class="num-col">${{ number_format($vAdm,0,',','.') }}</td>
    <td class="num-col" style="display:none">{{ $vIva>0?'$'.number_format($vIva,0,',','.'):'—' }}</td>
    <td class="num-col" style="font-weight:700;color:{{ $yaP?'#16a34a':'#0f172a' }}">
        ${{ number_format($vTot,0,',','.') }}
    </td>
    {{-- Mora: real si ya facturada, estimada si no --}}
    <td class="num-col">
        @if($vMora > 0)
            <span style="display:inline-block;padding:.1rem .4rem;border-radius:20px;font-size:.62rem;font-weight:700;background:#fef3c7;color:#92400e;"
                  title="{{ $fact && ($fact->mora??0)>0 ? 'Mora cobrada en factura' : 'Mora estimada (aún sin facturar)' }}">
                ${{ number_format($vMora,0,',','.') }}
            </span>
        @else
            <span style="color:#cbd5e1;font-size:.7rem">—</span>
        @endif
    </td>
    @php
        $totAFavor    += $c->saldo_a_favor;
        $totPendiente += $c->saldo_pendiente;
        $totProxFavor    = ($totProxFavor ?? 0)    + $c->saldo_proximo_favor;
        $totProxPendiente= ($totProxPendiente ?? 0) + $c->saldo_proximo_pendiente;
    @endphp
    <td style="text-align:center">
        @if($fact)
        @php $colores=$estadoBg($fact->estado); @endphp
        <span style="display:inline-block;padding:.16rem .5rem;border-radius:20px;font-size:.63rem;font-weight:700;background:{{ $colores[0] }};color:{{ $colores[1] }}">
            {{ $estadoLabel($fact->estado) }}
        </span>
        @else<span style="color:#94a3b8;font-size:.7rem">Sin factura</span>@endif
    </td>
    <td style="text-align:center;font-weight:700;color:#2563eb;font-size:.8rem">{{ $fact?->np ?? '—' }}</td>
    <td style="text-align:center;white-space:nowrap;">
        @if($fact)
            <button onclick="abrirRecibo('{{ route('admin.facturacion.recibo',$fact->id) }}?modal=1')"
               class="btn-sm" style="background:#eff6ff;color:#1d4ed8;" title="Ver recibo">🖨</button>
            @if(!in_array($fact->estado,['pagada']))
            <button class="btn-sm" style="background:#fef3c7;color:#92400e;margin-left:2px;"
                onclick="abrirAbono({{ $fact->id }},{{ $fact->total }},{{ $fact->total_abonado??0 }})">💵</button>
            @endif
        @else
            <input type="checkbox" class="chk-row" value="{{ $c->id }}"
                   onchange="onCheckChange()"
                   style="width:1.1rem;height:1.1rem;cursor:pointer;accent-color:#2563eb;"
                   title="Seleccionar para facturar">
        @endif
    </td>
</tr>
@empty
<tr><td colspan="18" style="text-align:center;padding:2rem;color:#94a3b8">No hay contratos activos ni retiros del mes anterior para esta empresa en este período.</td></tr>
@endforelse
</tbody>
<tfoot>
<tr class="totales">
    <td colspan="6" style="padding:.5rem;font-size:.73rem;">TOTALES ({{ $contratos->count() }} contratos)</td>
    <td class="num-col tot-val">${{ number_format($totEps,  0,',','.') }}</td>
    <td class="num-col tot-val">${{ number_format($totArl,  0,',','.') }}</td>
    <td class="num-col tot-val">${{ number_format($totCaja, 0,',','.') }}</td>
    <td class="num-col tot-val">${{ number_format($totPen,  0,',','.') }}</td>
    <td class="num-col tot-val">${{ number_format($totAdmon,0,',','.') }}</td>
    <td class="num-col tot-val" style="display:none">${{ number_format($totIva,  0,',','.') }}</td>
    <td class="num-col tot-val" style="font-size:.9rem">${{ number_format($totTotal,0,',','.') }}</td>
    <td class="num-col tot-val" style="color:#fbbf24;font-weight:800;">
        {{ $totMora > 0 ? '$'.number_format($totMora,0,',','.') : '—' }}
    </td>
    <td colspan="3"></td>
</tr>
</tfoot>
</table>
</div>
</div>

{{-- ─── Panel saldo neto de la EMPRESA (calculado en el controlador) ─────────
     Usa empresa_id: suma TODOS los saldo_proximo hasta e incluyendo el mes actual.
     Abril: +700k  |  Mayo: +700k - 700k = 0  |  Junio: correcto
--}}
@if($saldoEmpresaFavor > 0 || $saldoEmpresaPendiente > 0)
<div style="display:flex;justify-content:flex-end;gap:.6rem;flex-wrap:wrap;margin-top:.55rem;">

    @if($saldoEmpresaFavor > 0)
    <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:.55rem .9rem;display:flex;align-items:center;gap:.5rem;min-width:210px;">
        <span style="font-size:1.2rem;">✅</span>
        <div>
            <div style="font-size:.6rem;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.04em;">Saldo a favor empresa</div>
            <div style="font-size:.95rem;font-weight:800;color:#15803d;">+${{ number_format($saldoEmpresaFavor,0,',','.') }}</div>
            <div style="font-size:.58rem;color:#4ade80;">Se descuenta automáticamente al facturar</div>
        </div>
    </div>
    @endif

    @if($saldoEmpresaPendiente > 0)
    <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:.55rem .9rem;display:flex;align-items:center;gap:.5rem;min-width:210px;">
        <span style="font-size:1.2rem;">⚠️</span>
        <div>
            <div style="font-size:.6rem;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.04em;">Pendiente empresa</div>
            <div style="font-size:.95rem;font-weight:800;color:#dc2626;">-${{ number_format($saldoEmpresaPendiente,0,',','.') }}</div>
            <div style="font-size:.58rem;color:#fca5a5;">Se suma al total al facturar</div>
        </div>
    </div>
    @endif

</div>
@endif

{{-- ═══ MODAL FACTURAR UNIFICADO ══════════════════════════════════ --}}
@php $mfMes = $mes; $mfAnio = $anio; @endphp
@include('admin.facturacion._modal_facturar', ['bancos' => $bancos, 'mfMes' => $mfMes, 'mfAnio' => $mfAnio])

{{-- ═══ MODAL OTRO INGRESO ═════════════════════════════════════════ --}}
@php $oiMes = $mes; $oiAnio = $anio; $oiEmpresaId = $empresa->id; @endphp
@include('admin.facturacion._modal_otro_ingreso', [
    'bancos' => $bancos, 'oiMes' => $oiMes, 'oiAnio' => $oiAnio, 'oiEmpresaId' => $oiEmpresaId
])





{{-- ═══ MODAL ABONAR ═════════════════════════════════════════════ --}}
<div id="modalAbonar" class="modal-overlay" style="display:none;" onclick="cerrarSi(event,'modalAbonar')">
<div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-title">💵 Registrar Abono</div>
    <input type="hidden" id="ab_id">
    <div class="resumen-box" style="margin-bottom:.7rem;">
        <div class="resumen-row"><span>Total factura</span><strong id="ab_total">$0</strong></div>
        <div class="resumen-row"><span>Ya abonado</span><span id="ab_ya">$0</span></div>
        <div class="resumen-row total"><span>Saldo restante</span><strong id="ab_rest" style="color:#dc2626">$0</strong></div>
    </div>
    <div class="form-row">
        <div><label class="flb">Valor a abonar</label><input type="text" id="ab_valor" class="finp"></div>
        <div>
            <label class="flb">Forma de pago</label>
            <select id="ab_forma" class="finp" onchange="onAbForma()">
                <option value="efectivo">Efectivo</option>
                <option value="consignacion">Consignación</option>
                <option value="mixto">Mixto</option>
            </select>
        </div>
    </div>
    <div id="ab_banco_wrap" style="display:none;margin-bottom:.5rem;">
        <label class="flb">Cuenta bancaria</label>
        <select id="ab_banco" class="finp">
            <option value="">-- Seleccionar --</option>
            @foreach($bancos as $b)
            <option value="{{ $b->id }}">{{ $b->banco }} — {{ $b->nombre }} | {{ $b->numero_cuenta }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-row form-full" style="margin-bottom:.4rem;">
        <div class="form-full"><label class="flb">Observación</label><input type="text" id="ab_obs" class="finp" placeholder="Opcional..."></div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:.4rem;">
        <button class="btn-cancelar" onclick="cerrar('modalAbonar')">Cancelar</button>
        <button class="btn-guardar" style="width:auto;padding:.48rem 1.4rem;" onclick="guardarAbono()">💵 Registrar</button>
    </div>
</div>
</div>

@push('scripts')
<script src="{{ asset('js/modal_facturar.js') }}"></script>
<script>
const CSRF    = document.querySelector('meta[name="csrf-token"]').content;
const URL_FAC = '{{ route('admin.facturacion.facturar') }}';
let selec = [];

const numFmt   = v => '$' + Math.round(v||0).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
const numParse = s => parseInt((s||'0').replace(/[^0-9]/g,''))||0;

// ── Inicializar modal unificado en modo masivo ──────────────────
MF.init({
    modo: 'masivo',
    urlFacturar: URL_FAC,
    urlMesPagado: '', // no aplica en masivo
    urlSaldosContratos: '{{ route('admin.facturacion.api.saldos_contratos') }}',
    urlConsignacionImagen: '{{ route('admin.facturacion.consignacion.imagen.subir', ['id' => '__ID__']) }}',
    csrf: CSRF,
    empresaId: {{ $empresa->id }}, // para identificar pagos de empresa
    onExito: (data) => {
        MF.cerrar();
        if (data.recibo_url) {
            // Abrir recibo en el modal iframe existente (no nueva pestaña)
            abrirRecibo(data.recibo_url + '?modal=1');
            // El reload ocurre al cerrar el modal de recibo (ver cerrarRecibo)
        } else {
            location.reload();
        }
    }
});

// Bancos disponibles como array JS
const BANCOS = [
    {id:'', label:'-- Seleccionar banco --'},
    @foreach($bancos as $b)
    {id:{{ $b->id }}, label:{!! json_encode($b->banco . ' — ' . $b->nombre . ' | ' . $b->tipo_cuenta . ' ' . $b->numero_cuenta) !!}},
    @endforeach
];

// ─── Filtros ─────────────────────────────────────────────────
function filtrar(btn, tipo) {
    document.querySelectorAll('.fil-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#tblTrab tbody tr').forEach(tr=>{
        const est=tr.dataset.estado;
        let show=true;
        if(tipo==='pendiente') show=!['pagada','prestamo'].includes(est);
        else if(tipo==='pago')     show=est==='pagada';
        else if(tipo==='prestamo') show=est==='prestamo';
        tr.style.display=show?'':'none';
    });
}

// ─── Checkboxes ───────────────────────────────────────────────
function toggleAll(chk){
    // Solo selecciona checkboxes de filas VISIBLES (respeta el filtro activo)
    document.querySelectorAll('.chk-row:not(:disabled)').forEach(c=>{
        const fila = c.closest('tr');
        if (fila && fila.style.display !== 'none') {
            c.checked = chk.checked;
        }
    });
    onCheckChange();
}
function onCheckChange(){
    selec=[...document.querySelectorAll('.chk-row:checked')].map(c=>c.closest('tr'));
    const n=selec.length;
    document.getElementById('ctSelec').textContent=n;
    const sinSel = n===0;
    document.getElementById('btnFacturarSel').disabled=sinSel;
    document.getElementById('btnCuentaCobro').disabled=sinSel;
    actualizarResumen();
}

// ─── Cuenta de Cobro ─────────────────────────────────────────────
function abrirCuentaCobro(tipo) {
    if (!selec.length) return;
    const ids = selec.map(r => r.dataset.contrato);
    // Guardar en ventana para que la vista pueda redirigir al detallado
    window.__ccContratos = ids;
    // Crear form dinámico y enviarlo a nueva pestaña
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("admin.facturacion.cuenta_cobro.preview") }}';
    form.target = '_blank';
    form.style.display = 'none';
    // CSRF
    const csrf = document.createElement('input');
    csrf.type='hidden'; csrf.name='_token';
    csrf.value = CSRF;
    form.appendChild(csrf);
    // Tipo de vista
    const tInput = document.createElement('input');
    tInput.type='hidden'; tInput.name='tipo'; tInput.value = tipo;
    form.appendChild(tInput);
    // Mes y año
    const mesInput = document.createElement('input');
    mesInput.type='hidden'; mesInput.name='mes';
    mesInput.value = new URLSearchParams(location.search).get('mes') || '{{ $mes }}';
    form.appendChild(mesInput);
    const anioInput = document.createElement('input');
    anioInput.type='hidden'; anioInput.name='anio';
    anioInput.value = new URLSearchParams(location.search).get('anio') || '{{ $anio }}';
    form.appendChild(anioInput);
    // Empresa
    const empInput = document.createElement('input');
    empInput.type='hidden'; empInput.name='empresa_id'; empInput.value='{{ $empresa->id }}';
    form.appendChild(empInput);
    // Contratos seleccionados
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type='hidden'; inp.name='contratos[]'; inp.value=id;
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// ─── Resumen ─────────────────────────────────────────────
function _buildContratosSelec() {
    return selec.map(r => ({
        id:        r.dataset.contrato,
        eps:       parseInt(r.dataset.veps   || 0),
        arl:       parseInt(r.dataset.varl   || 0),
        afp:       parseInt(r.dataset.vpen   || 0),
        caja:      parseInt(r.dataset.vcaja  || 0),
        admon:     parseInt(r.dataset.vadmon || 0),
        seg:       parseInt(r.dataset.seguro || 0),
        iva:       parseInt(r.dataset.viva   || 0),
        arl_nivel: parseInt(r.dataset.arlnivel || 1),
        dias:      parseInt(r.dataset.dias   || 30),
        nombre:    r.dataset.nombre || '',
        tipo:      r.dataset.tipo   || 'planilla',
        afiliacion: parseInt(r.dataset.afiliacion || 0),
        esindact:  r.dataset.esindact === '1',   // I ACT primer mes: afil + planilla juntas
    }));
}

// ─── Abrir modal facturar ───────────────────────────────────
function abrirModalFacturar(){
    if(!selec.length) return;
    const contratos = _buildContratosSelec();
    MF.abrir(contratos, selec.length + ' trabajadores');
}

function facturarUno(id){
    const chk = document.querySelector(`.chk-row[value="${id}"]`);
    if(chk && !chk.disabled){ chk.checked=true; onCheckChange(); }
    abrirModalFacturar();
}

// guardarFactura() ha sido reemplazada por MF.guardar() en modal_facturar.js

// ─── Modal Abonar ─────────────────────────────────────────────
function abrirAbono(id,total,ya){
    document.getElementById('ab_id').value=id;
    document.getElementById('ab_total').textContent=numFmt(total);
    document.getElementById('ab_ya').textContent=numFmt(ya);
    document.getElementById('ab_rest').textContent=numFmt(total-ya);
    document.getElementById('ab_valor').value=0;
    document.getElementById('modalAbonar').style.display='flex';
}
function onAbForma(){
    const f=document.getElementById('ab_forma').value;
    document.getElementById('ab_banco_wrap').style.display=['consignacion','mixto'].includes(f)?'':'none';
}
async function guardarAbono(){
    const id  = document.getElementById('ab_id').value;
    const val = numParse(document.getElementById('ab_valor').value);
    if(!val) return alert('Ingrese un valor válido.');
    try{
        const res=await fetch(`{{ url('admin/facturacion/abonar') }}/${id}`,{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body:JSON.stringify({
                valor:           val,
                forma_pago:      document.getElementById('ab_forma').value,
                banco_cuenta_id: document.getElementById('ab_banco').value||null,
                observacion:     document.getElementById('ab_obs').value,
            })
        });
        const data=await res.json();
        if(data.ok){
            cerrar('modalAbonar');
            if(data.recibo_url) abrirRecibo(data.recibo_url + '?modal=1');
            location.reload();
        } else alert(data.mensaje||'Error al abonar');
    }catch(e){ alert('Error de conexión'); }
}

// ─── Helpers ──────────────────────────────────────────────
function cerrar(id){ const e = document.getElementById(id); if(e) e.style.display='none'; }
function cerrarSi(e,id){ if(e.target.id===id) cerrar(id); }

// ─── Modal Recibo (iframe) ─────────────────────────────────
function abrirRecibo(url) {
    document.getElementById('recibo-frame').src = url;
    document.getElementById('recibo-modal-ov').style.display = 'flex';
}
function cerrarRecibo() {
    document.getElementById('recibo-modal-ov').style.display = 'none';
    document.getElementById('recibo-frame').src = '';
    location.reload(); // refrescar tabla después de ver el recibo
}

// ─── Otro Ingreso — abrir desde empresa ───────────────────────
function OI_abrirEmpresa() {
    OI.abrir({
        cedula:       null, // sin cédula fija — se pedirá por campo en el modal si aplica
        empresaId:    {{ $empresa->id }},
        subtitulo:    '{{ addslashes($empresa->empresa) }}',
        aplicaIva:    {{ strtoupper($empresa->iva ?? '') === 'SI' ? 'true' : 'false' }},
        pctIva:       19,   // porcentaje estándar
        mes:          {{ $mes }},
        anio:         {{ $anio }},
        asesorId:     {!! json_encode($empresa->asesor_id) !!},
        asesorNombre: {!! json_encode($empresa->asesor?->nombre ?? '') !!},
    });
}
</script>

{{-- Modal Recibo reutilizable --}}
<div id="recibo-modal-ov"
     onclick="if(event.target.id==='recibo-modal-ov')cerrarRecibo()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:center;justify-content:center;">
    <div style="position:relative;width:96vw;max-width:1100px;height:93vh;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.5);display:flex;flex-direction:column;">
        {{-- Header del modal --}}
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:.6rem 1rem;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:.5rem;">
                <span style="font-size:1.1rem;">🧾</span>
                <span style="color:#fff;font-size:.9rem;font-weight:700;letter-spacing:.02em;">Recibo de Pago</span>
            </div>
            <button onclick="cerrarRecibo()"
                    style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:6px;width:28px;height:28px;font-size:1rem;cursor:pointer;line-height:1;font-weight:700;transition:background .15s;"
                    onmouseover="this.style.background='rgba(255,255,255,.28)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">&#x2715;</button>
        </div>
        <div style="flex:1;background:#e8edf2;padding:.35rem 0 0;overflow:hidden;">
            <iframe id="recibo-frame" src="" style="width:100%;height:100%;border:none;display:block;"></iframe>
        </div>
    </div>
</div>
@endpush

{{-- Panel Claves y Accesos de la Empresa --}}
@include('admin.facturacion.partials.clave_accesos_empresa')

@endsection
