<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pension;
use App\Services\FormularioCampos;
use Illuminate\Http\Request;

/**
 * Gemelo de EpsFormularioController para los fondos de pensión.
 *
 * COLPENSIONES tiene su propio formulario de afiliación y no es una EPS: si se
 * colara como una fila más en `eps` aparecería en todos los selects de EPS
 * (contratos, cotizador, planos PILA) y aun así no se podría imprimir, porque
 * el formulario se arma con la EPS del contrato. Vive aquí, sobre `pensiones`,
 * y sirve igual para Porvenir, Protección y las demás.
 */
class PensionFormularioController extends Controller
{
    /** Carpeta (dentro de storage/app) donde viven los PDF de los fondos. */
    private const DIR_PDF = 'formularios/pensiones';

    public function __construct()
    {
        // El control vive en la ruta (permiso:formularios_pdf.editar, que es
        // solo_brynex). Aquí solo se exige sesión, igual que en el de EPS.
        $this->middleware('auth');
    }

    /**
     * Editor visual de mapeo para un fondo de pensión.
     * GET /admin/configuracion/pensiones/{pension}/formulario
     */
    public function editor(Pension $pension)
    {
        $campos = FormularioCampos::disponibles();
        $mapeados = $pension->formulario_campos ?? [];

        // La lista del selector usa `nombre`; en `pensiones` el nombre es la
        // razón social (el modelo expone el alias).
        $lista = Pension::orderBy('razon_social')->get(['id', 'razon_social', 'formulario_pdf']);

        return view('admin.configuracion.formulario_mapeo', [
            'entidad' => $pension,
            'lista' => $lista,
            'campos' => $campos,
            'mapeados' => $mapeados,
            'titulo' => 'Formularios Pensión',
            'subtitulo' => 'el fondo de pensión',
            'urlBase' => url('/admin/configuracion/pensiones'),
            'urlSubirPdf' => route('admin.configuracion.pensiones.formulario.pdf', $pension),
            'urlGuardar' => route('admin.configuracion.pensiones.formulario.guardar', $pension),
            'urlVerPdf' => route('admin.configuracion.pensiones.formulario.vpdf', $pension),
        ]);
    }

    /**
     * Sirve el PDF del fondo para verlo en el editor (fuera de public/).
     * GET /admin/configuracion/pensiones/{pension}/formulario/pdf
     */
    public function verPdf(Pension $pension)
    {
        if (! $pension->formulario_pdf) {
            abort(404, 'No hay PDF configurado para este fondo de pensión.');
        }
        $ruta = storage_path('app/'.self::DIR_PDF.'/'.$pension->formulario_pdf);
        if (! file_exists($ruta)) {
            abort(404, 'Archivo PDF no encontrado.');
        }

        return response()->file($ruta, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Guarda el mapeo de campos.
     * POST /admin/configuracion/pensiones/{pension}/formulario
     */
    public function guardar(Request $request, Pension $pension)
    {
        $campos = json_decode($request->input('formulario_campos', '[]'), true) ?? [];
        $pension->update(['formulario_campos' => $campos]);

        return back()->with('success', 'Mapeo guardado correctamente para '.$pension->razon_social);
    }

    /**
     * Sube el PDF del formulario del fondo.
     * POST /admin/configuracion/pensiones/{pension}/formulario/pdf
     */
    public function subirPdf(Request $request, Pension $pension)
    {
        $request->validate(['pdf' => 'required|file|mimes:pdf|max:20480']);

        $archivo = $request->file('pdf');
        $nombre = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $pension->razon_social)).'.pdf';
        $archivo->storeAs(self::DIR_PDF, $nombre, 'local');

        $pension->update(['formulario_pdf' => $nombre]);

        return back()->with('success', 'PDF subido correctamente: '.$nombre);
    }
}
