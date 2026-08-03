<?php

namespace App\Http\Controllers;

use App\Models\Incapacidad;
use App\Models\Radicado;
use App\Services\CompresorDocumentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Portal público de subida de documentos para el cliente.
 * No requiere autenticación — solo validación por token UUID + cédula.
 *
 * Flujo:
 *  1. Aliado genera link en el sistema → GET /incapacidades/subir/{token}
 *  2. Cliente abre el link, ingresa su cédula para verificar identidad
 *  3. Sube los documentos requeridos (cédula, historia clínica, incapacidad, otro)
 *     y escribe una descripción de lo que le pasó
 *  4. Los documentos quedan en tabla radicados, asociados a la incapacidad_id
 */
class IncapacidadUploadController extends Controller
{
    // Tipos de documentos que puede subir el cliente
    const TIPOS_DOC = [
        'cedula'          => '🪪 Cédula de Ciudadanía',
        'historia_clinica'=> '📋 Historia Clínica / Epicrisis',
        'incapacidad'     => '📄 Incapacidad (documento oficial)',
        'examen'          => '🔬 Examen / Diagnóstico',
        'otro'            => '📎 Otro documento',
    ];

    // ── SHOW (GET) ────────────────────────────────────────────────────────────
    public function show(string $token, Request $request)
    {
        $inc = Incapacidad::where('token_subida', $token)
            ->whereNull('deleted_at')
            ->first();

        if (!$inc) {
            return view('incapacidades.subir-error', [
                'mensaje' => 'El enlace no es válido o ha expirado. Solicite un nuevo enlace a su asesor.',
            ]);
        }

        // Si ya está verificada la cédula en sesión para este token
        $verificado = session("inc_upload_ok_{$token}") === true;

        // Datos del aliado (para mostrar nombre de la empresa)
        $aliado = DB::table('aliados')->where('id', $inc->aliado_id)->first();

        // Documentos ya subidos (por si el cliente quiere ver lo que ya envió)
        $docsYaSubidos = DB::table('radicados')
            ->where('incapacidad_id', $inc->id)
            ->where('tipo', 'incapacidad')
            ->orderByDesc('id')
            ->get(['id', 'tipo_documento', 'created_at', 'ruta_pdf']);

        return view('incapacidades.subir', compact(
            'inc', 'token', 'verificado', 'aliado', 'docsYaSubidos'
        ));
    }

    // ── UPLOAD (POST) ─────────────────────────────────────────────────────────
    public function upload(string $token, Request $request)
    {
        $inc = Incapacidad::where('token_subida', $token)
            ->whereNull('deleted_at')
            ->firstOrFail();

        // ── Paso 1: verificar cédula ─────────────────────────────────────────
        if (!session("inc_upload_ok_{$token}")) {
            $request->validate([
                'cedula_verificacion' => 'required|string',
            ], [
                'cedula_verificacion.required' => 'Debe ingresar su cédula para continuar.',
            ]);

            if ($request->cedula_verificacion !== $inc->cedula_usuario) {
                return back()->withErrors([
                    'cedula_verificacion' => 'La cédula no coincide con la de esta incapacidad.',
                ]);
            }

            session(["inc_upload_ok_{$token}" => true]);

            // Si solo vino a verificar (sin archivo) → redirigir al formulario de subida
            if (!$request->hasFile('archivo')) {
                return redirect()->route('incapacidades.subir', $token)
                    ->with('success_verificacion', '✅ Identidad verificada. Ahora puede subir sus documentos.');
            }
        }

        // ── Paso 2: subir archivos ───────────────────────────────────────────
        $request->validate([
            'archivo'         => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,webp',
            'tipo_documento'  => 'required|string|in:' . implode(',', array_keys(self::TIPOS_DOC)),
        ], [
            'archivo.required'  => 'Debe seleccionar un archivo.',
            'archivo.max'       => 'El archivo no puede superar 20 MB.',
            'archivo.mimes'     => 'Solo se aceptan archivos PDF, JPG o PNG.',
        ]);

        $file   = $request->file('archivo');
        $cedula = $inc->cedula_usuario;

        // Disco 'local' (storage/app), NO servido por el servidor web: son
        // documentos de salud. Con 'public' quedaban accesibles por URL directa
        // en /storage/incapacidades/{aliado}/{cedula}/... sin autenticación.
        // Se consultan desde el admin vía IncapacidadController::verDocumento().
        // Comprimido antes de guardar: el cliente sube fotos de celular de 5-10 MB
        // que se leen igual de bien a 2200 px. Ver CompresorDocumentoService.
        $ruta = app(CompresorDocumentoService::class)->guardar(
            $file,
            "incapacidades/{$inc->aliado_id}/{$cedula}/{$inc->id}/cliente",
            'local'
        );

        // Registrar en tabla radicados
        Radicado::create([
            'incapacidad_id'  => $inc->id,
            'aliado_id'       => $inc->aliado_id,
            'contrato_id'     => $inc->contrato_id ?? 0,
            'tipo'            => 'incapacidad',
            'tipo_documento'  => $request->tipo_documento,
            'estado'          => 'ok',
            'observacion'     => $request->observacion,
            'ruta_pdf'        => $ruta,
            'user_id'         => null, // subido por el cliente (sin usuario del sistema)
            'enviado_al_cliente' => false,
        ]);

        // Guardar descripción del cliente si viene
        if ($request->filled('descripcion_cliente')) {
            $inc->update([
                'descripcion_cliente' => $request->descripcion_cliente,
            ]);
        }

        return redirect()->route('incapacidades.subir', $token)
            ->with('success', '✅ Documento "' . (self::TIPOS_DOC[$request->tipo_documento] ?? $request->tipo_documento) . '" recibido correctamente.');
    }
}
