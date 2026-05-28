<?php

namespace App\Http\Controllers;

use App\Services\WhatsappWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $tokenEsperado = config('services.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $tokenEsperado) {
            Log::info('WhatsApp webhook verificado correctamente');
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook: verificación fallida', [
            'mode'  => $mode,
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
        if (!$this->validarFirmaHmac($request)) {
            Log::warning('WhatsApp webhook: firma HMAC inválida', [
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }

        $payload = $request->all();

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
                'error'   => $e->getMessage(),
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
        $appSecret = config('services.whatsapp.app_secret');

        // Si no hay App Secret configurado, saltamos la validación (desarrollo local)
        if (empty($appSecret)) {
            return true;
        }

        $signatureHeader = $request->header('X-Hub-Signature-256', '');
        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $signatureRecibida = substr($signatureHeader, 7);
        $payload           = $request->getContent();
        $signatureEsperada = hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($signatureEsperada, $signatureRecibida);
    }
}
