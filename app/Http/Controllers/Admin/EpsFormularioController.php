<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eps;
use App\Services\FormularioCampos;
use Illuminate\Http\Request;

class EpsFormularioController extends Controller
{
    public function __construct()
    {
        // El control vive en la ruta (permiso:formularios_pdf.editar, que es
        // solo_brynex). Aquí solo se exige sesión, para no tener dos fuentes
        // de verdad que se contradigan: era justo lo que pasaba antes, el
        // controlador dejaba pasar a un admin y la ruta lo rebotaba.
        $this->middleware('auth');
    }

    /**
     * Muestra el editor visual de mapeo para una EPS.
     * GET /admin/configuracion/eps/{eps}/formulario
     */
    public function editor(Eps $eps)
    {
        $campos = FormularioCampos::disponibles();
        $mapeados = $eps->formulario_campos ?? [];

        // La vista es la misma del editor de fondos de pensión: recibe la
        // entidad dueña del PDF y las URLs ya resueltas.
        return view('admin.configuracion.formulario_mapeo', [
            'entidad' => $eps,
            'lista' => Eps::orderBy('nombre')->get(['id', 'nombre', 'formulario_pdf']),
            'campos' => $campos,
            'mapeados' => $mapeados,
            'titulo' => 'Formularios EPS',
            'subtitulo' => 'la EPS',
            'urlBase' => url('/admin/configuracion/eps'),
            'urlSubirPdf' => route('admin.configuracion.eps.formulario.pdf', $eps),
            'urlGuardar' => route('admin.configuracion.eps.formulario.guardar', $eps),
            'urlVerPdf' => route('admin.configuracion.eps.formulario.vpdf', $eps),
        ]);
    }

    /**
     * Sirve el PDF de la EPS para visualizarlo en el editor (fuera de la carpeta pública).
     * GET /admin/configuracion/eps/{eps}/formulario/pdf
     */
    public function verPdf(Eps $eps)
    {
        if (! $eps->formulario_pdf) {
            abort(404, 'No hay PDF configurado para esta EPS.');
        }
        $ruta = storage_path('app/formularios/eps/'.$eps->formulario_pdf);
        if (! file_exists($ruta)) {
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

        return back()->with('success', 'Mapeo guardado correctamente para '.$eps->nombre);
    }

    /**
     * Sube el PDF del formulario para una EPS.
     * POST /admin/configuracion/eps/{eps}/formulario/pdf
     */
    public function subirPdf(Request $request, Eps $eps)
    {
        $request->validate(['pdf' => 'required|file|mimes:pdf|max:20480']);

        $archivo = $request->file('pdf');
        $nombre = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $eps->nombre)).'.pdf';
        $archivo->storeAs('formularios/eps', $nombre, 'local');

        $eps->update(['formulario_pdf' => $nombre]);

        return back()->with('success', 'PDF subido correctamente: '.$nombre);
    }
}
