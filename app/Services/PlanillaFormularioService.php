<?php

namespace App\Services;

use App\Models\Plano;
use App\Models\OperadorPlanillaTemplate;
use setasign\Fpdi\Fpdi;

class PlanillaFormularioService
{
    /**
     * Ensambla los datos del plano y genera el PDF rellenado.
     * @param Plano $plano
     * @param int|null $forceOperadorId Si se provee, ignora la autodetección y fuerza este operador.
     * @return string Contenido binario del PDF.
     */
    public function generar(Plano $plano, ?int $forceOperadorId = null): string
    {
        // 1. Intentar autodetectar el operador por el que se pagó la planilla (o usar el forzado)
        $operadorPlanillaId = $forceOperadorId ?? $this->detectarOperadorId($plano);

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
            // Autocopia en tiempo real (en local o producción) para prevenir fallbacks al código estático viejo
            $sourcePdf = resource_path('pdf/certificado_suaporte_template.pdf');
            if (file_exists($sourcePdf)) {
                $dir = dirname($rutaPdf);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                copy($sourcePdf, $rutaPdf);
            } else {
                return SuaportePdfService::generar($plano);
            }
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
    public function ensamblarDatos(Plano $plano): array
    {
        $c = PilaCotizanteCalculator::calcular($plano);

        $nombreAfp = $plano->nombre_afp ?: 'PORVENIR';
        $nombreEps = $plano->nombre_eps ?: 'NUEVA EPS';
        $nombreArl = $plano->nombre_arl ?: 'ARL SURA';

        $esIndependiente = (bool)($plano->razonSocial?->es_independiente ?? false);
        $sinCajaCcf = ($c['codCcfPila'] == 'CCF68');

        $nombreCaja = $plano->nombre_caja ?: ($c['sinCaja'] ? 'COMCAJA' : 'COMCAJA');
        if ($esIndependiente && $sinCajaCcf) {
            $nombreCaja = 'NINGUNA CCF';
        }

        // Resolver el código de PILA real de AFP si es que viene como NIT
        $codAfpPilaReal = $c['codAfpPila'];
        if (!empty($c['codAfpPila'])) {
            $nitAfpLimpio = preg_replace('/[^0-9]/', '', $c['codAfpPila']);
            $pension = \App\Models\Pension::where('nit', $nitAfpLimpio)->orWhere('codigo', $c['codAfpPila'])->first();
            if ($pension && !empty($pension->codigo)) {
                $codAfpPilaReal = $pension->codigo;
            }
        }

        // Resolver el código de PILA real de EPS si es que viene como NIT
        $codEpsPilaReal = $c['codEpsPila'];
        if (!empty($c['codEpsPila'])) {
            $nitEpsLimpio = preg_replace('/[^0-9]/', '', $c['codEpsPila']);
            $epsObj = \App\Models\Eps::where('nit', $nitEpsLimpio)->orWhere('codigo', $c['codEpsPila'])->first();
            if ($epsObj && !empty($epsObj->codigo)) {
                $codEpsPilaReal = $epsObj->codigo;
            }
        }

        // Periodo de Cotización: es el mes del plano (ej: 06)
        $perCot = $plano->anio_plano . str_pad($plano->mes_plano, 2, '0', STR_PAD_LEFT);
        
        // Buscar el gasto de tipo pago_planilla asociado al número de planilla y aliado
        $gasto = null;
        if (!empty($plano->numero_planilla)) {
            $gasto = \App\Models\Gasto::where('aliado_id', $plano->aliado_id)
                ->where('numero_planilla', $plano->numero_planilla)
                ->where('tipo', 'pago_planilla')
                ->first();
        }

        $fechaPagoParaCarbon = null;
        if ($gasto) {
            $fechaPagoParaCarbon = $gasto->created_at ?? $gasto->fecha;
        } elseif ($plano->fecha_pago) {
            $fechaPagoParaCarbon = $plano->fecha_pago;
        } elseif ($plano->factura?->fecha_pago) {
            $fechaPagoParaCarbon = $plano->factura->fecha_pago;
        }

        // Periodo de Servicio: es el mes en que se EFECTÚA el pago / factura (ej: 07).
        // Se asume mes_plano + 1, o el mes real de la fecha de pago si existe.
        $mesPago = $plano->mes_plano == 12 ? 1 : $plano->mes_plano + 1;
        $anioPago = $plano->mes_plano == 12 ? $plano->anio_plano + 1 : $plano->anio_plano;
        
        if ($fechaPagoParaCarbon) {
            $dtPago = \Carbon\Carbon::parse($fechaPagoParaCarbon);
            $mesPago = $dtPago->month;
            $anioPago = $dtPago->year;
        }

        $perSer = $anioPago . str_pad($mesPago, 2, '0', STR_PAD_LEFT);

        $granTotal = $c['vAfp'] + $c['vEps'] + $c['vArl'] + $c['vCcf'];
        
        $afiliadosCount = Plano::where('numero_planilla', $plano->numero_planilla)
            ->where('aliado_id', $plano->aliado_id)
            ->count();

        if ($fechaPagoParaCarbon) {
            $dt = \Carbon\Carbon::parse($fechaPagoParaCarbon);
            $pagoFecha = $dt->format('Y-m-d');
            $pagoHora  = $dt->format('H:i:s.0');
            $pagoHoraSinMs = $dt->format('H:i:s');
        } else {
            $pagoFecha = '2026-07-03';
            $pagoHora  = '14:03:12.0';
            $pagoHoraSinMs = '14:03:12';
        }
        $clienteObj = $plano->contrato?->cliente;

        $razonSocialAportante = $esIndependiente
            ? strtoupper(trim(implode(' ', array_filter([$plano->primer_nombre, $plano->segundo_nombre, $plano->primer_ape, $plano->segundo_ape]))))
            : strtoupper($plano->razon_social);

        $nitAportante = $esIndependiente
            ? (($plano->tipo_doc ?? 'CC') . ' ' . ($plano->no_identifi ?? ''))
            : ('NI ' . ($plano->razonSocial?->nit ?? '901918923'));

        $direccionAportante = $esIndependiente
            ? strtoupper($clienteObj?->direccion_vivienda ?? $clienteObj?->direccion_cobro ?? 'CR 39 #43 - 04')
            : strtoupper($plano->razonSocial?->direccion ?? 'CR 39 #43 - 04');

        $tipoAportante = $esIndependiente ? 'INDEPENDIENTE' : 'EMPLEADOR';
        $tipoPersona = $esIndependiente ? 'NATURAL' : 'JURÍDICA';

        $telefonoAportante = $esIndependiente
            ? ($clienteObj?->celular ?? $clienteObj?->telefono ?? '5555555')
            : ($plano->razonSocial?->telefonos ?? $plano->razonSocial?->telefono ?? '5555555');

        $afiliadosCountVal = $esIndependiente ? '1' : (string)max(1, $afiliadosCount);

        $representanteVal = $esIndependiente
            ? ''
            : strtoupper($plano->razonSocial?->nombre_rep ?? $plano->razonSocial?->representante_legal ?? 'GARCIA VIDAL BRAYAN HUMBERTO');

        $representanteCedVal = $esIndependiente
            ? ''
            : ('CC ' . ($plano->razonSocial?->cedula_rep ?? $plano->razonSocial?->representante_cedula ?? '1143944458'));

        $exoneradoVal = $esIndependiente ? 'N' : 'S';

        $tipoPlanilla = $esIndependiente ? 'I' : 'E';
        $formaPresentacion = $esIndependiente ? 'ÚNICO' : ($plano->razonSocial?->forma_presentacion ?? 'ÚNICO');

        $departamentoAportante = $esIndependiente
            ? strtoupper($clienteObj?->departamento?->nombre ?? 'VALLE DEL CAUCA')
            : 'VALLE DEL CAUCA';

        $ciudadAportante = $esIndependiente
            ? strtoupper($clienteObj?->municipio?->nombre ?? 'CALI')
            : 'CALI';

        $ciudadAfiliado = $esIndependiente
            ? (($clienteObj?->municipio?->id ?? '76001') . '000 - ' . ($clienteObj?->departamento?->id ?? '76'))
            : '94001000 - 94';

        $ubicacionLaboralAfiliado = $esIndependiente
            ? strtoupper($clienteObj?->departamento?->nombre ?? 'VALLE DEL CAUCA')
            : 'GUAINIA';

        return [
            // Aportante
            'aportante.razon_social'         => $razonSocialAportante,
            'aportante.nit'                  => $nitAportante,
            'aportante.direccion'            => $direccionAportante,
            'aportante.tipo_aportante'       => $tipoAportante,
            'aportante.tipo_persona'         => $tipoPersona,
            'aportante.sucursal'             => $esIndependiente ? 'ÚNICO' : 'SUCURSAL',
            'aportante.departamento'         => $departamentoAportante,
            'aportante.ciudad'               => $ciudadAportante,
            'aportante.telefono'             => $telefonoAportante,
            'aportante.forma_presentacion'   => $formaPresentacion,
            'aportante.afiliados'            => $afiliadosCountVal,
            'aportante.representante'        => $representanteVal,
            'aportante.cedula_representante' => $representanteCedVal,

            // Metadatos
            'plano.fecha_creacion'          => now()->format('Y-m-d, h:i:s') . ' ' . (now()->format('a') === 'am' ? 'a. m.' : 'p. m.'),
            'plano.tipo_planilla'           => $tipoPlanilla,
            'plano.numero_planilla'         => $plano->numero_planilla,
            'plano.periodo_cotizacion'      => $perCot,
            'plano.periodo_servicio'        => $perSer,
            'plano.fecha_pago_completa'     => "{$pagoFecha} {$pagoHora}",
            'plano.fecha_pago_estado'       => 'PAGADA',
            'plano.fecha_pago_fecha'        => $pagoFecha,
            'plano.fecha_pago_hora'         => $pagoHora,

            // Afiliado
            'afiliado.tipo_doc'             => $plano->tipo_doc,
            'afiliado.cedula'               => $plano->no_identifi,
            'afiliado.tipo_doc_cedula'      => $plano->tipo_doc . ' ' . $plano->no_identifi,
            'afiliado.nombre_completo'      => strtoupper($plano->primer_ape . ' ' . $plano->segundo_ape . ' ' . $plano->primer_nombre . ' ' . $plano->segundo_nombre),
            'afiliado.exonerado'            => $exoneradoVal,
            'afiliado.ciudad'               => $ciudadAfiliado,
            'afiliado.ubicacion_laboral'    => $ubicacionLaboralAfiliado,
            'afiliado.tipo_cotizante'       => str_pad($c['tipoCotizante'], 2, '0', STR_PAD_LEFT),
            'afiliado.subtipo_cotizante'    => str_pad($c['subtipoCotizante'], 2, '0', STR_PAD_LEFT),

            // Aportes Detallados
            'aporte.novedad_ing'  => !empty($plano->fecha_ing) ? 'X' : '',
            'aporte.novedad_ret'  => !empty($plano->fecha_ret) ? 'X' : '',
            'aporte.novedad_irp'  => (string)$this->calcularNovedadIrp($plano),
            'aporte.dias_afp'     => $c['diasPension'],
            'aporte.dias_eps'     => $c['diasSalud'],
            'aporte.dias_arl'     => $c['diasArl'],
            'aporte.dias_ccf'     => $c['diasCcf'],
            'aporte.tipo_salario' => 'F',
            'aporte.salario'      => '$ ' . number_format($c['ibcFull'], 0, ',', '.'),
            
            // Pensión
            'aporte.afp_codigo'  => $codAfpPilaReal,
            'aporte.afp_tarifa'  => number_format($c['tarifaAfpDecimal'] * 100, 0) . ' %',
            'aporte.afp_ibc'     => '$ ' . number_format($c['ibcAfp'], 0, ',', '.'),
            'aporte.afp_aporte'  => '$ ' . number_format($c['vAfp'], 0, ',', '.'),
            'aporte.afp_fsp'     => '$ 0',
            'aporte.afp_fsps'    => '$ 0',

            // Salud
            'aporte.eps_codigo'  => $codEpsPilaReal,
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
            'aporte.ccf_codigo'  => ($esIndependiente && $sinCajaCcf ? 'NIN-CC' : ($c['codCcfPila'] == 'CCF68' ? 'CCF66' : $c['codCcfPila'])),
            'aporte.ccf_tarifa'  => ($esIndependiente && $sinCajaCcf ? '0 %' : '4 %'),
            'aporte.ccf_ibc'     => ($esIndependiente && $sinCajaCcf ? '$ 0' : '$ ' . number_format($c['ibcCcf'], 0, ',', '.')),
            'aporte.ccf_aporte'  => ($esIndependiente && $sinCajaCcf ? '$ 0' : '$ ' . number_format($c['vCcf'], 0, ',', '.')),

            // Parafiscales
            'aporte.sena_tarifa' => '0 %',
            'aporte.sena_aporte' => '$ 0',
            'aporte.icbf_tarifa' => '0 %',
            'aporte.icbf_aporte' => '$ 0',

            // Totales Administradoras (Nombres fijos o calculados)
            'total.afp_nombre'  => $nombreAfp,
            'total.eps_nombre'  => $nombreEps,
            'total.arl_nombre'  => 'ARL ' . $nombreArl,
            'total.ccf_nombre'  => $nombreCaja,
            'total.fsp_nombre'  => 'FSP SOLIDARIDAD',
            'total.fsps_nombre' => 'FSP SUBSISTENCIA',
            'total.sena_nombre' => 'SENA',
            'total.icbf_nombre' => 'ICBF',
            'total.esap_nombre' => 'ESAP',
            'total.men_nombre'  => 'MEN',

            // Totales Valores
            'total.afp'   => '$ ' . number_format($c['vAfp'], 0, ',', '.'),
            'total.fsp'   => '$ 0',
            'total.fsps'  => '$ 0',
            'total.eps'   => '$ ' . number_format($c['vEps'], 0, ',', '.'),
            'total.arl'   => '$ ' . number_format($c['vArl'], 0, ',', '.'),
            'total.ccf'   => ($esIndependiente && $sinCajaCcf ? '$ 0' : '$ ' . number_format($c['vCcf'], 0, ',', '.')),
            'total.sena'  => '$ 0',
            'total.icbf'  => '$ 0',
            'total.esap'  => '$ 0',
            'total.men'   => '$ 0',
            'total.final' => '$ ' . number_format($granTotal, 0, ',', '.'),
        ];
    }

    /**
     * Calcula la cantidad de días de incapacidad por riesgos profesionales (IRP) del cotizante en el periodo.
     */
    protected function calcularNovedadIrp(Plano $plano): int
    {
        if (!$plano->contrato_id || !$plano->mes_plano || !$plano->anio_plano) {
            return 0;
        }

        try {
            $inicioMes = \Carbon\Carbon::create($plano->anio_plano, $plano->mes_plano, 1)->startOfMonth();
            $finMes = \Carbon\Carbon::create($plano->anio_plano, $plano->mes_plano, 1)->endOfMonth();

            $incapacidades = \DB::table('incapacidades')
                ->where('contrato_id', $plano->contrato_id)
                ->where(function($q) {
                    $q->where('tipo_entidad', 'arl')
                      ->orWhere('tipo_incapacidad', 'accidente_laboral');
                })
                ->where(function($q) use ($inicioMes, $finMes) {
                    $q->whereBetween('fecha_inicio', [$inicioMes->format('Y-m-d'), $finMes->format('Y-m-d')])
                      ->orWhereBetween('fecha_terminacion', [$inicioMes->format('Y-m-d'), $finMes->format('Y-m-d')])
                      ->orWhere(function($inner) use ($inicioMes, $finMes) {
                          $inner->where('fecha_inicio', '<=', $inicioMes->format('Y-m-d'))
                                ->where('fecha_terminacion', '>=', $finMes->format('Y-m-d'));
                      });
                })
                ->whereNull('deleted_at')
                ->get(['fecha_inicio', 'fecha_terminacion']);

            if ($incapacidades->isEmpty()) {
                return 0;
            }

            $diasTotales = 0;
            foreach ($incapacidades as $inc) {
                $ini = \Carbon\Carbon::parse($inc->fecha_inicio);
                $fin = \Carbon\Carbon::parse($inc->fecha_terminacion);

                $rangoInicio = $ini->greaterThan($inicioMes) ? $ini : $inicioMes;
                $rangoFin = $fin->lessThan($finMes) ? $fin : $finMes;

                if ($rangoInicio->lessThanOrEqualTo($rangoFin)) {
                    $diasTotales += $rangoInicio->diffInDays($rangoFin) + 1;
                }
            }

            return min(30, $diasTotales);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Rellena las celdas de la plantilla PDF a través de FPDF y FPDI.
     */
    protected function rellenarPdf(string $rutaPdf, array $campos, array $datos): string
    {
        $pdf = new BrynexFpdi('L', 'pt');
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

            // Aplicar espaciado de caracteres (letter spacing / Tc)
            $charSpacing = floatval($c['letter_spacing'] ?? 0);
            $pdf->SetCharSpacing($charSpacing);

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

            // Lógica idéntica a formularios EPS: anclar texto al fondo de la caja
            $cellH = $fontSize + 1; // celda justa alrededor del texto
            $textY = $y + $h - $cellH; // anclar al fondo del rect

            // Ajuste global milimétrico para coincidir con la vista HTML
            // El HTML tiene un padding-left de 2px (1.5pt), mientras que FPDF usa 2.83pt por defecto.
            $pdf->setCMargin(1.5);

            $align = $c['align'] ?? 'left';
            $alignFpdf = 'L';
            if ($align === 'right') {
                $alignFpdf = 'R';
            } elseif ($align === 'center') {
                $alignFpdf = 'C';
            }

            // Restauramos el Y original ya que el +1.5 lo bajó demasiado
            $textY = $y + $h - $cellH;

            $pdf->SetXY($x, $textY);
            
            // FPDF solo soporta ISO-8859-1 (Latin-1). Convertimos el texto para soportar tildes y ñ.
            $valorIso = mb_convert_encoding((string)$valor, 'ISO-8859-1', 'UTF-8');
            
            if ($w > 0) {
                $pdf->Cell($w, $cellH, $valorIso, 0, 0, $alignFpdf);
            } else {
                $pdf->Write($cellH, $valorIso);
            }

            // Restaurar espaciado a 0
            $pdf->SetCharSpacing(0);
        }

        return $pdf->Output('S');
    }
}

/**
 * Subclase de FPDI para extender sus capacidades de FPDF con soporte a espaciado de caracteres (Tc).
 */
class BrynexFpdi extends Fpdi
{
    public function SetCharSpacing($spacing)
    {
        $this->_out(sprintf('%.3F Tc', $spacing));
    }

    public function setCMargin($margin)
    {
        $this->cMargin = $margin;
    }
}
