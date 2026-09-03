<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Cuenta de Cobro – {{ $empresa->empresa ?? '' }} – {{ $meses[$mes] }} {{ $anio }}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Arial', sans-serif; font-size: 11px; color: #222; background: #fff; }

/* Botones de acción — solo en pantalla */
.acciones-bar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 999;
    background: #1e3a5f; padding: .5rem 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.btn-ac {
    padding: .35rem .9rem; border-radius: 6px; border: none;
    cursor: pointer; font-size: .8rem; font-weight: 700; display: flex; align-items: center; gap: .3rem;
}
.btn-print  { background: #2563eb; color: #fff; }
.btn-detail { background: #047857; color: #fff; }
.btn-close  { background: rgba(255,255,255,.15); color: #fff; margin-left: auto; }
.btn-ac:hover { opacity: .88; }

/* Wrapper del documento */
.doc-wrap {
    max-width: 820px; margin: 0 auto; padding: 3.5rem 2rem 2rem;
}

/* Encabezado */
.doc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.2rem; }
.logo-area img { max-height: 90px; max-width: 200px; object-fit: contain; }
.logo-area-text { font-size: 1rem; font-weight: 800; color: #1e3a5f; }
.aliado-info { text-align: right; font-size: 10px; color: #444; line-height: 1.6; }
.aliado-info strong { font-size: 11px; color: #1e3a5f; }

/* Título central */
.doc-title {
    text-align: center; font-size: 1.2rem; font-weight: 900;
    letter-spacing: .12em; text-transform: uppercase;
    border-top: 2px solid #1e3a5f; border-bottom: 2px solid #1e3a5f;
    padding: .35rem 0; margin-bottom: 1.1rem; color: #1e3a5f;
}

/* Destinatario */
.destinatario { margin-bottom: .9rem; line-height: 1.7; }
.destinatario .empresa-dest { font-size: 1rem; font-weight: 800; color: #1e3a5f; text-transform: uppercase; }

/* Cuerpo del texto */
.cuerpo-texto { margin-bottom: .9rem; line-height: 1.6; }

/* Tabla principal */
table.tbl-cc { width: 100%; border-collapse: collapse; margin-bottom: .6rem; }
table.tbl-cc thead tr { background: #1e3a5f; color: #fff; }
table.tbl-cc th { padding: .42rem .45rem; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
table.tbl-cc td { padding: .38rem .45rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
table.tbl-cc tr:nth-child(even) td { background: #f8fafc; }
table.tbl-cc .num { text-align: right; font-family: monospace; }
.estado-badge {
    display: inline-block; padding: .1rem .4rem; border-radius: 12px; font-size: 9px; font-weight: 700;
}
.est-vigente  { background: #dcfce7; color: #15803d; }
.est-pre      { background: #fef9c3; color: #854d0e; }
.est-prestamo { background: #ede9fe; color: #6d28d9; }
.est-abono    { background: #fef3c7; color: #92400e; }
.est-retiro   { background: #fee2e2; color: #dc2626; }
.est-sin      { background: #f1f5f9; color: #64748b; }

/* Saldo info */
.saldo-info { font-size: 9.5px; margin-top: 2px; }
.saldo-favor    { color: #15803d; font-weight: 700; }
.saldo-pendiente{ color: #dc2626; font-weight: 700; }

/* Resumen totales */
.totales-bloque { text-align: right; margin-bottom: .9rem; }
.tot-row { display: flex; justify-content: flex-end; gap: .5rem; font-size: 11px; padding: .15rem 0; }
.tot-row.principal { font-size: 1rem; font-weight: 800; color: #1e3a5f; border-top: 2px solid #1e3a5f; padding-top: .4rem; margin-top: .2rem; }
.tot-label { min-width: 180px; text-align: left; }
.tot-val { min-width: 90px; text-align: right; font-family: monospace; }

/* Cuentas bancarias */
.cuentas-cobro { margin-bottom: .9rem; }
.cuentas-cobro h4 { font-size: 10.5px; font-weight: 800; color: #1e3a5f; margin-bottom: .3rem; text-transform: uppercase; letter-spacing: .05em; }
/* Cuentas bancarias — bloque destacado */
.cuenta-item {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem .8rem;
    align-items: center;
    padding: .65rem .9rem;
    border-left: 5px solid #2563eb;
    margin-bottom: .5rem;
    background: #eff6ff;
    border-radius: 0 8px 8px 0;
    border-top: 1px solid #bfdbfe;
    border-bottom: 1px solid #bfdbfe;
}
.cuenta-banco { font-weight: 900; font-size: 13px; color: #1e3a5f; min-width: 120px; }
.cuenta-num   { font-family: monospace; font-size: 13px; font-weight: 700; color: #1d4ed8; letter-spacing: .04em; }
.cuenta-tipo  { color: #475569; font-size: 10px; background:#dbeafe; padding:.1rem .4rem; border-radius:10px; font-weight:600; }

/* Firmas */
.firmas { display: flex; justify-content: space-between; margin: 1.4rem 0 1rem; }
.firma-bloque { text-align: center; }
.firma-linea { width: 160px; border-top: 1px solid #555; padding-top: .35rem; margin: 2rem auto 0; font-size: 10px; }

/* Nota legal */
.nota-legal {
    background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px;
    padding: .55rem .75rem; font-size: 9px; color: #7f1d1d; line-height: 1.5;
    margin-bottom: .7rem;
}
.nota-legal strong { font-size: 9.5px; }

/* Pie */
.pie-doc {
    border-top: 1px solid #cbd5e1; padding-top: .45rem;
    font-size: 9px; color: #64748b; display: flex; justify-content: space-between; flex-wrap: wrap; gap: .3rem;
}

/* Impresión */
@media print {
    .acciones-bar { display: none !important; }
    .doc-wrap { padding-top: .5rem; }
    body { font-size: 10px; }
}
</style>
</head>
<body>

{{-- Barra de acciones --}}
<div class="acciones-bar">
    <button class="btn-ac btn-print" onclick="window.print()">🖨 Imprimir / Descargar PDF</button>
    @if($empresa)
    <button class="btn-ac" onclick="MCA.abrir({{ $empresa->id }})" style="background:#0284c7;color:#fff;">⚙️ Cobros Adicionales</button>
    @endif
    <form id="frmDetallada" class="frm-cc" method="POST" action="{{ route('admin.facturacion.cuenta_cobro.preview') }}" target="_blank" style="display:inline;">
        @include('admin.facturacion._cc_params', ['tipoDestino' => 'detallada', 'moraDestino' => ($incluirMora ?? true) ? '1' : '0'])
        <button type="submit" class="btn-ac btn-detail">📋 Ver Detallada</button>
    </form>
    @include('admin.facturacion._cc_boton_mora')
    <button class="btn-ac btn-close" onclick="window.close()">✕ Cerrar</button>
    <span style="color:#94a3b8;font-size:.75rem;margin-left:.5rem;">
        Cuenta de Cobro — {{ $empresa->empresa ?? '' }} — {{ $meses[$mes] }} {{ $anio }}
    </span>
</div>

<div class="doc-wrap">

    {{-- Encabezado --}}
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

    {{-- Fecha actual --}}
    <div style="margin-bottom: 1.2rem; font-size: .8rem; font-weight: 500; color: #1e293b;">
        {{ $aliado->ciudad ?? 'Bogotá D.C.' }}, {{ now()->translatedFormat('d \d\e F \d\e Y') }}
    </div>

    {{-- Destinatario --}}
    <div class="destinatario">
        <div>Señores:</div>
        <div class="empresa-dest">{{ $empresa->empresa ?? '' }}</div>
        {{-- Datos de entrega: los usa el mensajero que lleva el documento --}}
        @php
            $destTel  = collect([$empresa?->celular, $empresa?->telefono])->filter()->implode(' / ');
            $destCont = collect([$empresa?->contacto, $destTel])->filter()->implode(' — ');
        @endphp
        @if($destCont)<div>{{ $destCont }}</div>@endif
        @if($empresa?->direccion)<div>{{ $empresa->direccion }}</div>@endif
    </div>

    <div style="margin-bottom:.6rem;"><em>Cordial Saludo,</em></div>

    <div class="cuerpo-texto">
        Por medio de la presente se le envía la cuenta de cobro del pago correspondiente al periodo de servicio
        de seguridad social del mes de <strong>{{ $meses[$mes] }}</strong> del año <strong>{{ $anio }}</strong>.
    </div>

    {{-- Tabla --}}
    <table class="tbl-cc">
        <thead>
            <tr>
                <th style="text-align:center;width:28px;">No</th>
                <th>Razón Social</th>
                <th style="text-align:center;">Documento</th>
                <th>Nombre</th>
                <th style="text-align:center;">Ingreso / Plan</th>
                <th class="num">Total</th>
                <th style="text-align:center;">Estado</th>
            </tr>
        </thead>
        <tbody>
        @php $no = 1; $totIva = 0; $ivaSobreAdmon = false; $ivaSobreAfil = false; @endphp
        @foreach($items as $item)
        @php
            $totIva += (int)($item->v_iva ?? 0);
            // Qué base gravó el IVA, para nombrarla en el renglón
            if ((int)($item->v_iva ?? 0) > 0) {
                if ($item->es_afil) { $ivaSobreAfil = true; } else { $ivaSobreAdmon = true; }
            }
            // saldo_proximo: positivo = a favor, negativo = pendiente
            $spItem = (int)($item->saldo_proximo ?? 0);
            $itemAFavor    = $spItem > 0 ? $spItem : 0;
            $itemPendiente = $spItem < 0 ? abs($spItem) : 0;
            // Sin factura del período manda el estado del CONTRATO: la cuenta de
            // cobro entera está pendiente por definición, así que repetir
            // "Pendiente" en cada fila no dice nada. Si ya hay factura, lo que
            // informa es cómo quedó el pago.
            if (($item->estado ?? 'sin_factura') === 'sin_factura') {
                [$estadoClass, $estadoLabel] = match($item->estado_contrato ?? 'vigente') {
                    'retirado'         => ['est-retiro', 'Retiro'],
                    'retiro_pendiente' => ['est-abono',  'Retiro Pd.'],
                    default            => ['est-vigente', 'Vigente'],
                };
            } else {
                $estadoClass = match($item->estado) {
                    'pagada'      => 'est-vigente',
                    'prestamo'    => 'est-prestamo',
                    'pre_factura' => 'est-pre',
                    'abono'       => 'est-abono',
                    default       => 'est-sin',
                };
                $estadoLabel = match($item->estado) {
                    'pagada'      => 'Pagada',
                    'prestamo'    => 'Préstamo',
                    'pre_factura' => 'Pre-Fac',
                    'abono'       => 'Abono',
                    default       => 'Pendiente',
                };
            }
        @endphp
        <tr>
            <td style="text-align:center;color:#64748b;">{{ $no++ }}</td>
            <td>
                <div style="font-weight:800;font-size:10.5px;color:#1e3a5f;text-transform:uppercase;">{{ $item->razon_social }}</div>
                @if($item->es_afil)<span style="font-size:8.5px;color:#7c3aed;font-weight:700;margin-top:2px;display:inline-block;">📌 Afiliación</span>@endif
            </td>


            <td style="text-align:center;font-family:monospace;">{{ $item->cedula }}</td>
            <td>
                <div style="font-weight:600;white-space:nowrap;">{{ $item->nombre }}</div>
                @if($itemAFavor > 0)
                    <div class="saldo-info saldo-favor">✅ Saldo a favor: ${{ number_format($itemAFavor,0,',','.') }}</div>
                @endif
                @if($itemPendiente > 0)
                    <div class="saldo-info saldo-pendiente">⚠️ Pendiente meses anteriores: ${{ number_format($itemPendiente,0,',','.') }}</div>
                @endif
                @if(isset($item->v_mora) && $item->v_mora > 0)
                    <div class="saldo-info" style="color:#b45309;font-weight:700;">⚠️ Mora: ${{ number_format($item->v_mora,0,',','.') }}</div>
                @endif
            </td>
            <td style="text-align:center;color:#64748b;white-space:nowrap;">
                <div>{{ $item->fecha_ingreso ? $item->fecha_ingreso->format('d/m/Y') : '—' }}</div>
                <div style="font-size:9.5px;color:#d97706;font-weight:700;margin-top:2px;">Plan: {{ $item->modalidad }}</div>
            </td>
            <td class="num" style="font-weight:700;">${{ number_format($item->v_total - (int)($item->v_iva ?? 0),0,',','.') }}</td>
            <td style="text-align:center;">
                <span class="estado-badge {{ $estadoClass }}">{{ $estadoLabel }}</span>
            </td>
        </tr>
        @endforeach
        @php
            $ivaBaseTexto = ($ivaSobreAdmon && $ivaSobreAfil) ? 'administración y afiliación'
                : ($ivaSobreAfil ? 'la afiliación' : 'administración');
        @endphp
        {{-- IVA como renglón único, no prorrateado por trabajador --}}
        @if($totIva > 0)
        <tr>
            <td style="text-align:center;color:#94a3b8;font-size:10px;">—</td>
            <td colspan="4">
                <div style="font-weight:700;font-size:10.5px;color:#1e3a5f;">IVA ({{ rtrim(rtrim(number_format($ivaPct ?? 19, 2, ',', '.'), '0'), ',') }}%)</div>
                <span style="font-size:8.5px;color:#64748b;font-weight:600;">Sobre el valor de {{ $ivaBaseTexto }}</span>
            </td>
            <td class="num" style="font-weight:700;">${{ number_format($totIva, 0, ',', '.') }}</td>
            <td style="text-align:center;">
                <span class="estado-badge est-vigente" style="background:#ede9fe;color:#6d28d9;">IVA</span>
            </td>
        </tr>
        @endif
        @if(isset($cobrosAdicionalesCC) && $cobrosAdicionalesCC->isNotEmpty())
        <tr>
            <td colspan="7" style="background:#f8fafc;font-weight:800;font-size:9.5px;color:#1e3a5f;padding:.45rem .6rem;text-transform:uppercase;border-top:1.5px solid #cbd5e1;border-bottom:1.5px solid #cbd5e1;letter-spacing:.05em;">
                💼 Cobros Adicionales de la Empresa
            </td>
        </tr>
        @foreach($cobrosAdicionalesCC as $cobro)
        <tr>
            <td style="text-align:center;color:#94a3b8;font-size:10px;">—</td>
            <td colspan="4">
                <div style="font-weight:700;font-size:10.5px;color:#2563eb;">{{ $cobro->descripcion }}</div>
                <span style="font-size:8.5px;color:#64748b;font-weight:600;">
                    Concepto Adicional ({{ $cobro->tipo === 'recurrente' ? 'Recurrente' : 'Única vez' }})
                </span>
            </td>
            <td class="num" style="font-weight:700;">${{ number_format($cobro->valor, 0, ',', '.') }}</td>
            <td style="text-align:center;">
                <span class="estado-badge est-vigente" style="background:#e0f2fe;color:#0369a1;">Activo</span>
            </td>
        </tr>
        @endforeach
        @endif
        </tbody>
        <tfoot>
            @if(($saldoNetoCC ?? 0) != 0)
            @php $subtotalCC = (int) $items->sum('v_total') + (int) ($totalCobrosAdicionales ?? 0); @endphp
            <tr>
                <td colspan="5" style="text-align:right;padding:.45rem;font-weight:700;font-size:10px;color:#64748b;">
                    SUBTOTAL DEL MES:
                </td>
                <td class="num" style="font-weight:700;">${{ number_format($subtotalCC,0,',','.') }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="5" style="text-align:right;padding:.45rem;font-weight:700;font-size:10px;color:{{ $saldoNetoCC > 0 ? '#dc2626' : '#15803d' }};">
                    {{ $saldoNetoCC > 0 ? '(+) SALDO PENDIENTE DE MESES ANTERIORES:' : '(−) SALDO A FAVOR DE MESES ANTERIORES:' }}
                </td>
                <td class="num" style="font-weight:700;color:{{ $saldoNetoCC > 0 ? '#dc2626' : '#15803d' }}">
                    {{ $saldoNetoCC > 0 ? '' : '−' }}${{ number_format(abs($saldoNetoCC),0,',','.') }}
                </td>
                <td></td>
            </tr>
            @endif
            <tr style="background:#1e3a5f;">
                <td colspan="5" style="text-align:right;padding:.5rem;color:#94a3b8;font-weight:700;font-size:11px;">
                    TOTAL
                </td>
                <td class="num" style="font-weight:800;font-size:1.05rem;color:#34d399;">
                    ${{ number_format($totalGeneral, 0, ',', '.') }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Pie de gracia --}}
    <div style="margin:1rem 0 .7rem;line-height:1.6;">
        Agradecemos la atención prestada,<br>
        <br>
        Atentamente<br>
        <br>
        <strong>{{ auth()->user()?->nombre ?? ($aliado?->contacto ?? ($aliado?->nombre ?? 'Asesor')) }}</strong><br>
        <em>Tu asesor de Confianza</em>
    </div>

    {{-- Cuentas para consignar --}}
    @if($cuentasCobro->isNotEmpty())
    <div class="cuentas-cobro">
        <h4 style="font-size:12px;font-weight:900;color:#1e3a5f;margin-bottom:.55rem;text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid #2563eb;padding-bottom:.3rem;">🏦 Cuentas para Consignar</h4>
        @foreach($cuentasCobro as $cuenta)
        <div class="cuenta-item">
            <span class="cuenta-banco">{{ $cuenta->banco }}</span>
            <span class="cuenta-tipo">{{ $cuenta->tipo_cuenta }}</span>
            <span class="cuenta-num">{{ $cuenta->numero_cuenta }}</span>
            @if($cuenta->nombre)<span style="color:#64748b;">— {{ $cuenta->nombre }}</span>@endif
            @if($cuenta->nit)<span style="color:#94a3b8;font-size:9.5px;">NIT: {{ $cuenta->nit }}</span>@endif
        </div>
        @endforeach
    </div>
    @else
    <div style="background:#fef9c3;border-radius:6px;padding:.5rem .75rem;margin-bottom:.8rem;font-size:10px;color:#854d0e;">
        ⚠️ No hay cuentas bancarias marcadas para cobro. Configure las cuentas en la sección de Configuración.
    </div>
    @endif

    {{-- Nota legal --}}
    <div class="nota-legal">
        <strong>NOTA:</strong> Los pagos deberán realizarse los primeros 5 días hábiles de cada mes.
        Los pagos efectuados fuera de estas fechas generan el no pago de licencias de maternidad e incapacidades
        DECRETO 047 DEL 2000 ART. 3 NUMERAL 2 DECRETO 1804/99 ART. 21.
        El plazo máximo para retiros de aportes de seguridad social deberá ser 05 días antes del mes.
    </div>

    {{-- Pie del documento --}}
    <div class="pie-doc">
        @if($aliado?->direccion)<span>Dir: {{ $aliado->direccion }}</span>@endif
        @if($aliado?->telefono || $aliado?->celular)
            <span>Teléfonos: {{ implode(' - ', array_filter([$aliado?->telefono, $aliado?->celular])) }}</span>
        @endif
        @if($aliado?->correo)<span>{{ $aliado->correo }}</span>@endif
    </div>

</div>

<script>
// Autocompletar los contratos seleccionados en el form de vista detallada
document.addEventListener('DOMContentLoaded', function() {
    // Obtener contratos del sessionStorage si fueron guardados por la ventana padre
    if (window.opener && window.opener.__ccContratos) {
        const ids = window.opener.__ccContratos;
        // Todos los botones que recargan la cuenta necesitan los mismos contratos
        document.querySelectorAll('form.frm-cc').forEach(form => {
            form.querySelectorAll('input[name="contratos[]"]').forEach(e => e.remove());
            ids.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'contratos[]'; inp.value = id;
                form.appendChild(inp);
            });
        });
    }
});
</script>
@include('admin.facturacion._modal_cobros_adicionales')
</body>
</html>
