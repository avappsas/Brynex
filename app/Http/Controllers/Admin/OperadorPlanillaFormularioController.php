<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperadorPlanilla;
use App\Models\OperadorPlanillaTemplate;
use Illuminate\Http\Request;

class OperadorPlanillaFormularioController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:superadmin|admin']);
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->es_brynex) {
                abort(403, 'Acceso denegado. Esta sección es exclusiva de BryNex.');
            }
            return $next($request);
        });
    }

    /**
     * Muestra el editor visual de mapeo para un operador de planilla.
     * GET /admin/configuracion/operadores/{operador}/formulario
     */
    public function editor(OperadorPlanilla $operador)
    {
        $template = OperadorPlanillaTemplate::firstOrCreate(
            ['operador_planilla_id' => $operador->id],
            ['nombre' => 'Plantilla ' . $operador->nombre]
        );

        $campos     = self::camposDisponibles();
        $mapeados   = $template->formulario_campos ?? [];
        $operadores = OperadorPlanilla::orderBy('nombre')->get(['id', 'nombre', 'activo']);

        return view('admin.configuracion.planilla_formulario', compact('operador', 'template', 'campos', 'mapeados', 'operadores'));
    }

    /**
     * Sirve el PDF para visualizarlo en el editor.
     * GET /admin/configuracion/operadores/{operador}/formulario/pdf
     */
    public function verPdf(OperadorPlanilla $operador)
    {
        $template = OperadorPlanillaTemplate::where('operador_planilla_id', $operador->id)->first();
        if (!$template || !$template->formulario_pdf) {
            abort(404, 'No hay PDF configurado para este operador.');
        }

        $ruta = storage_path('app/formularios/planillas/' . $template->formulario_pdf);
        if (!file_exists($ruta)) {
            // Autocopia en local para prevenir 404 si el archivo físico de producción no existe en la laptop
            $sourcePdf = resource_path('pdf/certificado_suaporte_template.pdf');
            if (file_exists($sourcePdf)) {
                $dir = dirname($ruta);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                copy($sourcePdf, $ruta);
            } else {
                abort(404, 'Archivo PDF no encontrado.');
            }
        }

        return response()->file($ruta, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Guarda el mapeo de campos.
     * POST /admin/configuracion/operadores/{operador}/formulario
     */
    public function guardar(Request $request, OperadorPlanilla $operador)
    {
        $campos = json_decode($request->input('formulario_campos', '[]'), true) ?? [];

        $template = OperadorPlanillaTemplate::updateOrCreate(
            ['operador_planilla_id' => $operador->id],
            [
                'nombre' => 'Plantilla ' . $operador->nombre,
                'formulario_campos' => $campos
            ]
        );

        return back()->with('success', 'Mapeo de planilla guardado correctamente para ' . $operador->nombre);
    }

    /**
     * Sube el PDF de la planilla en blanco.
     * POST /admin/configuracion/operadores/{operador}/formulario/pdf
     */
    public function subirPdf(Request $request, OperadorPlanilla $operador)
    {
        $request->validate(['pdf' => 'required|file|mimes:pdf|max:20480']);

        $archivo  = $request->file('pdf');
        $nombre   = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $operador->nombre)) . '.pdf';
        
        $dir = storage_path('app/formularios/planillas');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $archivo->storeAs('formularios/planillas', $nombre, 'local');

        $template = OperadorPlanillaTemplate::updateOrCreate(
            ['operador_planilla_id' => $operador->id],
            [
                'nombre' => 'Plantilla ' . $operador->nombre,
                'formulario_pdf' => $nombre
            ]
        );

        return back()->with('success', 'PDF de planilla subido correctamente: ' . $nombre);
    }

    /**
     * Lista de campos disponibles para mapear en la planilla de pago.
     */
    public static function camposDisponibles(): array
    {
        return [
            // Aportante
            'aportante.razon_social'         => 'Razón social del aportante',
            'aportante.nit'                  => 'NIT de la empresa',
            'aportante.direccion'            => 'Dirección de la empresa',
            'aportante.tipo_aportante'       => 'Tipo Aportante (EMPLEADOR)',
            'aportante.tipo_persona'         => 'Tipo Persona (JURÍDICA)',
            'aportante.sucursal'             => 'Sucursal (SUCURSAL)',
            'aportante.departamento'         => 'Departamento Aportante (VALLE DEL CAUCA)',
            'aportante.ciudad'               => 'Ciudad Aportante (CALI)',
            'aportante.telefono'             => 'Teléfono de la empresa',
            'aportante.afiliados'            => 'Total afiliados en el plano',
            'aportante.representante'        => 'Nombre del representante legal',
            'aportante.cedula_representante' => 'Cédula del representante legal',

            // Metadatos planilla
            'plano.fecha_creacion'          => '📅 Fecha creación reporte (dd/mm/aaaa hh:mm)',
            'plano.tipo_planilla'           => 'Tipo Planilla (E)',
            'plano.numero_planilla'         => 'Número de Planilla PILA',
            'plano.periodo_cotizacion'      => 'Periodo Cotización (aaaamm)',
            'plano.periodo_servicio'        => 'Periodo Servicio (aaaamm)',
            'plano.fecha_pago_completa'     => 'Fecha y Hora de Pago (AAAA-MM-DD HH:MM:SS.0)',
            'plano.fecha_pago_estado'       => 'Estado Pago (PAGADA)',
            'plano.fecha_pago_fecha'        => 'Fecha Pago (AAAA-MM-DD)',
            'plano.fecha_pago_hora'         => 'Hora Pago (HH:MM:SS.0)',

            // Afiliado
            'afiliado.tipo_doc'             => 'Tipo doc del afiliado (CC, CE)',
            'afiliado.cedula'               => 'Cédula / Documento afiliado',
            'afiliado.tipo_doc_cedula'      => 'Tipo y Cédula (ej. CC 1058846712)',
            'afiliado.nombre_completo'      => 'Nombre completo del afiliado',
            'afiliado.exonerado'            => 'Exonerado (S / N)',
            'afiliado.ciudad'               => 'Código Ciudad - Depto (ej. 94001000 - 94)',
            'afiliado.ubicacion_laboral'    => 'Ubicación Laboral (ej. GUAINIA)',
            'afiliado.tipo_cotizante'       => 'Tipo Cotizante (ej. 01)',
            'afiliado.subtipo_cotizante'    => 'Subtipo Cotizante (ej. 00)',

            // Aportes Detallados (Fila Sección III)
            'aporte.novedad_ing' => 'ING (X si ingresó)',
            'aporte.novedad_ret' => 'RET (X si se retiró)',
            'aporte.novedad_irp' => 'IRP (Días de incapacidad por riesgos profesionales, 0 por defecto)',
            'aporte.dias_afp'    => 'Días AFP',
            'aporte.dias_eps'    => 'Días EPS',
            'aporte.dias_arl'    => 'Días ARL',
            'aporte.dias_ccf'    => 'Días CCF',
            'aporte.tipo_salario' => 'Tipo Salario (F)',
            'aporte.salario'     => 'Salario base',

            // Pensión
            'aporte.afp_codigo'  => 'Código administradora AFP',
            'aporte.afp_tarifa'  => 'Tarifa AFP (16%)',
            'aporte.afp_ibc'     => 'IBC Pensión',
            'aporte.afp_aporte'  => 'Aporte Pensión',
            'aporte.afp_fsp'     => 'Aporte FSP',
            'aporte.afp_fsps'    => 'Aporte FSPS',

            // Salud
            'aporte.eps_codigo'  => 'Código administradora EPS',
            'aporte.eps_tarifa'  => 'Tarifa EPS (4%)',
            'aporte.eps_ibc'     => 'IBC Salud',
            'aporte.eps_aporte'  => 'Aporte Salud',
            'aporte.eps_upc'     => 'Aporte UPC',

            // Riesgos
            'aporte.arl_codigo'  => 'Código administradora ARL',
            'aporte.arl_clase'   => 'Clase Riesgo ARL (1 a 5)',
            'aporte.arl_tarifa'  => 'Tarifa ARL (ej: 2.436%)',
            'aporte.arl_ibc'     => 'IBC Riesgos ARL',
            'aporte.arl_aporte'  => 'Aporte Riesgos ARL',

            // Caja
            'aporte.ccf_codigo'  => 'Código CCF (Caja)',
            'aporte.ccf_tarifa'  => 'Tarifa CCF (4%)',
            'aporte.ccf_ibc'     => 'IBC Caja CCF',
            'aporte.ccf_aporte'  => 'Aporte Caja CCF',

            // Parafiscales
            'aporte.sena_tarifa' => 'Tarifa SENA',
            'aporte.sena_aporte' => 'Aporte SENA',
            'aporte.icbf_tarifa' => 'Tarifa ICBF',
            'aporte.icbf_aporte' => 'Aporte ICBF',

            // Totales
            'total.afp_nombre' => 'Nombre AFP en Totales',
            'total.eps_nombre' => 'Nombre EPS en Totales',
            'total.arl_nombre'  => 'Nombre ARL en Totales',
            'total.ccf_nombre'  => 'Nombre Caja en Totales',
            'total.fsp_nombre'  => 'Etiqueta FSP SOLIDARIDAD',
            'total.fsps_nombre' => 'Etiqueta FSP SUBSISTENCIA',
            'total.sena_nombre' => 'Etiqueta SENA',
            'total.icbf_nombre' => 'Etiqueta ICBF',
            'total.esap_nombre' => 'Etiqueta ESAP',
            'total.men_nombre'  => 'Etiqueta MEN',
            
            'total.afp'   => 'Total Aporte Pensión',
            'total.fsp'   => 'Total Aporte FSP',
            'total.fsps'  => 'Total Aporte FSPS',
            'total.eps'   => 'Total Aporte Salud',
            'total.arl'   => 'Total Aporte Riesgos',
            'total.ccf'   => 'Total Aporte Cajas',
            'total.sena'  => 'Total Aporte SENA',
            'total.icbf'  => 'Total Aporte ICBF',
            'total.esap'  => 'Total Aporte ESAP',
            'total.men'   => 'Total Aporte MEN',
            'total.final' => '💰 Total Final Planilla',
        ];
    }

    /**
     * Obtiene los datos dinámicos de un plano de ejemplo para la previsualización en tiempo real.
     * GET /admin/configuracion/operadores/datos-ejemplo
     */
    public function obtenerDatosEjemplo(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $cedula = $request->input('cedula');
        $numeroPlanilla = $request->input('numero_planilla');

        if (!$cedula || !$numeroPlanilla) {
            return response()->json(['error' => 'Debe suministrar cédula y número de planilla.'], 400);
        }

        $plano = \App\Models\Plano::with('razonSocial')
            ->where('aliado_id', $aliadoId)
            ->where('no_identifi', $cedula)
            ->where('numero_planilla', $numeroPlanilla)
            ->whereNull('deleted_at')
            ->first();

        if (!$plano) {
            return response()->json(['error' => 'No se encontró el registro de planilla de pago solicitado.'], 404);
        }

        // Ensamblar los datos reales usando el servicio
        $service = new \App\Services\PlanillaFormularioService();
        $datos = $service->ensamblarDatos($plano);

        return response()->json($datos);
    }
}
