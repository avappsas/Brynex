<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cuenta de Cobro Detallada – {{ $empresa->empresa ?? '' }} – {{ $meses[$mes] }} {{ $anio }}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Arial', sans-serif; font-size: 10px; color: #222; background: #fff; }

.acciones-bar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 999;
    background: #1e3a5f; padding: .5rem 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.btn-ac {
    padding: .35rem .9rem; border-radius: 6px; border: none;
    cursor: pointer; font-size: .78rem; font-weight: 700; display: flex; align-items: center; gap: .3rem;
}
.btn-print  { background: #2563eb; color: #fff; }
.btn-simple { background: #475569; color: #fff; }
.btn-close  { background: rgba(255,255,255,.15); color: #fff; margin-left: auto; }
.btn-ac:hover { opacity: .88; }

.doc-wrap { max-width: 1100px; margin: 0 auto; padding: 3.5rem 1.5rem 2rem; }

.doc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.logo-area img   { max-height: 80px; max-width: 180px; object-fit: contain; }
.logo-area-text  { font-size: .95rem; font-weight: 800; color: #1e3a5f; }
.aliado-info     { text-align: right; font-size: 9.5px; color: #444; line-height: 1.65; }
.aliado-info strong { font-size: 10.5px; color: #1e3a5f; }

.doc-title {
    text-align: center; font-size: 1.1rem; font-weight: 900;
    letter-spacing: .12em; text-transform: uppercase;
    border-top: 2px solid #1e3a5f; border-bottom: 2px solid #1e3a5f;
    padding: .3rem 0; margin-bottom: 1rem; color: #1e3a5f;
}
.doc-subtitle { text-align: center; font-size: .78rem; color: #475569; margin-bottom: .9rem; }

.destinatario { margin-bottom: .8rem; line-height: 1.7; }
.empresa-dest { font-size: .95rem; font-weight: 800; color: #1e3a5f; text-transform: uppercase; }
.cuerpo-texto { margin-bottom: .8rem; line-height: 1.6; }

/* Tabla detallada — scroll horizontal en pantalla */
.tbl-wrap { overflow-x: auto; margin-bottom: .7rem; }
table.tbl-det { width: 100%; border-collapse: collapse; }
table.tbl-det thead tr { background: #1e3a5f; color: #fff; }
table.tbl-det th {
    padding: .38rem .35rem; font-size: 8.5px; text-transform: uppercase;
    letter-spacing: .04em; white-space: nowrap;
}
table.tbl-det td { padding: .35rem .35rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
table.tbl-det tr:nth-child(even) td { background: #f8fafc; }
table.tbl-det .num { text-align: right; font-family: monospace; white-space: nowrap; }
table.tbl-det tfoot tr { background: #0f172a; color: #fff; font-weight: 700; }
table.tbl-det tfoot .num { color: #34d399; }

/* Celda de entidad: nombre + valor */
.ent-cell { min-width: 90px; }
.ent-nombre { font-size: 8.5px; color: #2563eb; font-weight: 600; display: block; white-space: nowrap; }
.ent-valor  { font-family: monospace; font-weight: 700; }

.estado-badge {
    display: inline-block; padding: .1rem .35rem; border-radius: 12px;
    font-size: 8.5px; font-weight: 700;
}
.est-vigente  { background: #dcfce7; color: #15803d; }
.est-pre      { background: #fef9c3; color: #854d0e; }
.est-prestamo { background: #ede9fe; color: #6d28d9; }
.est-abono    { background: #fef3c7; color: #92400e; }
.est-sin      { background: #f1f5f9; color: #64748b; }

.saldo-info { font-size: 8.5px; margin-top: 2px; }
.saldo-favor     { color: #15803d; font-weight: 700; }
.saldo-pendiente { color: #dc2626; font-weight: 700; }

/* Resumen cuentas */
.cuentas-cobro { margin-bottom: .8rem; }
.cuentas-cobro h4 { font-size: 10px; font-weight: 800; color: #1e3a5f; margin-bottom: .3rem; text-transform: uppercase; }
.cuenta-item {
    display: flex; gap: .7rem; align-items: baseline;
    font-size: 10px; padding: .22rem .4rem;
    border-left: 3px solid #2563eb; margin-bottom: .2rem; background: #f8fafc;
}
.cuenta-banco { font-weight: 800; min-width: 90px; }
.cuenta-num   { font-family: monospace; color: #1e3a5f; }

.nota-legal {
    background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px;
    padding: .5rem .7rem; font-size: 8.5px; color: #7f1d1d; line-height: 1.5; margin-bottom: .6rem;
}
.pie-doc {
    border-top: 1px solid #cbd5e1; padding-top: .4rem;
    font-size: 8.5px; color: #64748b; display: flex; justify-content: space-between; flex-wrap: wrap; gap: .3rem;
}

@media print {
    .acciones-bar { display: none !important; }
    .doc-wrap { padding-top: .5rem; font-size: 8.5px; }
    .tbl-wrap { overflow: visible; }
    table.tbl-det th, table.tbl-det td { font-size: 7.5px; padding: .25rem .25rem; }
}
</style>
</head>
<body>

<div class="acciones-bar">
    <button class="btn-ac btn-print" onclick="window.print()">🖨 Imprimir / Descargar PDF</button>
    <form id="frmSimple" method="POST" action="{{ route('admin.facturacion.cuenta_cobro.preview') }}" target="_blank" style="display:inline;">
        @csrf
        <input type="hidden" name="tipo" value="simple">
        <input type="hidden" name="mes" value="{{ $mes }}">
        <input type="hidden" name="anio" value="{{ $anio }}">
        <input type="hidden" name="empresa_id" value="{{ $empresa->id ?? '' }}">
        @foreach(request()->input('contratos', []) as $cid)
        <input type="hidden" name="contratos[]" value="{{ $cid }}">
        @endforeach
        <button type="submit" class="btn-ac btn-simple">📄 Ver Simple</button>
    </form>
    <button class="btn-ac btn-close" onclick="window.close()">✕ Cerrar</button>
    <span style="color:#94a3b8;font-size:.72rem;margin-left:.5rem;">
        Vista Detallada — {{ $empresa->empresa ?? '' }} — {{ $meses[$mes] }} {{ $anio }}
    </span>
</div>

<div class="doc-wrap">

    <div class="doc-header">
        <div class="logo-area">
            @if($aliado?->logo)
                <img src="{{ asset('storage/' . $aliado->logo) }}" alt="Logo">
            @else
                <div class="logo-area-text">{{ $aliado?->razon_social ?? $aliado?->nombre ?? 'BRYGAR' }}</div>
            @endif
        </div>
        <div class="aliado-info">
            <strong>{{ $aliado?->razon_social ?? $aliado?->nombre ?? '' }}</strong><br>
            @if($aliado?->nit)<span>NIT: {{ $aliado->nit }}</span><br>@endif
            @if($aliado?->direccion)<span>Dir: {{ $aliado->direccion }}</span><br>@endif
            @if($aliado?->ciudad)<span>{{ $aliado->ciudad }}</span><br>@endif
            @if($aliado?->telefono || $aliado?->celular)
                <span>Tel: {{ implode(' - ', array_filter([$aliado?->telefono, $aliado?->celular])) }}</span><br>
            @endif
            @if($aliado?->correo)<span>{{ $aliado->correo }}</span>@endif
        </div>
    </div>

    <div class="doc-title">Cuenta de Cobro — Detallada</div>
    <div class="doc-subtitle">Desglose por entidad — {{ $meses[$mes] }} {{ $anio }}</div>

    <div class="destinatario">
        <div>Señores:</div>
        <div class="empresa-dest">{{ $empresa->empresa ?? '' }}</div>
        @if($empresa?->contacto)<div>Att. {{ $empresa->contacto }}</div>@endif
    </div>

    <div style="margin-bottom:.5rem;"><em>Cordial Saludo,</em></div>

    <div class="cuerpo-texto">
        A continuación el detalle de aportes de seguridad social correspondientes al periodo
        <strong>{{ $meses[$mes] }} {{ $anio }}</strong>.
    </div>

    <div class="tbl-wrap">
    <table class="tbl-det">
        <thead>
            <tr>
                <th style="text-align:center;width:22px;">#</th>
                <th>Documento</th>
                <th>Nombre</th>
                <th style="text-align:center;">Ingreso</th>
                <th style="text-align:center;">Días</th>
                <th class="num">EPS</th>
                <th class="num">ARL</th>
                <th class="num">AFP</th>
                <th class="num">Caja</th>
                <th class="num">Admon</th>
                <th class="num">IVA</th>
                <th class="num" style="color:#34d399;">Total</th>
                <th style="text-align:center;">Estado</th>
            </tr>
        </thead>
        <tbody>
        @php $no=1; $totEps=$totArl=$totAfp=$totCaja=$totAdmon=$totIva=$totTotal=0; $totalSaldo=0; @endphp
        @foreach($items as $item)
        @php
            $totEps   += $item->v_eps;
            $totArl   += $item->v_arl;
            $totAfp   += $item->v_afp;
            $totCaja  += $item->v_caja;
            $totAdmon += $item->v_admon;
            $totIva   += $item->v_iva;
            $totTotal += $item->v_total;
            // saldo_proximo: positivo = a favor, negativo = pendiente
            $spItem = (int)($item->saldo_proximo ?? 0);
            $itemAFavor    = $spItem > 0 ? $spItem : 0;
            $itemPendiente = $spItem < 0 ? abs($spItem) : 0;
            $totalSaldo += $itemPendiente - $itemAFavor;
            $estadoClass = match($item->estado) {
                'pagada'      => 'est-vigente',
                'prestamo'    => 'est-prestamo',
                'pre_factura' => 'est-pre',
                'abono'       => 'est-abono',
                default       => 'est-sin',
            };
            $estadoLabel = match($item->estado) {
                'pagada'      => 'Vigente',
                'prestamo'    => 'Préstamo',
                'pre_factura' => 'Pre-Fac',
                'abono'       => 'Abono',
                default       => 'Pendiente',
            };
        @endphp
        <tr>
            <td style="text-align:center;color:#94a3b8;">{{ $no++ }}</td>
            <td style="font-family:monospace;">{{ number_format($item->cedula,0,'','.') }}</td>
            <td style="min-width:160px;">
                <div style="font-weight:700;">{{ $item->nombre }}</div>
                <div style="font-size:8.5px;color:#dc2626;font-weight:600;">{{ $item->razon_social }}</div>
                @if($item->es_afil)<span style="font-size:8px;color:#7c3aed;font-weight:700;">📌 Afiliación</span>@endif
                @if($itemAFavor > 0)
                    <div class="saldo-info saldo-favor">✅ A favor: ${{ number_format($itemAFavor,0,',','.') }}</div>
                @endif
                @if($itemPendiente > 0)
                    <div class="saldo-info saldo-pendiente">⚠️ Pendiente: ${{ number_format($itemPendiente,0,',','.') }}</div>
                @endif
            </td>
            <td style="text-align:center;color:#64748b;white-space:nowrap;">
                {{ $item->fecha_ingreso ? $item->fecha_ingreso->format('d/m/Y') : '—' }}
            </td>
            <td style="text-align:center;font-weight:700;color:{{ $item->dias < 30 ? '#d97706' : '#0f172a' }}">
                {{ $item->es_afil ? '—' : $item->dias }}
            </td>
            {{-- EPS --}}
            <td class="num ent-cell">
                @if($item->v_eps > 0)
                    <span class="ent-nombre">EPS {{ $item->eps_nombre }}</span>
                    <span class="ent-valor">${{ number_format($item->v_eps,0,',','.') }}</span>
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
            {{-- ARL --}}
            <td class="num ent-cell">
                @if($item->v_arl > 0)
                    <span class="ent-nombre">ARL {{ $item->arl_nombre }} N{{ $item->n_arl }}</span>
                    <span class="ent-valor">${{ number_format($item->v_arl,0,',','.') }}</span>
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
            {{-- AFP --}}
            <td class="num ent-cell">
                @if($item->v_afp > 0)
                    <span class="ent-nombre">AFP {{ $item->afp_nombre }}</span>
                    <span class="ent-valor">${{ number_format($item->v_afp,0,',','.') }}</span>
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
            {{-- Caja --}}
            <td class="num ent-cell">
                @if($item->v_caja > 0)
                    <span class="ent-nombre">{{ $item->caja_nombre }}</span>
                    <span class="ent-valor">${{ number_format($item->v_caja,0,',','.') }}</span>
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
            {{-- Admon --}}
            <td class="num">
                @if($item->v_admon > 0)
                    <span class="ent-valor">${{ number_format($item->v_admon,0,',','.') }}</span>
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
            {{-- IVA --}}
            <td class="num">
                @if($item->v_iva > 0)
                    <span class="ent-valor">${{ number_format($item->v_iva,0,',','.') }}</span>
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
            {{-- Total --}}
            <td class="num" style="font-weight:800;color:#0f172a;">${{ number_format($item->v_total,0,',','.') }}</td>
            <td style="text-align:center;">
                <span class="estado-badge {{ $estadoClass }}">{{ $estadoLabel }}</span>
            </td>
        </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="padding:.45rem .35rem;color:#94a3b8;font-size:9px;text-align:right;">
                    TOTALES ({{ $items->count() }} trabajadores)
                </td>
                <td class="num">${{ number_format($totEps,  0,',','.') }}</td>
                <td class="num">${{ number_format($totArl,  0,',','.') }}</td>
                <td class="num">${{ number_format($totAfp,  0,',','.') }}</td>
                <td class="num">${{ number_format($totCaja, 0,',','.') }}</td>
                <td class="num">${{ number_format($totAdmon,0,',','.') }}</td>
                <td class="num">${{ number_format($totIva,  0,',','.') }}</td>
                <td class="num" style="font-size:1rem;">${{ number_format($totTotal,0,',','.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    </div>

    {{-- Resumen de saldos --}}
    @if($totalFavor > 0 || $totalPendiente > 0)
    <div style="display:flex;gap:1.5rem;margin:.7rem 0;flex-wrap:wrap;">
        @if($totalFavor > 0)
        <div style="background:#dcfce7;border-radius:8px;padding:.45rem .85rem;font-size:10px;">
            ✅ <strong>Total a favor:</strong>
            <span style="font-family:monospace;color:#15803d;font-weight:800;">${{ number_format($totalFavor,0,',','.') }}</span>
        </div>
        @endif
        @if($totalPendiente > 0)
        <div style="background:#fee2e2;border-radius:8px;padding:.45rem .85rem;font-size:10px;">
            ⚠️ <strong>Total pendiente:</strong>
            <span style="font-family:monospace;color:#dc2626;font-weight:800;">${{ number_format($totalPendiente,0,',','.') }}</span>
        </div>
        @endif
        <div style="background:#1e3a5f;border-radius:8px;padding:.45rem 1rem;font-size:11px;color:#fff;font-weight:800;">
            TOTAL A COBRAR:
            <span style="font-family:monospace;color:#34d399;">${{ number_format($totalGeneral,0,',','.') }}</span>
        </div>
    </div>
    @else
    <div style="text-align:right;margin:.6rem 0;">
        <span style="background:#1e3a5f;border-radius:8px;padding:.45rem 1rem;font-size:11px;color:#fff;font-weight:800;display:inline-block;">
            TOTAL A COBRAR: <span style="font-family:monospace;color:#34d399;">${{ number_format($totalGeneral,0,',','.') }}</span>
        </span>
    </div>
    @endif

    <div style="margin:.8rem 0 .6rem;line-height:1.6;font-size:10px;">
        Agradecemos la atención prestada,<br><br>
        Atentamente<br><br>
        <strong>{{ $aliado?->contacto ?? ($aliado?->nombre ?? '#¿Nombre?') }}</strong><br>
        <em>Tu asesor de Confianza</em>
    </div>

    {{-- Cuentas para consignar --}}
    @if($cuentasCobro->isNotEmpty())
    <div class="cuentas-cobro">
        <h4>📌 Cuentas para Consignar</h4>
        @foreach($cuentasCobro as $cuenta)
        <div class="cuenta-item">
            <span class="cuenta-banco">{{ $cuenta->banco }}</span>
            <span style="color:#64748b;font-size:9px;">{{ $cuenta->tipo_cuenta }}</span>
            <span class="cuenta-num">{{ $cuenta->numero_cuenta }}</span>
            @if($cuenta->nombre)<span style="color:#64748b;">— {{ $cuenta->nombre }}</span>@endif
            @if($cuenta->nit)<span style="color:#94a3b8;font-size:9px;">NIT: {{ $cuenta->nit }}</span>@endif
        </div>
        @endforeach
    </div>
    @else
    <div style="background:#fef9c3;border-radius:6px;padding:.45rem .7rem;margin-bottom:.7rem;font-size:9.5px;color:#854d0e;">
        ⚠️ No hay cuentas marcadas para cobro. Configure las cuentas en Configuración.
    </div>
    @endif

    <div class="nota-legal">
        <strong>NOTA:</strong> Los pagos deberán realizarse los primeros 5 días hábiles de cada mes.
        Los pagos efectuados fuera de estas fechas generan el no pago de licencias de maternidad e incapacidades
        DECRETO 047 DEL 2000 ART. 3 NUMERAL 2 DECRETO 1804/99 ART. 21.
        El plazo máximo para retiros de aportes de seguridad social deberá ser 05 días antes del mes.
        <strong>Cuenta a Consignar.</strong>
    </div>

    <div class="pie-doc">
        @if($aliado?->direccion)<span>Dir: {{ $aliado->direccion }}</span>@endif
        @if($aliado?->telefono || $aliado?->celular)
            <span>Teléfonos: {{ implode(' - ', array_filter([$aliado?->telefono, $aliado?->celular])) }}</span>
        @endif
        @if($aliado?->correo)<span>{{ $aliado->correo }}</span>@endif
    </div>

</div>
</body>
</html>
