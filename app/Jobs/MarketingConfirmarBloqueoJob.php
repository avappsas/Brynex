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

    /** @param string $tipoAcuse 'baja' (por defecto) o 'reactivacion'. */
    public function __construct(
        protected int $conversacionId,
        protected string $tipoAcuse = 'baja'
    ) {}

    public function handle(WhatsappApiService $whatsappApi): void
    {
        $conversacion = WhatsappConversacion::find($this->conversacionId);
        if (!$conversacion) return;

        $config = WhatsappConfig::paraAliado($conversacion->aliado_id);
        if (!$config->credencialesCompletas()) return;

        // El acuse de baja cumple tres cosas a la vez: confirma la baja, aclara que los
        // mensajes de sus trámites y pagos siguen llegando (para que no crea que se quedó
        // incomunicado), y deja una salida por si el bloqueo se disparó por error. Ese
        // "responde SÍ QUIERO" recupera a quien se arrepiente, con constancia de que lo pidió.
        $texto = $this->tipoAcuse === 'reactivacion'
            ? '¡Listo! Volverás a recibir nuestra información. Gracias por confiar en nosotros. 🙌'
            : 'Listo, no volverás a recibir publicidad nuestra. Disculpa la molestia. 🙏' . "\n\n"
                . 'Seguirás recibiendo solo lo de tus trámites y pagos.' . "\n"
                . 'Si fue un error, respóndeme *SÍ QUIERO* y te reactivo.';

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
