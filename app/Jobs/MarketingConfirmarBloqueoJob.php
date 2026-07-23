<?php

namespace App\Jobs;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Models\{WhatsappConfig, WhatsappConversacion, WhatsappMensaje};
use App\Services\WhatsappApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Envía el acuse de bloqueo cuando el cliente toca el botón "No me interesa" (u otro
 * equivalente) de una plantilla de marketing. Se dispara desde WhatsappWebhookService,
 * fuera del ciclo normal de respuesta del Asistente IA, para no gastar tokens en algo
 * que ya sabemos que es un rechazo.
 */
class MarketingConfirmarBloqueoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(protected int $conversacionId) {}

    public function handle(WhatsappApiService $whatsappApi): void
    {
        $conversacion = WhatsappConversacion::find($this->conversacionId);
        if (!$conversacion) return;

        $config = WhatsappConfig::paraAliado($conversacion->aliado_id);
        if (!$config->credencialesCompletas()) return;

        $texto = 'Listo, no volverás a recibir mensajes publicitarios de nuestra parte. Disculpa la molestia. 🙏';

        $envio = $whatsappApi->enviarTexto($conversacion->wa_contact_id, $texto, $config);
        if (!$envio['ok']) {
            Log::warning('Marketing: fallo al enviar acuse de bloqueo por botón', [
                'error' => $envio['error'] ?? null,
                'conversacion_id' => $conversacion->id,
            ]);
            return;
        }

        $mensajeBot = WhatsappMensaje::create([
            'conversacion_id' => $conversacion->id,
            'aliado_id'       => $conversacion->aliado_id,
            'wa_message_id'   => $envio['wa_message_id'],
            'direccion'       => 'saliente',
            'tipo'            => 'text',
            'contenido'       => $texto,
            'estado'          => 'enviado',
            'es_bot'          => true,
        ]);

        $conversacion->update(['ultimo_mensaje_at' => now()]);

        broadcast(new WhatsappMensajeNuevo($mensajeBot, $conversacion));
        broadcast(new WhatsappConversacionActualizada($conversacion));
    }
}
