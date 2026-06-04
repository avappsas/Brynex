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
        $tab     = $request->get('tab', 'general');
        $buscar  = $request->get('buscar');

        ['conversaciones' => $conversaciones, 'totalNoLeidos' => $totalNoLeidos] =
            $this->cargarDatosSidebar($alidoId, $tab, $buscar);

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
    public function show(Request $request, int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $tab          = $request->get('tab', 'general');
        $buscar       = $request->get('buscar');

        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);
        $conversacion->resetNoLeidos();

        // Query directa desde WhatsappMensaje para evitar el ORDER BY heredado
        // de la relación hasMany (que tiene ->orderBy('created_at') fijo).
        // SQL Server rechaza tener la misma columna dos veces en ORDER BY.
        $mensajes = WhatsappMensaje::where('conversacion_id', $conversacion->id)
            ->with(['usuario:id,nombre', 'plantilla:id,nombre_display'])
            ->orderBy('created_at', 'desc')
            ->take(150)
            ->get()
            ->sortBy('created_at')
            ->values();

        // Usuarios y plantillas
        $usuarios = User::where('aliado_id', $alidoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $plantillas = \App\Models\WhatsappPlantilla::delAliado($alidoId)
            ->aprobadas()
            ->select('id', 'nombre', 'nombre_display', 'cuerpo', 'variables_mapa')
            ->get();

        // Sidebar — método compartido, sin duplicar lógica
        ['conversaciones' => $conversaciones, 'totalNoLeidos' => $totalNoLeidos] =
            $this->cargarDatosSidebar($alidoId, $tab, $buscar);

        // Mapear conversaciones para Alpine.js
        $conversacionesData = $this->mapearConversacionesSidebar($conversaciones);

        $mensajesData = $this->mapearMensajes($mensajes);

        $conversacionData = [
            'id'              => $conversacion->id,
            'nombre'          => $conversacion->nombreMostrar(),
            'celular'         => $conversacion->wa_contact_id,
            'contrato_id'     => $conversacion->contrato_id,
            'contrato_url'    => $conversacion->cliente_url,
            'estado'          => $conversacion->estado,
            'asignado_a'      => $conversacion->asignado_a,
            'asignado_nombre' => $conversacion->asignado?->nombre,
            'ventana_activa'  => $conversacion->ventanaActiva(),
            'ventana_minutos' => $conversacion->minutosVentanaRestante(),
        ];

        return view('admin.whatsapp.chat.show', compact(
            'conversacion', 'mensajes', 'usuarios', 'plantillas',
            'conversaciones', 'conversacionesData', 'tab', 'buscar', 'totalNoLeidos',
            'mensajesData', 'conversacionData'
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
            $rules['plantilla_id'] = 'required|integer|exists:whatsapp_plantillas,id';
            $rules['parametros']   = 'nullable|array';
        } else {
            $rules['archivo'] = 'required|file|max:25600'; // 25MB máx
        }

        $validated = $request->validate($rules);

        // ── Verificar ventana de 24h para mensajes libres ──────────────
        if ($tipo !== 'template' && !$conversacion->ventanaActiva()) {
            return response()->json([
                'ok'    => false,
                'error' => 'La ventana de 24h no está activa. Debes enviar una plantilla aprobada para iniciar o reabrir la conversación.',
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

        // Autoasignación al agente emisor actual
        $conversacion->update([
            'asignado_a'        => Auth::id(),
            'estado'            => 'asignada',
            'ultimo_mensaje_at' => now(),
        ]);

        return response()->json([
            'ok'             => true,
            'mensaje'        => $resultado['mensaje'],
            'ventana_activa' => $conversacion->fresh()->ventanaActiva(),
            'ventana_minutos'=> $conversacion->fresh()->minutosVentanaRestante(),
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
     */
    public function descargarMedia(int $mensajeId)
    {
        $alidoId = session('aliado_id_activo');
        $mensaje = WhatsappMensaje::where('aliado_id', $alidoId)->findOrFail($mensajeId);

        if (!$mensaje->media_url || !Storage::disk('local')->exists($mensaje->media_url)) {
            abort(404, 'Archivo no encontrado');
        }

        $contenido = Storage::disk('local')->get($mensaje->media_url);
        $mimeType  = $mensaje->media_mime_type ?? 'application/octet-stream';
        $nombre    = $mensaje->media_nombre ?? basename($mensaje->media_url);

        return response($contenido, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $nombre . '"');
    }

    /**
     * API: total de mensajes no leídos del aliado activo (para badge en menú).
     */
    public function apiNoLeidos()
    {
        $alidoId = session('aliado_id_activo');
        $user    = Auth::user();
        $userId  = $user->id;
        $esAdmin = $user->es_brynex || $user->hasRole(['admin', 'superadmin']);

        $query = WhatsappConversacion::delAliado($alidoId)->activas();

        if (!$esAdmin) {
            $query->where(function ($q) use ($userId) {
                $q->where('asignado_a', $userId)->orWhereNull('asignado_a');
            });
        }

        return response()->json(['total' => (int) $query->sum('total_mensajes_no_leidos')]);
    }

    /**
     * API: retorna los mensajes e información de una conversación (para cambio de chat SPA).
     * Limitado a los últimos 150 mensajes para máxima velocidad.
     */
    public function apiMensajes(int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);
        $conversacion->resetNoLeidos();

        $mensajes = WhatsappMensaje::where('conversacion_id', $conversacion->id)
            ->with(['usuario:id,nombre', 'plantilla:id,nombre_display'])
            ->orderBy('created_at', 'desc')
            ->take(150)
            ->get()
            ->sortBy('created_at')
            ->values();

        return response()->json([
            'ok'          => true,
            'conversacion' => [
                'id'              => $conversacion->id,
                'nombre'          => $conversacion->nombreMostrar(),
                'celular'         => $conversacion->wa_contact_id,
                'contrato_id'     => $conversacion->contrato_id,
                'contrato_url'    => $conversacion->cliente_url,
                'estado'          => $conversacion->estado,
                'asignado_a'      => $conversacion->asignado_a,
                'asignado_nombre' => $conversacion->asignado?->nombre,
                'ventana_activa'  => $conversacion->ventanaActiva(),
                'ventana_minutos' => $conversacion->minutosVentanaRestante(),
            ],
            'mensajes' => $this->mapearMensajes($mensajes),
        ]);
    }

    /**
     * API: retorna los datos de UNA conversación en formato sidebar.
     * Usado por el frontend cuando llega un evento Reverb de una conversación
     * que todavía no está en la lista del sidebar (contacto nuevo).
     */
    public function apiConversacionSidebar(int $id)
    {
        $alidoId = session('aliado_id_activo');

        $conversacion = WhatsappConversacion::delAliado($alidoId)
            ->activas()
            ->with(['mensajes' => fn($q) => $q->reorder()->latest()->limit(1), 'asignado'])
            ->select([
                'id', 'aliado_id', 'wa_contact_id', 'nombre_contacto',
                'total_mensajes_no_leidos', 'ultimo_mensaje_at',
                'asignado_a', 'estado', 'contrato_id',
            ])
            ->find($id);

        if (!$conversacion) {
            return response()->json(['ok' => false, 'error' => 'Conversación no encontrada'], 404);
        }

        return response()->json([
            'ok'           => true,
            'conversacion' => $this->mapearConversacionSidebar($conversacion),
        ]);
    }

    // ── Helpers privados ─────────────────────────────────────────────

    /**
     * Carga las conversaciones y el total de no leídos para el sidebar.
     * Método compartido entre index() y show() para eliminar duplicación.
     */
    private function cargarDatosSidebar(int $alidoId, ?string $tab, ?string $buscar): array
    {
        $userId  = Auth::id();
        $user    = Auth::user();
        $esAdmin = $user->es_brynex || $user->hasRole(['admin', 'superadmin']);

        $query = WhatsappConversacion::delAliado($alidoId)
            ->activas()
            ->with(['mensajes' => fn($q) => $q->reorder()->latest()->limit(1), 'asignado'])
            ->select([
                'id', 'aliado_id', 'wa_contact_id', 'nombre_contacto',
                'total_mensajes_no_leidos', 'ultimo_mensaje_at',
                'asignado_a', 'estado', 'contrato_id',
            ])
            ->orderByDesc('ultimo_mensaje_at');

        if (!$esAdmin) {
            $query->where(function ($q) use ($userId) {
                $q->where('asignado_a', $userId)->orWhereNull('asignado_a');
            });
        }

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

        // Badge total no leídos — reutiliza la misma colección, sin segunda query
        $totalNoLeidos = $conversaciones->sum('total_mensajes_no_leidos');

        // Si hay búsqueda activa, calcular el total sin filtro de búsqueda
        if ($buscar) {
            $queryNoLeidos = WhatsappConversacion::delAliado($alidoId)->activas();
            if (!$esAdmin) {
                $queryNoLeidos->where(function ($q) use ($userId) {
                    $q->where('asignado_a', $userId)->orWhereNull('asignado_a');
                });
            }
            $totalNoLeidos = $queryNoLeidos->sum('total_mensajes_no_leidos');
        }

        return compact('conversaciones', 'totalNoLeidos');
    }

    /**
     * Mapea una colección de conversaciones al formato del sidebar Alpine.js.
     */
    private function mapearConversacionesSidebar($conversaciones): array
    {
        return $conversaciones->map(fn($c) => $this->mapearConversacionSidebar($c))->toArray();
    }

    /**
     * Mapea UNA conversación al formato del sidebar Alpine.js.
     */
    private function mapearConversacionSidebar(WhatsappConversacion $c): array
    {
        return [
            'id'                       => $c->id,
            'nombre'                   => $c->nombreMostrar(),
            'celular'                  => $c->wa_contact_id,
            'total_mensajes_no_leidos' => (int) $c->total_mensajes_no_leidos,
            'ultimo_mensaje_at'        => $c->ultimo_mensaje_at?->toIso8601String(),
            'hora_display'             => $c->ultimo_mensaje_at
                ? ($c->ultimo_mensaje_at->isToday()
                    ? $c->ultimo_mensaje_at->format('H:i')
                    : $c->ultimo_mensaje_at->format('d/m'))
                : '',
            'preview'                  => $c->previewUltimoMensaje(),
            'asignado_a'               => $c->asignado_a,
            'asignado_nombre'          => $c->asignado?->nombre,
            'url_show'                 => route('admin.whatsapp.chat.show', $c->id),
        ];
    }

    /**
     * Mapea una colección de mensajes al formato Alpine.js.
     */
    private function mapearMensajes($mensajes): array
    {
        return $mensajes->map(function ($m) {
            return [
                'id'              => $m->id,
                'tipo'            => $m->tipo,
                'contenido'       => $m->contenido,
                'es_entrante'     => $m->esEntrante(),
                'usuario_nombre'  => $m->usuario?->nombre,
                'plantilla_nombre'=> $m->plantilla?->nombre_display,
                'tiene_media'     => $m->tieneMedia(),
                'media_url'       => $m->urlMedia(),
                'media_nombre'    => $m->media_nombre,
                'media_mime_type' => $m->media_mime_type,
                'hora'            => $m->created_at->format('H:i'),
                'icono_estado'    => $m->iconoEstado(),
            ];
        })->toArray();
    }

    private function enviarTexto(WhatsappConversacion $conv, array $data, WhatsappConfig $config): array
    {
        $nombreAgente = Auth::user()->nombre;
        $textoFirmado = "*Atendido por {$nombreAgente}:*\n\n" . $data['contenido'];

        $resultado = $this->apiService->enviarTexto($conv->wa_contact_id, $textoFirmado, $config);

        if (!$resultado['ok']) return $resultado;

        $mensaje = WhatsappMensaje::create([
            'conversacion_id' => $conv->id,
            'aliado_id'       => $conv->aliado_id,
            'wa_message_id'   => $resultado['wa_message_id'],
            'direccion'       => 'saliente',
            'tipo'            => 'text',
            'contenido'       => $textoFirmado,
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

        $directorio = 'whatsapp/' . now()->format('Y/m');
        $path = $archivo->store($directorio, 'local');

        $nombreAgente    = Auth::user()->nombre;
        $captionOriginal = $request->input('caption') ?? '';
        $captionFirmado  = "*Atendido por {$nombreAgente}:*" . ($captionOriginal ? "\n\n" . $captionOriginal : '');

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
            'contenido'       => $captionFirmado,
            'estado'          => 'enviado',
            'usuario_id'      => Auth::id(),
        ]);

        return ['ok' => true, 'mensaje' => $mensaje];
    }
}
