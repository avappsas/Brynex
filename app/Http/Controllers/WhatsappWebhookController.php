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
            // Aparte de aceptar o no el payload: GARVIS solo actúa con la firma
            // de Meta de verdad, aunque el webhook todavía acepte sin ella.
            $firmaVerificada = $this->firmaCuadra($request, $this->appSecret());

            $this->webhookService->procesarPayload($payload, $firmaVerificada);
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
        // Pero cerrar de golpe deja a los aliados sin recibir mensajes: si
        // algún número llega firmado por otra app de Meta (un aliado con su
        // propia cuenta), su firma no cuadrará con este App Secret. Por eso el
        // rechazo es un interruptor explícito y no una fecha: mientras esté
        // apagado se acepta y se avisa, por número, del motivo exacto, que es
        // el dato que dice si encenderlo es seguro.
        $estricto = (bool) config('services.whatsapp.webhook_estricto');

        if (app()->environment('local')) {
            return true;
        }

        $firmaValida = $this->firmaCuadra($request, $appSecret);

        if ($firmaValida) {
            return true;
        }

        // En estricto se rechaza y punto. Fuera de estricto se acepta, pero se
        // deja constancia de POR QUÉ no cuadró y de qué número venía: si en un
        // día entero no aparece ninguno, activar el estricto no deja a nadie
        // por fuera.
        if ($estricto) {
            Log::error('WhatsApp webhook: firma no válida en modo estricto, se rechaza.', [
                'motivo' => $this->motivoFallo($request, $appSecret),
                'numeros' => $this->numerosDelPayload($request),
            ]);

            return false;
        }

        $this->avisarWebhookSinVerificar($this->motivoFallo($request, $appSecret), $this->numerosDelPayload($request));

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
     * arreglas tú) a que no llegue la cabecera (algo entre Meta y Brynex la
     * quita) o a que llegue y no cuadre (App Secret de otra app de Meta).
     */
    private function motivoFallo(Request $request, ?string $appSecret): string
    {
        if (empty($appSecret)) {
            return 'falta el App Secret';
        }

        if (! str_starts_with($request->header('X-Hub-Signature-256', ''), 'sha256=')) {
            return 'no llega la cabecera X-Hub-Signature-256';
        }

        return 'la cabecera llega pero no coincide: el App Secret no es el de la app de Meta que manda este número';
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
     * Los phone_number_id que trae el payload: dicen de qué número (y por tanto
     * de qué aliado) era lo que no se pudo verificar.
     */
    private function numerosDelPayload(Request $request): array
    {
        $numeros = [];

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $id = $change['value']['metadata']['phone_number_id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $numeros[$id] = true;
                }
            }
        }

        return array_keys($numeros);
    }

    /**
     * Un aviso al día por número, no uno por mensaje entrante. Se marca con
     * add(), que es atómico: con varios workers a la vez solo uno gana y sale
     * una sola alerta. Va por número porque un solo número que falle es un
     * aliado que el modo estricto dejaría sin mensajes.
     */
    private function avisarWebhookSinVerificar(string $motivo, array $numeros): void
    {
        foreach ($numeros ?: ['desconocido'] as $numero) {
            if (! Cache::add('wa_firma_no_cuadra:'.$numero, true, now()->addDay())) {
                continue;
            }

            Log::warning('WhatsApp webhook: aceptando sin verificar la firma.', [
                'motivo' => $motivo,
                'phone_number_id' => $numero,
            ]);

            EnviarAlertaOperativa::dispatch(
                'WhatsApp sin verificar firma',
                'El webhook aceptó sin comprobar que viniera de Meta un mensaje del número '.$numero
                    .'. Motivo: '.$motivo
                    .'. Mientras siga así, no se puede encender WHATSAPP_WEBHOOK_ESTRICTO sin dejar ese número sin mensajes.'
            );
        }
    }
}
