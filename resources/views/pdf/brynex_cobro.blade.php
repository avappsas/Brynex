<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuenta de Cobro BryNex</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .container {
            padding: 20px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .logo-section {
            width: 50%;
            vertical-align: top;
        }
        .logo-title {
            font-size: 24px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0 0 4px 0;
            letter-spacing: 0.5px;
        }
        .logo-subtitle {
            font-size: 9px;
            color: #64748b;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .meta-section {
            width: 50%;
            text-align: right;
            vertical-align: top;
        }
        .meta-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 5px 0;
        }
        .meta-value {
            font-size: 10px;
            color: #475569;
            margin: 2px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .info-cell {
            padding: 10px 15px;
            vertical-align: top;
            width: 50%;
        }
        .info-label {
            font-size: 8px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 11px;
            color: #0f172a;
            font-weight: 500;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .items-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #1e3a8a;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
            color: #334155;
        }
        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .summary-table {
            width: 40%;
            margin-left: 60%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .summary-row td {
            padding: 6px 10px;
            font-size: 10px;
            color: #475569;
        }
        .summary-row.total td {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            border-top: 2px solid #cbd5e1;
            padding-top: 10px;
        }
        .footer {
            margin-top: 50px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            text-align: center;
            color: #94a3b8;
            font-size: 9px;
        }
        .payment-info {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 25px;
        }
        .payment-info-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 4px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-pendiente { background-color: #fee2e2; color: #991b1b; }
        .badge-parcial { background-color: #fef3c7; color: #92400e; }
        .badge-pagado { background-color: #dcfce7; color: #166534; }
    </style>
</head>
<body>

<div class="container">
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-section">
                <div class="logo-title">BryNex</div>
                <div class="logo-subtitle">Asesores en Seguridad Social</div>
            </td>
            <td class="meta-section">
                <div class="meta-title">CUENTA DE COBRO</div>
                <div class="meta-value"><strong>Número:</strong> CB-{{ $cobro->anio }}{{ str_pad($cobro->mes, 2, '0', STR_PAD_LEFT) }}-{{ str_pad($cobro->id, 4, '0', STR_PAD_LEFT) }}</div>
                <div class="meta-value"><strong>Fecha de Emisión:</strong> {{ \Carbon\Carbon::parse($cobro->fecha_cierre)->format('d/m/Y') }}</div>
                <div class="meta-value"><strong>Período Facturado:</strong> {{ $cobro->periodo }}</div>
                <div class="meta-value">
                    <strong>Estado:</strong>
                    <span class="badge badge-{{ $cobro->estado }}">
                        {{ $cobro->estado === 'pagado' ? 'Pagado' : ($cobro->estado === 'parcial' ? 'Pago Parcial' : 'Pendiente') }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Datos del Aliado y de Brynex --}}
    <table class="info-table">
        <tr>
            <td class="info-cell" style="border-right: 1px solid #e2e8f0;">
                <div class="info-label">De:</div>
                <div class="info-value" style="font-weight: bold;">BRYNEX S.A.S.</div>
                <div class="info-value">NIT: 901.234.567-8</div>
                <div class="info-value">Contacto: administracion@brynex.co</div>
                <div class="info-value">Teléfono: +57 300 123 4567</div>
            </td>
            <td class="info-cell">
                <div class="info-label">Para el Aliado:</div>
                <div class="info-value" style="font-weight: bold;">{{ $cobro->aliado->nombre }}</div>
                <div class="info-value">NIT/Cédula: {{ $cobro->aliado->nit }}</div>
                <div class="info-value">Contacto: {{ $cobro->aliado->contacto }}</div>
                <div class="info-value">WhatsApp: {{ $cobro->aliado->whatsapp ?? '—' }}</div>
            </td>
        </tr>
    </table>

    {{-- Tabla de Detalle --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Descripción del Servicio</th>
                <th style="width: 15%; text-align: center;">Cantidad</th>
                <th style="width: 20%; text-align: right;">Tarifa / Mínimo</th>
                <th style="width: 20%; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cobro->detalles as $det)
                <tr>
                    <td>
                        <strong>{{ $det->modulo->nombre }}</strong><br>
                        <span style="font-size: 8px; color: #64748b;">{{ $det->descripcion }}</span>
                    </td>
                    <td style="text-align: center;">{{ number_format($det->cant_unidades, 0, ',', '.') }}</td>
                    <td style="text-align: right;">
                        @if($det->tarifa_unidad > 0)
                            ${{ number_format($det->tarifa_unidad, 0, ',', '.') }} / u
                        @elseif($det->tarifa_minima_aplicada > 0)
                            ${{ number_format($det->tarifa_minima_aplicada, 0, ',', '.') }} (Mín.)
                        @else
                            Personalizado
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: 500;">${{ number_format($det->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Tabla de Resumen --}}
    <table class="summary-table">
        <tr class="summary-row">
            <td style="text-align: left;">Subtotal Período:</td>
            <td style="text-align: right; font-weight: 500;">${{ number_format($cobro->total_cobrado, 0, ',', '.') }}</td>
        </tr>
        <tr class="summary-row">
            <td style="text-align: left;">Saldo Anterior Pendiente:</td>
            <td style="text-align: right; color: #ef4444;">${{ number_format($saldoAnterior, 0, ',', '.') }}</td>
        </tr>
        <tr class="summary-row">
            <td style="text-align: left;">Total Recaudado (Abonos):</td>
            <td style="text-align: right; color: #10b981;">-${{ number_format($cobro->total_pagado, 0, ',', '.') }}</td>
        </tr>
        <tr class="summary-row total">
            <td style="text-align: left;">Total a Pagar:</td>
            <td style="text-align: right;">${{ number_format(max(0, $cobro->total_cobrado + $saldoAnterior - $cobro->total_pagado), 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Instrucciones de Pago --}}
    <div class="payment-info">
        <div class="payment-info-title">ℹ️ Instrucciones de Pago</div>
        <div style="font-size: 9px; color: #1e3a8a; line-height: 1.4;">
            Por favor realice su transferencia a la cuenta bancaria de BryNex S.A.S:<br>
            <strong>Banco Bancolombia</strong> — Cuenta de Ahorros Nro. <strong>123-456789-01</strong>.<br>
            Una vez realizado el pago, por favor envíe el soporte al correo <strong>pagos@brynex.co</strong> o repórtelo a su asesor a través de WhatsApp.
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Esta cuenta de cobro representa una relación comercial y no sustituye a la facturación electrónica cuando ésta sea requerida legalmente.<br>
        <strong>BryNex © {{ date('Y') }} — Todos los derechos reservados.</strong>
    </div>
</div>

</body>
</html>
