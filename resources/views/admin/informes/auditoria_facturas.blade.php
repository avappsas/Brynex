@extends('layouts.app')
@section('modulo','Auditoría de Facturas')
@section('contenido')
@php
    $fmt  = fn($v) => '$ '.number_format((float)$v,0,',','.');
    $mesesN    = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $mesesFull = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $estadoColor = ['pagada'=>'#16a34a','abono'=>'#d97706','prestamo'=>'#7c3aed','pre_factura'=>'#64748b','retiro'=>'#dc2626'];
    $tipoLabel   = ['planilla'=>'📋 Planilla','afiliacion'=>'🔗 Afiliación','otro_ingreso'=>'📄 Trámite'];
    $tipoIcon    = ['planilla'=>'📋','afiliacion'=>'🔗','otro_ingreso'=>'📄'];
    $cobroMap    = ['consignado'=>'Consignación','efectivo'=>'Efectivo','prestamo'=>'Préstamo','anticipo'=>'Anticipo'];
    $totalReg    = count($facturas);
@endphp
<style>
*{box-sizing:border-box}
body{background:#f1f5f9;font-family:'Inter',sans-serif;font-size:.8rem;margin:0}
/* Barra superior */
.topbar{background:#1e293b;padding:.48rem 1rem;display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;position:sticky;top:0;z-index:20;border-bottom:2px solid #3b82f6}
.topbar .title{font-size:.84rem;font-weight:800;color:#f1f5f9;white-space:nowrap;margin-right:.2rem}
.topbar label{font-size:.62rem;font-weight:600;color:#94a3b8;white-space:nowrap}
.topbar select,.topbar input[type=text],.topbar input[type=number]{padding:.22rem .42rem;border:1px solid #334155;border-radius:6px;font-size:.7rem;color:#f1f5f9;background:#0f172a;height:25px}
.topbar input[type=number]{width:56px}.topbar input[type=text]{width:125px}
.sep{width:1px;background:#334155;height:19px;flex-shrink:0}
.btn-f{padding:.22rem .68rem;border-radius:6px;font-size:.7rem;font-weight:700;border:none;cursor:pointer;height:25px;white-space:nowrap}
.btn-f.ok{background:#3b82f6;color:#fff}.btn-f.cl{background:#0f172a;color:#94a3b8;border:1px solid #334155}
.reg-info{margin-left:auto;font-size:.7rem;color:#64748b;white-space:nowrap}
.reg-info strong{color:#93c5fd}
/* Tabla: el wrapper ocupa todo el alto restante */
.wrap{height:calc(100vh - 46px - 36px);overflow:auto;position:relative}
table{width:100%;border-collapse:collapse;font-size:.7rem;white-space:nowrap}
/* Cabecera pegada arriba */
thead tr th{
    padding:.34rem .48rem;font-weight:700;font-size:.58rem;
    text-transform:uppercase;letter-spacing:.04em;
    position:sticky;top:0;z-index:11;
    border-bottom:1px solid #1e293b;cursor:default;
}
th.filterable,th.sortable,th.expandable{cursor:pointer;user-select:none}
th.filterable:hover,th.sortable:hover,th.expandable:hover{filter:brightness(1.18)}
th.af{box-shadow:inset 0 -3px 0 #facc15}
th.g-id   {background:#1e293b;color:#cbd5e1}
th.g-cobro{background:#065f46;color:#a7f3d0}
th.g-admon{background:#1d4ed8;color:#bfdbfe}
th.g-afil {background:#0c4a6e;color:#bae6fd}
th.g-ss   {background:#4c1d95;color:#ddd6fe}
th.g-dist {background:#78350f;color:#fde68a}
th.g-tot  {background:#0f172a;color:#f1f5f9}
tbody tr{border-bottom:1px solid #f1f5f9;transition:.07s}
tbody tr:hover{background:#fef9c3}
tbody tr.es-retiro{background:#fef2f2}
tbody tr.es-retiro:hover{background:#fecaca}
tbody td{padding:.26rem .48rem;vertical-align:middle}
td.num{text-align:right;font-family:monospace;color:#1e293b}
td.num.z{color:#d1d5db} td.num.g{color:#16a34a;font-weight:700}
td.num.b{color:#2563eb} td.num.p{color:#7c3aed}
td.num.a{color:#d97706} td.num.r{color:#ef4444}
.badge{display:inline-block;padding:.08rem .34rem;border-radius:20px;font-size:.58rem;font-weight:700;color:#fff}
td.n-row{text-align:right;color:#94a3b8;font-size:.63rem;font-family:monospace;padding-right:.3rem}
/* Totales: barra fija al fondo de la pantalla */
.totbar{
    position:fixed;bottom:0;left:0;right:0;z-index:100;
    background:#1e293b;border-top:2px solid #3b82f6;
    overflow:hidden;
}
.totbar table{
    width:100%;border-collapse:collapse;font-size:.7rem;white-space:nowrap;
    table-layout:fixed;
}
.totbar td{
    font-weight:800;font-size:.68rem;color:#f1f5f9;
    padding:.32rem .48rem;overflow:hidden;
}
tfoot td.num{text-align:right;font-family:monospace}
tfoot td.num.g{color:#6ee7b7} tfoot td.num.b{color:#93c5fd}
tfoot td.num.p{color:#c4b5fd} tfoot td.num.a{color:#fcd34d}
tfoot td.num.r{color:#fca5a5} tfoot td.num.w{color:#e2e8f0}
.totbar td.num{text-align:right;font-family:monospace}
.totbar td.num.g{color:#6ee7b7} .totbar td.num.b{color:#93c5fd}
.totbar td.num.p{color:#c4b5fd} .totbar td.num.a{color:#fcd34d}
.totbar td.num.r{color:#fca5a5} .totbar td.num.w{color:#e2e8f0}
.totbar .col-ss,.totbar .col-afil-det{display:none}
.totbar .col-ss.open,.totbar .col-afil-det.open{display:table-cell}
/* Dropdown columna */
.col-dd{display:none;position:fixed;z-index:999;background:#1e293b;border:1px solid #3b82f6;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.45);min-width:155px;padding:.28rem 0}
.col-dd.show{display:block}
.col-dd a{display:block;padding:.3rem .78rem;color:#e2e8f0;font-size:.73rem;text-decoration:none;white-space:nowrap}
.col-dd a:hover{background:#334155}
.col-dd a.sel{color:#facc15;font-weight:700}
.col-dd .dd-sep{height:1px;background:#334155;margin:.2rem 0}
/* Columnas colapsables */
.col-ss,.col-afil-det{display:none}
.col-ss.open,.col-afil-det.open{display:table-cell}
tfoot .col-ss,tfoot .col-afil-det{display:none}
tfoot .col-ss.open,tfoot .col-afil-det.open{display:table-cell}
</style>

<form method="GET" action="{{ route('admin.informes.auditoria_facturas') }}" id="frmAudit">
<div class="topbar">
    <span class="title">🔍 Auditoría Facturas</span>
    <div class="sep"></div>
    <label>Año</label>
    <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030">
    <label>Mes pago</label>
    <select name="mes_pago" onchange="this.form.submit()">
        <option value="todos" @selected($mesPago === 'todos' || $mesPago === '')>Todos</option>
        @foreach(range(1,12) as $mp)
        <option value="{{ $mp }}" @selected((string)$mesPago === (string)$mp)>{{ $mesesFull[$mp] }}</option>
        @endforeach
    </select>
    {{-- Campos ocultos controlados por dropdowns de columna --}}
    <input type="hidden" name="mes"       id="hMes"     value="{{ $mes ?? '' }}">
    <input type="hidden" name="tipo"      id="hTipo"    value="{{ $tipo }}">
    <input type="hidden" name="estado"    id="hEstado"  value="{{ $estado }}">
    <input type="hidden" name="forma"     id="hForma"   value="{{ $forma }}">
    <input type="hidden" name="cobro"     id="hCobro"   value="{{ $cobro }}">
    <input type="hidden" name="asesor_id" id="hAsId"    value="{{ $asId }}">
    <input type="hidden" name="banco"     id="hBanco"   value="{{ $banco }}">
    <input type="hidden" name="sort_dir"  id="hSortDir" value="{{ $sortDir }}">
    <label>Buscar</label>
    <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Cédula / #Fact / NP">
    <button type="submit" class="btn-f ok">Filtrar</button>
    <a href="{{ route('admin.informes.auditoria_facturas') }}" class="btn-f cl">↺</a>
    <span class="reg-info"><strong>{{ number_format($totalReg) }}</strong> registros</span>
</div>
</form>

{{-- ── DROPDOWNS ── --}}
<div class="col-dd" id="ddPeriodo">
    <a href="#" class="{{ !$mes ? 'sel':'' }}" onclick="return setF('mes','')">Todos los meses</a>
    <div class="dd-sep"></div>
    @foreach($opcionesPeriodo as $nm)
    <a href="#" class="{{ $mes==$nm ? 'sel':'' }}" onclick="return setF('mes','{{ $nm }}')">{{ $mesesFull[$nm] ?? $nm }}</a>
    @endforeach
</div>
<div class="col-dd" id="ddTipo">
    <a href="#" class="{{ $tipo=='todos' ? 'sel':'' }}" onclick="return setF('tipo','todos')">Todos</a>
    <div class="dd-sep"></div>
    @foreach($opcionesTipo as $tp)
    <a href="#" class="{{ $tipo==$tp ? 'sel':'' }}" onclick="return setF('tipo','{{ $tp }}')">{{ $tipoLabel[$tp] ?? ucfirst($tp) }}</a>
    @endforeach
</div>
<div class="col-dd" id="ddEstado">
    <a href="#" class="{{ $estado=='todos' ? 'sel':'' }}"       onclick="return setF('estado','todos')">Todos</a>
    <div class="dd-sep"></div>
    <a href="#" class="{{ $estado=='pagada' ? 'sel':'' }}"      onclick="return setF('estado','pagada')">✅ Pagada</a>
    <a href="#" class="{{ $estado=='abono' ? 'sel':'' }}"       onclick="return setF('estado','abono')">🟡 Abono</a>
    <a href="#" class="{{ $estado=='prestamo' ? 'sel':'' }}"    onclick="return setF('estado','prestamo')">🟣 Préstamo</a>
    <a href="#" class="{{ $estado=='pre_factura' ? 'sel':'' }}" onclick="return setF('estado','pre_factura')">⬜ Pre-factura</a>
</div>
<div class="col-dd" id="ddForma">
    <a href="#" class="{{ $forma=='todos' ? 'sel':'' }}" onclick="return setF('forma','todos')">Todas</a>
    <div class="dd-sep"></div>
    @foreach($opcionesForma as $fp)
    <a href="#" class="{{ $forma==$fp ? 'sel':'' }}" onclick="return setF('forma','{{ $fp }}')">{{ ucfirst($fp) }}</a>
    @endforeach
</div>
<div class="col-dd" id="ddCobro">
    <a href="#" class="{{ $cobro=='todos' ? 'sel':'' }}"      onclick="return setF('cobro','todos')">Todos</a>
    <div class="dd-sep"></div>
    <a href="#" class="{{ $cobro=='consignado' ? 'sel':'' }}" onclick="return setF('cobro','consignado')">Solo Consignación</a>
    <a href="#" class="{{ $cobro=='efectivo'   ? 'sel':'' }}" onclick="return setF('cobro','efectivo')">Solo Efectivo</a>
    <a href="#" class="{{ $cobro=='prestamo'   ? 'sel':'' }}" onclick="return setF('cobro','prestamo')">Solo Préstamo</a>
    <a href="#" class="{{ $cobro=='anticipo'   ? 'sel':'' }}" onclick="return setF('cobro','anticipo')">Solo Anticipo</a>
</div>
<div class="col-dd" id="ddAsesor">
    <a href="#" class="{{ $asId=='todos' ? 'sel':'' }}" onclick="return setF('asesor_id','todos')">Todos los asesores</a>
    <div class="dd-sep"></div>
    @foreach($opcionesAsesor as $as)
    <a href="#" class="{{ $asId==$as->id ? 'sel':'' }}" onclick="return setF('asesor_id','{{ $as->id }}')">{{ $as->nombre }}</a>
    @endforeach
</div>
<div class="col-dd" id="ddBanco">
    <a href="#" class="{{ $banco=='todos' ? 'sel':'' }}" onclick="return setF('banco','todos')">🏦 Todos los bancos</a>
    <div class="dd-sep"></div>
    @forelse($opcionesBanco as $bk)
    <a href="#" class="{{ $banco==$bk ? 'sel':'' }}" onclick="return setF('banco','{{ $bk }}')">{{ $bk }}</a>
    @empty
    <a href="#" style="color:#64748b;cursor:default">Sin datos</a>
    @endforelse
</div>

{{-- ── TABLA: contenedor con scroll ── --}}
<div class="wrap">
<table>
<thead><tr>
    <th class="g-id" style="min-width:30px">#</th>
    <th class="g-id sortable {{ $sortDir=='asc' ? 'af':'' }}" style="min-width:84px" onclick="toggleSort()">
        Fecha pago {{ $sortDir=='asc' ? '▲':'▼' }}
    </th>
    <th class="g-id filterable {{ $mes ? 'af':'' }}" id="thPer" style="min-width:70px" onclick="toggleDD('ddPeriodo',this)">
        Período{{ $mes ? ' ('.$mesesN[$mes].')':'' }} ▾
    </th>
    <th class="g-id" style="min-width:54px">#Fact</th>
    <th class="g-id filterable {{ $tipo!='todos' ? 'af':'' }}" id="thTipo" style="min-width:88px" onclick="toggleDD('ddTipo',this)">
        Tipo{{ $tipo!='todos' ? ' ('.$tipoIcon[$tipo].')':'' }} ▾
    </th>
    <th class="g-id filterable {{ $estado!='todos' ? 'af':'' }}" id="thEstado" style="min-width:84px" onclick="toggleDD('ddEstado',this)">
        Estado{{ $estado!='todos' ? ' ('.ucfirst($estado).')':'' }} ▾
    </th>
    <th class="g-id" style="min-width:148px">Cliente / Empresa</th>
    <th class="g-id" style="min-width:86px">Cédula</th>
    <th class="g-id" style="min-width:68px">NP</th>
    <th class="g-id filterable {{ $forma!='todos' ? 'af':'' }}" id="thForma" style="min-width:84px" onclick="toggleDD('ddForma',this)">
        F.Pago{{ $forma!='todos' ? ' ('.ucfirst($forma).')':'' }} ▾
    </th>
    <th class="g-id filterable {{ $asId!='todos' ? 'af':'' }}" id="thAsesor" style="min-width:80px" onclick="toggleDD('ddAsesor',this)">Asesor ▾</th>
    <th class="g-id filterable {{ $banco!='todos' ? 'af':'' }}" id="thBanco" style="min-width:100px" onclick="toggleDD('ddBanco',this)">Banco ▾</th>
    <th class="g-cobro filterable {{ $cobro=='consignado' ? 'af':'' }}" style="min-width:94px" onclick="toggleDD('ddCobro',this)">Consig. ▾</th>
    <th class="g-cobro filterable {{ $cobro=='efectivo'   ? 'af':'' }}" style="min-width:94px" onclick="toggleDD('ddCobro',this)">Efectivo ▾</th>
    <th class="g-cobro filterable {{ $cobro=='prestamo'   ? 'af':'' }}" style="min-width:94px" onclick="toggleDD('ddCobro',this)">Préstamo ▾</th>
    <th class="g-cobro filterable {{ $cobro=='anticipo'   ? 'af':'' }}" style="min-width:94px" onclick="toggleDD('ddCobro',this)">Anticipo ▾</th>
    <th class="g-admon" style="min-width:84px">Admon</th>
    <th class="g-admon" style="min-width:84px">Seguro</th>
    <th class="g-admon" style="min-width:84px">Mensajería</th>
    <th class="g-admon" style="min-width:66px">IVA</th>
    <th class="g-admon" style="min-width:72px">Mora</th>
    <th class="g-afil expandable" style="min-width:90px" onclick="toggleAfil()">
        Afiliación <span id="iconAfil">＋</span>
    </th>
    <th class="g-afil col-afil-det" style="min-width:80px">Retiro</th>
    <th class="g-afil col-afil-det" style="min-width:80px">Otros</th>
    <th class="g-ss expandable" style="min-width:96px" onclick="toggleSS()">
        Total SS <span id="iconSS">＋</span>
    </th>
    <th class="g-ss col-ss" style="min-width:82px">EPS</th>
    <th class="g-ss col-ss" style="min-width:82px">AFP</th>
    <th class="g-ss col-ss" style="min-width:72px">ARL</th>
    <th class="g-ss col-ss" style="min-width:72px">Caja</th>
    <th class="g-dist" style="min-width:82px">C.Asesor</th>
    <th class="g-dist" style="min-width:82px">C.Utilidad</th>
    <th class="g-tot" style="min-width:100px">TOTAL</th>
    <th class="g-tot" style="min-width:88px">Saldo prox.</th>
</tr></thead>

<tbody>
@forelse($facturas as $f)
@php
    $esRetiro = ((int)($f->numero_factura ?? 0) === 0);
    $tipoMostrar  = $esRetiro ? '🏃 Retiro' : ($tipoLabel[$f->tipo]  ?? $f->tipo);
    $estadoLabel  = $esRetiro ? 'Retiro'    : ucfirst($f->estado);
    $estadoBg     = $esRetiro ? '#dc2626'   : ($estadoColor[$f->estado] ?? '#64748b');
    $periodo      = ($mesesN[$f->mes ?? 0] ?? '').'/'.substr((string)($f->anio ?? ''),2);
@endphp
<tr class="{{ $esRetiro ? 'es-retiro':'' }}" onclick="abrirRecibo('{{ route('admin.facturacion.recibo', $f->id) }}?modal=1')" style="cursor:pointer">
    <td class="n-row">{{ $loop->iteration }}</td>
    <td style="color:#475569;font-size:.67rem">{{ $f->fecha_pago ?? '—' }}</td>
    <td style="color:#94a3b8;font-size:.65rem">{{ $periodo }}</td>
    <td style="font-family:monospace;font-weight:700;color:{{ $esRetiro ? '#dc2626':'#475569' }};font-size:.67rem">
        #{{ $f->numero_factura }}
    </td>
    <td style="font-size:.68rem">{{ $tipoMostrar }}</td>
    <td><span class="badge" style="background:{{ $estadoBg }}">{{ $estadoLabel }}</span></td>
    <td style="max-width:148px;overflow:hidden;text-overflow:ellipsis;font-weight:600;color:#1e293b" title="{{ $f->nombre_cliente }}">{{ $f->nombre_cliente }}</td>
    <td style="color:#94a3b8;font-family:monospace;font-size:.65rem">{{ $f->cedula }}</td>
    <td style="color:#64748b;font-family:monospace;font-size:.65rem">{{ $f->np ?? '—' }}</td>
    <td style="color:#475569;font-size:.66rem">{!! $esRetiro ? '<span style="color:#94a3b8;font-style:italic">Ninguno</span>' : e($f->forma_pago ?? '—') !!}</td>
    <td style="font-size:.65rem;color:#7c3aed;max-width:78px;overflow:hidden;text-overflow:ellipsis" title="{{ $f->asesor_nombre }}">{{ $f->asesor_nombre }}</td>
    <td style="font-size:.65rem;color:#0369a1;font-weight:600;max-width:98px;overflow:hidden;text-overflow:ellipsis" title="{{ $f->nombre_banco ?? '' }}">
        {{ $f->nombre_banco ? strtoupper($f->nombre_banco) : '—' }}
    </td>
    <td class="num {{ $f->valor_consignado  > 0 ? 'b':'z' }}">{{ $f->valor_consignado  > 0 ? $fmt($f->valor_consignado)  : '—' }}</td>
    <td class="num {{ $f->valor_efectivo    > 0 ? 'g':'z' }}">{{ $f->valor_efectivo    > 0 ? $fmt($f->valor_efectivo)    : '—' }}</td>
    <td class="num {{ $f->valor_prestamo    > 0 ? 'p':'z' }}">{{ $f->valor_prestamo    > 0 ? $fmt($f->valor_prestamo)    : '—' }}</td>
    <td class="num {{ $f->anticipo_aplicado > 0 ? 'a':'z' }}">{{ $f->anticipo_aplicado > 0 ? $fmt($f->anticipo_aplicado) : '—' }}</td>
    <td class="num">{{ $f->admon      > 0 ? $fmt($f->admon)      : '—' }}</td>
    <td class="num">{{ $f->seguro     > 0 ? $fmt($f->seguro)     : '—' }}</td>
    <td class="num">{{ $f->mensajeria > 0 ? $fmt($f->mensajeria) : '—' }}</td>
    <td class="num">{{ $f->iva        > 0 ? $fmt($f->iva)        : '—' }}</td>
    <td class="num {{ $f->mora > 0 ? 'r':'z' }}">{{ $f->mora > 0 ? $fmt($f->mora) : '—' }}</td>
    <td class="num b">{{ $f->afiliacion > 0 ? $fmt($f->afiliacion) : '—' }}</td>
    <td class="num col-afil-det">{{ $f->retiro > 0 ? $fmt($f->retiro) : '—' }}</td>
    <td class="num col-afil-det">{{ $f->otros  > 0 ? $fmt($f->otros)  : '—' }}</td>
    <td class="num" style="color:#6d28d9;font-weight:700">{{ $f->total_ss > 0 ? $fmt($f->total_ss) : '—' }}</td>
    <td class="num p col-ss">{{ $f->v_eps  > 0 ? $fmt($f->v_eps)  : '—' }}</td>
    <td class="num p col-ss">{{ $f->v_afp  > 0 ? $fmt($f->v_afp)  : '—' }}</td>
    <td class="num p col-ss">{{ $f->v_arl  > 0 ? $fmt($f->v_arl)  : '—' }}</td>
    <td class="num p col-ss">{{ $f->v_caja > 0 ? $fmt($f->v_caja) : '—' }}</td>
    <td class="num a">{{ $f->c_asesor   > 0 ? $fmt($f->c_asesor)   : '—' }}</td>
    <td class="num g">{{ $f->c_utilidad > 0 ? $fmt($f->c_utilidad) : '—' }}</td>
    <td class="num" style="font-weight:800;color:#0f172a">{{ $fmt($f->total) }}</td>
    <td class="num {{ ($f->saldo_proximo??0)<0?'r':(($f->saldo_proximo??0)>0?'g':'z') }}">
        {{ ($f->saldo_proximo??0)!=0 ? $fmt($f->saldo_proximo) : '—' }}
    </td>
</tr>
@empty
<tr><td colspan="34" style="text-align:center;padding:3rem;color:#94a3b8">Sin resultados.</td></tr>
@endforelse
</tbody>

{{-- TOTALES: position:sticky bottom:0 — SIEMPRE VISIBLE --}}
<tfoot><tr>
    <td colspan="11" style="color:#93c5fd;font-size:.65rem;font-weight:700">
        TOTALES — {{ number_format($tots->cant ?? 0) }} fact.
        @if($cobro!='todos')   &nbsp;·&nbsp;<span style="color:#facc15">{{ $cobroMap[$cobro] }}</span> @endif
        @if($asId!='todos')    &nbsp;·&nbsp;<span style="color:#c4b5fd">Asesor filtrado</span> @endif
        @if($mesPago)          &nbsp;·&nbsp;<span style="color:#6ee7b7">Pago {{ $mesesFull[(int)$mesPago] ?? '' }}</span> @endif
    </td>
    <td class="num b">{{ $fmt($tots->tot_consig     ?? 0) }}</td>
    <td class="num g">{{ $fmt($tots->tot_efect      ?? 0) }}</td>
    <td class="num p">{{ $fmt($tots->tot_prestamo   ?? 0) }}</td>
    <td class="num a">{{ $fmt($tots->tot_anticipo   ?? 0) }}</td>
    <td class="num w">{{ $fmt($tots->tot_admon      ?? 0) }}</td>
    <td class="num w">{{ $fmt($tots->tot_seguro     ?? 0) }}</td>
    <td class="num w">{{ $fmt($tots->tot_mensajeria ?? 0) }}</td>
    <td class="num w">{{ $fmt($tots->tot_iva        ?? 0) }}</td>
    <td class="num r">{{ $fmt($tots->tot_mora       ?? 0) }}</td>
    <td class="num b">{{ $fmt($tots->tot_afiliacion ?? 0) }}</td>
    <td class="num w col-afil-det">{{ $fmt($tots->tot_retiro ?? 0) }}</td>
    <td class="num w col-afil-det">{{ $fmt($tots->tot_otros  ?? 0) }}</td>
    <td class="num" style="color:#c4b5fd;font-weight:900">{{ $fmt($tots->tot_ss ?? 0) }}</td>
    <td class="num p col-ss">{{ $fmt($tots->tot_eps  ?? 0) }}</td>
    <td class="num p col-ss">{{ $fmt($tots->tot_afp  ?? 0) }}</td>
    <td class="num p col-ss">{{ $fmt($tots->tot_arl  ?? 0) }}</td>
    <td class="num p col-ss">{{ $fmt($tots->tot_caja ?? 0) }}</td>
    <td class="num a">{{ $fmt($tots->tot_asesor   ?? 0) }}</td>
    <td class="num g">{{ $fmt($tots->tot_utilidad ?? 0) }}</td>
    <td class="num" style="color:#fff;font-size:.78rem;font-weight:900">{{ $fmt($tots->total ?? 0) }}</td>
    <td></td>
</tr></tfoot>
</table>
</div>{{-- /wrap --}}

{{-- BARRA DE TOTALES FIJA AL FONDO — SIEMPRE VISIBLE --}}
<div class="totbar" id="totbar">
<table id="totTable">
<tr>
    {{-- Col 1: # --}}
    <td style="color:#64748b;font-size:.62rem;font-weight:700">Σ</td>
    {{-- Col 2: Fecha pago --}}
    <td></td>
    {{-- Col 3: Período --}}
    <td></td>
    {{-- Col 4: #Fact --}}
    <td style="color:#93c5fd;font-size:.62rem">{{ number_format($tots->cant ?? 0) }}</td>
    {{-- Col 5: Tipo --}}
    <td></td>
    {{-- Col 6: Estado --}}
    <td></td>
    {{-- Col 7: Cliente --}}
    <td style="color:#93c5fd;font-size:.6rem;white-space:nowrap;overflow:hidden">
        TOTALES
        @if($cobro!='todos') &nbsp;·&nbsp;<span style="color:#facc15">{{ $cobroMap[$cobro] }}</span> @endif
        @if($asId!='todos')  &nbsp;·&nbsp;<span style="color:#c4b5fd">Asesor▾</span> @endif
        @if($mesPago)        &nbsp;·&nbsp;<span style="color:#6ee7b7">{{ $mesesN[(int)$mesPago] ?? '' }}</span> @endif
    </td>
    {{-- Col 8: Cédula --}}
    <td></td>
    {{-- Col 9: NP --}}
    <td></td>
    {{-- Col 10: F.Pago --}}
    <td></td>
    {{-- Col 11: Asesor --}}
    <td></td>
    {{-- Col 12: Banco --}}
    <td style="color:#0369a1;font-size:.6rem;font-weight:600">{{ $banco!='todos' ? $banco : '' }}</td>
    {{-- Col 13: Consig. --}}
    <td class="num b">{{ $fmt($tots->tot_consig     ?? 0) }}</td>
    {{-- Col 13: Efectivo --}}
    <td class="num g">{{ $fmt($tots->tot_efect      ?? 0) }}</td>
    {{-- Col 14: Préstamo --}}
    <td class="num p">{{ $fmt($tots->tot_prestamo   ?? 0) }}</td>
    {{-- Col 15: Anticipo --}}
    <td class="num a">{{ $fmt($tots->tot_anticipo   ?? 0) }}</td>
    {{-- Col 16: Admon --}}
    <td class="num w">{{ $fmt($tots->tot_admon      ?? 0) }}</td>
    {{-- Col 17: Seguro --}}
    <td class="num w">{{ $fmt($tots->tot_seguro     ?? 0) }}</td>
    {{-- Col 18: Mensajería --}}
    <td class="num w">{{ $fmt($tots->tot_mensajeria ?? 0) }}</td>
    {{-- Col 19: IVA --}}
    <td class="num w">{{ $fmt($tots->tot_iva        ?? 0) }}</td>
    {{-- Col 20: Mora --}}
    <td class="num r">{{ $fmt($tots->tot_mora       ?? 0) }}</td>
    {{-- Col 21: Afiliación --}}
    <td class="num b">{{ $fmt($tots->tot_afiliacion ?? 0) }}</td>
    {{-- Col 22: Retiro (col-afil-det) --}}
    <td class="num w col-afil-det">{{ $fmt($tots->tot_retiro ?? 0) }}</td>
    {{-- Col 23: Otros (col-afil-det) --}}
    <td class="num w col-afil-det">{{ $fmt($tots->tot_otros  ?? 0) }}</td>
    {{-- Col 24: Total SS --}}
    <td class="num" style="color:#c4b5fd;font-weight:900">{{ $fmt($tots->tot_ss ?? 0) }}</td>
    {{-- Col 25: EPS (col-ss) --}}
    <td class="num p col-ss">{{ $fmt($tots->tot_eps  ?? 0) }}</td>
    {{-- Col 26: AFP (col-ss) --}}
    <td class="num p col-ss">{{ $fmt($tots->tot_afp  ?? 0) }}</td>
    {{-- Col 27: ARL (col-ss) --}}
    <td class="num p col-ss">{{ $fmt($tots->tot_arl  ?? 0) }}</td>
    {{-- Col 28: Caja (col-ss) --}}
    <td class="num p col-ss">{{ $fmt($tots->tot_caja ?? 0) }}</td>
    {{-- Col 29: C.Asesor --}}
    <td class="num a">{{ $fmt($tots->tot_asesor   ?? 0) }}</td>
    {{-- Col 30: C.Utilidad --}}
    <td class="num g">{{ $fmt($tots->tot_utilidad ?? 0) }}</td>
    {{-- Col 31: TOTAL --}}
    <td class="num" style="color:#fff;font-size:.78rem;font-weight:900">{{ $fmt($tots->total ?? 0) }}</td>
    {{-- Col 32: Saldo prox --}}
    <td></td>
</tr>
</table>
</div>

<script>
// ── Sincronizar ancho de totbar con la tabla ──────────────────────
function syncTotBar() {
    const ths  = [...document.querySelectorAll('thead tr th')].filter(th => getComputedStyle(th).display !== 'none');
    const tds  = document.querySelectorAll('#totTable tr td');
    const wrap = document.querySelector('.wrap');
    // Sincronizar scroll horizontal
    document.getElementById('totTable').style.transform = `translateX(-${wrap.scrollLeft}px)`;
    // Copiar ancho real de cada th al td correspondiente
    let i = 0;
    ths.forEach(th => {
        if (tds[i]) {
            const w = th.getBoundingClientRect().width + 'px';
            tds[i].style.width = w;
            tds[i].style.minWidth = w;
            tds[i].style.maxWidth = w;
        }
        i++;
    });
}
document.querySelector('.wrap').addEventListener('scroll', syncTotBar);
window.addEventListener('resize', syncTotBar);
setTimeout(syncTotBar, 150);

// ── Dropdowns ──────────────────────────────────────────────────────
let _dd = null;
function toggleDD(id, th) {
    const dd = document.getElementById(id);
    if (_dd && _dd !== dd) _dd.classList.remove('show');
    if (dd.classList.contains('show')) { dd.classList.remove('show'); _dd = null; return; }
    const r = th.getBoundingClientRect();
    dd.style.top  = (r.bottom + 3) + 'px';
    dd.style.left = Math.min(r.left, window.innerWidth - 200) + 'px';
    dd.classList.add('show'); _dd = dd;
}
document.addEventListener('click', e => {
    if (_dd && !_dd.contains(e.target) && !e.target.closest('th.filterable') && !e.target.closest('th.sortable'))
        { _dd.classList.remove('show'); _dd = null; }
});
function setF(campo, valor) {
    const map = { mes:'hMes', tipo:'hTipo', estado:'hEstado', forma:'hForma',
                  cobro:'hCobro', asesor_id:'hAsId', banco:'hBanco', sort_dir:'hSortDir' };
    document.getElementById(map[campo]).value = valor;
    if (_dd) { _dd.classList.remove('show'); _dd = null; }
    document.getElementById('frmAudit').submit();
    return false;
}
// ── Ordenar fecha_pago ─────────────────────────────────────────────
function toggleSort() {
    const h = document.getElementById('hSortDir');
    h.value = h.value === 'desc' ? 'asc' : 'desc';
    document.getElementById('frmAudit').submit();
}
// ── Expandir Total SS ──────────────────────────────────────────────
let _ssOpen = false;
function toggleSS() {
    _ssOpen = !_ssOpen;
    document.querySelectorAll('.col-ss').forEach(el => el.classList.toggle('open', _ssOpen));
    document.getElementById('iconSS').textContent = _ssOpen ? '－' : '＋';
    setTimeout(syncTotBar, 50);
}
// ── Expandir Afiliación ────────────────────────────────────────────
let _afilOpen = false;
function toggleAfil() {
    _afilOpen = !_afilOpen;
    document.querySelectorAll('.col-afil-det').forEach(el => el.classList.toggle('open', _afilOpen));
    document.getElementById('iconAfil').textContent = _afilOpen ? '－' : '＋';
    setTimeout(syncTotBar, 50);
}

// ─── Modal Recibo (iframe) ─────────────────────────────────
function abrirRecibo(url) {
    document.getElementById('recibo-frame').src = url;
    document.getElementById('recibo-modal-ov').style.display = 'flex';
}
function cerrarRecibo() {
    document.getElementById('recibo-modal-ov').style.display = 'none';
    document.getElementById('recibo-frame').src = '';
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
@endsection
