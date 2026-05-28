<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, WhatsappConfig, WhatsappConversacion, WhatsappMensaje};
use App\Services\WhatsappApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Storage};

/**
 * Controlador del Chat de WhatsApp.
 * Gestiona el inbox, visualización y envío de mensajes.
 */
class WhatsappChatController extends Controller
{
    public function __construct(protected WhatsappApiService $apiService) {}

    /**
     * Inbox de conversaciones del aliado.
     * Muestra: General (todas sin asignar + asignadas) y Mis conversaciones.
     */
    public function index(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $userId  = Auth::id();
        $tab     = $request->get('tab', 'general'); // general | mias
        $buscar  = $request->get('buscar');

        $query = WhatsappConversacion::delAliado($alidoId)
            ->activas()
            ->with(['mensajes' => fn($q) => $q->reorder()->latest()->limit(1)])
            ->orderByDesc('ultimo_mensaje_at');

        if ($tab === 'mias') {
            $query->where('asignado_a', $userId);
        }

        if ($buscar) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre_contacto', 'like', "%{$buscar}%")
                  ->orWhere('wa_contact_id', 'like', "%{$buscar}%");
            });
        }

        $conversaciones = $query->get();

        // Badge total no leídos del aliado
        $totalNoLeidos = WhatsappConversacion::delAliado($alidoId)
            ->activas()
            ->sum('total_mensajes_no_leidos');

        // Usuarios del aliado para la asignación
        $usuarios = User::where('aliado_id', $alidoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.whatsapp.chat.index', compact(
            'conversaciones', 'tab', 'buscar', 'totalNoLeidos', 'usuarios'
        ));
    }

    /**
     * Vista individual del chat con todos los mensajes.
     */
    public function show(int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);
        $conversacion->resetNoLeidos();

        $mensajes = $conversacion->mensajes()
            ->with(['usuario:id,nombre', 'plantilla:id,nombre_display'])
            ->get();

        // Usuarios del aliado para asignación
        $usuarios = User::where('aliado_id', $alidoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // Plantillas aprobadas para cuando la ventana esté inactiva
        $plantillas = \App\Models\WhatsappPlantilla::delAliado($alidoId)
            ->aprobadas()
            ->select('id', 'nombre', 'nombre_display', 'cuerpo', 'variables_mapa')
            ->get();

        return view('admin.whatsapp.chat.show', compact(
            'conversacion', 'mensajes', 'usuarios', 'plantillas'
        ));
    }

    /**
     * Envía un mensaje desde el sistema al cliente.
     * Soporta: texto, imagen, audio, documento, plantilla.
     */
    public function enviarMensaje(Request $request, int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);
        $config       = WhatsappConfig::paraAliado($alidoId);

        if (!$config->credencialesCompletas()) {
            return response()->json(['ok' => false, 'error' => 'No hay credenciales de WhatsApp configuradas.'], 422);
        }

        $tipo = $request->input('tipo', 'text');

        // ── Validación según tipo ──────────────────────────────────────
        $rules = ['tipo' => 'required|in:text,image,audio,document,template'];

        if ($tipo === 'text') {
            $rules['contenido'] = 'required|string|max:4096';
        } elseif ($tipo === 'template') {
            $rules['plantilla_id']  = 'required|integer|exists:whatsapp_plantillas,id';
            $rules['parametros']    = 'nullable|array';
        } else {
            $rules['archivo'] = 'required|file|max:25600'; // 25MB máx
        }

        $validated = $request->validate($rules);

        // ── Verificar ventana de 24h para texto libre ──────────────────
        if ($tipo === 'text' && !$conversacion->ventanaActiva()) {
            return response()->json([
                'ok'    => false,
                'error' => 'La ventana de 24h expiró. Debes enviar una plantilla aprobada para iniciar la conversación.',
            ], 422);
        }

        // ── Enviar según tipo ──────────────────────────────────────────
        $resultado = match($tipo) {
            'text'     => $this->enviarTexto($conversacion, $validated, $config),
            'template' => $this->enviarTemplate($conversacion, $validated, $config, $alidoId),
            default    => $this->enviarMedia($conversacion, $request, $tipo, $config),
        };

        if (!$resultado['ok']) {
            return response()->json(['ok' => false, 'error' => $resultado['error']], 422);
        }

        // Actualizar último mensaje
        $conversacion->update(['ultimo_mensaje_at' => now()]);

        return response()->json([
            'ok'      => true,
            'mensaje' => $resultado['mensaje'],
        ]);
    }

    /**
     * Asigna o reasigna una conversación a un usuario.
     */
    public function asignar(Request $request, int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validated['user_id']) {
            $conversacion->asignarA($validated['user_id']);
            $usuario = User::find($validated['user_id']);
            $msg = "Conversación asignada a {$usuario->nombre}";
        } else {
            $conversacion->liberar();
            $msg = 'Conversación devuelta al inbox general';
        }

        return response()->json(['ok' => true, 'mensaje' => $msg]);
    }

    /**
     * Cierra una conversación.
     */
    public function cerrar(int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);
        $conversacion->cerrar();

        return response()->json(['ok' => true]);
    }

    /**
     * Marca los mensajes de una conversación como leídos.
     */
    public function marcarLeido(int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);
        $conversacion->resetNoLeidos();

        return response()->json(['ok' => true]);
    }

    /**
     * Descarga/sirve un archivo media (imagen, audio, PDF).
     * Valida que el mensaje pertenece al aliado antes de servir el archivo.
     */
    public function descargarMedia(int $mensajeId)
    {
        $alidoId = session('aliado_id_activo');
        $mensaje = WhatsappMensaje::where('aliado_id', $alidoId)->findOrFail($mensajeId);

        if (!$mensaje->media_url || !Storage::disk('local')->exists($mensaje->media_url)) {
            abort(404, 'Archivo no encontrado');
        }

        $contenido  = Storage::disk('local')->get($mensaje->media_url);
        $mimeType   = $mensaje->media_mime_type ?? 'application/octet-stream';
        $nombre     = $mensaje->media_nombre ?? basename($mensaje->media_url);

        return response($contenido, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $nombre . '"');
    }

    /**
     * API: total de mensajes no leídos del aliado activo (para badge en menú).
     */
    public function apiNoLeidos()
    {
        $alidoId   = session('aliado_id_activo');
        $noLeidos  = WhatsappConversacion::delAliado($alidoId)
            ->activas()
            ->sum('total_mensajes_no_leidos');

        return response()->json(['total' => (int)$noLeidos]);
    }

    // ── Helpers privados ─────────────────────────────────────────────

    private function enviarTexto(WhatsappConversacion $conv, array $data, WhatsappConfig $config): array
    {
        $resultado = $this->apiService->enviarTexto($conv->wa_contact_id, $data['contenido'], $config);

        if (!$resultado['ok']) return $resultado;

        $mensaje = WhatsappMensaje::create([
            'conversacion_id' => $conv->id,
            'aliado_id'       => $conv->aliado_id,
            'wa_message_id'   => $resultado['wa_message_id'],
            'direccion'       => 'saliente',
            'tipo'            => 'text',
            'contenido'       => $data['contenido'],
            'estado'          => 'enviado',
            'usuario_id'      => Auth::id(),
        ]);

        return ['ok' => true, 'mensaje' => $mensaje];
    }

    private function enviarTemplate(WhatsappConversacion $conv, array $data, WhatsappConfig $config, int $alidoId): array
    {
        $plantilla = \App\Models\WhatsappPlantilla::delAliado($alidoId)->findOrFail($data['plantilla_id']);
        $params    = $data['parametros'] ?? [];

        $resultado = $this->apiService->enviarTemplate($conv->wa_contact_id, $plantilla, $params, $config);

        if (!$resultado['ok']) return $resultado;

        $mensaje = WhatsappMensaje::create([
            'conversacion_id'      => $conv->id,
            'aliado_id'            => $conv->aliado_id,
            'wa_message_id'        => $resultado['wa_message_id'],
            'direccion'            => 'saliente',
            'tipo'                 => 'template',
            'plantilla_id'         => $plantilla->id,
            'plantilla_parametros' => $params,
            'estado'               => 'enviado',
            'usuario_id'           => Auth::id(),
        ]);

        return ['ok' => true, 'mensaje' => $mensaje];
    }

    private function enviarMedia(WhatsappConversacion $conv, Request $request, string $tipo, WhatsappConfig $config): array
    {
        $archivo  = $request->file('archivo');
        $mimeType = $archivo->getMimeType();
        $nombre   = $archivo->getClientOriginalName();

        // Guardar localmente primero
        $directorio = 'whatsapp/' . now()->format('Y/m');
        $path = $archivo->store($directorio, 'local');

        $resultado = $this->apiService->enviarMedia(
            $conv->wa_contact_id,
            $tipo,
            $path,
            $mimeType,
            $nombre,
            $config
        );

        if (!$resultado['ok']) return $resultado;

        $mensaje = WhatsappMensaje::create([
            'conversacion_id' => $conv->id,
            'aliado_id'       => $conv->aliado_id,
            'wa_message_id'   => $resultado['wa_message_id'],
            'direccion'       => 'saliente',
            'tipo'            => $tipo,
            'media_url'       => $path,
            'media_mime_type' => $mimeType,
            'media_nombre'    => $nombre,
            'contenido'       => $request->input('caption'),
            'estado'          => 'enviado',
            'usuario_id'      => Auth::id(),
        ]);

        return ['ok' => true, 'mensaje' => $mensaje];
    }
}
