<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Individual SuAporte - {{ $plano->no_identifi }}</title>
    <style>
        @page {
            size: landscape;
            margin: 0.3cm 0.4cm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.2pt;
            color: #000000;
            line-height: 1.25;
            background-color: #ffffff;
        }
        
        /* Contenedor principal con borde general gris claro */
        .report-border {
            border: 0.5px solid #888888;
            padding: 5px;
            width: 100%;
            box-sizing: border-box;
            background-color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            table-layout: fixed;
        }
        
        td, th {
            border: 0.5px solid #888888;
            padding: 1.5px 3px;
            font-size: 6.5pt;
            vertical-align: middle;
            box-sizing: border-box;
        }
        
        /* Cabecera Principal */
        .header-table {
            border: 0.5px solid #888888;
            margin-bottom: 5px;
        }
        .header-table td {
            border: 0.5px solid #888888;
            padding: 2px 4px;
        }
        .logo-box {
            width: 25%;
            padding: 2px !important;
            text-align: left;
            border-right: none !important;
        }
        .logo-img {
            height: 25px; /* Tamaño del logo compacto */
            vertical-align: middle;
        }
        .title-box {
            width: 45%;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            border-left: none !important;
            border-right: none !important;
        }
        .meta-box {
            width: 30%;
            font-size: 6.5pt;
            line-height: 1.3;
            padding: 0 !important;
            border-left: none !important;
        }
        .meta-inner-table {
            width: 100%;
            margin: 0;
            table-layout: fixed;
        }
        .meta-inner-table td {
            border: none !important;
            border-bottom: 0.5px solid #e0e0e0 !important;
            padding: 1.5px 4px !important;
        }
        .meta-inner-table tr:last-child td {
            border-bottom: none !important;
        }

        /* Barra de estado */
        .status-bar {
            background-color: #d9d9d9;
            border: 0.5px solid #888888;
            font-weight: bold;
            font-size: 8.5pt;
            text-align: center;
            padding: 3px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        /* Títulos de secciones */
        .section-title {
            font-weight: bold;
            font-size: 7.8pt;
            margin-top: 5px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        /* Tablas de Datos (Secciones I y II) */
        .data-table td {
            font-size: 6.5pt;
        }
        .data-table .label {
            background-color: #eaeaea;
            font-weight: bold;
            color: #000000;
        }
        .data-table .value {
            background-color: #ffffff;
        }

        /* Sección III - Aportes Detallados */
        .detail-table {
            border: 0.5px solid #888888;
        }
        .detail-table th {
            background-color: #eaeaea;
            border: 0.5px solid #888888;
            font-weight: bold;
            font-size: 5pt; /* Fuente súper pequeña para evitar amontonamiento */
            padding: 1.5px 0.5px;
            text-align: center;
            line-height: 1.1;
        }
        .detail-table td {
            border: 0.5px solid #888888;
            padding: 1.5px 1px;
            font-size: 5.5pt; /* Fuente de datos compacta */
            text-align: center;
            background-color: #ffffff;
            line-height: 1.1;
        }
        
        /* Columnas de novedades delgadas */
        .detail-table .nov-col {
            width: 1.1%;
        }
        .detail-table .nov-title {
            font-size: 4.2pt;
            padding: 0;
            height: 26px;
        }
        .detail-table .nov-text-vertical {
            writing-mode: vertical-lr;
            transform: rotate(270deg);
            white-space: nowrap;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
        .detail-table .align-left {
            text-align: left;
            padding-left: 2px;
        }
        .detail-table .align-right {
            text-align: right;
            padding-right: 2px;
        }

        /* Sección IV - Totales */
        .totals-table th {
            background-color: #eaeaea;
            border: 0.5px solid #888888;
            font-weight: bold;
            font-size: 6.2pt;
            padding: 2px;
            text-align: center;
        }
        .totals-table td {
            border: 0.5px solid #888888;
            padding: 3px;
            font-size: 7.2pt;
            text-align: center;
            background-color: #ffffff;
        }
        .totals-table .total-title-cell {
            background-color: #eaeaea;
            font-weight: bold;
            font-size: 7.5pt;
        }

        /* Pie de Página */
        .footer-table {
            width: 100%;
            border-top: 0.5px solid #888888;
            margin-top: 8px;
            padding-top: 4px;
            table-layout: fixed;
        }
        .footer-table td {
            border: none;
            padding: 1px;
            font-size: 6pt;
            color: #000000;
            vertical-align: bottom;
        }
        .footer-logo-img {
            height: 22px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

@php
    // Invocamos el calculador oficial de Brynex
    $c = \App\Services\PilaCotizanteCalculator::calcular($plano);
    
    // Nombres de administradoras
    $nombreAfp = $plano->nombre_afp ?: 'PORVENIR';
    $nombreEps = $plano->nombre_eps ?: 'NUEVA EPS';
    $nombreArl = $plano->nombre_arl ?: 'ARL SURA';
    $nombreCaja = $plano->nombre_caja ?: ($c['sinCaja'] ? 'COMCAJA' : 'COMCAJA');
    
    // Períodos servicio y cotización
    $perCot = $plano->anio_plano . str_pad($plano->mes_plano, 2, '0', STR_PAD_LEFT);
    
    // Periodo servicio (vencido o actual según modalidad)
    $mesServicio = $plano->mes_plano > 1 ? $plano->mes_plano - 1 : 12;
    $anioServicio = $plano->mes_plano > 1 ? $plano->anio_plano : $plano->anio_plano - 1;
    if ($plano->paga_mes_actual) {
        $mesServicio = $plano->mes_plano;
        $anioServicio = $plano->anio_plano;
    }
    $perSer = $anioServicio . str_pad($mesServicio, 2, '0', STR_PAD_LEFT);

    // Cargar logotipos reales extraídos del PDF en Base64
    $logoEnlacePath = public_path('img/extracted_2_img0.jpg');
    $logoExpertosPath = public_path('img/extracted_0_img2.png');
    
    $logoEnlaceBase64 = '';
    if (file_exists($logoEnlacePath)) {
        $logoEnlaceBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoEnlacePath));
    }
    
    $logoExpertosBase64 = '';
    if (file_exists($logoExpertosPath)) {
        $logoExpertosBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoExpertosPath));
    }
@endphp

<div class="report-border">

    <!-- I. CABECERA PRINCIPAL (Logo, Reporte y Metadatos en la misma fila) -->
    <table class="header-table">
        <tr>
            <!-- LOGOTIPO ORIGINAL -->
            <td class="logo-box">
                @if($logoEnlaceBase64)
                    <img class="logo-img" src="{{ $logoEnlaceBase64 }}" alt="enlace operativo">
                @else
                    <span style="font-size: 14pt; font-weight: bold; color: #4a4a4a;">enlace <span style="color: #0056b3; font-weight: 300;">operativo</span></span>
                @endif
            </td>
            <!-- TÍTULO DEL REPORTE -->
            <td class="title-box">
                SuAporte | REPORTE INDIVIDUAL
            </td>
            <!-- METADATOS DEL REPORTE -->
            <td class="meta-box">
                <table class="meta-inner-table">
                    <tr>
                        <td style="font-weight: bold; width: 45%;">Fecha creación reporte:</td>
                        <td style="text-align: right;">{{ now()->format('Y-m-d, h:i:s p. m.') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Tipo Planilla:</td>
                        <td style="text-align: right;">E</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Número Planilla:</td>
                        <td style="text-align: right;">{{ $plano->numero_planilla }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Periodo Cotización:</td>
                        <td style="text-align: right;">{{ $perCot }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Periodo Servicio:</td>
                        <td style="text-align: right;">{{ $perSer }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- BARRA DE ESTADO DE PAGO -->
    <div class="status-bar">
        PAGADA {{ $plano->fecha_pago ? \Carbon\Carbon::parse($plano->fecha_pago)->format('Y-m-d H:i:s.0') : '2026-07-03 14:03:12.0' }}
    </div>

    <!-- I. DATOS DEL APORTANTE -->
    <div class="section-title">I. DATOS DEL APORTANTE</div>
    <table class="data-table">
        <tr>
            <td class="label" style="width: 15%;">Razón Social</td>
            <td class="value" colspan="7">{{ $plano->razon_social }}</td>
        </tr>
        <tr>
            <td class="label" style="width: 15%;">Documento</td>
            <td class="value" style="width: 35%;" colspan="3">NI {{ $plano->razonSocial?->nit ?? '901918923' }}</td>
            <td class="label" style="width: 15%;">Dirección</td>
            <td class="value" style="width: 35%;" colspan="3">CR 39 #43 - 04</td>
        </tr>
        <tr>
            <td class="label">Tipo de Empresa</td>
            <td class="value" colspan="3">EMPLEADOR</td>
            <td class="label">Teléfono</td>
            <td class="value" colspan="3">5555555</td>
        </tr>
        <tr>
            <td class="label" style="width: 15%;">Tipo Persona</td>
            <td class="value" style="width: 18%;">JURÍDICA</td>
            <td class="label" style="width: 15%;">Forma Presentación</td>
            <td class="value" style="width: 18%;">SUCURSAL</td>
            <td class="label" style="width: 15%;">Total Afiliados</td>
            <td class="value" colspan="3">3</td>
        </tr>
        <tr>
            <td class="label">Ciudad</td>
            <td class="value" colspan="3">CALI</td>
            <td class="label">Departamento</td>
            <td class="value" colspan="3">VALLE DEL CAUCA</td>
        </tr>
        <tr>
            <td class="label">Representante Legal</td>
            <td class="value" colspan="3">GARCIA VIDAL BRAYAN HUMBERTO</td>
            <td class="label">Identificación</td>
            <td class="value" colspan="3">CC 1143944458</td>
        </tr>
    </table>

    <!-- II. DATOS DEL AFILIADO -->
    <div class="section-title">II. DATOS DEL AFILIADO</div>
    <table class="data-table">
        <tr>
            <td class="label" style="width: 12%;">Documento</td>
            <td class="label" style="width: 8%;">Residente</td>
            <td class="label" style="width: 8%;">Exonerado</td>
            <td class="label" style="width: 4%;">S</td>
            <td class="label" style="width: 34%;">Apellidos y Nombres</td>
            <td class="label" style="width: 12%;">Código Ciudad - Depto</td>
            <td class="label" style="width: 10%;">Centro de Trabajo</td>
            <td class="label" style="width: 12%;">Ubicación Laboral</td>
        </tr>
        <tr>
            <td class="value">{{ $plano->tipo_doc }} {{ $plano->no_identifi }}</td>
            <td class="value"></td>
            <td class="value"></td>
            <td class="value">S</td>
            <td class="value" style="font-weight: bold;">{{ $plano->primer_ape }} {{ $plano->segundo_ape }} {{ $plano->primer_nombre }} {{ $plano->segundo_nombre }}</td>
            <td class="value">94001000 - 94</td>
            <td class="value"></td>
            <td class="value">GUAINIA</td>
        </tr>
        <tr>
            <td class="label">Tipo Cotizante</td>
            <td class="value" colspan="7">
                <table style="width: 100%; border: none; margin: 0; table-layout: fixed;">
                    <tr style="border: none;">
                        <td style="border: none; padding: 0; width: 50%;">{{ str_pad($c['tipoCotizante'], 2, '0', STR_PAD_LEFT) }}</td>
                        <td style="border: none; padding: 0; width: 50%; border-left: 0.5px solid #7f7f7f; padding-left: 6px;">{{ str_pad($c['subtipoCotizante'], 2, '0', STR_PAD_LEFT) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- III. APORTE POR CADA UNA DE LAS ADMINISTRADORAS ASOCIADAS AL AFILIADO -->
    <div class="section-title">III. APORTE POR CADA UNA DE LAS ADMINISTRADORAS ASOCIADAS AL AFILIADO:</div>
    <table class="detail-table">
        <thead>
            <tr>
                <th colspan="19" style="width: 22%;">Novedades</th>
                <th style="width: 4.2%;" rowspan="2">Extranjero</th>
                <th style="width: 4.2%;" rowspan="2">Tipo salario</th>
                <th style="width: 6.8%;" rowspan="2">Salario</th>
                <th colspan="7" style="width: 21%;">Pensión</th>
                <th colspan="6" style="width: 17%;">Salud</th>
                <th colspan="5" style="width: 15%;">Riesgos</th>
                <th colspan="4" style="width: 11%;">Caja</th>
                <th colspan="4" style="width: 10%;">Parafiscales</th>
            </tr>
            <tr class="sub-header">
                <!-- Novedades de 1.1% cada una -->
                <th class="nov-col nov-title"><span class="nov-text-vertical">ING</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">RET</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">TDE</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">TAE</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">TDP</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">TAP</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">VSP</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">VST</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">SLN</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">IGE</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">VAC</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">AVP</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">VCT</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">IRP</span></th>
                <th class="nov-col nov-title"><span class="nov-text-vertical">LMA</span></th>
                <!-- Días de cada subsistema al lado de novedades -->
                <th style="width: 1.3%; font-size: 4.8pt; font-weight: normal; line-height: 1;">Dias AFP</th>
                <th style="width: 1.3%; font-size: 4.8pt; font-weight: normal; line-height: 1;">Dias EPS</th>
                <th style="width: 1.3%; font-size: 4.8pt; font-weight: normal; line-height: 1;">Dias ARL</th>
                <th style="width: 1.3%; font-size: 4.8pt; font-weight: normal; line-height: 1;">Dias CCF</th>
                <!-- Pensión -->
                <th style="width: 3.8%;">Código AFP</th>
                <th style="width: 2.2%;">Cód. Tras.</th>
                <th style="width: 2.2%;">Tarifa</th>
                <th style="width: 4.8%;">IBC</th>
                <th style="width: 4.8%;">Aporte</th>
                <th style="width: 1.6%;">FSP</th>
                <th style="width: 1.6%;">FSPS</th>
                <!-- Salud -->
                <th style="width: 3.8%;">Código EPS</th>
                <th style="width: 2.2%;">Cód. Tras.</th>
                <th style="width: 2.2%;">Tarifa</th>
                <th style="width: 4.8%;">IBC EPS</th>
                <th style="width: 4.8%;">Aporte</th>
                <th style="width: 1.6%;">UPC</th>
                <!-- Riesgos -->
                <th style="width: 3.8%;">Código ARL</th>
                <th style="width: 2.0%;">Clase</th>
                <th style="width: 2.5%;">Tarifa</th>
                <th style="width: 4.8%;">IBC ARL</th>
                <th style="width: 4.8%;">Aporte</th>
                <!-- Caja -->
                <th style="width: 3.8%;">Código CCF</th>
                <th style="width: 2.2%;">Tarifa</th>
                <th style="width: 4.8%;">IBC CCF</th>
                <th style="width: 4.8%;">Aporte</th>
                <!-- Parafiscales -->
                <th style="width: 2.2%;">SENA %</th>
                <th style="width: 2.8%;">SENA $</th>
                <th style="width: 2.2%;">ICBF %</th>
                <th style="width: 2.8%;">ICBF $</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <!-- Novedades -->
                <td>{{ !empty($plano->fecha_ing) ? 'X' : '' }}</td>
                <td>{{ !empty($plano->fecha_ret) ? 'X' : '' }}</td>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <!-- Días de cada subsistema -->
                <td>{{ $c['diasPension'] }}</td>
                <td>{{ $c['diasSalud'] }}</td>
                <td>{{ $c['diasArl'] }}</td>
                <td>{{ $c['diasCcf'] }}</td>
                
                <td></td>
                <td>F</td>
                <td class="align-right">$ {{ number_format($c['ibcFull'], 0, ',', '.') }}</td>
                
                <!-- Pensión -->
                <td>{{ $c['codAfpPila'] }}</td>
                <td></td> 
                <td>{{ number_format($c['tarifaAfpDecimal'] * 100, 0) }}%</td>
                <td class="align-right">$ {{ number_format($c['ibcAfp'], 0, ',', '.') }}</td>
                <td class="align-right">$ {{ number_format($c['vAfp'], 0, ',', '.') }}</td>
                <td class="align-right">$ 0</td>
                <td class="align-right">$ 0</td>
                
                <!-- Salud -->
                <td>{{ $c['codEpsPila'] }}</td>
                <td></td> 
                <td>{{ number_format(floatval($c['tarifaEpsStr']) * 100, 0) }}%</td>
                <td class="align-right">$ {{ number_format($c['ibcEps'], 0, ',', '.') }}</td>
                <td class="align-right">$ {{ number_format($c['vEps'], 0, ',', '.') }}</td>
                <td class="align-right">$ 0</td>
                
                <!-- Riesgos -->
                <td>{{ $c['codArlPila'] ?: '14-11' }}</td>
                <td>{{ $c['nivelRiesgo'] }}</td>
                <td>{{ number_format($c['tarifaArlDecimal'] * 100, 3, '.', '') }}%</td>
                <td class="align-right">$ {{ number_format($c['ibcArl'], 0, ',', '.') }}</td>
                <td class="align-right">$ {{ number_format($c['vArl'], 0, ',', '.') }}</td>
                
                <!-- Caja -->
                <td>{{ $c['codCcfPila'] == 'CCF68' ? 'CCF66' : $c['codCcfPila'] }}</td>
                <td>4%</td>
                <td class="align-right">$ {{ number_format($c['ibcCcf'], 0, ',', '.') }}</td>
                <td class="align-right">$ {{ number_format($c['vCcf'], 0, ',', '.') }}</td>
                
                <!-- Parafiscales -->
                <td>0%</td>
                <td class="align-right">$ 0</td>
                <td>0%</td>
                <td class="align-right">$ 0</td>
            </tr>
        </tbody>
    </table>

    <!-- IV. TOTALES HORIZONTAL -->
    <div class="section-title">IV. TOTALES</div>
    <table class="totals-table">
        <thead>
            <tr>
                <th>Total Aportes Pensión</th>
                <th>Total Aportes FSP</th>
                <th>Total Aportes FSPS</th>
                <th>Total Aportes Salud</th>
                <th>Total Aportes Riesgos</th>
                <th>Total Aportes Cajas</th>
                <th>Total Aportes SENA</th>
                <th>Total Aportes ICBF</th>
                <th>Total Aportes ESAP</th>
                <th>Total Aportes MEN</th>
                <th class="total-title-cell" style="width: 12%;">Total Final</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold;">{{ $nombreAfp }}</td>
                <td>FSP SOLIDARIDAD</td>
                <td>FSP SUBSISTENCIA</td>
                <td style="font-weight: bold;">{{ $nombreEps }}</td>
                <td style="font-weight: bold;">ARL {{ $nombreArl }}</td>
                <td style="font-weight: bold;">{{ $nombreCaja }}</td>
                <td>SENA</td>
                <td>ICBF</td>
                <td>ESAP</td>
                <td>MEN</td>
                <td class="total-title-cell" rowspan="2" style="font-size: 9.5pt; font-weight: bold; vertical-align: middle;">
                    @php
                        $granTotal = $c['vAfp'] + $c['vEps'] + $c['vArl'] + $c['vCcf'];
                    @endphp
                    $ {{ number_format($granTotal, 0, ',', '.') }}
                </td>
            </tr>
            <tr style="font-weight: bold;">
                <td>$ {{ number_format($c['vAfp'], 0, ',', '.') }}</td>
                <td style="font-weight: normal; color: #666;">$ 0</td>
                <td style="font-weight: normal; color: #666;">$ 0</td>
                <td>$ {{ number_format($c['vEps'], 0, ',', '.') }}</td>
                <td>$ {{ number_format($c['vArl'], 0, ',', '.') }}</td>
                <td>$ {{ number_format($c['vCcf'], 0, ',', '.') }}</td>
                <td style="font-weight: normal; color: #666;">$ 0</td>
                <td style="font-weight: normal; color: #666;">$ 0</td>
                <td style="font-weight: normal; color: #666;">$ 0</td>
                <td style="font-weight: normal; color: #666;">$ 0</td>
            </tr>
        </tbody>
    </table>

    <!-- PIE DE PÁGINA -->
    <table class="footer-table">
        <tr>
            <td style="width: 80%; text-align: center; font-size: 6.2pt; line-height: 1.3;">
                Enlace Operativo – Línea Expertos en PILA: Barranquilla (605) 385 24 44 · Bogotá (601) 742 44 88 · Bucaramanga (607) 697 87 27 · Cali (602) 485 94 44 · Cartagena (605) 693 77 27 · Pereira (606) 340 13 27 ·<br>
                Manizales (606) 892 80 27 · Medellín (604) 604 27 27 · Desde otras ciudades: 01 8000 51 99 77 · WhatsApp: 3164416952
            </td>
            <td style="width: 20%; text-align: right; vertical-align: bottom;">
                @if($logoExpertosBase64)
                    <img class="footer-logo-img" src="{{ $logoExpertosBase64 }}" alt="expertos en pila">
                @else
                    <div style="background-color: #0056b3; color: #ffffff; font-weight: bold; padding: 2px 5px; border-radius: 10px; font-size: 7.5pt; display: inline-block;">EXPERTOS en PILA</div>
                @endif
                <div style="font-size: 6pt; margin-top: 2px;">Página 1 de 1</div>
            </td>
        </tr>
    </table>

</div>

</body>
</html>
