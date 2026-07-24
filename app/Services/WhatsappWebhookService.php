<?php

namespace App\Services;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Jobs\MarketingConfirmarBloqueoJob;
use App\Jobs\WhatsappDescargarMediaJob;
use App\Jobs\WhatsappEscalarMultimediaJob;
use App\Jobs\WhatsappResponderIaJob;
use App\Models\{
    IaConfiguracionAliado,
    MarketingBloqueado,
    WhatsappConfig,
    WhatsappConversacion,
    WhatsappMensaje
};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookService
{
    /** Segundos de silencio a esperar antes de responder, para agrupar mensajes seguidos. */
    private const SEGUNDOS_DEBOUNCE = 6;

    /**
     * Tope máximo de espera total: aunque el cliente siga escribiendo sin pausar (lo que
     * reiniciaría el debounce indefinidamente), a los este tiempo se fuerza la respuesta.
     * Evita que un mensaje de hace varios minutos quede "pendiente" y se combine con algo
     * dicho mucho después, como si fuera parte del mismo pensamiento.
     */
    private const SEGUNDOS_MAX_ESPERA_TOTAL = 20;

    /** Frases de botón (ya normalizadas: minúsculas, sin tildes) que se interpretan como rechazo de publicidad. */
    private const FRASES_NO_INTERESA = [
        'no me interesa', 'no interesa', 'no gracias', 'no quiero', 'no deseo recibir mas',
        'dejar de recibir', 'eliminarme', 'quitarme', 'no contactar', 'unsubscribe', 'stop',
    ];

    public function __construct(protected WhatsappApiService $whatsappApi) {}

    /** Detecta si el texto de un botón de plantilla equivale a un rechazo de publicidad. */
    private static function esBotonNoInteresa(string $textoBoton): bool
    {
        $normalizado = Str::ascii(mb_strtolower(trim($textoBoton)));

        foreach (self::FRASES_NO_INTERESA as $frase) {
            if (str_contains($normalizado, $frase)) return true;
        }

        return false;
    }

    /** Clave de cache compartida con WhatsappResponderIaJob para el debounce por conversación. */
    public static function claveDebounce(int $conversacionId): string
    {
        return "wa_debounce_conv_{$conversacionId}";
    }

    /** Marca cuándo empezó el lote de mensajes pendiente actual, para poder aplicar el tope máximo. */
    public static function claveInicioLote(int $conversacionId): string
    {
        return "wa_debounce_inicio_lote_{$conversacionId}";
    }

    /**
     * Procesa el payload completo de un webhook de Meta.
     * Meta puede enviar múltiples entradas en un solo request.
     */
    public function procesarPayload(array $data): void
    {
        $entries = $data['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // Verificar firma del número para identificar el aliado
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                if (!$phoneNumberId) continue;

                $config = WhatsappConfig::where('phone_number_id', $phoneNumberId)
                    ->where('activo', true)
                    ->first();

                // Si no encontramos config específica, buscar si es el número Brynex
                if (!$config) {
                    $brynexPhoneId = \App\Models\ConfiguracionBrynex::obtener('whatsapp_global_phone_number_id') ?: config('services.whatsapp.phone_number_id');
                    if ($phoneNumberId === $brynexPhoneId) {
                        // Buscar aliados que usen la cuenta Brynex — procesar para todos
                        $configs = WhatsappConfig::where('usa_cuenta_brynex', true)
                            ->where('activo', true)
                            ->get();
                        // Para el webhook, necesitamos saber a qué aliado corresponde el número del cliente
                        // Buscamos la conversación existente
                        $this->procesarParaMultipleConfigs($value, $configs->toArray());
                        continue;
                    }
                    continue;
                }

                // Mensajes entrantes
                $mensajes = $value['messages'] ?? [];
                foreach ($mensajes as $msg) {
                    $this->procesarMensajeEntrante($msg, $config->aliado_id, $config, $value);
                }

                // Actualizaciones de estado (entregado/leído)
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $status) {
                    $this->procesarActualizacionEstado($status);
                }
            }
        }
    }

    /**
     * Procesa un mensaje entrante del cliente.
     */
    public function procesarMensajeEntrante(array $msg, int $alidoId, WhatsappConfig $config, array $changeValue = []): void
    {
        $waFrom = $msg['from'] ?? null;
        $waId   = $msg['id']   ?? null;
        $tipo   = $msg['type'] ?? 'text';

        if (!$waFrom || !$waId) return;

        // Evitar duplicados (Meta puede reenviar mensajes)
        if (WhatsappMensaje::where('wa_message_id', $waId)->exists()) return;

        // Obtener o crear la conversación
        $conversacion = $this->obtenerOCrearConversacion($waFrom, $alidoId, $msg, $changeValue);

        // Construir el mensaje
        $dataMensaje = [
            'conversacion_id' => $conversacion->id,
            'aliado_id'       => $alidoId,
            'wa_message_id'   => $waId,
            'direccion'       => 'entrante',
            'tipo'            => $tipo,
        ];

        // Extraer contenido según el tipo
        switch ($tipo) {
            case 'text':
                $dataMensaje['contenido'] = $msg['text']['body'] ?? '';
                break;

            case 'image':
            case 'audio':
            case 'document':
            case 'video':
                $media = $msg[$tipo] ?? [];
                $dataMensaje['media_wa_id']    = $media['id'] ?? null;
                $dataMensaje['media_mime_type']= $media['mime_type'] ?? null;
                $dataMensaje['media_nombre']   = $media['filename'] ?? ($tipo . '_' . now()->timestamp);
                $dataMensaje['contenido']      = $media['caption'] ?? null;
                break;

            case 'button':
                $dataMensaje['contenido'] = $msg['button']['text'] ?? '';
                break;

            default:
                $dataMensaje['contenido'] = '[Tipo de mensaje no soportado: ' . $tipo . ']';
        }

        $mensaje = WhatsappMensaje::create($dataMensaje);

        // Si tiene media, programar descarga en background
        if (!empty($dataMensaje['media_wa_id']) && $config) {
            dispatch(new WhatsappDescargarMediaJob($mensaje->id, $config->aliado_id));
        }

        // Actualizar conversación
        $conversacion->renovarVentana();
        $conversacion->incrementarNoLeidos();

        // Emitir evento Reverb para actualizar el chat en tiempo real
        broadcast(new WhatsappMensajeNuevo($mensaje, $conversacion))->toOthers();
        broadcast(new WhatsappConversacionActualizada($conversacion))->toOthers();

        // Botón "No me interesa" (u otro equivalente) de una plantilla de marketing: se
        // bloquea de una vez, sin pasar por la IA — es un rechazo explícito, no algo que
        // requiera interpretación.
        $esRechazoPublicidad = $tipo === 'button' && self::esBotonNoInteresa($dataMensaje['contenido'] ?? '');
        if ($esRechazoPublicidad) {
            MarketingBloqueado::bloquear(
                $alidoId,
                $waFrom,
                'boton_no_interesa',
                'Tocó el botón "' . $dataMensaje['contenido'] . '" de una plantilla de marketing.',
                null,
                $conversacion->id
            );
            dispatch(new MarketingConfirmarBloqueoJob($conversacion->id));
        }

        // Asistente IA: solo si el bot está activo en esta conversación y el aliado
        // tiene la IA activada para WhatsApp. Se procesa en un Job para no bloquear
        // la respuesta al webhook de Meta (~20s de margen).
        if ($conversacion->bot_activo && !$esRechazoPublicidad) {
            $iaActiva = IaConfiguracionAliado::where('aliado_id', $alidoId)->value('activo_whatsapp');
            if ($iaActiva) {
                if (in_array($tipo, ['text', 'button'], true) && !empty($dataMensaje['contenido'])) {
                    // Indicador de "escribiendo" — gratis, no pasa por la IA ni consume tokens.
                    // Se apaga solo al enviar la respuesta real o a los ~25s si no se envía nada.
                    $this->whatsappApi->marcarLeidoYEscribiendo($waId, $config);

                    // Debounce: si el cliente manda varios mensajes seguidos, esperamos
                    // unos segundos de silencio y los respondemos todos juntos en un solo
                    // turno de IA. El token es el id de este mensaje; si llega uno más
                    // nuevo antes de cumplirse la espera, ese token queda desactualizado y
                    // este job se aborta solo (ver WhatsappResponderIaJob::handle()) — el
                    // job programado para el mensaje más reciente es el que agrupa todo.
                    //
                    // Tope máximo: si el cliente no deja de escribir, el debounce se
                    // reiniciaría sin fin y un mensaje de hace varios minutos (ej. una
                    // petición vieja de planilla) terminaría combinado con algo dicho mucho
                    // después. Por eso se marca cuándo empezó el lote actual, y si ya pasó
                    // el tope, se fuerza la respuesta casi de inmediato en vez de esperar de nuevo.
                    $claveInicioLote = self::claveInicioLote($conversacion->id);
                    $inicioLote = Cache::get($claveInicioLote);
                    if (!$inicioLote) {
                        $inicioLote = now();
                        Cache::put($claveInicioLote, $inicioLote, now()->addSeconds(self::SEGUNDOS_MAX_ESPERA_TOTAL + self::SEGUNDOS_DEBOUNCE + 10));
                    }
                    $superoTope = now()->diffInSeconds($inicioLote) >= self::SEGUNDOS_MAX_ESPERA_TOTAL;
                    $delay = $superoTope ? 1 : self::SEGUNDOS_DEBOUNCE;

                    Cache::put(self::claveDebounce($conversacion->id), $mensaje->id, now()->addSeconds(30));
                    WhatsappResponderIaJob::dispatch($conversacion->id, $mensaje->id)
                        ->delay(now()->addSeconds($delay));
                } elseif (in_array($tipo, ['image', 'audio', 'document', 'video'], true)) {
                    // El bot no puede leer multimedia: avisa al cliente y escala a un humano
                    // en vez de quedarse en silencio (ej. comprobantes de pago requieren revisión humana).
                    dispatch(new WhatsappEscalarMultimediaJob($conversacion->id, $tipo));
                }
            }
        }
    }

    /**
     * Procesa una actualización de estado de mensaje saliente.
     * Meta notifica cuando un mensaje fue: enviado, entregado o leído.
     */
    public function procesarActualizacionEstado(array $status): void
    {
        $waMessageId = $status['id']     ?? null;
        $nuevoEstado = $status['status'] ?? null;

        if (!$waMessageId || !$nuevoEstado) return;

        $mapEstados = [
            'sent'      => 'enviado',
            'delivered' => 'entregado',
            'read'      => 'leido',
            'failed'    => 'fallido',
        ];

        $estadoLocal = $mapEstados[$nuevoEstado] ?? null;
        if (!$estadoLocal) return;

        $mensaje = WhatsappMensaje::where('wa_message_id', $waMessageId)->first();
        if (!$mensaje) {
            // Incluso si el mensaje no existe en la BD local, loguear la falla de Meta para auditoría
            if ($estadoLocal === 'fallido') {
                Log::error('WhatsApp webhook (auditoría): Mensaje sin registro local falló en Meta', [
                    'wa_message_id' => $waMessageId,
                    'errors' => $status['errors'] ?? 'Sin detalles de error',
                ]);
            }
            return;
        }

        if ($estadoLocal === 'fallido') {
            Log::error("WhatsApp webhook: Mensaje ID {$mensaje->id} falló en Meta", [
                'wa_message_id' => $waMessageId,
                'errors' => $status['errors'] ?? 'Sin detalles de error',
            ]);
        }

        $mensaje->update([
            'estado'    => $estadoLocal,
            'estado_at' => now(),
        ]);

        // Actualizar el estado del detalle de envío masivo si existe
        if ($waMessageId) {
            $detalle = \App\Models\WhatsappEnvioMasivoDetalle::where('wa_message_id', $waMessageId)->first();
            if ($detalle) {
                $detalle->update([
                    'estado' => $estadoLocal,
                    'error'  => $estadoLocal === 'fallido' ? (json_encode($status['errors'] ?? 'Fallo reportado por Meta')) : null,
                ]);

                // Ajustar contadores de la cabecera del lote masivo
                $envio = $detalle->envio;
                if ($envio) {
                    if ($estadoLocal === 'fallido') {
                        $envio->increment('total_fallidos');
                        if ($envio->total_enviados > 0) {
                            $envio->decrement('total_enviados');
                        }
                    }
                }
            }
        }

        // Emitir evento para actualizar ícono de estado en el chat
        broadcast(new WhatsappConversacionActualizada($mensaje->conversacion))->toOthers();
    }

    /**
     * Obtiene una conversación existente o crea una nueva.
     * Intenta vincularla con un contrato/cliente si el número coincide.
     */
    public function obtenerOCrearConversacion(
        string $waFrom,
        int $alidoId,
        array $msgData = [],
        array $changeValue = []
    ): WhatsappConversacion {
        // Buscar cualquier conversación existente (incluyendo cerrada) para este contacto
        $conversacion = WhatsappConversacion::where('aliado_id', $alidoId)
            ->where('wa_contact_id', $waFrom)
            ->first();

        if ($conversacion) {
            // Si la conversación ya existe pero tiene un nombre genérico o vacío, intentar corregirlo
            $nombreActual = $conversacion->nombre_contacto;
            if (!$nombreActual || strtolower(trim($nombreActual)) === 'contacto de prueba') {
                $nombreContacto = null;
                $contacts = $changeValue['contacts'] ?? $msgData['contacts'] ?? [];
                foreach ($contacts as $contact) {
                    if (($contact['wa_id'] ?? '') === $waFrom) {
                        $nombreContacto = $contact['profile']['name'] ?? null;
                        break;
                    }
                }
                if (!$nombreContacto && !empty($contacts)) {
                    $nombreContacto = $contacts[0]['profile']['name'] ?? $msgData['profile']['name'] ?? null;
                }

                // Buscar en BD por número celular
                $numeroLimpio = preg_replace('/[^0-9]/', '', $waFrom);
                $clienteConCelular = \App\Models\Cliente::where('aliado_id', $alidoId)
                    ->where(function ($q) use ($numeroLimpio) {
                        $q->where('celular', $numeroLimpio)
                          ->orWhere('celular', '+57' . $numeroLimpio)
                          ->orWhere('celular', 'like', '%' . substr($numeroLimpio, -10));
                    })
                    ->first();

                $nuevoNombre = null;
                if ($clienteConCelular) {
                    $nuevoNombre = trim(
                        ($clienteConCelular->primer_nombre ?? '') . ' ' .
                        ($clienteConCelular->primer_apellido ?? '')
                    );
                }

                if (!$nuevoNombre) {
                    $nuevoNombre = $nombreContacto;
                }

                if ($nuevoNombre && $nuevoNombre !== $nombreActual) {
                    $conversacion->update(['nombre_contacto' => $nuevoNombre]);
                }
            }

            // Si la conversación estaba cerrada, la reabrimos asignada al último agente que le contestó
            if ($conversacion->estado === 'cerrada') {
                $ultimoMensajeSaliente = WhatsappMensaje::where('conversacion_id', $conversacion->id)
                    ->where('direccion', 'saliente')
                    ->whereNotNull('usuario_id')
                    ->latest()
                    ->first();

                if ($ultimoMensajeSaliente) {
                    $conversacion->update([
                        'estado'     => 'asignada',
                        'asignado_a' => $ultimoMensajeSaliente->usuario_id,
                    ]);
                } else {
                    $conversacion->update([
                        'estado'     => 'abierta',
                        'asignado_a' => null,
                    ]);
                }
            }
            return $conversacion;
        }

        // Buscar nombre del perfil en los datos del webhook
        $nombreContacto = null;
        $contacts = $changeValue['contacts'] ?? $msgData['contacts'] ?? [];
        foreach ($contacts as $contact) {
            if (($contact['wa_id'] ?? '') === $waFrom) {
                $nombreContacto = $contact['profile']['name'] ?? null;
                break;
            }
        }
        if (!$nombreContacto) {
            $nombreContacto = $contacts[0]['profile']['name'] ?? $msgData['profile']['name'] ?? null;
        }

        // Intentar vincular con un contrato/cliente y/o empresa por número de celular
        $numeroLimpio = preg_replace('/[^0-9]/', '', $waFrom);
        $contrato = null;
        $empresa  = null;

        // 1. Buscar en la BD (por celular del cliente)
        $clienteConCelular = \App\Models\Cliente::where('aliado_id', $alidoId)
            ->where(function ($q) use ($numeroLimpio) {
                $q->where('celular', $numeroLimpio)
                  ->orWhere('celular', '+57' . $numeroLimpio)
                  ->orWhere('celular', 'like', '%' . substr($numeroLimpio, -10));
            })
            ->first();

        if ($clienteConCelular) {
            // Buscar el contrato vigente de este cliente
            $contrato = \App\Models\Contrato::where('aliado_id', $alidoId)
                ->where('cedula', $clienteConCelular->cedula)
                ->whereIn('estado', ['vigente', 'activo'])
                ->first();

            if (!$nombreContacto) {
                $nombreContacto = trim(
                    ($clienteConCelular->primer_nombre ?? '') . ' ' .
                    ($clienteConCelular->primer_apellido ?? '')
                );
            }
        }

        // 2. Buscar en la BD (por celular o teléfono de la empresa)
        $empresaConTelefono = \App\Models\Empresa::where('aliado_id', $alidoId)
            ->where(function ($q) use ($numeroLimpio) {
                $q->where('celular', $numeroLimpio)
                  ->orWhere('celular', '+57' . $numeroLimpio)
                  ->orWhere('celular', 'like', '%' . substr($numeroLimpio, -10))
                  ->orWhere('telefono', $numeroLimpio)
                  ->orWhere('telefono', '+57' . $numeroLimpio)
                  ->orWhere('telefono', 'like', '%' . substr($numeroLimpio, -10));
            })
            ->first();

        if ($empresaConTelefono) {
            $empresa = $empresaConTelefono;
            if (!$nombreContacto) {
                $nombreContacto = $empresaConTelefono->contacto ?: $empresaConTelefono->empresa;
            }
        }

        $campana = $this->buscarCampanaOrigen($numeroLimpio, $alidoId);
        $publicacionOrigenId = $this->buscarPublicacionOrigen($msgData['text']['body'] ?? '', $alidoId);

        return WhatsappConversacion::create([
            'aliado_id'                 => $alidoId,
            'wa_contact_id'             => $waFrom,
            'nombre_contacto'           => $nombreContacto,
            'contrato_id'               => $contrato?->id,
            'empresa_id'                => $empresa?->id,
            'origen_campana'            => $campana['nombre'],
            'origen_campana_categoria'  => $campana['categoria'],
            'origen_campana_id'         => $campana['campana_id'],
            'origen_publicacion_id'     => $publicacionOrigenId,
            'estado'                    => 'abierta',
            // Explícito porque el default de BD no se refleja en el objeto en memoria que
            // devuelve create() — sin esto, bot_activo queda null aquí y la IA no responde
            // al primer mensaje de un contacto nuevo (bot_activo=null es falsy en el if).
            'bot_activo'                => true,
        ]);
    }

    /**
     * Si el número respondió recientemente (últimos 7 días) a una plantilla de un envío
     * masivo, devuelve su nombre y categoría de Meta (MARKETING|UTILITY|AUTHENTICATION)
     * para dar contexto a la IA (ej. "respondió a una campaña de marketing" vs "respondió
     * a un recordatorio de cobro"). No aplica a conversaciones que ya existían.
     *
     * Si el envío pertenece a una campaña de marketing, también se devuelve su
     * campana_id para que la IA reciba el contexto de venta completo de esa campaña
     * (descripción, objetivo y guía de botones) en vez de partir de cero.
     *
     * @return array{nombre: ?string, categoria: ?string, campana_id: ?int}
     */
    /**
     * Si el primer mensaje del contacto trae el código "ref: P{id}" (el que se agrega
     * automáticamente al link de WhatsApp de cada pieza publicada en redes), atribuye la
     * conversación a esa publicación concreta — atribución real, no una correlación por
     * ventana de tiempo. Solo se llama al CREAR una conversación nueva.
     */
    private function buscarPublicacionOrigen(string $texto, int $alidoId): ?int
    {
        if (!preg_match('/ref:\s*P(\d+)/i', $texto, $m)) {
            return null;
        }

        return \App\Models\Publicacion::where('id', (int) $m[1])
            ->where('aliado_id', $alidoId)
            ->value('id');
    }

    private function buscarCampanaOrigen(string $numeroLimpio, int $alidoId): array
    {
        $detalle = \App\Models\WhatsappEnvioMasivoDetalle::query()
            ->where(function ($q) use ($numeroLimpio) {
                $q->where('wa_numero', $numeroLimpio)
                  ->orWhere('wa_numero', '+57' . $numeroLimpio)
                  ->orWhere('wa_numero', 'like', '%' . substr($numeroLimpio, -10));
            })
            ->whereIn('estado', ['enviado', 'entregado', 'leido'])
            ->whereHas('envio', fn ($q) => $q->where('aliado_id', $alidoId))
            ->where('created_at', '>=', now()->subDays(7))
            ->with('envio.plantilla:id,nombre_display,categoria')
            ->latest()
            ->first();

        return [
            'nombre'     => $detalle?->envio?->plantilla?->nombre_display,
            'categoria'  => $detalle?->envio?->plantilla?->categoria,
            'campana_id' => $detalle?->envio?->campana_id,
        ];
    }

    /**
     * Cuando múltiples aliados comparten el número Brynex,
     * buscamos a qué aliado pertenece la conversación por el número del cliente.
     */
    private function procesarParaMultipleConfigs(array $value, array $configs): void
    {
        // Para el caso multi-aliado con número compartido,
        // buscamos conversaciones existentes y procesamos la que coincida.
        $mensajes = $value['messages'] ?? [];

        foreach ($mensajes as $msg) {
            $waFrom = $msg['from'] ?? null;
            if (!$waFrom) continue;

            // Buscar si ya existe una conversación activa para este número (ordenando por la más reciente usando COALESCE para evitar problemas con NULLs)
            $convExistente = WhatsappConversacion::where('wa_contact_id', $waFrom)
                ->whereIn('aliado_id', array_column($configs, 'aliado_id'))
                ->whereIn('estado', ['abierta', 'asignada'])
                ->orderByRaw('COALESCE(ultimo_mensaje_at, updated_at) DESC')
                ->first();

            if ($convExistente) {
                $config = WhatsappConfig::where('aliado_id', $convExistente->aliado_id)->first();
                if ($config) {
                    $this->procesarMensajeEntrante($msg, $convExistente->aliado_id, $config, $value);
                }
            }
            // Si no hay conversación existente, el mensaje se pierde en modo multi-aliado compartido
            // (se requiere configuración de número propio por aliado para mensajes nuevos)
        }
    }
}
