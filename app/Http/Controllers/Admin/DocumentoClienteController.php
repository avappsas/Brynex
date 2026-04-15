<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentoClienteController extends Controller
{
    // Tipos de documento soportados
    public const TIPOS = [
        'cedula'            => 'Cédula',
        'carta_laboral'     => 'Carta Laboral',
        'registro_civil'    => 'Registro Civil',
        'tarjeta_identidad' => 'Tarjeta Identidad',
        'decl_juramentada'  => 'Declaración Juramentada',
        'acta_matrimonio'   => 'Acta de Matrimonio',
        'otro'              => 'Otro',
    ];

    /** Lista documentos de un cliente (JSON para AJAX) */
    public function index(Request $request, $cedula)
    {
        $alidoId = session('aliado_id_activo');

        $docs = DocumentoCliente::with('subidor:id,nombre')
            ->where('aliado_id', $alidoId)
            ->where('cc_cliente', $cedula)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => [
                'id'              => $d->id,
                'tipo'            => $d->tipo_legible,
                'nombre_archivo'  => $d->nombre_archivo,
                'doc_beneficiario'=> $d->doc_beneficiario,
                'subido_por'      => $d->subidor?->nombre ?? '—',
                'fecha'           => $d->created_at->format('d/m/Y H:i'),
            ]);

        if ($request->expectsJson()) {
            return response()->json($docs);
        }
        return back();
    }

    /** Subir un documento */
    public function store(Request $request, $cedula)
    {
        $request->validate([
            'archivo'          => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'tipo_documento'   => 'required|string|in:' . implode(',', array_keys(self::TIPOS)),
            'doc_beneficiario' => 'nullable|string|max:20',
        ]);

        $alidoId = session('aliado_id_activo');
        $archivo = $request->file('archivo');

        // Nombre único: tipo_timestamp_random.ext
        $nombreUnico = $request->tipo_documento . '_' . time() . '_' . Str::random(6) . '.' . $archivo->getClientOriginalExtension();
        $ruta = "documentos/{$alidoId}/{$cedula}/{$nombreUnico}";

        // Guardar en disco privado
        Storage::disk('local')->put($ruta, file_get_contents($archivo->getRealPath()));

        DocumentoCliente::create([
            'aliado_id'        => $alidoId,
            'cc_cliente'       => $cedula,
            'doc_beneficiario' => $request->doc_beneficiario ?: null,
            'tipo_documento'   => $request->tipo_documento,
            'nombre_archivo'   => $archivo->getClientOriginalName(),
            'ruta'             => $ruta,
            'subido_por'       => auth()->id(),
        ]);

        return back()->with('success', 'Documento subido correctamente.');
    }

    /** Descargar / ver un documento (ruta protegida) */
    public function download($id)
    {
        $alidoId = session('aliado_id_activo');
        $doc = DocumentoCliente::where('aliado_id', $alidoId)->findOrFail($id);

        if (!Storage::disk('local')->exists($doc->ruta)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('local')->download($doc->ruta, $doc->nombre_archivo);
    }

    /** Eliminar un documento */
    public function destroy($id)
    {
        $alidoId = session('aliado_id_activo');
        $doc = DocumentoCliente::where('aliado_id', $alidoId)->findOrFail($id);

        // Eliminar archivo físico
        Storage::disk('local')->delete($doc->ruta);

        $doc->delete(); // El Observer registra en bitácora

        return back()->with('success', 'Documento eliminado.');
    }
}
