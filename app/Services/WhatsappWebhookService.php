<?php

namespace App\Services;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Jobs\WhatsappDescargarMediaJob;
use App\Models\{
    WhatsappConfig,
    WhatsappConversacion,
    WhatsappMensaje
};
use Illuminate\Support\Facades\Log;

class WhatsappWebhookService
{
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
                    $brynexPhoneId = config('services.whatsapp.phone_number_id');
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
        if (!$mensaje) return;

        $mensaje->update([
            'estado'    => $estadoLocal,
            'estado_at' => now(),
        ]);

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
        $conversacion = WhatsappConversacion::where('aliado_id', $alidoId)
            ->where('wa_contact_id', $waFrom)
            ->whereIn('estado', ['abierta', 'asignada'])
            ->first();

        if ($conversacion) return $conversacion;

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

        // Intentar vincular con un contrato/cliente por número de celular
        $numeroLimpio = preg_replace('/[^0-9]/', '', $waFrom);
        $contrato = null;
        $empresa  = null;

        // Buscar en la BD (por celular del cliente)
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

        return WhatsappConversacion::create([
            'aliado_id'       => $alidoId,
            'wa_contact_id'   => $waFrom,
            'nombre_contacto' => $nombreContacto,
            'contrato_id'     => $contrato?->id,
            'empresa_id'      => $empresa?->id,
            'estado'          => 'abierta',
        ]);
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

            // Buscar si ya existe una conversación activa para este número
            $convExistente = WhatsappConversacion::where('wa_contact_id', $waFrom)
                ->whereIn('aliado_id', array_column($configs, 'aliado_id'))
                ->whereIn('estado', ['abierta', 'asignada'])
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
