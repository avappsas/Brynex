<?php

namespace App\Events;

use App\Models\IaConfiguracionAliado;
use App\Models\WhatsappConversacion;
use Illuminate\Broadcasting\{InteractsWithSockets, PrivateChannel};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para actualizar el badge de mensajes no leídos en el menú
 * y refrescar el estado de conversaciones en el inbox.
 */
class WhatsappConversacionActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(protected WhatsappConversacion $conversacion)
    {
        // pendiente_atencion/bot_activo/asignado_nombre/atendida_por_ia se incluyen porque el
        // badge del sidebar (show.blade.php) decide qué mostrar ("Pendiente por atender" vs
        // "Atendiendo la IA" vs asesor asignado) con esos campos exactos — sin ellos el
        // frontend no tiene con qué actualizar el badge al recibir este evento.
        $atendidaPorIa = $conversacion->bot_activo
            && (bool) IaConfiguracionAliado::where('aliado_id', $conversacion->aliado_id)->value('activo_whatsapp');

        $this->payload = [
            'conversacion_id'     => $conversacion->id,
            'estado'              => $conversacion->estado,
            'asignado_a'          => $conversacion->asignado_a,
            'asignado_nombre'     => $conversacion->asignado?->nombre,
            'bot_activo'          => (bool) $conversacion->bot_activo,
            'pendiente_atencion'  => (bool) $conversacion->pendiente_atencion,
            'atendida_por_ia'     => $atendidaPorIa,
            'no_leidos'           => $conversacion->total_mensajes_no_leidos,
            'ultimo_mensaje'      => $conversacion->ultimo_mensaje_at?->toIso8601String(),
            'ventana_activa'      => $conversacion->ventanaActiva(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whatsapp-aliado.' . $this->conversacion->aliado_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversacion.actualizada';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
