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
/* Estilos para filtros y ordenación en tabla */
.tbl-header-select {
    background: transparent;
    color: #94a3b8;
    border: none;
    font-size: .63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
    outline: none;
    cursor: pointer;
    padding: 0;
    margin: 0;
    width: auto;
    max-width: 100%;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    transition: color .15s;
}
.tbl-header-select:focus {
    color: #fff;
    background: #0f172a;
}
.tbl-header-select option {
    background: #0f172a;
    color: #fff;
    font-size: .75rem;
    text-transform: none;
    letter-spacing: normal;
}
.tbl-header-select.active-filter {
    color: #3b82f6 !important;
    text-shadow: 0 0 1px rgba(59, 130, 246, 0.5);
}
.sort-icon {
    display: inline-block;
    width: 0;
    height: 0;
    margin-left: 4px;
    vertical-align: middle;
    border-right: 4px solid transparent;
    border-left: 4px solid transparent;
    border-top: 4px solid #64748b;
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.sort-icon.asc {
    transform: rotate(180deg);
    border-top-color: #3b82f6;
}
.sort-icon.desc {
    transform: rotate(0deg);
    border-top-color: #3b82f6;
}
</style>

{{-- Header empresa --}}
<div class="fac-header">
    <div class="fac-h-top">
        {{-- Info empresa --}}
        <div>
            <a href="{{ route('admin.facturacion.index') }}" style="color:#94a3b8;text-decoration:none;font-size:.8rem;">← Empresas</a>
            <div class="fac-h-nom" style="margin-top:.2rem; display:inline-flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                <span>🏢 {{ $empresa->empresa }}</span>
                @if($empresa->asesor || $empresa->contacto)
                    <span style="font-size:1rem; font-weight:500; color:#cbd5e1; margin-left:.3rem;">
                        · 👤 {{ $empresa->asesor ? $empresa->asesor->nombre : $empresa->contacto }}
                    </span>
                @endif
            </div>
            <div class="fac-h-meta">
                @if($empresa->nit)<span>NIT: {{ $empresa->nit }}</span>@endif
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
            <button type="button" onclick="MCA.abrir({{ $empresa->id }})"
               style="display:inline-flex;align-items:center;gap:.35rem;padding:.38rem .85rem;font-size:.8rem;font-weight:600;border-radius:7px;background:rgba(255,255,255,.1);color:#cbd5e1;border:none;cursor:pointer;transition:background .15s;"
               onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'"
               title="Administrar cobros adicionales (recurrentes o de única vez) de la empresa">⚙️ Cobros Adicionales</button>
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
    {{-- Izquierda: filtros de estado + buscador --}}
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;">
        <span class="fil-btn active" onclick="filtrar(this,'todos')">Todos ({{ $contratos->count() }})</span>
        <span class="fil-btn" onclick="filtrar(this,'pendiente')">⏳ Pendientes</span>
        <span class="fil-btn" onclick="filtrar(this,'pago')">✅ Pagados</span>
        {{-- Buscador inline por nombre y cédula --}}
        <div style="position:relative;display:inline-flex;align-items:center;">
            <span style="position:absolute;left:.5rem;color:#94a3b8;font-size:.85rem;pointer-events:none;">🔍</span>
            <input id="inp-buscar" type="search" placeholder="Nombre o cédula..."
                   oninput="buscar(this.value)"
                   name="q_{{ rand() }}"
                   autocomplete="off" data-lpignore="true" data-form-type="other" spellcheck="false"
                   readonly onfocus="this.removeAttribute('readonly'); this.style.borderColor='#3b82f6';this.style.background='#fff';this.style.width='230px'"
                   style="padding:.28rem .6rem .28rem 1.75rem;border:1.5px solid #e2e8f0;
                          border-radius:20px;font-size:.8rem;background:#f8fafc;
                          color:#334155;outline:none;width:190px;transition:border .15s,width .2s;"
                   onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';this.style.width='190px'">
            <button id="btn-limpiar-bus" onclick="limpiarBuscar()" title="Limpiar búsqueda"
                    style="display:none;position:absolute;right:.4rem;background:none;border:none;
                           cursor:pointer;color:#94a3b8;font-size:.85rem;line-height:1;"
                    onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">✕</button>
        </div>
    </div>
    <div style="display:flex;gap:.45rem;align-items:center;flex-wrap:wrap;">


        <button class="btn-act btn-exp" onclick="exportarExcel()">📊 Excel</button>

        <button onclick="abrirModalCargaCedulas()"
            title="Cargar lista de cédulas con NP provisional"
            style="display:inline-flex;align-items:center;justify-content:center;
                   width:30px;height:30px;padding:0;
                   background:linear-gradient(135deg,#0f766e,#0d9488);
                   color:#fff;border:none;border-radius:7px;cursor:pointer;
                   font-size:1rem;transition:opacity .15s;"
            onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
            📋
        </button>

        <button class="btn-act" onclick="MAD.abrir({{ $empresa->id }}, '{{ addslashes($empresa->razon_social) }}')"
            style="background:linear-gradient(135deg,#b45309,#d97706);color:#fff;"
            title="Registrar anticipo de la empresa para ser distribuido entre contratos">
            🪙 Anticipos
        </button>

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
<thead>
<tr style="user-select: none;">
    <th>
        <select id="filter-tipo" class="tbl-header-select" onchange="aplicarFiltrosTabla()" title="Filtrar por Tipo">
            <option value="todos">TIPO ▾</option>
        </select>
    </th>
    <th onclick="ordenarTabla('cedula')" style="cursor:pointer; text-align:left;" title="Clic para ordenar por Cédula">
        CÉDULA <span id="sort-icon-cedula" class="sort-icon"></span>
    </th>
    <th onclick="ordenarTabla('nombre')" style="cursor:pointer; text-align:left;" title="Clic para ordenar por Nombre">
        NOMBRE <span id="sort-icon-nombre" class="sort-icon"></span>
    </th>
    <th style="max-width:105px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
        <select id="filter-rs" class="tbl-header-select" onchange="aplicarFiltrosTabla()" style="text-align:left; max-width:105px; text-overflow:ellipsis; overflow:hidden;" title="Filtrar por Razón Social">
            <option value="todos">RAZÓN SOCIAL ▾</option>
        </select>
    </th>
    <th onclick="ordenarTabla('fecha')" style="cursor:pointer; text-align:center;" title="Clic para ordenar por Ingreso / Retiro">
        ING/RET <span id="sort-icon-fecha" class="sort-icon"></span>
    </th>
    <th onclick="ordenarTabla('dias')" style="cursor:pointer; text-align:center;" title="Clic para ordenar por Días">
        DÍAS <span id="sort-icon-dias" class="sort-icon"></span>
    </th>
    <th class="num-col">
        <select id="filter-eps" class="tbl-header-select" onchange="aplicarFiltrosTabla()" style="text-align:right;" title="Filtrar por EPS">
            <option value="todos">EPS ▾</option>
        </select>
    </th>
    <th class="num-col">
        <select id="filter-arl" class="tbl-header-select" onchange="aplicarFiltrosTabla()" style="text-align:right;" title="Filtrar por ARL">
            <option value="todos">ARL ▾</option>
        </select>
    </th>
    <th class="num-col">
        <select id="filter-caja" class="tbl-header-select" onchange="aplicarFiltrosTabla()" style="text-align:right;" title="Filtrar por Caja">
            <option value="todos">CAJA ▾</option>
        </select>
    </th>
    <th class="num-col">
        <select id="filter-pension" class="tbl-header-select" onchange="aplicarFiltrosTabla()" style="text-align:right;" title="Filtrar por Pensión">
            <option value="todos">PENSIÓN ▾</option>
        </select>
    </th>
    <th class="num-col">
        <select id="filter-admon" class="tbl-header-select" onchange="aplicarFiltrosTabla()" style="text-align:right;" title="Filtrar por Admon">
            <option value="todos">ADMON ▾</option>
        </select>
    </th>
    <th class="num-col" style="display:none">IVA</th>
    <th onclick="ordenarTabla('total')" style="cursor:pointer;" class="num-col" title="Clic para ordenar por Total">
        TOTAL <span id="sort-icon-total" class="sort-icon"></span>
    </th>
    <th onclick="ordenarTabla('mora')" style="cursor:pointer;" class="num-col" title="Clic para ordenar por Mora">
        ⚠️ MORA <span id="sort-icon-mora" class="sort-icon"></span>
    </th>
    @if($hayAnticipos)
    <th class="num-col" style="color:#b45309;" title="Anticipo disponible asignado a este contrato">
        🟡 ANTICIPO
    </th>
    @endif
    <th style="text-align:center">
        <select id="filter-estado" class="tbl-header-select" onchange="aplicarFiltrosTabla()" title="Filtrar por Estado">
            <option value="todos">ESTADO ▾</option>
        </select>
    </th>
    <th style="text-align:center">
        <select id="filter-np" class="tbl-header-select" onchange="aplicarFiltrosTabla()" title="Filtrar por Número de Planilla">
            <option value="todos">NP ▾</option>
        </select>
    </th>
    <th style="text-align:center">
        <input type="checkbox" id="chkAll" onchange="toggleAll(this)" title="Seleccionar todos"
               style="width:1rem;height:1rem;cursor:pointer;vertical-align:middle;"> SEL
    </th>
</tr>
</thead>
<tbody>
@php
$totEps=$totArl=$totCaja=$totPen=$totAdmon=$totIva=$totTotal=$totMora=0;
$totAFavor=$totPendiente=0;

// Calcular fecha predeterminada (30 del mes anterior al período actual consultado)
$tmpFechaPred = \Carbon\Carbon::create((int)($anio ?? now()->year), (int)($mes ?? now()->month), 1)->subMonth();
$ultimoDiaPred = $tmpFechaPred->endOfMonth()->day;
$diaSugeridoPred = min(30, $ultimoDiaPred);
$fechaPredeterminada = \Carbon\Carbon::create((int)($anio ?? now()->year), (int)($mes ?? now()->month), 1)->subMonth()->day($diaSugeridoPred)->format('Y-m-d');
@endphp

@forelse($contratos as $c)
@php
$fact  = $c->factura_exist;
$factRetiroPreview = (!$fact && ($c->tiene_retiro_facturable ?? false)) ? ($c->factura_retiro_0 ?? null) : null;
// Para retiro facturable usamos la factura_0 como fuente de valores de preview
$yaP   = $fact && in_array($fact->estado,['pagada','prestamo']);
// Nombre: solo primer nombre + primer apellido
$nombre = trim(($c->cliente?->primer_nombre ?? '') . ' ' . ($c->cliente?->primer_apellido ?? ''));
if(!$nombre) $nombre = $c->cliente?->nombre_completo ?? '—';
// Tipo: campo tipo_modalidad directo (ej: 'E', 'I')
$tipoMod    = $c->tipoModalidad?->tipo_modalidad ?? '—';
$tipoNom    = $c->tipoModalidad?->nombre ?? '—';  // tooltip
$rs         = $c->razonSocial?->razon_social ?? '—';
$esRetirado = $c->estado === 'retirado';
$esIngRet   = (int)($c->tipo_modalidad_id) === 12;
$fIng       = $c->fecha_ingreso ? $c->fecha_ingreso->format('d/m/Y') : '—';
$fRet       = ($esRetirado && $c->fecha_retiro) ? $c->fecha_retiro->format('d/m/Y') : null;
// Retiro pendiente (nuevo flujo desde vista empresa)
$tieneRetiroPendiente = $c->tiene_retiro_pendiente ?? false; // seteado por controller
$fechaRetiroPendienteStr = $tieneRetiroPendiente && $c->fecha_retiro_pendiente ? $c->fecha_retiro_pendiente->format('Y-m-d') : '';
$diasRetiroPendiente = $tieneRetiroPendiente ? (int) ($c->fecha_retiro_pendiente?->day ?? 0) : 0;
$cobrarAdmonRetiroPendiente = $tieneRetiroPendiente ? (bool) ($c->retiro_pendiente_cobrar_admon ?? false) : false;
$dias = $fact
    ? (int)$fact->dias_cotizados
    : ($factRetiroPreview
        ? (int)$factRetiroPreview->dias_cotizados  // usar días reales del retiro marcado
        : ($c->dias_cotizar ?? 30));
// Detectar si este período debe ser afiliación pura (I VENC, empresa, ARL)
// vs I ACT primer mes (viene del controlador como es_ind_act_primer_mes)
$esIndep          = $c->tipoModalidad?->esIndependiente() ?? false;
$esIndActPrimerMes = $c->es_ind_act_primer_mes ?? false; // flag del controller
$esArlModalidad   = (int)($c->tipo_modalidad_id) === 15;
$esAfil = false;
$esIngresoFuturo = false;
if ($esArlModalidad) {
    // Gestión ARL siempre es cobro de afiliación, no planilla
    $esAfil = true;
} elseif ($c->fecha_ingreso) {
    $fIngC = $c->fecha_ingreso;
    $periodoIngresoVista = (int)$fIngC->year * 100 + (int)$fIngC->month;
    $periodoActualVista  = $anio * 100 + $mes;
    if ($periodoIngresoVista > $periodoActualVista) {
        // Ingreso en mes futuro: no facturable en este período
        $esIngresoFuturo = true;
    } elseif ((int)$fIngC->month === $mes && (int)$fIngC->year === $anio) {
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
// Corrección: si es mes de afiliación (ingresó este mes), ignorar los días del retiro preview
if ($esAfil && !$fact) {
    $dias = 0;
}
// Tiempo Parcial: detectar y obtener días por entidad
$esTP     = $c->tipoModalidad?->esTiempoParcial() ?? false;
$diasTP   = $esTP ? $c->tipoModalidad->diasPorEntidad() : null;
// Valores: si hay factura usar los reales; si es retirado facturable → usar factura_0;
// Si es retiro pendiente → calcular SS proporcional con los días del retiro pendiente;
// si activo sin factura → estimar
$vEps  = $fact ? $r100($fact->v_eps)  : 0;
$vArl  = $fact ? $r100($fact->v_arl)  : 0;
$vCaja = $fact ? $r100($fact->v_caja) : 0;
$vPen  = $fact ? $r100($fact->v_afp)  : 0;
$vAdm  = $fact ? (int)($fact->admon + $fact->admin_asesor) : (($esRetirado && !$esAfil) ? 0 : (int)(($c->administracion??0) + ($c->admon_asesor??0)));
$vIva  = $fact ? $r100($fact->iva)    : 0;
// Total y SS
$cotiz = $c->cotizacion_calc ?? $c->calcularCotizacion($dias); // pre-calculado en controller
if (!$fact) {
    if ($esRetirado && $factRetiroPreview && !$esAfil) {
        // Retiro facturable: mostrar valores reales de la factura_0
        $vEps  = $r100($factRetiroPreview->v_eps);
        $vArl  = $r100($factRetiroPreview->v_arl);
        $vPen  = $r100($factRetiroPreview->v_afp);
        $vCaja = $r100($factRetiroPreview->v_caja);
        $vIva  = $r100($factRetiroPreview->iva);
        $vSS   = $r100($factRetiroPreview->total_ss);
        // Admon: el valor base de contrato (30 días); se recalculará por JS según el checkbox
        $vAdm  = (int)(($c->administracion??0) + ($c->admon_asesor??0));
        $vAdmProporcional = (int)(($vAdm / 30) * $dias); // proporcional a días de retiro
        $vTot  = $vSS + $vAdm + $vIva; // total con admon completa (JS ajusta si es proporcional)
    } elseif ($tieneRetiroPendiente) {
        // Retiro pendiente nuevo: calcular SS proporcional con los días del retiro
        $cotizRetPend = $c->calcularCotizacion($diasRetiroPendiente);
        $vEps  = $r100($cotizRetPend['eps']  ?? 0);
        $vArl  = $r100($cotizRetPend['arl']  ?? 0);
        $vPen  = $r100($cotizRetPend['pen']  ?? 0);
        $vCaja = $r100($cotizRetPend['caja'] ?? 0);
        $vIva  = $r100($cotizRetPend['iva']  ?? 0);
        $vSS   = $r100($cotizRetPend['ss']   ?? 0);
        $vAdm  = $cobrarAdmonRetiroPendiente
            ? (int)(($c->administracion??0) + ($c->admon_asesor??0))
            : 0;
        $vTot  = $vSS + $vAdm + $vIva;
    } elseif ($esRetirado && !$factRetiroPreview) {
        // Retirado sin retiro facturable — ya fue cobrado o es retiro masivo → 0
        $vEps = $vArl = $vPen = $vCaja = $vIva = $vAdm = $vSS = 0;
        $vTot = 0;
    } elseif ($esArlModalidad && !$esAfil) {
        // ARL fuera de su mes de ARL → cobro es 0, no paga planilla
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
    } elseif ($esIngresoFuturo) {
        // Ingreso en mes futuro: el contrato aún no inicia → todo en 0
        $vEps = $vArl = $vPen = $vCaja = $vIva = $vAdm = $vSS = 0;
        $vTot = 0;
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
// Mora: solo mostrar si el contrato NO está pagado aún
// - Con factura pendiente → usar mora guardada en la factura
// - Sin factura → usar mora estimada del batch pre-calculado
// - Ya pagado ($yaP) → ocultar mora (ya fue liquidada, no hay alerta pendiente)
$vMora = 0;
if (!$yaP) {
    if ($fact && ($fact->mora ?? 0) > 0) {
        $vMora = (int)$fact->mora;
    } elseif (!$fact) {
        $vMora = (int)($moraPorContrato[$c->id] ?? 0);
    }
}
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
    data-tipo="{{ ($esAfil && !$esIndActPrimerMes) ? 'afiliacion' : 'planilla' }}"
    data-tipomod="{{ $tipoMod }}"
    data-tipo_modalidad_id="{{ $c->tipo_modalidad_id }}"
    data-rs="{{ $rs }}"
    data-fecha_ingreso_retiro="{{ $esRetirado && $c->fecha_retiro ? $c->fecha_retiro->format('Y-m-d') : ($c->fecha_ingreso ? $c->fecha_ingreso->format('Y-m-d') : '') }}"
    data-tiene-retiro-pendiente="{{ $tieneRetiroPendiente ? '1' : '0' }}"
    data-fecha-retiro-pendiente="{{ $fechaRetiroPendienteStr }}"
    data-cobrar-admon-ret-pend="{{ $cobrarAdmonRetiroPendiente ? '1' : '0' }}"
    data-vmora="{{ $vMora }}"
    data-np="{{ $fact?->np ?? $c->np ?? '' }}"
    data-es-retiro-facturable="{{ ($factRetiroPreview ?? null) && !$esAfil ? '1' : '0' }}"
    data-dias-retiro="{{ ($factRetiroPreview ?? null) && !$esAfil ? (int)$factRetiroPreview->dias_cotizados : 0 }}"
    data-admon-full="{{ ($factRetiroPreview ?? null) && !$esAfil ? (int)(($c->administracion??0) + ($c->admon_asesor??0)) : 0 }}"
    data-admon-proporcional="{{ ($factRetiroPreview ?? null) && !$esAfil ? ($vAdmProporcional ?? 0) : 0 }}"
    data-vss-retiro="{{ ($factRetiroPreview ?? null) && !$esAfil ? $r100($factRetiroPreview->total_ss) : 0 }}"
    data-np-prov="{{ $c->np ?? '' }}">

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
        </span>
    </td>
    <td style="font-family:monospace;font-size:.75rem">{{ $c->cedula }}</td>
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
    <td style="font-size:.7rem;color:#64748b;max-width:105px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $rs }}">{{ Str::limit($rs,12) }}</td>
    <td style="text-align:center;font-size:.75rem;">
        @if($esRetirado && $fRet)
            <span style="color:#dc2626;font-weight:700;">{{ $fRet }}</span>
        @else
            <span style="color:#64748b;">{{ $fIng }}</span>
        @endif
    </td>
    <td style="text-align:center;font-weight:700;color:{{ ($tieneRetiroPendiente || $dias < 30) && $dias > 0 ? '#d97706' : ($dias===0?'#9333ea':'#0f172a') }};position:relative;">
        @if($esTP && $diasTP)
            <span title="T. Parcial: ARL {{ $diasTP['arl'] }}d · AFP {{ $diasTP['afp'] }}d · CAJA {{ $diasTP['caja'] }}d"
                  style="font-size:.62rem;background:#fef3c7;color:#78350f;border-radius:6px;padding:.1rem .35rem;font-weight:700;cursor:help;white-space:nowrap;">
              ⏱TP
            </span>
        @else
            {{ $dias === 0 ? '—' : $dias }}
        @endif
        @if(!$esRetirado && !$esAfil && !$esTP && !$yaP && !(isset($fact) && $fact))
            <button
                type="button"
                onclick="abrirModalRetiroPendiente({{ $c->id }}, '{{ addslashes($nombre) }}', '{{ addslashes($rs) }}', {{ (int)(($c->administracion??0)+($c->admon_asesor??0)) }}, '{{ $fechaRetiroPendienteStr }}', {{ $cobrarAdmonRetiroPendiente ? 'true' : 'false' }})"
                title="{{ $tieneRetiroPendiente ? 'Editar retiro pendiente ('.$diasRetiroPendiente.' días)' : 'Registrar fecha de retiro' }}"
                style="background:none;border:none;cursor:pointer;font-size:.8rem;padding:0 0 0 3px;line-height:1;color:{{ $tieneRetiroPendiente ? '#c2410c' : '#94a3b8' }};vertical-align:middle;">
                {{ $tieneRetiroPendiente ? '✏️' : '🗓' }}
            </button>
        @endif
    </td>
    <td class="num-col">{{ (!$esTP && $vEps>0)?'$'.number_format($vEps,0,',','.'):'—' }}</td>
    <td class="num-col">{{ $vArl>0?'$'.number_format($vArl,0,',','.'):'—' }}</td>
    <td class="num-col">{{ $vCaja>0?'$'.number_format($vCaja,0,',','.'):'—' }}</td>
    <td class="num-col">{{ $vPen>0?'$'.number_format($vPen,0,',','.'):'—' }}</td>
    <td class="num-col celda-admon">${{ number_format($vAdm,0,',','.') }}</td>
    <td class="num-col" style="display:none">{{ $vIva>0?'$'.number_format($vIva,0,',','.'):'—' }}</td>
    <td class="num-col celda-tot" style="font-weight:700;color:{{ $yaP?'#16a34a':'#0f172a' }}">
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
    @if($hayAnticipos)
    @php
        $vAnticipo = $saldoAnticipoPorContrato->get($c->id, 0);
    @endphp
    <td class="num-col">
        @if($vAnticipo > 0)
            <span style="display:inline-block;padding:.1rem .4rem;border-radius:20px;font-size:.62rem;font-weight:700;background:#fef3c7;color:#b45309;"
                  title="Anticipo disponible asignado a este contrato">
                ${{ number_format($vAnticipo,0,',','.') }}
            </span>
        @else
            <span style="color:#cbd5e1;font-size:.7rem">—</span>
        @endif
    </td>
    @endif
    @php
        $totAFavor    += $c->saldo_a_favor;
        $totPendiente += $c->saldo_pendiente;
        $totProxFavor    = ($totProxFavor ?? 0)    + $c->saldo_proximo_favor;
        $totProxPendiente= ($totProxPendiente ?? 0) + $c->saldo_proximo_pendiente;
    @endphp
    <td style="text-align:center">
        @if($esRetirado && $esAfil && !$fact)
            {{-- Retirado que ingresó este mes: afiliación pendiente de cobro --}}
            <span style="display:inline-block;padding:.16rem .5rem;border-radius:20px;font-size:.63rem;font-weight:800;background:#ede9fe;color:#6d28d9;"
                  title="Ingresó este mes — afiliación pendiente de facturar">
                AFIL. PEND.
            </span>
        @elseif($esRetirado)
            @php
                $factRetiro0 = $c->factura_retiro_0 ?? null;
                $tieneRetiroFacturable = $c->tiene_retiro_facturable ?? false;
                $numeroPlanillaRet = $factRetiro0 ? (\App\Models\Plano::where('factura_id', $factRetiro0->id)->whereNull('deleted_at')->value('numero_planilla') ?? null) : null;
            @endphp
            @if($tieneRetiroFacturable)
                {{-- Retiro marcado pero no cobrado aún — mostrar en rojo --}}
                <span style="display:inline-block;padding:.16rem .5rem;border-radius:20px;font-size:.63rem;font-weight:800;background:#fee2e2;color:#dc2626;"
                      title="Retiro marcado — pendiente de facturar{{ $numeroPlanillaRet ? ' ⚠ Planilla pagada: '.$numeroPlanillaRet : '' }}">
                    RETIRO
                </span>
            @else
                {{-- Retirado sin factura 0 (ya cobrado o retiro masivo) --}}
                <span style="display:inline-block;padding:.16rem .5rem;border-radius:20px;font-size:.63rem;font-weight:800;background:#fee2e2;color:#dc2626">
                    RETIRO
                </span>
            @endif
        @elseif($tieneRetiroPendiente)
            {{-- Retiro pendiente: registrado desde vista empresa, aún no facturado --}}
            <span style="display:inline-block;padding:.16rem .5rem;border-radius:20px;font-size:.63rem;font-weight:800;background:#fff7ed;color:#b45309;border:1px solid #fcd34d;"
                  title="Retiro pendiente — {{ $diasRetiroPendiente }} días — {{ $cobrarAdmonRetiroPendiente ? 'Con admon' : 'Sin admon' }} — pendiente de facturar">
                Retiro Pd.
            </span>
        @elseif($fact)
        @php $colores=$estadoBg($fact->estado); @endphp
        @if((int)$fact->numero_factura === 0)
        <span style="display:inline-block;padding:.16rem .5rem;border-radius:20px;font-size:.63rem;font-weight:800;background:#fee2e2;color:#dc2626">
            RETIRO
        </span>
        @else
        <span style="display:inline-block;padding:.16rem .5rem;border-radius:20px;font-size:.63rem;font-weight:700;background:{{ $colores[0] }};color:{{ $colores[1] }}">
            {{ $estadoLabel($fact->estado) }}
        </span>
        @endif
        @else<span style="color:#94a3b8;font-size:.7rem">Sin factura</span>@endif
    </td>
    <td style="text-align:center;font-size:.8rem">
        @php
            $npMostrar = $fact?->np ?? $c->np ?? null;
            $npProvisional = !$fact && $c->np;
        @endphp
        @if($npMostrar)
            @if($npProvisional)
                <span style="display:inline-block;padding:.12rem .45rem;border-radius:20px;font-size:.75rem;font-weight:800;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;">{{ $npMostrar }}</span>
            @else
                <strong style="color:#2563eb;font-weight:800;">{{ $npMostrar }}</strong>
            @endif
        @else
            <span style="color:#cbd5e1;font-size:.7rem">—</span>
        @endif
    </td>
    <td style="text-align:center;white-space:nowrap;">
        @if($fact && (int)$fact->numero_factura !== 0)
            <button onclick="abrirRecibo('{{ route('admin.facturacion.recibo',$fact->id) }}?modal=1')"
               class="btn-sm" style="background:#eff6ff;color:#1d4ed8;" title="Ver recibo">🖨</button>
        @elseif($tieneRetiroFacturable ?? false)
            {{-- Retiro facturable: mostrar checkbox igual que un activo sin factura --}}
            <input type="checkbox" class="chk-row" value="{{ $c->id }}"
                   data-es-retiro-facturable="1"
                   data-dias-retiro="{{ $factRetiro0?->dias_cotizados ?? 0 }}"
                   onchange="onCheckChange()"
                   style="width:1.1rem;height:1.1rem;cursor:pointer;accent-color:#c2410c;"
                   title="Seleccionar para facturar retiro ({{ $factRetiro0?->dias_cotizados ?? 0 }} días){{ isset($numeroPlanillaRet) && $numeroPlanillaRet ? ' ⚠ Ya tiene planilla: '.$numeroPlanillaRet : '' }}">
        @elseif(!$esRetirado && !$fact)
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
    <td class="num-col tot-val" id="tot-admon-val">${{ number_format($totAdmon,0,',','.') }}</td>
    <td class="num-col tot-val" style="display:none">${{ number_format($totIva,  0,',','.') }}</td>
    <td class="num-col tot-val" id="tot-general-val" style="font-size:.9rem">${{ number_format($totTotal,0,',','.') }}</td>
    <td class="num-col tot-val" style="color:#fbbf24;font-weight:800;">
        {{ $totMora > 0 ? '$'.number_format($totMora,0,',','.') : '—' }}
    </td>
    <td colspan="3"></td>
</tr>
</tfoot>
</table>
</div>
</div>

{{-- ─── Checkbox: cobrar administración completa en retiros ──────────────────── --}}
<div id="panel-admon-retiro" style="display:none;align-items:center;gap:.65rem;margin-top:.5rem;padding:.5rem .9rem;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;width:fit-content;">
    <input type="checkbox" id="chk-admon-retiro" checked onchange="actualizarAdmonRetiro(this.checked)"
           style="width:1rem;height:1rem;cursor:pointer;accent-color:#ea580c;flex-shrink:0;">
    <label for="chk-admon-retiro" style="font-size:.73rem;font-weight:700;color:#c2410c;cursor:pointer;user-select:none;">
        💼 Cobrar administración en retiros cortos
    </label>
    <span style="font-size:.65rem;color:#9a3412;font-weight:500;">
        — Desmarcado: sin admon si ≤ 3 días (ya facturado antes de retirarse)
    </span>
</div>
<script>
function actualizarAdmonRetiro(admonCompleta) {
    document.querySelectorAll('tr[data-es-retiro-facturable="1"]').forEach(function(tr) {
        const admonFull = parseInt(tr.dataset.admonFull || 0);
        const diasRet   = parseInt(tr.dataset.diasRetiro || 0);
        const vss       = parseInt(tr.dataset.vssRetiro || 0);
        const viva      = parseInt(tr.dataset.viva || 0);

        // Regla: marcado = admon completa siempre
        // Desmarcado: si días <= 3 → sin admon; si días > 3 → admon completa igualmente
        let nuevoAdmon;
        if (admonCompleta) {
            nuevoAdmon = admonFull;
        } else {
            nuevoAdmon = diasRet <= 3 ? 0 : admonFull;
        }

        const nuevoTot = vss + nuevoAdmon + viva;
        tr.dataset.vadmon = nuevoAdmon;
        tr.dataset.vtot   = nuevoTot;

        const celdaAdmon = tr.querySelector('.celda-admon');
        if (celdaAdmon) celdaAdmon.textContent = '$' + nuevoAdmon.toLocaleString('es-CO');
        const celdaTot = tr.querySelector('.celda-tot');
        if (celdaTot) celdaTot.textContent = '$' + nuevoTot.toLocaleString('es-CO');
    });

    // Recalcular dinámicamente la suma acumulada de administración y total general en el tfoot de la tabla
    let totalAdmonAcum = 0;
    let totalGeneralAcum = 0;
    document.querySelectorAll('tbody tr[data-vadmon]').forEach(function(tr) {
        totalAdmonAcum += parseInt(tr.dataset.vadmon || 0);
        totalGeneralAcum += parseInt(tr.dataset.vtot || 0);
    });

    const elTotAdmon = document.getElementById('tot-admon-val');
    if (elTotAdmon) elTotAdmon.textContent = '$' + totalAdmonAcum.toLocaleString('es-CO');
    const elTotGen = document.getElementById('tot-general-val');
    if (elTotGen) elTotGen.textContent = '$' + totalGeneralAcum.toLocaleString('es-CO');
}

// Mostrar/ocultar el panel según si hay retiros seleccionados en la selección actual
function actualizarVisibilidadPanelAdmon() {
    const panel = document.getElementById('panel-admon-retiro');
    if (!panel) return;
    const chksRetiro = document.querySelectorAll('.chk-row:checked[data-es-retiro-facturable="1"]');
    panel.style.display = chksRetiro.length > 0 ? 'flex' : 'none';
}
// Inicializar visibilidad al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    actualizarVisibilidadPanelAdmon();
});
</script>

{{-- ─── Panel saldo neto de la EMPRESA (calculado en el controlador) ─────────
     Usa empresa_id: suma TODOS los saldo_proximo hasta e incluyendo el mes actual.
     Abril: +700k  |  Mayo: +700k - 700k = 0  |  Junio: correcto
--}}
@if($saldoEmpresaFavor > 0 || $saldoEmpresaPendiente > 0 || $totalAnticipoDisponible > 0)
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

    {{-- ─── Panel anticipo disponible ───────────────────────────── --}}
    @if($totalAnticipoDisponible > 0)
    <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:.55rem .9rem;display:flex;align-items:flex-start;gap:.5rem;min-width:210px;max-width:320px;">
        <span style="font-size:1.2rem;margin-top:.05rem;">🟡</span>
        <div style="flex:1;">
            <div style="font-size:.6rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.04em;">Anticipo disponible</div>
            <div style="font-size:.95rem;font-weight:800;color:#92400e;">${{ number_format($totalAnticipoDisponible,0,',','.') }}</div>
            <div style="font-size:.58rem;color:#b45309;margin-bottom:.3rem;">Se puede aplicar al facturar este mes</div>
            {{-- Detalle de cada anticipo --}}
            @foreach($anticiposEmpresa as $ant)
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:.62rem;padding:.1rem 0;border-top:.5px solid #fde68a;color:#78350f;">
                <span>
                    {{ ucfirst($ant->forma_pago) }}
                    · {{ $ant->fecha_pago->format('d/m/Y') }}
                    @if($ant->estado === 'parcial')
                        <span style="background:#fed7aa;color:#c2410c;border-radius:4px;padding:.05rem .25rem;font-size:.55rem;font-weight:700;">Parcial</span>
                    @endif
                </span>
                <strong style="font-family:monospace;">${{ number_format($ant->valor_disponible,0,',','.') }}</strong>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endif

{{-- ═══ MODAL CARGA MASIVA CÉDULAS (NP PROVISIONAL) ════════════════ --}}
<style>
#modalCargaCedulas .cc-modal-inner {
    background: #fff;
    border-radius: 16px;
    width: min(640px, 96vw);
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: 0 24px 60px rgba(0,0,0,.22);
    display: flex;
    flex-direction: column;
}
#modalCargaCedulas .cc-header {
    background: linear-gradient(135deg, #0f172a, #134e4a);
    border-radius: 16px 16px 0 0;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
#modalCargaCedulas .cc-header-title {
    color: #fff;
    font-size: .95rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: .5rem;
}
#modalCargaCedulas .cc-header-sub {
    color: #5eead4;
    font-size: .68rem;
    margin-top: .15rem;
    font-weight: 500;
}
#modalCargaCedulas .cc-close {
    background: rgba(255,255,255,.1);
    border: none;
    border-radius: 8px;
    color: #cbd5e1;
    font-size: 1.1rem;
    width: 30px; height: 30px;
    cursor: pointer;
    transition: background .15s;
    display: flex; align-items: center; justify-content: center;
}
#modalCargaCedulas .cc-close:hover { background: rgba(239,68,68,.35); color: #fff; }
#modalCargaCedulas .cc-body { padding: 1.1rem 1.25rem; }
#modalCargaCedulas .cc-label {
    display: block;
    font-size: .63rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: .25rem;
}
#modalCargaCedulas .cc-select,
#modalCargaCedulas .cc-textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: .4rem .55rem;
    font-size: .82rem;
    color: #1e293b;
    transition: border-color .15s;
    outline: none;
}
#modalCargaCedulas .cc-select:focus,
#modalCargaCedulas .cc-textarea:focus { border-color: #0d9488; }
#modalCargaCedulas .cc-textarea {
    font-family: monospace;
    line-height: 1.6;
    resize: vertical;
}
#modalCargaCedulas .cc-footer {
    padding: .85rem 1.25rem;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .5rem;
    background: #f8fafc;
    border-radius: 0 0 16px 16px;
}
#modalCargaCedulas .cc-btn-prim {
    padding: .45rem 1.3rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 700;
    font-size: .82rem;
    transition: opacity .15s;
}
#modalCargaCedulas .cc-btn-prim:hover { opacity: .88; }
#modalCargaCedulas .cc-btn-prim:disabled { opacity: .45; cursor: not-allowed; }
#modalCargaCedulas .cc-btn-sec {
    padding: .4rem .9rem;
    border-radius: 8px;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    color: #475569;
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
#modalCargaCedulas .cc-btn-sec:hover { border-color: #94a3b8; background: #f8fafc; }
#modalCargaCedulas .cc-chip-group {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: .5rem;
    margin-bottom: .8rem;
}
#modalCargaCedulas .cc-chip {
    border-radius: 10px;
    padding: .55rem .4rem;
    text-align: center;
    border: 1px solid;
}
#modalCargaCedulas .cc-chip .cc-num { font-size: 1.5rem; font-weight: 900; line-height: 1; }
#modalCargaCedulas .cc-chip .cc-lab { font-size: .6rem; font-weight: 700; margin-top: .15rem; }
#modalCargaCedulas .cc-copybox { border-radius: 8px; overflow: hidden; margin-bottom: .6rem; }
#modalCargaCedulas .cc-copybox-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .35rem .6rem;
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}
#modalCargaCedulas .cc-copybox textarea {
    width: 100%;
    box-sizing: border-box;
    border: none;
    padding: .45rem .6rem;
    font-family: monospace;
    font-size: .73rem;
    resize: none;
    outline: none;
}
#modalCargaCedulas .cc-copy-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: .75rem;
    padding: 0;
    opacity: .7;
    transition: opacity .15s;
}
#modalCargaCedulas .cc-copy-btn:hover { opacity: 1; }
</style>

<div id="modalCargaCedulas" class="modal-overlay" style="display:none;" onclick="cerrarSi(event,'modalCargaCedulas')">
<div class="cc-modal-inner" onclick="event.stopPropagation()">

    {{-- Header --}}
    <div class="cc-header">
        <div>
            <div class="cc-header-title">📋 Cargar Lista de Cédulas</div>
            <div class="cc-header-sub">Asignar NP provisional · {{ $meses[$mes-1] }} {{ $anio }}</div>
        </div>
        <button class="cc-close" onclick="cerrar('modalCargaCedulas')">&times;</button>
    </div>

    {{-- Paso 1: Entrada --}}
    <div id="cc-paso1" class="cc-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-bottom:.8rem;">
            <div>
                <label class="cc-label">NP Provisional (1–5)</label>
                <select id="cc-np" class="cc-select" style="font-size:.9rem;font-weight:700;text-align:center;">
                    <option value="1">NP 1</option>
                    <option value="2">NP 2</option>
                    <option value="3">NP 3</option>
                    <option value="4">NP 4</option>
                    <option value="5">NP 5</option>
                </select>
            </div>
            <div>
                <label class="cc-label">Período activo</label>
                <div style="padding:.4rem .55rem;background:#f0fdf4;border:1.5px solid #86efac;border-radius:8px;font-size:.88rem;font-weight:700;color:#15803d;text-align:center;">
                    {{ $meses[$mes-1] }} {{ $anio }}
                </div>
            </div>
        </div>

        <div style="margin-bottom:.8rem;">
            <label class="cc-label">Cédulas (una por línea, coma o espacio)</label>
            <textarea id="cc-cedulas" class="cc-textarea" rows="9"
                placeholder="1000000001&#10;1000000002&#10;1000000003..."></textarea>
            <div style="margin-top:.3rem;font-size:.63rem;color:#94a3b8;">Separa por salto de línea, coma, punto y coma o espacio. Los dúpicados se eliminan automáticamente.</div>
        </div>

        <div class="cc-footer" style="margin:-1.1rem -1.25rem -1.1rem;flex-wrap:wrap;gap:.4rem;">
            <button class="cc-btn-sec" onclick="document.getElementById('cc-cedulas').value=''">🗑 Limpiar Texto</button>
            <div style="display:flex;gap:.45rem;">
                <button id="cc-btn-reset-todos" class="cc-btn-sec"
                    style="color:#dc2626;border-color:#fca5a5;"
                    onclick="ccResetearNPsEmpresa('cc-btn-reset-todos')" title="Borrar el NP provisional de TODOS los contratos activos de esta empresa en la base de datos">
                    🗑 Resetear NPs Empresa
                </button>
                <button id="cc-btn-verificar" class="cc-btn-prim"
                    style="background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;"
                    onclick="ccVerificar()">
                    🔍 Verificar →
                </button>
            </div>
        </div>
    </div>

    {{-- Paso 2: Resultado --}}
    <div id="cc-paso2" class="cc-body" style="display:none;">

        {{-- Chips resumen --}}
        <div id="cc-resumen" class="cc-chip-group"></div>

        {{-- Copybox: No encontradas --}}
        <div id="cc-wrap-no-enc" class="cc-copybox" style="display:none;border:1px solid #fca5a5;">
            <div class="cc-copybox-head" style="background:#fef2f2;color:#dc2626;">
                <span>❌ No encontradas</span>
                <button class="cc-copy-btn" onclick="ccCopiar('cc-txt-no-enc')" title="Copiar">📋 Copiar</button>
            </div>
            <textarea id="cc-txt-no-enc" rows="3" readonly
                style="background:#fef2f2;color:#dc2626;"></textarea>
        </div>

        {{-- Copybox: Ya facturadas --}}
        <div id="cc-wrap-ya-fac" class="cc-copybox" style="display:none;border:1px solid #fde68a;">
            <div class="cc-copybox-head" style="background:#fffbeb;color:#d97706;">
                <span>⚠️ Ya tienen factura este mes</span>
                <button class="cc-copy-btn" onclick="ccCopiar('cc-txt-ya-fac')" title="Copiar">📋 Copiar</button>
            </div>
            <textarea id="cc-txt-ya-fac" rows="3" readonly
                style="background:#fffbeb;color:#d97706;"></textarea>
        </div>

        <div class="cc-footer" style="margin:-1.1rem -1.25rem -1.1rem;flex-wrap:wrap;gap:.4rem;">
            <button class="cc-btn-sec" onclick="ccVolver()">← Volver</button>
            <div style="display:flex;gap:.45rem;">
                <button id="cc-btn-limpiar-np" class="cc-btn-sec"
                    style="color:#dc2626;border-color:#fca5a5;"
                    onclick="ccResetearNPsEmpresa('cc-btn-limpiar-np')" title="Borrar el NP provisional de TODOS los contratos activos de esta empresa en la base de datos">
                    🗑 Resetear NPs Empresa
                </button>
                <button id="cc-btn-asignar" class="cc-btn-prim"
                    style="background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;"
                    onclick="ccAsignar()">
                    ✅ Asignar NP y Seleccionar
                </button>
            </div>
        </div>
    </div>

</div>
</div>

{{-- ═══ MODAL FACTURAR UNIFICADO ══════════════════════════════════ --}}
@php $mfMes = $mes; $mfAnio = $anio; @endphp
@include('admin.facturacion._modal_facturar', ['bancos' => $bancos, 'mfMes' => $mfMes, 'mfAnio' => $mfAnio])

{{-- ═══ MODAL OTRO INGRESO ═════════════════════════════════════════ --}}
@php $oiMes = $mes; $oiAnio = $anio; $oiEmpresaId = $empresa->id; @endphp
@include('admin.facturacion._modal_otro_ingreso', [
    'bancos' => $bancos, 'oiMes' => $oiMes, 'oiAnio' => $oiAnio, 'oiEmpresaId' => $oiEmpresaId
])

{{-- ═══ MODAL ANTICIPO DISTRIBUIDO ═════════════════════════════════ --}}
@include('admin.facturacion._modal_anticipo_distribuido', ['bancos' => $bancos])

{{-- ═══ MODAL COBROS ADICIONALES DE LA EMPRESA ════════════════════ --}}
@include('admin.facturacion._modal_cobros_adicionales')

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
            <option value="{{ $b->id }}">{{ strtoupper($b->banco) }}   {{ $b->nombre }}   # {{ $b->numero_cuenta }}</option>
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
<script src="/js/modal_facturar_v2.js?v={{ time() }}"></script>
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
    salarioMinimo: {{ (int) \App\Models\ConfiguracionBrynex::obtener('salario_minimo', 1423500) }},
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
    {id:{{ $b->id }}, label:{!! json_encode(strtoupper($b->banco) . '   ' . $b->nombre . '   # ' . $b->numero_cuenta) !!}},
    @endforeach
];

// ─── Ordenación de columnas ──────────────────────────────────────────
let _sortCampo = null;
let _sortAsc = true;

function ordenarTabla(campo) {
    const tbody = document.querySelector('#tblTrab tbody');
    if (!tbody) return;
    const filas = Array.from(tbody.querySelectorAll('tr'));
    if (filas.length <= 1 && filas[0]?.cells?.length === 1 && filas[0]?.cells[0]?.colSpan > 1) {
        return; // Fila vacía
    }

    if (_sortCampo === campo) {
        _sortAsc = !_sortAsc;
    } else {
        _sortCampo = campo;
        _sortAsc = true;
    }

    // Actualizar iconos visuales
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.classList.remove('asc', 'desc');
    });
    const activeIcon = document.getElementById(`sort-icon-${campo}`);
    if (activeIcon) {
        activeIcon.classList.add(_sortAsc ? 'asc' : 'desc');
    }

    filas.sort((a, b) => {
        let valA, valB;
        switch (campo) {
            case 'cedula':
                valA = parseInt(a.dataset.cedula) || 0;
                valB = parseInt(b.dataset.cedula) || 0;
                break;
            case 'nombre':
                valA = (a.dataset.nombre || '').toLowerCase();
                valB = (b.dataset.nombre || '').toLowerCase();
                return _sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
            case 'fecha':
                valA = a.dataset.fecha_ingreso_retiro || '';
                valB = b.dataset.fecha_ingreso_retiro || '';
                break;
            case 'dias':
                valA = parseInt(a.dataset.dias) || 0;
                valB = parseInt(b.dataset.dias) || 0;
                break;
            case 'total':
                valA = parseInt(a.dataset.vtot) || 0;
                valB = parseInt(b.dataset.vtot) || 0;
                break;
            case 'mora':
                valA = parseInt(a.dataset.vmora) || 0;
                valB = parseInt(b.dataset.vmora) || 0;
                break;
            default:
                return 0;
        }

        if (valA < valB) return _sortAsc ? -1 : 1;
        if (valA > valB) return _sortAsc ? 1 : -1;
        return 0;
    });

    // Reinsertar en el DOM
    filas.forEach(fila => tbody.appendChild(fila));
}

// ─── Estado del filtro activo (para combinarlo con la búsqueda) ───────
let _filtroActivo = 'todos';

// ─── Filtro por estado ───────────────────────────────────────────────
function filtrar(btn, tipo) {
    _filtroActivo = tipo;
    document.querySelectorAll('.fil-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    aplicarFiltrosTabla();
}

// ─── Búsqueda por nombre / cédula ───────────────────────────────────
function buscar(q) {
    const btnLimp = document.getElementById('btn-limpiar-bus');
    btnLimp.style.display = q.trim() ? 'block' : 'none';
    aplicarFiltrosTabla();
}
function limpiarBuscar() {
    const inp = document.getElementById('inp-buscar');
    inp.value = '';
    document.getElementById('btn-limpiar-bus').style.display = 'none';
    inp.focus();
    aplicarFiltrosTabla();
}

// ─── Títulos originales de las columnas para los filtros ──────────────
const TITULOS_FILTROS = {
    'filter-tipo': 'TIPO',
    'filter-rs': 'RAZÓN SOCIAL',
    'filter-eps': 'EPS',
    'filter-arl': 'ARL',
    'filter-caja': 'CAJA',
    'filter-pension': 'PENSIÓN',
    'filter-admon': 'ADMON',
    'filter-estado': 'ESTADO',
    'filter-np': 'NP'
};

// ─── Inicializar y autopoblar los filtros dropdown ───────────────────
function inicializarFiltrosTabla() {
    const filas = Array.from(document.querySelectorAll('#tblTrab tbody tr'));
    if (filas.length <= 1 && filas[0]?.cells?.length === 1 && filas[0]?.cells[0]?.colSpan > 1) {
        return; // Fila vacía
    }

    const tipos = new Set();
    const razones = new Set();
    const epsVals = new Set();
    const arlVals = new Set();
    const cajaVals = new Set();
    const penVals = new Set();
    const admVals = new Set();
    const estados = new Set();
    const npVals = new Set();

    filas.forEach(tr => {
        if (tr.dataset.tipomod) tipos.add(tr.dataset.tipomod.trim());
        if (tr.dataset.rs) razones.add(tr.dataset.rs.trim());

        const eps = parseInt(tr.dataset.veps) || 0;
        if (eps > 0) epsVals.add(eps);

        const arl = parseInt(tr.dataset.varl) || 0;
        if (arl > 0) arlVals.add(arl);

        const caja = parseInt(tr.dataset.vcaja) || 0;
        if (caja > 0) cajaVals.add(caja);

        const pen = parseInt(tr.dataset.vpen) || 0;
        if (pen > 0) penVals.add(pen);

        const adm = parseInt(tr.dataset.vadmon) || 0;
        if (adm > 0) admVals.add(adm);

        if (tr.cells[14]) {
            const txt = tr.cells[14].textContent.trim().replace(/\s+/g, ' ');
            if (txt && txt !== '—') estados.add(txt);
        }

        const np = (tr.dataset.np || '').trim();
        if (np) npVals.add(np);
    });

    const setOptions = (id, set, isNum = false) => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const selectedVal = sel.value;
        const titulo = TITULOS_FILTROS[id] || 'FILTRO';
        let html = `<option value="todos">${titulo} ▾</option>`;
        if (isNum || id === 'filter-np') {
            html += '<option value="-">-</option>';
        }
        
        let sortedArr = Array.from(set);
        if (isNum) {
            sortedArr.sort((a, b) => a - b);
        } else {
            sortedArr.sort();
        }

        sortedArr.forEach(v => {
            const label = isNum ? numFmt(v) : v;
            html += `<option value="${v}">${label}</option>`;
        });
        sel.innerHTML = html;
        if (Array.from(set).includes(isNum ? parseInt(selectedVal) : selectedVal) || selectedVal === '-' || selectedVal === 'todos') {
            sel.value = selectedVal;
        }
    };

    setOptions('filter-tipo', tipos);
    setOptions('filter-rs', razones);
    setOptions('filter-eps', epsVals, true);
    setOptions('filter-arl', arlVals, true);
    setOptions('filter-caja', cajaVals, true);
    setOptions('filter-pension', penVals, true);
    setOptions('filter-admon', admVals, true);
    setOptions('filter-estado', estados);
    setOptions('filter-np', npVals);
}

// ─── Motor combinado de filtros: buscador + estado + dropdowns ──────
function aplicarFiltrosTabla() {
    const q = (document.getElementById('inp-buscar')?.value || '').trim().toLowerCase();
    const tokens = q ? q.split(/\s+/).filter(t => t.length > 0) : [];

    const fTipo = document.getElementById('filter-tipo')?.value || 'todos';
    const fRS = document.getElementById('filter-rs')?.value || 'todos';
    const fEps = document.getElementById('filter-eps')?.value || 'todos';
    const fArl = document.getElementById('filter-arl')?.value || 'todos';
    const fCaja = document.getElementById('filter-caja')?.value || 'todos';
    const fPen = document.getElementById('filter-pension')?.value || 'todos';
    const fAdm = document.getElementById('filter-admon')?.value || 'todos';
    const fEst = document.getElementById('filter-estado')?.value || 'todos';
    const fNP = document.getElementById('filter-np')?.value || 'todos';

    // Manejar estilo visual si el filtro está activo
    const toggleActiveFilter = (id, val) => {
        const sel = document.getElementById(id);
        if (sel) {
            if (val !== 'todos') {
                sel.classList.add('active-filter');
            } else {
                sel.classList.remove('active-filter');
            }
        }
    };
    toggleActiveFilter('filter-tipo', fTipo);
    toggleActiveFilter('filter-rs', fRS);
    toggleActiveFilter('filter-eps', fEps);
    toggleActiveFilter('filter-arl', fArl);
    toggleActiveFilter('filter-caja', fCaja);
    toggleActiveFilter('filter-pension', fPen);
    toggleActiveFilter('filter-admon', fAdm);
    toggleActiveFilter('filter-estado', fEst);
    toggleActiveFilter('filter-np', fNP);

    document.querySelectorAll('#tblTrab tbody tr').forEach(tr => {
        if (tr.cells.length === 1 && tr.cells[0].colSpan > 1) return; // Fila vacía

        const est = tr.dataset.estado;

        // 1) Botones de estado superior
        let showEstadoBtn = true;
        if (_filtroActivo === 'pendiente') showEstadoBtn = !['pagada','prestamo'].includes(est);
        else if (_filtroActivo === 'pago')  showEstadoBtn = est === 'pagada';

        // 2) Buscador global
        let showTexto = true;
        if (tokens.length > 0) {
            const nombre  = (tr.dataset.nombre  || '').toLowerCase();
            const cedula  = (tr.dataset.cedula  || '').toLowerCase();
            const haystack = nombre + ' ' + cedula;
            showTexto = tokens.every(t => haystack.includes(t));
        }

        // 3) Dropdowns
        let showTipo = fTipo === 'todos' || tr.dataset.tipomod === fTipo;
        let showRS = fRS === 'todos' || tr.dataset.rs === fRS;

        const vEps = parseInt(tr.dataset.veps) || 0;
        let showEps = fEps === 'todos' || (fEps === '-' ? vEps === 0 : vEps === parseInt(fEps));

        const vArl = parseInt(tr.dataset.varl) || 0;
        let showArl = fArl === 'todos' || (fArl === '-' ? vArl === 0 : vArl === parseInt(fArl));

        const vCaja = parseInt(tr.dataset.vcaja) || 0;
        let showCaja = fCaja === 'todos' || (fCaja === '-' ? vCaja === 0 : vCaja === parseInt(fCaja));

        const vPen = parseInt(tr.dataset.vpen) || 0;
        let showPen = fPen === 'todos' || (fPen === '-' ? vPen === 0 : vPen === parseInt(fPen));

        const vAdm = parseInt(tr.dataset.vadmon) || 0;
        let showAdm = fAdm === 'todos' || (fAdm === '-' ? vAdm === 0 : vAdm === parseInt(fAdm));

        let trEst = '';
        if (tr.cells[14]) {
            trEst = tr.cells[14].textContent.trim().replace(/\s+/g, ' ');
        }
        let showEst = fEst === 'todos' || trEst === fEst;

        const trNP = (tr.dataset.np || '').trim();
        let showNP = fNP === 'todos' || (fNP === '-' ? !trNP : trNP === fNP);

        const visible = showEstadoBtn && showTexto && showTipo && showRS && showEps && showArl && showCaja && showPen && showAdm && showEst && showNP;
        tr.style.display = visible ? '' : 'none';
    });

    onCheckChange();
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
    if (typeof actualizarResumen === 'function') {
        actualizarResumen();
    }
    if (typeof actualizarVisibilidadPanelAdmon === 'function') {
        actualizarVisibilidadPanelAdmon();
    }
}

// ─── Cuenta de Cobro ─────────────────────────────────────────────
function abrirCuentaCobro(tipo) {
    if (!selec.length) return;
    const ids = selec.map(r => r.dataset.contrato);
    window.__ccContratos = ids;

    // Leer si se cobra admon completa en retiros
    const chkAdmonRetiro = document.getElementById('chk-admon-retiro');
    const admonCompletaEnRetiros = chkAdmonRetiro ? (chkAdmonRetiro.checked ? '1' : '0') : '1';

    const queryParams = new URLSearchParams({
        tipo: tipo,
        mes: new URLSearchParams(location.search).get('mes') || '{{ $mes }}',
        anio: new URLSearchParams(location.search).get('anio') || '{{ $anio }}',
        empresa_id: '{{ $empresa->id }}',
        admon_retiro_completa: admonCompletaEnRetiros,
    });

    let url = '{{ route("admin.facturacion.cuenta_cobro.preview") }}?' + queryParams.toString();
    ids.forEach(id => {
        url += '&contratos[]=' + id;
    });

    window.open(url, '_blank');
}

// ─── Resumen ─────────────────────────────────────────────
function _buildContratosSelec() {
    // Leer si el checkbox de admon completa en retiros está activo
    const chkAdmonRetiro = document.getElementById('chk-admon-retiro');
    const admonCompletaEnRetiros = chkAdmonRetiro ? chkAdmonRetiro.checked : true;

    return selec.map(r => {
        const esRetFacturable = r.getAttribute('data-es-retiro-facturable') === "1";
        const diasRet = esRetFacturable ? parseInt(r.getAttribute('data-dias-retiro') || 0) : 0;

        return {
            id:        r.dataset.contrato,
            eps:       parseInt(r.dataset.veps   || 0),
            arl:       parseInt(r.dataset.varl   || 0),
            afp:       parseInt(r.dataset.vpen   || 0),
            caja:      parseInt(r.dataset.vcaja  || 0),
            admon:     parseInt(r.dataset.vadmon || 0),  // ya fue actualizado por actualizarAdmonRetiro()
            seg:       parseInt(r.dataset.seguro || 0),
            iva:       parseInt(r.dataset.viva   || 0),
            mora:      parseInt(r.dataset.vmora  || 0),
            arl_nivel: parseInt(r.dataset.arlnivel || 1),
            dias:      esRetFacturable ? diasRet : parseInt(r.dataset.dias || 30),
            nombre:    r.dataset.nombre || '',
            tipo:      r.dataset.tipo   || 'planilla',
            afiliacion: parseInt(r.dataset.afiliacion || 0),
            esindact:  r.dataset.esindact === '1',
            tipo_modalidad_id: parseInt(r.dataset.tipo_modalidad_id || 0),
            es_retiro_facturable: esRetFacturable,
            dias_retiro: diasRet,
            incluir_admon_retiro_corto: esRetFacturable ? admonCompletaEnRetiros : true,
        };
    });
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
// ─── Exportación a Excel en el lado del cliente (redirección al backend) ──────────────────────
function exportarExcel() {
    const btn = document.querySelector('.btn-exp');
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Exportando...';
    btn.style.opacity = '0.7';

    const urlParams = new URLSearchParams(window.location.search);
    const mes = urlParams.get('mes') || '{{ $mes }}';
    const anio = urlParams.get('anio') || '{{ $anio }}';

    // Redireccionar a la ruta del backend
    window.location.href = `{{ route('admin.facturacion.empresa.exportar', $empresa->id) }}?mes=${mes}&anio=${anio}`;

    setTimeout(() => {
        // Restaurar botón después de iniciar la descarga
        btn.disabled = false;
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
    }, 2000); // 2 segundos de feedback visual
}

// ─── Forzar campo buscador vacío al cargar (evita autorrelleno del navegador) ───
document.addEventListener('DOMContentLoaded', () => {
    const inp = document.getElementById('inp-buscar');
    if (inp) { inp.value = ''; }
    const btnLimp = document.getElementById('btn-limpiar-bus');
    if (btnLimp) btnLimp.style.display = 'none';
    inicializarFiltrosTabla();
    aplicarFiltrosTabla();
});
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

{{-- ═══════════════════════════════════════════════════════════════════════════
     MODAL: Registrar / Editar Retiro Pendiente
     Se abre al hacer clic en el ícono 🗓/✏️ junto a la columna DÍAS.
     Guarda la fecha de retiro y la decisión de cobrar admon via AJAX.
     El contrato permanece 'vigente' hasta que se facture.
═══════════════════════════════════════════════════════════════════════════ --}}
<div id="modal-retiro-pendiente-ov"
     onclick="if(event.target.id==='modal-retiro-pendiente-ov')cerrarModalRetiroPendiente()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.4);width:100%;max-width:460px;overflow:hidden;">

        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#78350f,#b45309);padding:.75rem 1.1rem;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:.5rem;">
                <span style="font-size:1.1rem;">📅</span>
                <span style="color:#fff;font-size:.95rem;font-weight:700;">Registrar Retiro Pendiente</span>
            </div>
            <button onclick="cerrarModalRetiroPendiente()"
                    style="background:rgba(255,255,255,.2);color:#fff;border:none;border-radius:6px;width:28px;height:28px;font-size:1rem;cursor:pointer;font-weight:700;">✕</button>
        </div>

        {{-- Body --}}
        <div style="padding:1.25rem 1.4rem;">
            {{-- Info del trabajador --}}
            <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:.6rem .9rem;margin-bottom:1rem;">
                <div style="font-size:.78rem;color:#78350f;font-weight:600;" id="rp-nombre-label">—</div>
                <div style="font-size:.72rem;color:#92400e;" id="rp-rs-label">—</div>
            </div>

            {{-- Fecha de retiro --}}
            <div style="margin-bottom:1rem;">
                <label for="rp-fecha" style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;">
                    📅 Fecha del último día trabajado
                </label>
                <input type="date" id="rp-fecha"
                       oninput="rpCalcularDias()"
                       style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:.5rem .75rem;font-size:.9rem;color:#111827;outline:none;box-sizing:border-box;"
                       onfocus="this.style.borderColor='#b45309'" onblur="this.style.borderColor='#d1d5db'">
            </div>

            {{-- Días calculados --}}
            <div id="rp-dias-box" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:.6rem .9rem;margin-bottom:1rem;">
                <div style="font-size:.8rem;color:#166534;">
                    📊 Días a cotizar: <strong id="rp-dias-valor" style="font-size:1rem;color:#15803d;">0</strong>
                </div>
                <div id="rp-admon-hint" style="font-size:.72rem;color:#15803d;margin-top:.2rem;"></div>
            </div>

            {{-- Checkbox admon --}}
            <div id="rp-admon-box" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.65rem .9rem;margin-bottom:1.2rem;">
                <label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;">
                    <input type="checkbox" id="rp-cobrar-admon"
                           style="width:1rem;height:1rem;accent-color:#2563eb;cursor:pointer;">
                    <span style="font-size:.82rem;color:#1e40af;font-weight:600;">💼 Cobrar administración en este retiro</span>
                </label>
                <div id="rp-admon-valor-label" style="font-size:.72rem;color:#3b82f6;margin-top:.3rem;margin-left:1.55rem;"></div>
            </div>

            {{-- Alerta si ya tiene retiro pendiente --}}
            <div id="rp-alerta-existente" style="display:none;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:.55rem .8rem;margin-bottom:1rem;font-size:.75rem;color:#c2410c;">
                ⚠️ Este contrato ya tiene un retiro pendiente registrado. Al guardar se reemplazará.
            </div>

            {{-- Botones --}}
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
                <button id="rp-btn-guardar"
                        onclick="rpGuardar()"
                        style="flex:1;background:linear-gradient(135deg,#b45309,#d97706);color:#fff;border:none;border-radius:8px;padding:.55rem .8rem;font-size:.85rem;font-weight:700;cursor:pointer;min-width:120px;">
                    ✅ Guardar Retiro
                </button>
                <button id="rp-btn-quitar"
                        onclick="rpQuitarRetiro()"
                        style="display:none;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:8px;padding:.55rem .8rem;font-size:.82rem;font-weight:600;cursor:pointer;">
                    🗑 Quitar Retiro
                </button>
                <button onclick="cerrarModalRetiroPendiente()"
                        style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:8px;padding:.55rem .8rem;font-size:.82rem;cursor:pointer;">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Retiro Pendiente — Estado del modal ──────────────────────────────────────
var _rpContratoId = null;
var _rpAdmonTotal = 0;
var _rpTieneRetiroActual = false;

function abrirModalRetiroPendiente(contratoId, nombre, rs, admonTotal, fechaActual, cobrarAdmonActual) {
    _rpContratoId    = contratoId;
    _rpAdmonTotal    = admonTotal;
    _rpTieneRetiroActual = !!fechaActual;

    document.getElementById('rp-nombre-label').textContent = nombre;
    document.getElementById('rp-rs-label').textContent     = rs;

    var fechaInput = document.getElementById('rp-fecha');
    fechaInput.value = fechaActual || '{{ $fechaPredeterminada }}';

    // Mostrar botón quitar solo si ya tiene retiro pendiente
    document.getElementById('rp-btn-quitar').style.display  = fechaActual ? 'inline-block' : 'none';
    document.getElementById('rp-alerta-existente').style.display = fechaActual ? 'block' : 'none';

    // Si hay un valor de fecha (sea la predeterminada o la existente), calcular días de inmediato
    if (fechaInput.value) {
        rpCalcularDias();
        if (fechaActual) {
            document.getElementById('rp-cobrar-admon').checked = !!cobrarAdmonActual;
        }
    } else {
        document.getElementById('rp-dias-box').style.display  = 'none';
        document.getElementById('rp-admon-box').style.display = 'none';
    }


    var ov = document.getElementById('modal-retiro-pendiente-ov');
    ov.style.display = 'flex';
}

function cerrarModalRetiroPendiente() {
    document.getElementById('modal-retiro-pendiente-ov').style.display = 'none';
    _rpContratoId = null;
}

function rpCalcularDias() {
    var fecha = document.getElementById('rp-fecha').value;
    if (!fecha) {
        document.getElementById('rp-dias-box').style.display  = 'none';
        document.getElementById('rp-admon-box').style.display = 'none';
        return;
    }
    // Extraer el día del mes directamente del string YYYY-MM-DD (evita problemas de zona horaria)
    var parts = fecha.split('-');
    var dia = parseInt(parts[2], 10);

    document.getElementById('rp-dias-valor').textContent = dia;
    document.getElementById('rp-dias-box').style.display  = 'block';
    document.getElementById('rp-admon-box').style.display = 'block';

    // Sugerencia de admon: marcado si > 3 días, desmarcado si ≤ 3
    var chk = document.getElementById('rp-cobrar-admon');
    if (!_rpTieneRetiroActual) {
        chk.checked = (dia > 3);
    }

    // Label del hint
    var hint = document.getElementById('rp-admon-hint');
    if (dia <= 3) {
        hint.textContent = '⚠️ ≤ 3 días: se sugiere NO cobrar administración.';
        hint.style.color = '#b45309';
    } else {
        hint.textContent = '✅ ' + dia + ' días: se sugiere SÍ cobrar administración.';
        hint.style.color = '#166534';
    }

    // Mostrar valor de admon
    document.getElementById('rp-admon-valor-label').textContent =
        'Valor admon: $' + Number(_rpAdmonTotal).toLocaleString('es-CO') + ' (completo mensual)';
}

function rpGuardar() {
    var fecha = document.getElementById('rp-fecha').value;
    if (!fecha) { alert('Por favor selecciona la fecha del último día trabajado.'); return; }

    var cobrarAdmon = document.getElementById('rp-cobrar-admon').checked ? 1 : 0;
    var btn = document.getElementById('rp-btn-guardar');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    fetch('{{ route("admin.facturacion.contrato.retiro_pendiente", ["contrato" => ":cid"]) }}'.replace(':cid', _rpContratoId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
        },
        body: JSON.stringify({ fecha_retiro: fecha, cobrar_admon: cobrarAdmon })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.textContent = '✅ Guardar Retiro';
        if (data.ok) {
            cerrarModalRetiroPendiente();
            location.reload();
        } else {
            alert(data.mensaje || 'Error al guardar el retiro.');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '✅ Guardar Retiro';
        alert('Error de conexión. Intenta de nuevo.');
    });
}

function rpQuitarRetiro() {
    if (!confirm('¿Seguro que deseas quitar el retiro pendiente de este contrato? Volverá a los 30 días normales.')) return;

    fetch('{{ route("admin.facturacion.contrato.retiro_pendiente", ["contrato" => ":cid"]) }}'.replace(':cid', _rpContratoId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
        },
        body: JSON.stringify({ fecha_retiro: '', cobrar_admon: 0 })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            cerrarModalRetiroPendiente();
            location.reload();
        } else {
            alert(data.mensaje || 'Error al quitar el retiro.');
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}
</script>

<script>
// ════════════════════════════════════════════════════════════════════════
//  MODAL CARGA MASIVA DE CÉDULAS — NP PROVISIONAL
// ════════════════════════════════════════════════════════════════════════
const CC_URL_VERIFICAR = '{{ route("admin.facturacion.empresa.verificar_cedulas", $empresa->id) }}';
const CC_URL_ASIGNAR   = '{{ route("admin.facturacion.empresa.asignar_np", $empresa->id) }}';
const CC_MES  = {{ $mes }};
const CC_ANIO = {{ $anio }};

let _ccExitosas = [];

function abrirModalCargaCedulas() {
    ccVolver();
    document.getElementById('cc-cedulas').value = '';
    document.getElementById('cc-np').value = '1';
    document.getElementById('modalCargaCedulas').style.display = 'flex';
}

function ccVolver() {
    document.getElementById('cc-paso1').style.display = 'block';
    document.getElementById('cc-paso2').style.display = 'none';
    _ccExitosas = [];
}

function ccParsearCedulas() {
    const raw = document.getElementById('cc-cedulas').value;
    return [...new Set(
        raw.split(/[\n,;\s]+/)
            .map(s => s.replace(/[^0-9]/g, '').trim())
            .filter(s => s.length >= 5)
    )];
}

// ── Paso 1: Verificar ─────────────────────────────────────────────────
function ccVerificar() {
    const cedulas = ccParsearCedulas();
    if (cedulas.length === 0) { alert('Pega al menos una cédula válida.'); return; }

    const btn = document.getElementById('cc-btn-verificar');
    btn.disabled = true;
    btn.textContent = '⏳ Verificando…';

    fetch(CC_URL_VERIFICAR, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ cedulas, mes: CC_MES, anio: CC_ANIO })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '🔍 Verificar →';
        if (!data.ok) { alert(data.message || 'Error al verificar.'); return; }

        _ccExitosas = data.exitosas || [];
        const ok    = _ccExitosas.length;
        const yaFac = (data.ya_facturadas || []).length;
        const noEnc = (data.no_encontradas || []).length;
        const total = data.total_input;

        // Chips resumen (usan clase cc-chip del CSS del modal)
        document.getElementById('cc-resumen').innerHTML = `
            <div class="cc-chip" style="background:#f0fdf4;border-color:#86efac;">
                <div class="cc-num" style="color:#15803d;">${ok}</div>
                <div class="cc-lab" style="color:#166534;">✅ Listas para facturar</div>
            </div>
            <div class="cc-chip" style="background:#fffbeb;border-color:#fde68a;">
                <div class="cc-num" style="color:#d97706;">${yaFac}</div>
                <div class="cc-lab" style="color:#92400e;">⚠️ Ya facturadas</div>
            </div>
            <div class="cc-chip" style="background:#fef2f2;border-color:#fca5a5;">
                <div class="cc-num" style="color:#dc2626;">${noEnc}</div>
                <div class="cc-lab" style="color:#991b1b;">❌ No encontradas</div>
            </div>
            <div style="grid-column:1/-1;text-align:center;font-size:.68rem;color:#94a3b8;margin-top:-.2rem;">${total} cédulas procesadas</div>`;

        // Copybox no encontradas
        const wNoEnc = document.getElementById('cc-wrap-no-enc');
        if (noEnc > 0) {
            document.getElementById('cc-txt-no-enc').value = (data.no_encontradas || []).join('\n');
            wNoEnc.style.display = 'block';
        } else { wNoEnc.style.display = 'none'; }

        // Copybox ya facturadas
        const wYaFac = document.getElementById('cc-wrap-ya-fac');
        if (yaFac > 0) {
            document.getElementById('cc-txt-ya-fac').value =
                (data.ya_facturadas || []).map(f => `${f.cedula} - ${f.nombre}`).join('\n');
            wYaFac.style.display = 'block';
        } else { wYaFac.style.display = 'none'; }

        document.getElementById('cc-btn-asignar').disabled    = ok === 0;
        document.getElementById('cc-btn-limpiar-np').disabled = false;

        document.getElementById('cc-paso1').style.display = 'none';
        document.getElementById('cc-paso2').style.display = 'block';
    })
    .catch(() => { btn.disabled = false; btn.textContent = '🔍 Verificar →'; alert('Error de conexión.'); });
}

// ── Paso 2: Asignar NP y seleccionar ─────────────────────────────────
// Si un contrato ya tenía NP distinto, se sobreescribe con el nuevo.
function ccAsignar() {
    if (_ccExitosas.length === 0) return;

    const np          = parseInt(document.getElementById('cc-np').value);
    const contratoIds = _ccExitosas.map(e => e.contrato_id);
    const btn = document.getElementById('cc-btn-asignar');
    btn.disabled = true; btn.textContent = '⏳ Asignando…';

    fetch(CC_URL_ASIGNAR, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ contrato_ids: contratoIds, np })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.textContent = '✅ Asignar NP y Seleccionar';
        if (!data.ok) { alert(data.message || 'Error al asignar NP.'); return; }

        // Actualizar DOM — sobreescribe cualquier NP anterior que tuviera
        const npStr = String(np);
        _ccExitosas.forEach(item => {
            const tr = document.querySelector(`tr[data-contrato="${item.contrato_id}"]`);
            if (!tr) return;
            tr.dataset.np    = npStr;
            tr.dataset.npProv = npStr;
            const celdas = tr.querySelectorAll('td');
            const idx    = celdas.length - 2; // NP | SEL
            if (celdas[idx]) {
                celdas[idx].innerHTML =
                    `<span style="display:inline-block;padding:.12rem .45rem;border-radius:20px;font-size:.75rem;font-weight:800;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;">${npStr}</span>`;
            }
        });

        // Reinicializar filtros y aplicar NP
        inicializarFiltrosTabla();
        const filtroNP = document.getElementById('filter-np');
        if (filtroNP) { filtroNP.value = npStr; filtroNP.classList.add('active-filter'); }
        aplicarFiltrosTabla();

        // Marcar checkboxes de filas visibles
        document.querySelectorAll('.chk-row:not(:disabled)').forEach(chk => {
            const fila = chk.closest('tr');
            if (fila && fila.style.display !== 'none') chk.checked = true;
        });
        onCheckChange();
        cerrar('modalCargaCedulas');
    })
    .catch(() => { btn.disabled = false; btn.textContent = '✅ Asignar NP y Seleccionar'; alert('Error de conexión.'); });
}

// ── Resetear NP de todos los contratos activos de esta empresa en la BD y DOM ──
function ccResetearNPsEmpresa(btnId) {
    if (!confirm('¿Seguro que deseas borrar el NP de TODOS los contratos activos de esta empresa?')) return;

    const btn = document.getElementById(btnId);
    let originalText = '';
    if (btn) {
        btn.disabled = true;
        originalText = btn.textContent;
        btn.textContent = '⏳ Limpiando…';
    }

    // 1. Limpiar DOM inmediatamente para todos los que tienen NP provisional
    const trConNP = Array.from(document.querySelectorAll('tr[data-np-prov]'))
        .filter(tr => tr.dataset.npProv && tr.dataset.npProv !== '');

    trConNP.forEach(tr => {
        tr.dataset.np     = '';
        tr.dataset.npProv = '';
        const celdas = tr.querySelectorAll('td');
        const idx    = celdas.length - 2;
        if (celdas[idx]) {
            celdas[idx].innerHTML = `<span style="color:#cbd5e1;font-size:.7rem">—</span>`;
        }
    });

    // 2. Limpiar los filtros si es necesario
    const filtroNP = document.getElementById('filter-np');
    if (filtroNP && filtroNP.value !== 'todos') {
        filtroNP.value = 'todos';
        filtroNP.classList.remove('active-filter');
    }
    inicializarFiltrosTabla();
    aplicarFiltrosTabla();

    // 3. Petición al backend
    fetch(CC_URL_ASIGNAR, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ np: 0, limpiar_todos: true })
    })
    .then(r => r.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        if (data.ok) {
            alert('NPs de contratos activos limpiados correctamente.');
        } else {
            alert(data.message || 'Error al limpiar NPs.');
        }
    })
    .catch(() => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        alert('Error de conexión al limpiar NPs.');
    });
}

// ── Copiar textarea ───────────────────────────────────────────────────
function ccCopiar(textareaId) {
    const ta = document.getElementById(textareaId);
    if (!ta || !ta.value.trim()) return;
    navigator.clipboard.writeText(ta.value.trim()).then(() => {
        const box = ta.closest('.cc-copybox');
        const btn = box?.querySelector('.cc-copy-btn');
        if (btn) { const o = btn.textContent; btn.textContent = '✅ Copiado'; setTimeout(() => btn.textContent = o, 1800); }
    }).catch(() => { ta.select(); document.execCommand('copy'); });
}
</script>

@endsection
