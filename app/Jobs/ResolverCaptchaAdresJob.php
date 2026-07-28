<?php

namespace App\Jobs;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Models\{AdresChequeo, IaConfiguracionAliado, WhatsappConfig, WhatsappConversacion, WhatsappMensaje};
use App\Services\Adres\{ChequeoService, EnvioCaptcha, RedactorDiagnostico};
use App\Services\WhatsappApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * El cliente respondió el código de seguridad de ADRES: se completa la consulta
 * y se le entrega el resultado traducido.
 *
 * Va en un job porque la consulta abre página, resuelve y descarga un PDF; el
 * webhook de Meta tiene ~20s de margen y no se puede quedar esperando eso.
 *
 * Si el código estaba mal, ADRES rota la imagen — hay que mandarle la NUEVA, no
 * repetir la anterior.
 */
class ResolverCaptchaAdresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;      // reintentar solo dispararía otra consulta a ADRES
    public int $timeout = 180;

    public function __construct(protected int $chequeoId, protected string $texto)
    {
    }

    public function handle(WhatsappApiService $whatsappApi): void
    {
        $chequeo = AdresChequeo::with('conversacion')->find($this->chequeoId);
        if (!$chequeo || !$chequeo->esperaCaptcha()) {
            return;
        }

        $conversacion = $chequeo->conversacion;
        if (!$conversacion) {
            return;
        }

        $r = (new ChequeoService())->resolverCaptcha($chequeo, $this->texto);

        // Código errado y todavía quedan intentos: se reenvía la imagen nueva.
        if (!$r['ok'] && !empty($r['reintentar'])) {
            $restantes = max(0, 3 - $r['chequeo']->intentos);
            (new EnvioCaptcha($whatsappApi))->enviar(
                $r['chequeo'],
                $r['captcha_png'],
                EnvioCaptcha::encabezadoReintento($restantes)
            );
            return;
        }

        if (!$r['ok']) {
            $this->responder(
                $whatsappApi,
                $conversacion,
                "No pude completar la consulta en ADRES 😕\n\n"
                . 'Ya le avisé a un asesor para que la haga manualmente y te cuente. Disculpa la demora.'
            );
            $conversacion->escalarAHumano('Chequeo ADRES fallido: ' . ($r['error'] ?? 'sin detalle'));
            return;
        }

        $this->responder($whatsappApi, $conversacion, RedactorDiagnostico::paraWhatsapp($r['chequeo']));

        // Un hallazgo grave no lo cierra el bot: puede implicar que otro operador
        // está fallando, y esa conversación la tiene que llevar una persona.
        if (!empty($r['chequeo']->diagnostico['requiere_asesor'])) {
            $conversacion->escalarAHumano(
                'Chequeo ADRES con hallazgos que requieren revisión (chequeo #' . $r['chequeo']->id . ').'
            );
        }
    }

    private function responder(WhatsappApiService $api, WhatsappConversacion $conversacion, string $texto): void
    {
        $config = WhatsappConfig::paraAliado($conversacion->aliado_id);
        if (!$config->credencialesCompletas()) {
            return;
        }

        $nombreBot = IaConfiguracionAliado::where('aliado_id', $conversacion->aliado_id)
            ->first()?->nombreBot() ?? 'Asistente Virtual';

        $firmado = "🤖 *{$nombreBot}:*\n" . $texto;

        $envio = $api->enviarTexto($conversacion->wa_contact_id, $firmado, $config);
        if (!$envio['ok']) {
            Log::warning('ADRES: fallo al entregar el resultado del chequeo', [
                'conversacion_id' => $conversacion->id,
                'error'           => $envio['error'] ?? null,
            ]);
            return;
        }

        $mensaje = WhatsappMensaje::create([
            'conversacion_id' => $conversacion->id,
            'aliado_id'       => $conversacion->aliado_id,
            'wa_message_id'   => $envio['wa_message_id'],
            'direccion'       => 'saliente',
            'tipo'            => 'text',
            'contenido'       => $firmado,
            'estado'          => 'enviado',
            'es_bot'          => true,
        ]);

        $conversacion->update(['ultimo_mensaje_at' => now()]);

        broadcast(new WhatsappMensajeNuevo($mensaje, $conversacion));
        broadcast(new WhatsappConversacionActualizada($conversacion));
    }
}
