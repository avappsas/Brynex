<?php

namespace App\Services;

use App\Models\Plano;
use App\Models\OperadorPlanillaTemplate;
use setasign\Fpdi\Fpdi;

class PlanillaFormularioService
{
    /**
     * Generar el PDF rellenando la plantilla asignada al operador de planilla.
     *
     * @param Plano $plano
     * @return string Contenido binario del PDF.
     */
    public function generar(Plano $plano): string
    {
        // 1. Intentar autodetectar el operador por el que se pagó la planilla
        $operadorPlanillaId = $this->detectarOperadorId($plano);

        // 2. Buscar si hay una plantilla configurada en base de datos para ese operador
        $template = null;
        if ($operadorPlanillaId) {
            $template = OperadorPlanillaTemplate::where('operador_planilla_id', $operadorPlanillaId)->first();
        }

        // Si no hay plantilla configurada en BD para este operador, usamos el código estático de SuAporte
        if (!$template || !$template->formulario_pdf) {
            return SuaportePdfService::generar($plano);
        }

        $rutaPdf = storage_path('app/formularios/planillas/' . $template->formulario_pdf);
        if (!file_exists($rutaPdf)) {
            // Fallback si el archivo en storage se borró
            return SuaportePdfService::generar($plano);
        }

        $campos = $template->formulario_campos ?? [];
        if (empty($campos)) {
            return file_get_contents($rutaPdf);
        }

        // 3. Obtener los datos del cotizante y del plano
        $datos = $this->ensamblarDatos($plano);

        // 4. Rellenar la plantilla PDF utilizando FPDI y FPDF
        return $this->rellenarPdf($rutaPdf, $campos, $datos);
    }

    /**
     * Autodetecta el operador de planilla del plano.
     */
    protected function detectarOperadorId(Plano $plano): ?int
    {
        // a. Intentar buscar en el registro de la API
        $apiPlanilla = \DB::table('operador_planillas_api')
            ->where('numero_planilla', $plano->numero_planilla)
            ->where('aliado_id', $plano->aliado_id)
            ->first();

        if ($apiPlanilla && $apiPlanilla->operador_planilla_id) {
            return (int)$apiPlanilla->operador_planilla_id;
        }

        // b. Buscar en el operador asignado al cliente del plano
        $cliente = \App\Models\Cliente::where('cedula', $plano->no_identifi)->first();
        if ($cliente && $cliente->operador_planilla_id) {
            return (int)$cliente->operador_planilla_id;
        }

        // c. Si no, retornar el operador Enlace/SuAporte por defecto
        $operadorDefault = \DB::table('operadores_planilla')
            ->where('codigo', 'ENLACE')
            ->first() ?? \DB::table('operadores_planilla')
            ->where('codigo', 'ARUS')
            ->first();

        return $operadorDefault ? (int)$operadorDefault->id : null;
    }

    /**
     * Ensambla todos los datos dinámicos del cotizante y aportante para rellenar la plantilla.
     */
    protected function ensamblarDatos(Plano $plano): array
    {
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
        
        $afiliadosCount = Plano::where('numero_planilla', $plano->numero_planilla)
            ->where('aliado_id', $plano->aliado_id)
            ->count();

        $fechaPago = $plano->fecha_pago 
            ? \Carbon\Carbon::parse($plano->fecha_pago)->format('Y-m-d H:i:s.0') 
            : '2026-07-03 14:03:12.0';

        return [
            // Aportante
            'aportante.razon_social'         => strtoupper($plano->razon_social),
            'aportante.nit'                  => 'NI ' . ($plano->razonSocial?->nit ?? '901918923'),
            'aportante.direccion'            => strtoupper($plano->razonSocial?->direccion ?? 'CR 39 #43 - 04'),
            'aportante.telefono'             => $plano->razonSocial?->telefono ?? '5555555',
            'aportante.afiliados'            => (string)max(1, $afiliadosCount),
            'aportante.representante'        => strtoupper($plano->razonSocial?->representante_legal ?? 'GARCIA VIDAL BRAYAN HUMBERTO'),
            'aportante.cedula_representante' => 'CC ' . ($plano->razonSocial?->representante_cedula ?? '1143944458'),

            // Metadatos
            'plano.fecha_creacion'          => now()->format('Y-m-d, h:i:s p. m.'),
            'plano.tipo_planilla'           => 'E',
            'plano.numero_planilla'         => $plano->numero_planilla,
            'plano.periodo_cotizacion'      => $perCot,
            'plano.periodo_servicio'        => $perSer,
            'plano.fecha_pago_completa'     => "PAGADA {$fechaPago}",

            // Afiliado
            'afiliado.tipo_doc'             => $plano->tipo_doc,
            'afiliado.cedula'               => $plano->no_identifi,
            'afiliado.tipo_doc_cedula'      => $plano->tipo_doc . ' ' . $plano->no_identifi,
            'afiliado.nombre_completo'      => strtoupper($plano->primer_ape . ' ' . $plano->segundo_ape . ' ' . $plano->primer_nombre . ' ' . $plano->segundo_nombre),
            'afiliado.exonerado'            => 'S',
            'afiliado.ciudad'               => '94001000 - 94',
            'afiliado.ubicacion_laboral'    => 'GUAINIA',
            'afiliado.tipo_cotizante'       => str_pad($c['tipoCotizante'], 2, '0', STR_PAD_LEFT),
            'afiliado.subtipo_cotizante'    => str_pad($c['subtipoCotizante'], 2, '0', STR_PAD_LEFT),

            // Aportes Detallados
            'aporte.novedad_ing' => !empty($plano->fecha_ing) ? 'X' : '',
            'aporte.novedad_ret' => !empty($plano->fecha_ret) ? 'X' : '',
            'aporte.dias_afp'    => $c['diasPension'],
            'aporte.dias_eps'    => $c['diasSalud'],
            'aporte.dias_arl'    => $c['diasArl'],
            'aporte.dias_ccf'    => $c['diasCcf'],
            'aporte.salario'     => '$ ' . number_format($c['ibcFull'], 0, ',', '.'),
            
            // Pensión
            'aporte.afp_codigo'  => $c['codAfpPila'],
            'aporte.afp_tarifa'  => number_format($c['tarifaAfpDecimal'] * 100, 0) . ' %',
            'aporte.afp_ibc'     => '$ ' . number_format($c['ibcAfp'], 0, ',', '.'),
            'aporte.afp_aporte'  => '$ ' . number_format($c['vAfp'], 0, ',', '.'),
            'aporte.afp_fsp'     => '$ 0',
            'aporte.afp_fsps'    => '$ 0',

            // Salud
            'aporte.eps_codigo'  => $c['codEpsPila'],
            'aporte.eps_tarifa'  => number_format(floatval($c['tarifaEpsStr']) * 100, 0) . ' %',
            'aporte.eps_ibc'     => '$ ' . number_format($c['ibcEps'], 0, ',', '.'),
            'aporte.eps_aporte'  => '$ ' . number_format($c['vEps'], 0, ',', '.'),
            'aporte.eps_upc'     => '$ 0',

            // Riesgos
            'aporte.arl_codigo'  => ($c['codArlPila'] ?: '14-11'),
            'aporte.arl_clase'   => $c['nivelRiesgo'],
            'aporte.arl_tarifa'  => number_format($c['tarifaArlDecimal'] * 100, 3, '.', '') . ' %',
            'aporte.arl_ibc'     => '$ ' . number_format($c['ibcArl'], 0, ',', '.'),
            'aporte.arl_aporte'  => '$ ' . number_format($c['vArl'], 0, ',', '.'),

            // Caja
            'aporte.ccf_codigo'  => ($c['codCcfPila'] == 'CCF68' ? 'CCF66' : $c['codCcfPila']),
            'aporte.ccf_tarifa'  => '4 %',
            'aporte.ccf_ibc'     => '$ ' . number_format($c['ibcCcf'], 0, ',', '.'),
            'aporte.ccf_aporte'  => '$ ' . number_format($c['vCcf'], 0, ',', '.'),

            // Parafiscales
            'aporte.sena_tarifa' => '0 %',
            'aporte.sena_aporte' => '$ 0',
            'aporte.icbf_tarifa' => '0 %',
            'aporte.icbf_aporte' => '$ 0',

            // Totales Administradoras
            'total.afp_nombre' => $nombreAfp,
            'total.eps_nombre' => $nombreEps,
            'total.arl_nombre' => 'ARL ' . $nombreArl,
            'total.ccf_nombre' => $nombreCaja,

            // Totales Valores
            'total.afp'   => '$ ' . number_format($c['vAfp'], 0, ',', '.'),
            'total.fsp'   => '$ 0',
            'total.fsps'  => '$ 0',
            'total.eps'   => '$ ' . number_format($c['vEps'], 0, ',', '.'),
            'total.arl'   => '$ ' . number_format($c['vArl'], 0, ',', '.'),
            'total.ccf'   => '$ ' . number_format($c['vCcf'], 0, ',', '.'),
            'total.sena'  => '$ 0',
            'total.icbf'  => '$ 0',
            'total.esap'  => '$ 0',
            'total.men'   => '$ 0',
            'total.final' => '$ ' . number_format($granTotal, 0, ',', '.'),
        ];
    }

    /**
     * Rellena las celdas de la plantilla PDF a través de FPDF y FPDI.
     */
    protected function rellenarPdf(string $rutaPdf, array $campos, array $datos): string
    {
        $pdf = new Fpdi('L', 'pt');
        $pdf->SetAutoPageBreak(false);

        $pdf->setSourceFile($rutaPdf);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);

        // Agregamos la página con las dimensiones exactas de la plantilla
        $pdf->AddPage('L', [$size['width'], $size['height']]);
        $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

        // Color de texto por defecto: Negro
        $pdf->SetTextColor(0, 0, 0);

        foreach ($campos as $c) {
            $llave = $c['dato'] ?? '';
            $valor = $datos[$llave] ?? ($c['label'] ?? '');

            // Si es un campo custom o vacío y no hay datos, usar el valor por defecto si existe
            if (empty($valor) && isset($c['value'])) {
                $valor = $c['value'];
            }

            // Coordenadas físicas del campo en la plantilla
            $x = floatval($c['x'] ?? 0);
            $y = floatval($c['y'] ?? 0);
            $w = floatval($c['w'] ?? 0);
            $h = floatval($c['h'] ?? 0);

            // Ajustar estilos y fuente
            $fontFamily = 'Arial';
            $fontStyle = '';
            if (!empty($c['bold'])) $fontStyle .= 'B';
            if (!empty($c['italic'])) $fontStyle .= 'I';

            $fontSize = floatval($c['font_size'] ?? 7.5);
            $pdf->SetFont($fontFamily, $fontStyle, $fontSize);

            // Determinar si debemos dibujar un rectángulo blanco antes para limpiar el original
            $limpiar = !empty($c['limpiar']) || !isset($c['limpiar']); // Limpiar por defecto si no se indica
            if ($limpiar && $w > 0 && $h > 0) {
                // Color de limpieza: blanco por defecto, o gris si se especifica en la configuración
                if (($c['color_fondo'] ?? '') === 'gris') {
                    $pdf->SetFillColor(204, 204, 204); // Gris de estado
                } else {
                    $pdf->SetFillColor(255, 255, 255); // Blanco
                }
                $pdf->Rect($x, $y, $w, $h, 'F');
            }

            // Escribir el nuevo texto
            // El Y de la línea de base es el Y del rectángulo más el alto del texto (para centrar verticalmente)
            $yBase = $y + ($h > 0 ? ($h * 0.8) : $fontSize);
            
            // Alineación de texto
            $align = $c['align'] ?? 'left';
            if ($align === 'right') {
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, $h, $valor, 0, 0, 'R');
            } elseif ($align === 'center') {
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, $h, $valor, 0, 0, 'C');
            } else {
                $pdf->Text($x + 2, $yBase, $valor);
            }
        }

        return $pdf->Output('S');
    }
}
