<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización - {{ $prospecto->nombre_completo }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #0056b3; padding-bottom: 10px; }
        .logo { max-width: 200px; max-height: 80px; }
        .title { color: #0056b3; font-size: 24px; margin: 10px 0 5px 0; }
        .subtitle { font-size: 14px; color: #666; }
        .section { margin-bottom: 25px; }
        .section-title { background-color: #f4f4f4; padding: 8px; font-weight: bold; border-left: 4px solid #0056b3; margin-bottom: 10px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f9f9f9; width: 40%; }
        .table-price th, .table-price td { padding: 10px; }
        .table-price th { background-color: #e9ecef; }
        .table-price .total-row { font-weight: bold; font-size: 16px; background-color: #d1e7dd; color: #0f5132; }
        .text-right { text-align: right !important; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 11px; color: #777; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        @if($aliado && $aliado->logo)
            <img src="{{ public_path('storage/' . $aliado->logo) }}" alt="Logo" class="logo">
        @else
            <h2 style="color:#0056b3; margin:0;">{{ $aliado->nombre ?? 'BryNex' }}</h2>
        @endif
        <div class="title">COTIZACIÓN DE AFILIACIÓN A SEGURIDAD SOCIAL</div>
        <div class="subtitle">Fecha: {{ date('d/m/Y') }} | Vigencia: 15 días</div>
    </div>

    <div class="section">
        <div class="section-title">1. Datos del Cliente</div>
        <table class="table">
            <tr>
                <th>Nombre del Cliente:</th>
                <td>{{ $prospecto->nombre_completo ?: 'Por definir' }}</td>
            </tr>
            <tr>
                <th>Identificación:</th>
                <td>{{ $prospecto->tipo_doc ?? 'CC' }} {{ $prospecto->cedula }}</td>
            </tr>
            <tr>
                <th>Celular de Contacto:</th>
                <td>{{ $prospecto->celular ?: 'No registrado' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. Detalles del Plan Seleccionado</div>
        <table class="table">
            <tr>
                <th>Modalidad:</th>
                <td>{{ $prospecto->modalidad->tipo_modalidad ?? 'No especificada' }}</td>
            </tr>
            <tr>
                <th>Plan de Servicios:</th>
                <td>{{ $prospecto->plan->nombre ?? 'No especificado' }}</td>
            </tr>
            <tr>
                <th>Ingreso Base Cotización (IBC):</th>
                <td>$ {{ number_format($prospecto->salario_base ?? 1300000, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">3. Resumen de Aportes Mensuales</div>
        <table class="table table-price">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-right">Valor Mensual</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Salud (EPS)</td>
                    <td class="text-right">$ {{ number_format($cotizacionCalc['eps'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Pensión (AFP)</td>
                    <td class="text-right">$ {{ number_format($cotizacionCalc['pen'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Riesgos Laborales (ARL)</td>
                    <td class="text-right">$ {{ number_format($cotizacionCalc['arl'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Caja de Compensación Familiar</td>
                    <td class="text-right">$ {{ number_format($cotizacionCalc['caja'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Honorarios Administrativos (Inc. IVA si aplica)</td>
                    <td class="text-right">$ {{ number_format(($cotizacionCalc['admon'] ?? 0) + ($cotizacionCalc['iva'] ?? 0), 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL A PAGAR MENSUALMENTE</td>
                    <td class="text-right">$ {{ number_format($cotizacionCalc['total'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <p style="font-size: 12px; color: #555; text-align: justify;">
            <strong>Nota importante:</strong> Los valores presentados en esta cotización son calculados según la normatividad vigente y el Salario Mínimo Legal Vigente. Si el salario mínimo cambia o las tarifas por ley se modifican, esta cotización quedará sujeta a dichos ajustes.
        </p>
    </div>

    <div class="footer">
        {{ $aliado->nombre ?? 'BryNex' }} | Generado por el Sistema de Gestión Empresarial
    </div>

</body>
</html>
