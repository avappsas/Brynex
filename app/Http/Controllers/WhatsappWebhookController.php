<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarAlertaOperativa;
use App\Models\ConfiguracionBrynex;
use App\Services\WhatsappWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Controlador público para el webhook de Meta WhatsApp.
 * NO requiere autenticación — Meta llama directamente a estas rutas.
 *
 * Seguridad: valida la firma HMAC-SHA256 de cada request entrante
 * usando el App Secret de la Meta App.
 */
class WhatsappWebhookController extends Controller
{
    public function __construct(protected WhatsappWebhookService $webhookService) {}

    /**
     * GET /whatsapp/webhook
     * Meta llama este endpoint para verificar el webhook al configurarlo.
     * Verifica el token y responde con el challenge.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $tokenEsperado = config('services.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $tokenEsperado) {
            Log::info('WhatsApp webhook verificado correctamente');

            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook: verificación fallida', [
            'mode' => $mode,
            'token' => $token,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * POST /whatsapp/webhook
     * Meta envía aquí todos los eventos (mensajes entrantes, estados).
     */
    public function receive(Request $request): Response
    {
        // Validar firma HMAC para garantizar que el request viene de Meta
        if (! $this->validarFirmaHmac($request)) {
            Log::warning('WhatsApp webhook: firma HMAC inválida', [
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized', 401);
        }

        $payload = $request->all();

        Log::info('WhatsApp webhook payload recibido:', $payload);

        // Ignorar si no es evento de WhatsApp
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response('OK', 200);
        }

        // Procesar de forma asíncrona para responder rápido a Meta
        // (Meta cancela si no recibe respuesta en ~20 segundos)
        try {
            $this->webhookService->procesarPayload($payload);
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook: error al procesar payload', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        // Siempre responder 200 OK a Meta (evitar reenvíos innecesarios)
        return response('OK', 200);
    }

    /**
     * Valida la firma HMAC-SHA256 enviada por Meta en el header X-Hub-Signature-256.
     * Referencia: https://developers.facebook.com/docs/messenger-platform/webhooks#validate-payloads
     */
    private function validarFirmaHmac(Request $request): bool
    {
        $appSecret = $this->appSecret();

        // Sin App Secret no hay nada que validar, y este endpoint es público:
        // fallar abierto significa aceptar payloads falsificados de cualquiera
        // — un mensaje entrante falso hace que el asistente de IA le conteste
        // al número del atacante con los datos del cliente cuya cédula diga
        // tener.
        //
        // Pero cerrar de golpe deja a los aliados sin recibir mensajes, y aquí
        // el webhook de Meta no apunta a BryNex sino a un relay: si ese relay
        // reserializa el cuerpo o no reenvía la cabecera, la firma no cuadrará
        // jamás por mucho App Secret que se ponga. Por eso el rechazo es un
        // interruptor explícito y no una fecha: mientras esté apagado se acepta
        // y se avisa a diario del motivo exacto, que es el dato que dice si
        // encenderlo es seguro.
        $estricto = (bool) config('services.whatsapp.webhook_estricto');

        if (app()->environment('local')) {
            return true;
        }

        $firmaValida = $this->firmaCuadra($request, $appSecret);

        if ($firmaValida) {
            return true;
        }

        // En estricto se rechaza y punto. Fuera de estricto se acepta, pero se
        // deja constancia diaria de POR QUÉ no cuadró: sin App Secret, sin
        // cabecera, o cabecera presente que no coincide. Ese diagnóstico es lo
        // que dice si el relay está estropeando la firma o si solo falta el
        // secreto — y por tanto si activar el estricto es seguro.
        if ($estricto) {
            Log::error('WhatsApp webhook: firma no válida en modo estricto, se rechaza.', [
                'motivo' => $this->motivoFallo($request, $appSecret),
            ]);

            return false;
        }

        $this->avisarWebhookSinVerificar($this->motivoFallo($request, $appSecret));

        return true;
    }

    /**
     * ¿La firma de Meta cuadra con el cuerpo recibido?
     */
    private function firmaCuadra(Request $request, ?string $appSecret): bool
    {
        if (empty($appSecret)) {
            return false;
        }

        $cabecera = $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($cabecera, 'sha256=')) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $request->getContent(), $appSecret),
            substr($cabecera, 7)
        );
    }

    /**
     * Por qué no cuadró. Cada motivo lleva a un arreglo distinto, así que la
     * alerta tiene que distinguirlos: no es lo mismo que falte el secreto (lo
     * arreglas tú) a que el relay no reenvíe la cabecera (lo arregla el relay).
     */
    private function motivoFallo(Request $request, ?string $appSecret): string
    {
        if (empty($appSecret)) {
            return 'falta el App Secret';
        }

        if (! str_starts_with($request->header('X-Hub-Signature-256', ''), 'sha256=')) {
            return 'el relay no reenvía la cabecera X-Hub-Signature-256';
        }

        return 'la cabecera llega pero no coincide: el relay está alterando el cuerpo del mensaje';
    }

    /**
     * App Secret, del .env o de la configuración global en BD.
     *
     * El respaldo en BD existe para no obligar a entrar por SSH al servidor:
     * se puede pegar desde el panel, igual que el access token, y ahí se guarda
     * cifrado. Si no está cifrado se usa tal cual, para tolerar que alguien lo
     * inserte a mano.
     */
    private function appSecret(): ?string
    {
        $delEnv = config('services.whatsapp.app_secret');

        if (! empty($delEnv)) {
            return $delEnv;
        }

        $deBd = ConfiguracionBrynex::obtener('whatsapp_global_app_secret');

        if (empty($deBd)) {
            return null;
        }

        try {
            return Crypt::decryptString($deBd);
        } catch (\Throwable $e) {
            return $deBd;
        }
    }

    /**
     * Un aviso al día, no uno por mensaje entrante. Se marca con add(), que es
     * atómico: con varios workers a la vez solo uno gana y sale una sola alerta.
     */
    private function avisarWebhookSinVerificar(string $motivo): void
    {
        if (! Cache::add('wa_webhook_sin_verificar', true, now()->addDay())) {
            return;
        }

        Log::warning('WhatsApp webhook: aceptando sin verificar la firma.', ['motivo' => $motivo]);

        EnviarAlertaOperativa::dispatch(
            'WhatsApp sin verificar firma',
            'El webhook acepta mensajes sin comprobar que vengan de Meta. Motivo: '.$motivo
                .'. Mientras siga así, cualquiera puede inyectar mensajes falsos.'
        );
    }
}
