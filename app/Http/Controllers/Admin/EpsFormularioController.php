<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eps;
use Illuminate\Http\Request;

class EpsFormularioController extends Controller
{
    // Campos disponibles para mapear (clave interna → etiqueta para el usuario)
    public static function camposDisponibles(): array
    {
        return [
            // ── Cotizante ──────────────────────────────────────────
            'cliente.primer_apellido'  => 'Primer apellido',
            'cliente.segundo_apellido' => 'Segundo apellido',
            'cliente.primer_nombre'    => 'Primer nombre',
            'cliente.segundo_nombre'   => 'Segundo nombre',
            'cliente.tipo_doc'         => 'Tipo de documento',
            'cliente.cedula'           => 'Número de documento / Cédula',
            'cliente.genero'           => 'Género (M / F)',
            'cliente.genero_m'         => 'Género — cuadro MASCULINO (X si es M)',
            'cliente.genero_f'         => 'Género — cuadro FEMENINO (X si es F)',
            'cliente.firma'            => '✍️ FIRMA del cliente (imagen PNG)',
            'cliente.fecha_nacimiento'       => 'Fecha de nacimiento (dd/mm/aaaa)',
            'cliente.fecha_nacimiento_d_esp' => 'Fecha nacimiento — DÍA espaciado (0 5)',
            'cliente.fecha_nacimiento_m_esp' => 'Fecha nacimiento — MES espaciado (0 2)',
            'cliente.fecha_nacimiento_a_esp' => 'Fecha nacimiento — AÑO espaciado (1 9 9 0)',
            'static.COLOMBIANA'        => 'Nacionalidad (predeterminada: COLOMBIANA)',
            'arl.nombre'               => 'Nombre ARL',
            'pension.nombre'           => 'Nombre Pensión',
            'contrato.salario'         => 'Salario del contrato',
            'cliente.direccion'        => 'Dirección del cotizante',
            'cliente.telefono'         => 'Teléfono',
            'cliente.celular'          => 'Celular',
            'cliente.correo'           => 'Correo electrónico',
            'cliente.departamento'     => 'Departamento del cotizante',
            'cliente.municipio'        => 'Municipio del cotizante',
            'cliente.barrio'           => 'Barrio del cotizante',
            'cliente.sisben'           => 'Sisben',
            'cliente.ips'              => 'IPS',
            'cliente.ocupacion'        => 'Ocupación / Cargo cliente',
            // ── Empresa / Razón Social ─────────────────────────────
            'empresa.razon_social'     => 'Nombre o razón social',
            'empresa.tipo_doc'         => 'Tipo de documento de la empresa (NIT)',
            'empresa.nit'              => 'NIT de la empresa',
            'empresa.nit_dv'           => 'NIT con dígito de verificación',
            'empresa.direccion'        => 'Dirección de la empresa',
            'empresa.telefono'         => 'Teléfono de la empresa',
            'empresa.correo'           => 'Correo de la empresa',
            'empresa.departamento'     => 'Departamento de la empresa',
            'empresa.municipio'        => 'Municipio de la empresa',
            'empresa.sello'            => '🖼️ SELLO / FIRMA (imagen PNG)',
            // ── Contrato ───────────────────────────────────────────
            'contrato.fecha_ingreso'       => 'Fecha de ingreso (dd/mm/aaaa)',
            'contrato.fecha_ingreso_d_esp' => 'Fecha ingreso — DÍA espaciado (0 5)',
            'contrato.fecha_ingreso_m_esp' => 'Fecha ingreso — MES espaciado (0 2)',
            'contrato.fecha_ingreso_a_esp' => 'Fecha ingreso — AÑO espaciado (2 0 2 6)',
            'contrato.cargo'           => 'Cargo / Ocupación contrato',
        ];
    }

    /**
     * Muestra el editor visual de mapeo para una EPS.
     * GET /admin/configuracion/eps/{eps}/formulario
     */
    public function editor(Eps $eps)
    {
        $eps->loadMissing([]);
        $campos     = self::camposDisponibles();
        $mapeados   = $eps->formulario_campos ?? [];
        $epsLista   = Eps::orderBy('nombre')->get(['id', 'nombre', 'formulario_pdf']);

        return view('admin.configuracion.eps_formulario', compact('eps', 'campos', 'mapeados', 'epsLista'));
    }

    /**
     * Sirve el PDF de la EPS para visualizarlo en el editor (fuera de la carpeta pública).
     * GET /admin/configuracion/eps/{eps}/formulario/pdf
     */
    public function verPdf(Eps $eps)
    {
        if (!$eps->formulario_pdf) {
            abort(404, 'No hay PDF configurado para esta EPS.');
        }
        $ruta = storage_path('app/formularios/eps/' . $eps->formulario_pdf);
        if (!file_exists($ruta)) {
            abort(404, 'Archivo PDF no encontrado.');
        }
        return response()->file($ruta, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Guarda el mapeo de campos.
     * POST /admin/configuracion/eps/{eps}/formulario
     */
    public function guardar(Request $request, Eps $eps)
    {
        $campos = json_decode($request->input('formulario_campos', '[]'), true) ?? [];
        $eps->update(['formulario_campos' => $campos]);

        return back()->with('success', 'Mapeo guardado correctamente para ' . $eps->nombre);
    }

    /**
     * Sube el PDF del formulario para una EPS.
     * POST /admin/configuracion/eps/{eps}/formulario/pdf
     */
    public function subirPdf(Request $request, Eps $eps)
    {
        $request->validate(['pdf' => 'required|file|mimes:pdf|max:20480']);

        $archivo  = $request->file('pdf');
        $nombre   = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $eps->nombre)) . '.pdf';
        $archivo->storeAs('formularios/eps', $nombre, 'local');

        $eps->update(['formulario_pdf' => $nombre]);

        return back()->with('success', 'PDF subido correctamente: ' . $nombre);
    }
}
