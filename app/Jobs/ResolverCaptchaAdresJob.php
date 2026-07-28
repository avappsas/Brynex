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
use Illuminate\Support\Facades\Storage;

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

        $chequeo = $r['chequeo'];

        $this->responder($whatsappApi, $conversacion, RedactorDiagnostico::paraWhatsapp($chequeo));

        // El PDF es la prueba: el cliente ve el documento oficial de ADRES, no
        // solo lo que le decimos nosotros. Es la misma lógica del "pagas después
        // de ver tu radicado".
        $oferta = RedactorDiagnostico::ofertaAsesor($chequeo);
        $enviado = $this->enviarPdf($whatsappApi, $conversacion, $chequeo, $oferta);

        // Si el PDF no salió, la pregunta por el asesor no se puede perder.
        if (!$enviado) {
            $this->responder($whatsappApi, $conversacion, $oferta);
        }

        // No se escala aquí a propósito: escalarAHumano() apaga el bot, y el
        // cliente acaba de recibir una pregunta. Si dice que sí, la IA lo pasa
        // con hablar_con_asesor. El flag requiere_asesor queda en el diagnóstico
        // para el panel interno.
    }

    /** Manda el reporte oficial de ADRES como documento adjunto. */
    private function enviarPdf(
        WhatsappApiService $api,
        WhatsappConversacion $conversacion,
        AdresChequeo $chequeo,
        string $caption
    ): bool {
        if (!$chequeo->pdf_path || !Storage::disk('local')->exists($chequeo->pdf_path)) {
            return false;
        }

        $config = WhatsappConfig::paraAliado($conversacion->aliado_id);
        if (!$config->credencialesCompletas()) {
            return false;
        }

        $nombre = 'Reporte ADRES - Periodos Compensados.pdf';

        $envio = $api->enviarMedia(
            $conversacion->wa_contact_id,
            'document',
            $chequeo->pdf_path,
            'application/pdf',
            $nombre,
            $config,
            $caption
        );

        if (!($envio['ok'] ?? false)) {
            Log::warning('ADRES: no se pudo enviar el PDF del chequeo', [
                'chequeo_id' => $chequeo->id,
                'error'      => $envio['error'] ?? null,
            ]);
            return false;
        }

        $mensaje = WhatsappMensaje::create([
            'conversacion_id' => $conversacion->id,
            'aliado_id'       => $conversacion->aliado_id,
            'wa_message_id'   => $envio['wa_message_id'],
            'direccion'       => 'saliente',
            'tipo'            => 'document',
            'contenido'       => $caption,
            'media_nombre'    => $nombre,
            'media_mime_type' => 'application/pdf',
            'estado'          => 'enviado',
            'es_bot'          => true,
        ]);

        $conversacion->update(['ultimo_mensaje_at' => now()]);
        $this->difundir($mensaje, $conversacion);

        return true;
    }

    /**
     * Refrescar el chat en vivo es un extra; entregarle los mensajes al cliente no.
     *
     * Este job manda dos cosas seguidas (resumen y PDF). Sin este try/catch, si
     * Reverb está caído el broadcast del primero revienta el job y el cliente se
     * queda sin el PDF ni la pregunta del asesor — con el resumen a medias y sin
     * saber qué pasó. El resto de jobs de WhatsApp mandan un solo mensaje, por eso
     * allá el broadcast suelto no hace daño.
     */
    private function difundir(WhatsappMensaje $mensaje, WhatsappConversacion $conversacion): void
    {
        try {
            broadcast(new WhatsappMensajeNuevo($mensaje, $conversacion));
            broadcast(new WhatsappConversacionActualizada($conversacion));
        } catch (\Throwable $e) {
            Log::warning('ADRES: no se pudo difundir el mensaje al chat en vivo', [
                'conversacion_id' => $conversacion->id,
                'error'           => $e->getMessage(),
            ]);
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
        $this->difundir($mensaje, $conversacion);
    }
}
