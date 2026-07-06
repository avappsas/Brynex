<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use App\Models\Plano;

class SuaportePdfService
{
    /**
     * Generar el PDF del certificado Suaporte rellenando la plantilla original.
     *
     * @param Plano $plano
     * @return string Contenido binario del PDF generado.
     */
    public static function generar(Plano $plano): string
    {
        // 1. Instanciar FPDI en formato Landscape (L) y puntos (pt)
        $pdf = new Fpdi('L', 'pt');
        $pdf->SetAutoPageBreak(false);

        // 2. Cargar plantilla original
        $templatePath = resource_path('pdf/certificado_suaporte_template.pdf');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("No se encontró la plantilla PDF original en: {$templatePath}");
        }

        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);

        // Agregar página con las dimensiones físicas exactas de la plantilla (797 x 612 pt)
        $pdf->AddPage('L', [$size['width'], $size['height']]);
        
        // Renderizar la plantilla original especificando ancho/alto exactos para evitar escalas
        $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

        // 3. Obtener los datos calculados de Brynex
        $c = PilaCotizanteCalculator::calcular($plano);

        $nombreAfp = $plano->nombre_afp ?: 'PORVENIR';
        $nombreEps = $plano->nombre_eps ?: 'NUEVA EPS';
        $nombreArl = $plano->nombre_arl ?: 'ARL SURA';
        $nombreCaja = $plano->nombre_caja ?: ($c['sinCaja'] ? 'COMCAJA' : 'COMCAJA');

        $perCot = $plano->anio_plano . str_pad($plano->mes_plano, 2, '0', STR_PAD_LEFT);

        $mesServicio = $plano->mes_plano > 1 ? $plano->mes_plano - 1 : 12;
        $anioServicio = $plano->mes_plano > 1 ? $plano->anio_plano : $plano->anio_plano - 1;
        if ($plano->tipo_modalidad_id == 11) {
            $mesServicio = $plano->mes_plano;
            $anioServicio = $plano->anio_plano;
        }
        $perSer = $anioServicio . str_pad($mesServicio, 2, '0', STR_PAD_LEFT);

        $granTotal = $c['vAfp'] + $c['vEps'] + $c['vArl'] + $c['vCcf'];

        // 4. Dibujar rectángulos blancos de limpieza sobre las coordenadas exactas de los textos antiguos
        $pdf->SetFillColor(255, 255, 255);

        // Cabecera (Metadatos a la derecha)
        $pdf->Rect(244, 38, 540, 30, 'F'); // Limpia toda la zona de textos de metadatos de la cabecera

        // Barra de estado de pago (Color gris de fondo original #cccccc / RGB: 204, 204, 204)
        $pdf->SetFillColor(204, 204, 204);
        $pdf->Rect(350, 122, 250, 12, 'F');
        $pdf->SetFillColor(255, 255, 255); // Reset a blanco

        // Sección I (Datos del Aportante - celdas de valor blancas)
        $pdf->Rect(116, 132, 672, 10, 'F'); // Razón Social (Y=140.40)
        $pdf->Rect(116, 145, 264, 10, 'F'); // Documento / NIT (Y=153.40)
        $pdf->Rect(490, 145, 298, 10, 'F'); // Dirección (Y=153.40)
        $pdf->Rect(490, 158, 298, 10, 'F'); // Teléfono (Y=166.40)
        $pdf->Rect(599, 171, 190, 10, 'F'); // Total Afiliados (Y=179.40)
        $pdf->Rect(116, 197, 264, 10, 'F'); // Representante Legal (Y=205.40)
        $pdf->Rect(490, 197, 298, 10, 'F'); // Identificación (Y=205.40)

        // Sección II (Datos del Afiliado - celdas de valor blancas)
        $pdf->Rect(12, 230, 110, 11, 'F');  // Documento (Y=238.90)
        $pdf->Rect(240, 230, 14, 11, 'F');  // Exonerado (Y=238.90)
        $pdf->Rect(254, 249, 254, 11, 'F'); // Apellidos y nombres (Y=257.90)
        $pdf->Rect(518, 249, 90, 11, 'F');  // Código Ciudad (Y=257.90)
        $pdf->Rect(680, 249, 100, 11, 'F'); // Ubicación laboral (Y=257.90)
        $pdf->Rect(12, 243, 110, 11, 'F');  // Tipo Cotizante / Subtipo (Y=251.90)

        // Sección III (Aporte detallado - limpiar toda la fila de datos original en Y=325.45)
        $pdf->Rect(8, 319, 780, 12, 'F');

        // Sección IV (Totales)
        $pdf->Rect(8, 370, 680, 12, 'F');   // Administradoras (Fila 1)
        $pdf->Rect(8, 390, 680, 12, 'F');   // Valores (Fila 2)
        $pdf->Rect(700, 390, 85, 14, 'F');  // Total final

        // 5. Escribir los nuevos datos reales sobre el PDF con precisión absoluta
        $pdf->SetTextColor(0, 0, 0);

        // --- CABECERA ---
        $pdf->SetFont('Arial', '', 7);
        $pdf->Text(246.56, 45.54, 'Fecha creación reporte:');
        $pdf->Text(346.56, 45.54, now()->format('Y-m-d, h:i:s p. m.'));
        $pdf->Text(425.00, 45.54, 'Tipo Planilla:');
        $pdf->Text(490.00, 45.54, 'E');
        $pdf->Text(545.00, 45.54, 'Número Planilla:');
        $pdf->Text(680.00, 45.54, $plano->numero_planilla);
        $pdf->Text(425.00, 61.54, 'Periodo Cotización:');
        $pdf->Text(506.00, 61.54, $perCot);
        $pdf->Text(620.00, 61.54, 'Periodo Servicio:');
        $pdf->Text(721.00, 61.54, $perSer);

        // --- BARRA ESTADO PAGO ---
        $pdf->SetFont('Arial', 'B', 8.5);
        $fechaPago = $plano->fecha_pago ? \Carbon\Carbon::parse($plano->fecha_pago)->format('Y-m-d H:i:s.0') : '2026-07-03 14:03:12.0';
        $pdf->Text(390, 131, "PAGADA {$fechaPago}");

        // --- SECCIÓN I ---
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Text(117, 140.40, $plano->razon_social);
        $pdf->Text(117, 153.40, 'NI ' . ($plano->razonSocial?->nit ?? '901918923'));
        $pdf->Text(491, 153.40, $plano->razonSocial?->direccion ?? 'CR 39 #43 - 04');
        $pdf->Text(491, 166.40, $plano->razonSocial?->telefono ?? '5555555');
        
        $afiliadosCount = Plano::where('numero_planilla', $plano->numero_planilla)
            ->where('aliado_id', $plano->aliado_id)
            ->count();
        $pdf->Text(600, 179.40, (string)max(1, $afiliadosCount));
        
        $pdf->Text(117, 205.40, $plano->razonSocial?->representante_legal ?? 'GARCIA VIDAL BRAYAN HUMBERTO');
        $pdf->Text(491, 205.40, 'CC ' . ($plano->razonSocial?->representante_cedula ?? '1143944458'));

        // --- SECCIÓN II ---
        $pdf->Text(65.00, 238.90, $plano->tipo_doc . ' ' . $plano->no_identifi);
        $pdf->Text(241, 238.90, 'S');
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Text(254, 257.90, $plano->primer_ape . ' ' . $plano->segundo_ape . ' ' . $plano->primer_nombre . ' ' . $plano->segundo_nombre);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Text(520, 257.90, '94001000 - 94');
        $pdf->Text(680, 257.90, 'GUAINIA');
        
        // Tipo / Subtipo Cotizante
        $pdf->Text(12, 251.90, str_pad($c['tipoCotizante'], 2, '0', STR_PAD_LEFT));
        $pdf->Text(50, 251.90, str_pad($c['subtipoCotizante'], 2, '0', STR_PAD_LEFT));

        // --- SECCIÓN III (Fila de Aportes - Y=325.45 pt) ---
        $pdf->SetFont('Arial', '', 5.5);
        $yRow = 325.45;

        // Novedades
        $pdf->Text(12, $yRow, !empty($plano->fecha_ing) ? 'X' : '');
        $pdf->Text(18, $yRow, !empty($plano->fecha_ret) ? 'X' : '');

        // Días de cada subsistema
        $pdf->Text(103.78, $yRow, $c['diasPension']);
        $pdf->Text(109.78, $yRow, $c['diasSalud']);
        $pdf->Text(115.78, $yRow, $c['diasArl']);
        $pdf->Text(121.78, $yRow, $c['diasCcf']);

        $pdf->Text(161.28, $yRow, 'F');
        $pdf->Text(181.44, $yRow, '$ ' . number_format($c['ibcFull'], 0, ',', '.'));

        // Pensión
        $pdf->Text(210.33, $yRow, $c['codAfpPila']);
        $pdf->Text(244.94, $yRow, number_format($c['tarifaAfpDecimal'] * 100, 0) . ' %');
        $pdf->Text(261.44, $yRow, '$ ' . number_format($c['ibcAfp'], 0, ',', '.'));
        $pdf->Text(288.10, $yRow, '$ ' . number_format($c['vAfp'], 0, ',', '.'));
        $pdf->Text(314.22, $yRow, '$ 0');
        $pdf->Text(334.22, $yRow, '$ 0');

        // Salud
        $pdf->Text(349.66, $yRow, $c['codEpsPila']);
        $pdf->Text(393.55, $yRow, number_format(floatval($c['tarifaEpsStr']) * 100, 0) . ' %');
        $pdf->Text(411.44, $yRow, '$ ' . number_format($c['ibcEps'], 0, ',', '.'));
        $pdf->Text(444.22, $yRow, '$ ' . number_format($c['vEps'], 0, ',', '.'));
        $pdf->Text(474.22, $yRow, '$ 0');

        // Riesgos ARL
        $pdf->Text(491.89, $yRow, ($c['codArlPila'] ?: '14-11'));
        $pdf->Text(515.39, $yRow, $c['nivelRiesgo']);
        $pdf->Text(529.16, $yRow, number_format($c['tarifaArlDecimal'] * 100, 3, '.', '') . ' %');
        $pdf->Text(550.94, $yRow, '$ ' . number_format($c['ibcArl'], 0, ',', '.'));
        $pdf->Text(583.72, $yRow, '$ ' . number_format($c['vArl'], 0, ',', '.'));

        // Caja
        $pdf->Text(610.67, $yRow, ($c['codCcfPila'] == 'CCF68' ? 'CCF66' : $c['codCcfPila']));
        $pdf->Text(633.55, $yRow, '4 %');
        $pdf->Text(657.00, $yRow, '$ ' . number_format($c['ibcCcf'], 0, ',', '.'));
        $pdf->Text(684.50, $yRow, '$ ' . number_format($c['vCcf'], 0, ',', '.'));

        // Parafiscales
        $pdf->Text(708.55, $yRow, '0 %');
        $pdf->Text(729.22, $yRow, '$ 0');
        $pdf->Text(748.55, $yRow, '0 %');
        $pdf->Text(769.22, $yRow, '$ 0');

        // --- SECCIÓN IV (Totales) ---
        $pdf->SetFont('Arial', '', 6);
        $yAdmin = 376.18;
        $yVal = 396.08;

        // Administradoras
        $pdf->Text(32.33, $yAdmin, $nombreAfp);
        $pdf->Text(97.49, $yAdmin, 'FSP SOLIDARIDAD');
        $pdf->Text(165.83, $yAdmin, 'FSP SUBSISTENCIA');
        $pdf->Text(246.83, $yAdmin, $nombreEps);
        $pdf->Text(319.00, $yAdmin, 'ARL ' . $nombreArl);
        $pdf->Text(389.33, $yAdmin, $nombreCaja);
        $pdf->Text(465.83, $yAdmin, 'SENA');
        $pdf->Text(537.17, $yAdmin, 'ICBF');
        $pdf->Text(601.50, $yAdmin, 'ESAP');
        $pdf->Text(662.83, $yAdmin, 'MEN');

        // Valores
        $pdf->Text(34.66, $yVal, '$ ' . number_format($c['vAfp'], 0, ',', '.'));
        $pdf->Text(119.83, $yVal, '$ 0');
        $pdf->Text(189.83, $yVal, '$ 0');
        $pdf->Text(252.32, $yVal, '$ ' . number_format($c['vEps'], 0, ',', '.'));
        $pdf->Text(322.32, $yVal, '$ ' . number_format($c['vArl'], 0, ',', '.'));
        $pdf->Text(396.49, $yVal, '$ ' . number_format($c['vCcf'], 0, ',', '.'));
        $pdf->Text(469.33, $yVal, '$ 0');
        $pdf->Text(539.33, $yVal, '$ 0');
        $pdf->Text(604.83, $yVal, '$ 0');
        $pdf->Text(664.83, $yVal, '$ 0');

        // Total Final
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Text(727.16, $yVal, '$ ' . number_format($granTotal, 0, ',', '.'));

        return $pdf->Output('S');
    }
}
