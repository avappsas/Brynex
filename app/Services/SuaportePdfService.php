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

        // 3. Los datos salen del mismo lugar que la plantilla configurable
        //    (PlanillaFormularioService): el cálculo con los datos del TXT, la
        //    ficha de la empresa que reporta el operador y la hora de pago que
        //    él imprime. Antes este servicio calculaba por su cuenta con el plano
        //    pelado y rellenaba lo que faltaba con los datos de Brygar —NIT,
        //    dirección, teléfono, representante— y con una fecha de pago fija.
        //    Lo que no se sabe queda en blanco.
        $d = (new PlanillaFormularioService())->ensamblarDatos($plano);

        // 4. Escribir los valores. La plantilla es el reporte de ARUS en blanco:
        //    ya trae las etiquetas y las celdas, así que solo se escriben los
        //    valores, a la derecha de su etiqueta o centrados en su columna
        //    (coordenadas medidas sobre la plantilla con `pdftotext -bbox`).
        //    Antes la plantilla era un certificado real con datos de Brygar y
        //    de un afiliado, tapados con rectángulos blancos: seguían dentro de
        //    cada PDF y se podían copiar.
        $pdf->SetTextColor(0, 0, 0);

        $escribir = function (float $x, float $y, ?string $texto) use ($pdf) {
            $pdf->Text($x, $y, self::latin1($texto));
        };
        $centrar = function (float $centro, float $y, ?string $texto) use ($pdf) {
            $texto = self::latin1($texto);
            $pdf->Text($centro - $pdf->GetStringWidth($texto) / 2, $y, $texto);
        };

        // --- CABECERA ---
        $pdf->SetFont('Arial', '', 7);
        $escribir(245, 46.3, $d['plano.fecha_creacion']);
        $escribir(392, 46.3, $d['plano.tipo_planilla']);
        $escribir(492, 46.3, (string) $d['plano.numero_planilla']);
        $escribir(403, 62.3, $d['plano.periodo_cotizacion']);
        $escribir(573, 62.3, $d['plano.periodo_servicio']);

        // --- BARRA ESTADO PAGO --- (sin pago registrado no se afirma ninguno)
        $pdf->SetFont('Arial', '', 9);
        $centrar($size['width'] / 2, 106.5, trim($d['plano.fecha_pago_estado'] . ' ' . $d['plano.fecha_pago_completa']));

        // --- SECCIÓN I ---
        $pdf->SetFont('Arial', '', 7.5);
        $escribir(119, 141.2, $d['aportante.razon_social']);
        $escribir(119, 154.2, $d['aportante.nit']);
        $escribir(494, 154.2, $d['aportante.direccion']);
        $escribir(119, 167.2, $d['aportante.tipo_aportante']);
        $escribir(494, 167.2, $d['aportante.telefono']);
        $escribir(119, 180.2, $d['aportante.tipo_persona']);
        $escribir(494, 180.2, $d['aportante.forma_presentacion']);
        $escribir(669, 180.2, (string) $d['aportante.afiliados']);
        $escribir(119, 193.2, $d['aportante.ciudad']);
        $escribir(494, 193.2, $d['aportante.departamento']);
        $escribir(119, 206.2, $d['aportante.representante']);
        $escribir(494, 206.2, $d['aportante.cedula_representante']);

        // --- SECCIÓN II ---
        $escribir(67, 239.5, $d['afiliado.tipo_doc_cedula']);
        $escribir(241, 249.5, $d['afiliado.exonerado']);
        $escribir(70, 258.5, $d['afiliado.tipo_cotizante']);
        $escribir(103, 258.5, $d['afiliado.subtipo_cotizante']);
        $pdf->SetFont('Arial', 'B', 7.5);
        $escribir(254, 257.9, $d['afiliado.nombre_completo']);
        $pdf->SetFont('Arial', '', 7.5);
        $escribir(520, 257.9, $d['afiliado.ciudad']);
        $escribir(680, 257.9, $d['afiliado.ubicacion_laboral']);

        // --- SECCIÓN III (fila de aportes) ---
        $yFila = 325.5;

        // Novedades y días van en columnas de 6 pt: letra más chica.
        $pdf->SetFont('Arial', '', 4.5);
        foreach ([
            10.5 => 'aporte.novedad_ing', 16.5 => 'aporte.novedad_ret', 100.5 => 'aporte.novedad_irp',
            106.5 => 'aporte.dias_afp', 112.5 => 'aporte.dias_eps', 118.5 => 'aporte.dias_arl', 124.5 => 'aporte.dias_ccf',
        ] as $centro => $campo) {
            $valor = (string) $d[$campo];
            $centrar($centro, $yFila, $campo === 'aporte.novedad_irp' && $valor === '0' ? '' : $valor);
        }

        $pdf->SetFont('Arial', '', 4.8);
        foreach ([
            166.8 => 'aporte.tipo_salario', 192.0 => 'aporte.salario',
            217.0 => 'aporte.afp_codigo', 249.5 => 'aporte.afp_tarifa', 272.0 => 'aporte.afp_ibc',
            297.0 => 'aporte.afp_aporte', 317.0 => 'aporte.afp_fsp', 337.0 => 'aporte.afp_fsps',
            357.0 => 'aporte.eps_codigo', 397.0 => 'aporte.eps_tarifa', 422.0 => 'aporte.eps_ibc',
            452.0 => 'aporte.eps_aporte', 477.0 => 'aporte.eps_upc',
            497.0 => 'aporte.arl_codigo', 517.0 => 'aporte.arl_clase', 537.0 => 'aporte.arl_tarifa',
            562.0 => 'aporte.arl_ibc', 592.0 => 'aporte.arl_aporte',
            617.0 => 'aporte.ccf_codigo', 637.0 => 'aporte.ccf_tarifa', 666.0 => 'aporte.ccf_ibc', 689.5 => 'aporte.ccf_aporte',
            712.0 => 'aporte.sena_tarifa', 732.0 => 'aporte.sena_aporte', 752.0 => 'aporte.icbf_tarifa', 772.0 => 'aporte.icbf_aporte',
        ] as $centro => $campo) {
            $centrar($centro, $yFila, (string) $d[$campo]);
        }

        // --- SECCIÓN IV (totales) ---
        $pdf->SetFont('Arial', '', 6);
        foreach ([
            48.5 => 'afp', 124.5 => 'fsp', 194.5 => 'fsps', 264.5 => 'eps', 334.5 => 'arl',
            404.5 => 'ccf', 474.0 => 'sena', 544.0 => 'icbf', 609.5 => 'esap', 669.5 => 'men',
        ] as $centro => $entidad) {
            $centrar($centro, 375.5, $d["total.{$entidad}_nombre"]);
            $centrar($centro, 396.0, $d["total.{$entidad}"]);
        }

        $pdf->SetFont('Arial', 'B', 8.5);
        $centrar(741, 396.0, $d['total.final']);

        return $pdf->Output('S');
    }

    /** FPDF solo escribe Latin-1: sin esto las tildes y las eñes salen como basura. */
    private static function latin1(?string $texto): string
    {
        return mb_convert_encoding((string) $texto, 'ISO-8859-1', 'UTF-8');
    }
}
