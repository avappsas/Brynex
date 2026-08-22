<?php

namespace App\Http\Controllers\Admin;

use App\Events\WhatsappConversacionActualizada;
use App\Http\Controllers\Controller;
use App\Models\{IaConfiguracionAliado, MarketingBloqueado, User, WhatsappConfig, WhatsappConversacion, WhatsappMensaje};
use App\Services\Finanzas\TelefonosDeudores;
use App\Services\WhatsappApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Storage};

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

        ['conversaciones' => $conversaciones, 'totalNoLeidos' => $totalNoLeidos, 'totalIa' => $totalIa] =
            $this->cargarDatosSidebar($alidoId, $tab, $buscar);

        // Usuarios del aliado para la asignación
        $usuarios = User::where('aliado_id', $alidoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.whatsapp.chat.index', compact(
            'conversaciones', 'tab', 'buscar', 'totalNoLeidos', 'totalIa', 'usuarios'
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

        $conversacion = $this->findConversacionProtected($alidoId, $id);
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

        $config = WhatsappConfig::paraAliado($alidoId);
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantillas = \App\Models\WhatsappPlantilla::delAliado($effectiveAliadoId)
            ->aprobadas()
            ->select('id', 'nombre', 'nombre_display', 'cuerpo', 'variables_mapa')
            ->get();

        // Sidebar — método compartido, sin duplicar lógica
        ['conversaciones' => $conversaciones, 'totalNoLeidos' => $totalNoLeidos, 'totalIa' => $totalIa] =
            $this->cargarDatosSidebar($alidoId, $tab, $buscar);

        // Mapear conversaciones para Alpine.js
        $conversacionesData = $this->mapearConversacionesSidebar($conversaciones);

        $mensajesData = $this->mapearMensajes($mensajes);

        $conversacionData = [
            'id'                 => $conversacion->id,
            'nombre'             => $conversacion->nombreMostrar(),
            'celular'            => $conversacion->wa_contact_id,
            'contrato_id'        => $conversacion->contrato_id,
            'contrato_url'       => $conversacion->cliente_url,
            'estado'             => $conversacion->estado,
            'asignado_a'         => $conversacion->asignado_a,
            'asignado_nombre'    => $conversacion->asignado?->nombre,
            'bot_activo'         => $conversacion->bot_activo,
            'atendida_por_ia'    => $this->esAtendidaPorIa($conversacion),
            'pendiente_atencion' => $conversacion->pendiente_atencion,
            'pendiente_motivo'   => $conversacion->pendiente_motivo,
            'ventana_activa'     => $conversacion->ventanaActiva(),
            'ventana_minutos'    => $conversacion->minutosVentanaRestante(),
        ];

        return view('admin.whatsapp.chat.show', compact(
            'conversacion', 'mensajes', 'usuarios', 'plantillas',
            'conversaciones', 'conversacionesData', 'tab', 'buscar', 'totalNoLeidos', 'totalIa',
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
        $conversacion = $this->findConversacionProtected($alidoId, $id);
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

        // Autoasignación al agente emisor actual. Si un humano escribe, el bot se silencia
        // en esta conversación hasta que alguien lo reactive explícitamente, y se resuelve
        // cualquier aviso de "pendiente por atender".
        $conversacion->update([
            'asignado_a'          => Auth::id(),
            'estado'              => 'asignada',
            'ultimo_mensaje_at'   => now(),
            'bot_activo'          => false,
            'pendiente_atencion'  => false,
            'pendiente_motivo'    => null,
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
        $conversacion = $this->findConversacionProtected($alidoId, $id);

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

        // Sin esto, otros asesores viendo el mismo inbox nunca se enteran de la asignación
        // hasta que refrescan la página a mano — el sidebar no tenía ninguna señal en tiempo real.
        broadcast(new WhatsappConversacionActualizada($conversacion))->toOthers();

        return response()->json(['ok' => true, 'mensaje' => $msg]);
    }

    /**
     * Activa o desactiva el Asistente IA en una conversación puntual.
     */
    /**
     * Reactiva la IA, o la silencia y toma la conversación para el usuario actual
     * (apagar el bot desde el chat significa "voy a atenderla yo").
     */
    public function toggleBot(Request $request, int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = $this->findConversacionProtected($alidoId, $id);

        $validated = $request->validate(['activo' => 'required|boolean']);
        $iaActivaAliado = IaConfiguracionAliado::where('aliado_id', $alidoId)->value('activo_whatsapp') ?? false;

        if ($validated['activo']) {
            $conversacion->activarBot();
            $mensaje = $iaActivaAliado
                ? 'Asistente IA reactivado en esta conversación'
                : 'Reactivado aquí, pero el Asistente IA está apagado para todo el aliado en /brynex/ia — no responderá hasta que lo actives ahí.';
        } else {
            $conversacion->tomarConversacion(Auth::id());
            $mensaje = 'Tomaste esta conversación — el Asistente IA ya no responderá aquí';
        }

        $conversacion->refresh();

        // Mismo motivo que en asignar(): sin esto, otros asesores viendo el mismo inbox no se
        // enteran de que alguien tomó/reactivó la IA hasta que recargan la página.
        broadcast(new WhatsappConversacionActualizada($conversacion))->toOthers();

        return response()->json([
            'ok'                 => true,
            'bot_activo'         => $conversacion->bot_activo,
            'atendida_por_ia'    => $iaActivaAliado && $conversacion->bot_activo,
            'pendiente_atencion' => $conversacion->pendiente_atencion,
            'asignado_a'         => $conversacion->asignado_a,
            'asignado_nombre'    => $conversacion->asignado?->nombre,
            'estado'             => $conversacion->estado,
            'mensaje'            => $mensaje,
        ]);
    }

    /**
     * Cierra una conversación.
     */
    public function cerrar(int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = $this->findConversacionProtected($alidoId, $id);
        $conversacion->cerrar();

        return response()->json(['ok' => true]);
    }

    /**
     * Bloquea el número de esta conversación para que nunca más reciba campañas de
     * marketing (no afecta la conversación actual ni el servicio ya en curso).
     */
    public function noContactar(Request $request, int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = $this->findConversacionProtected($alidoId, $id);

        $validated = $request->validate(['motivo' => 'nullable|string|max:500']);

        MarketingBloqueado::bloquear(
            $alidoId,
            $conversacion->wa_contact_id,
            'asesor',
            $validated['motivo'] ?? 'Bloqueado manualmente desde el chat',
            Auth::id(),
            $conversacion->id
        );

        return response()->json(['ok' => true, 'mensaje' => 'Número bloqueado de futuras campañas de marketing']);
    }

    /**
     * Marca los mensajes de una conversación como leídos.
     */
    public function marcarLeido(int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = $this->findConversacionProtected($alidoId, $id);
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

        $this->excluirDeudoresDelDueno($query, $userId);

        return response()->json(['total' => (int) $query->sum('total_mensajes_no_leidos')]);
    }

    /**
     * API: retorna los mensajes e información de una conversación (para cambio de chat SPA).
     * Limitado a los últimos 150 mensajes para máxima velocidad.
     */
    public function apiMensajes(int $id)
    {
        $alidoId      = session('aliado_id_activo');
        $conversacion = $this->findConversacionProtected($alidoId, $id);
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
                'id'                 => $conversacion->id,
                'nombre'             => $conversacion->nombreMostrar(),
                'celular'            => $conversacion->wa_contact_id,
                'contrato_id'        => $conversacion->contrato_id,
                'contrato_url'       => $conversacion->cliente_url,
                'estado'             => $conversacion->estado,
                'asignado_a'         => $conversacion->asignado_a,
                'asignado_nombre'    => $conversacion->asignado?->nombre,
                'bot_activo'         => $conversacion->bot_activo,
                'atendida_por_ia'    => $this->esAtendidaPorIa($conversacion),
                'pendiente_atencion' => $conversacion->pendiente_atencion,
                'pendiente_motivo'   => $conversacion->pendiente_motivo,
                'ventana_activa'     => $conversacion->ventanaActiva(),
                'ventana_minutos'    => $conversacion->minutosVentanaRestante(),
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
                'bot_activo', 'pendiente_atencion', 'pendiente_motivo',
            ])
            ->find($id);

        if (!$conversacion) {
            return response()->json(['ok' => false, 'error' => 'Conversación no encontrada'], 404);
        }

        $this->verificarAccesoConversacion($conversacion);

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
                'bot_activo', 'pendiente_atencion', 'pendiente_motivo',
            ])
            ->orderByDesc('ultimo_mensaje_at');

        $this->excluirDeudoresDelDueno($query, $userId);

        if (!$esAdmin) {
            $query->where(function ($q) use ($userId) {
                $q->where('asignado_a', $userId)->orWhereNull('asignado_a');
            });
        }

        if ($tab === 'mias') {
            $query->where('asignado_a', $userId);
        } elseif ($tab === 'ia') {
            $query->atendidasPorIa();
        }

        if ($buscar) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre_contacto', 'like', "%{$buscar}%")
                  ->orWhere('wa_contact_id', 'like', "%{$buscar}%");
            });
        }

        $conversaciones = $query->get();

        // "Atendida por IA" de verdad = permiso en la conversación (bot_activo) Y el
        // aliado tiene la IA de WhatsApp encendida. Sin esto, una conversación con
        // bot_activo=true por una prueba/reactivación se etiqueta como IA aunque el
        // interruptor general esté apagado y el bot nunca vaya a responder.
        // `foreach`, no `each()`: el closure devolvía el valor asignado, y en cuanto
        // una conversación daba `false` (la IA no la atiende), Collection::each
        // interpretaba ese false como "corta la iteración" y dejaba al resto en null.
        // Cada una de esas caía después en esAtendidaPorIa(), una consulta por fila:
        // 78 viajes a ia_configuracion_aliado, ~15 s de la carga del chat.
        $iaActivaAliado = IaConfiguracionAliado::where('aliado_id', $alidoId)->value('activo_whatsapp') ?? false;

        foreach ($conversaciones as $c) {
            $c->atendida_por_ia = $iaActivaAliado && (bool) $c->bot_activo;
        }

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

        // Contador para la pestaña "IA" (independiente del tab actual)
        $totalIa = WhatsappConversacion::delAliado($alidoId)->activas()->atendidasPorIa()->count();

        return compact('conversaciones', 'totalNoLeidos', 'totalIa');
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
    /** ¿La IA realmente atenderá esta conversación? (permiso local Y aliado con WhatsApp IA encendido). */
    private function esAtendidaPorIa(WhatsappConversacion $c): bool
    {
        if (!$c->bot_activo) return false;
        return (bool) IaConfiguracionAliado::where('aliado_id', $c->aliado_id)->value('activo_whatsapp');
    }

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
            'bot_activo'               => (bool) $c->bot_activo,
            'atendida_por_ia'          => (bool) ($c->atendida_por_ia ?? $this->esAtendidaPorIa($c)),
            'pendiente_atencion'       => (bool) $c->pendiente_atencion,
            'pendiente_motivo'         => $c->pendiente_motivo,
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
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantilla = \App\Models\WhatsappPlantilla::delAliado($effectiveAliadoId)->findOrFail($data['plantilla_id']);
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

    private function findConversacionProtected(int $alidoId, int $id): WhatsappConversacion
    {
        $conversacion = WhatsappConversacion::delAliado($alidoId)->findOrFail($id);
        $this->verificarAccesoConversacion($conversacion);
        return $conversacion;
    }

    private function verificarAccesoConversacion(WhatsappConversacion $conversacion): void
    {
        $duenoId = TelefonosDeudores::duenoId();

        if ($duenoId
            && Auth::id() !== $duenoId
            && TelefonosDeudores::esDeudor($conversacion->wa_contact_id)) {
            abort(403, 'No autorizado para acceder a esta conversación.');
        }
    }

    /**
     * Privacidad: las conversaciones con deudores del dueño del módulo de
     * finanzas no se le muestran al resto de usuarios.
     *
     * Antes esto eran N condiciones `not like '%<telefono>'`. El comodín al
     * inicio impide usar índice y obliga a comparar patrón por patrón contra
     * cada fila: medido el 21-ago-2026 sobre 908 conversaciones y 15 teléfonos,
     * costaba **13,8 ms** por consulta, casi una cuarta parte del tiempo total
     * de la petición del badge — que se sondea cada 30 s por pestaña abierta.
     *
     * Recortar a los últimos 10 dígitos y comparar por pertenencia baja lo
     * mismo a **0,88 ms**, 15 veces menos, sin cambiar el esquema ni lo que
     * filtra. Si algún día crece mucho, el siguiente paso es una columna
     * calculada PERSISTED con índice.
     *
     * Nota: una conversación con `wa_contact_id` nulo queda excluida, porque
     * `NULL NOT IN (...)` es UNKNOWN. Es el mismo comportamiento que tenía la
     * versión con `not like`, así que no cambia nada.
     */
    private function excluirDeudoresDelDueno($query, ?int $userId): void
    {
        $duenoId = TelefonosDeudores::duenoId();

        if (!$duenoId || $userId === $duenoId) {
            return;
        }

        $telefonos = TelefonosDeudores::ultimos10();

        if (empty($telefonos)) {
            return;
        }

        $query->whereNotIn(DB::raw('RIGHT(wa_contact_id, 10)'), $telefonos);
    }
}
