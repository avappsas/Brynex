<?php

namespace App\Jobs;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Models\{IaConfiguracionAliado, WhatsappConfig, WhatsappConversacion, WhatsappMensaje};
use App\Services\Ia\AsistenteIaService;
use App\Services\WhatsappApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Genera y envía la respuesta del Asistente IA a un mensaje entrante de WhatsApp.
 * Se ejecuta en background (igual que WhatsappDescargarMediaJob) para no bloquear
 * el webhook de Meta, que espera una respuesta rápida.
 *
 * Si la IA falla (sin crédito, error de proveedor, etc.), la conversación se escala
 * a un humano en vez de quedar en silencio — nadie debe quedar sin respuesta.
 */
class WhatsappResponderIaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(protected int $conversacionId, protected string $textoUsuario) {}

    public function handle(AsistenteIaService $asistenteIa, WhatsappApiService $whatsappApi): void
    {
        $conversacion = WhatsappConversacion::find($this->conversacionId);
        if (!$conversacion || !$conversacion->bot_activo) return;

        $iaConfig = IaConfiguracionAliado::where('aliado_id', $conversacion->aliado_id)->first();
        if (!$iaConfig || !$iaConfig->activo_whatsapp) return;

        $config = WhatsappConfig::paraAliado($conversacion->aliado_id);
        if (!$config->credencialesCompletas()) return;

        $nombreBot = $iaConfig->nombreBot();

        try {
            $resultado = $asistenteIa->responderWhatsapp(
                $conversacion->aliado_id,
                $conversacion->wa_contact_id,
                $this->textoUsuario,
                $conversacion->id,
                $conversacion->origen_campana
            );
        } catch (\Exception $e) {
            Log::warning('IA WhatsApp: no se pudo generar respuesta, se escala a un humano', [
                'error' => $e->getMessage(),
                'conversacion_id' => $conversacion->id,
            ]);

            $conversacion->escalarAHumano('El asistente tuvo un problema técnico y no pudo responder.');
            $this->enviarYRegistrar(
                $conversacion,
                $config,
                $whatsappApi,
                $nombreBot,
                'Disculpa, estoy teniendo un inconveniente técnico. Ya avisé a nuestro equipo para que te '
                . 'contacte en breve. 🙏'
            );
            return;
        }

        // La conversación pudo haber sido tomada por un humano mientras se generaba la respuesta.
        if (!$conversacion->fresh()->bot_activo) return;

        $this->enviarYRegistrar($conversacion, $config, $whatsappApi, $nombreBot, $resultado['respuesta']);
    }

    /** Envía un texto firmado con el nombre del bot y registra el mensaje saliente. No relanza si falla el envío. */
    private function enviarYRegistrar(
        WhatsappConversacion $conversacion,
        WhatsappConfig $config,
        WhatsappApiService $whatsappApi,
        string $nombreBot,
        string $texto
    ): void {
        $textoFirmado = "🤖 *{$nombreBot}:*\n" . $texto;

        $envio = $whatsappApi->enviarTexto($conversacion->wa_contact_id, $textoFirmado, $config);
        if (!$envio['ok']) {
            Log::warning('IA WhatsApp: fallo al enviar mensaje', [
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
            'contenido'       => $textoFirmado,
            'estado'          => 'enviado',
            'es_bot'          => true,
        ]);

        $conversacion->update(['ultimo_mensaje_at' => now()]);

        broadcast(new WhatsappMensajeNuevo($mensajeBot, $conversacion));
        broadcast(new WhatsappConversacionActualizada($conversacion));
    }
}
