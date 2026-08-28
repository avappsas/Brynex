<?php

namespace App\Services\Dataico;

use App\Models\DataicoConfiguracion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP del API de Dataico. No sabe nada de facturas de Brynex: recibe
 * un payload ya armado y devuelve el resultado crudo.
 *
 * Regla que no se negocia: un 4xx NO se reintenta. Un 422 significa que la
 * DIAN o Dataico rechazaron los datos; repetir el envío no arregla el dato y
 * sí puede emitir dos veces si el rechazo fue parcial. Solo se reintentan las
 * fallas de red, los 5xx y el 429.
 *
 * El 429 es la excepción a la regla del 4xx, y por eso está aparte: no dice
 * que el dato esté mal, dice que se le está pegando muy rápido. Emitir un mes
 * son doscientos envíos seguidos, así que además del reintento hay un piso de
 * tiempo entre llamada y llamada — sin él, Dataico empieza a devolver 429 a
 * mitad del lote y esas facturas quedan en error por una razón que no tiene
 * nada que ver con su contenido.
 */
class ApiClient
{
    public function __construct(private readonly DataicoConfiguracion $cfg) {}

    /**
     * @return array{ok: bool, status: int|null, body: array|null, raw: string, error: string|null}
     */
    public function crearFactura(array $payload): array
    {
        $url = rtrim(config('dataico.base_url'), '/')
             .config('dataico.endpoints.crear_factura');

        $this->respirar();

        try {
            $respuesta = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Auth-token' => $this->cfg->auth_token,
            ])
                ->timeout(config('dataico.timeout'))
                ->connectTimeout(config('dataico.connect_timeout'))
                ->retry(
                    config('dataico.reintentos'),
                    // Espera creciente: si el primer reintento tampoco pasó, el
                    // segundo tiene que llegar más tarde, no igual de pronto.
                    fn (int $intento) => $intento * (int) config('dataico.espera_ms'),
                    // Red, 5xx y 429. Los demás 4xx se aceptan como respuesta
                    // final. Ojo: ConnectionException NO tiene ->response, así
                    // que el instanceof va antes de tocar la propiedad.
                    fn ($e, $req) => $e instanceof ConnectionException
                        || ($e instanceof RequestException
                            && ($e->response->serverError() || $e->response->status() === 429)),
                    throw: false
                )
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('[dataico] falla de transporte', [
                'aliado_id' => $this->cfg->aliado_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'raw' => '',
                'error' => 'No se pudo contactar a Dataico: '.$e->getMessage(),
            ];
        }

        $raw = $respuesta->body();
        $body = $respuesta->json();

        if ($respuesta->successful()) {
            return [
                'ok' => true,
                'status' => $respuesta->status(),
                'body' => is_array($body) ? $body : null,
                'raw' => $raw,
                'error' => null,
            ];
        }

        return [
            'ok' => false,
            'status' => $respuesta->status(),
            'body' => is_array($body) ? $body : null,
            'raw' => $raw,
            'error' => $this->mensajeDeError($respuesta->status(), is_array($body) ? $body : null, $raw),
        ];
    }

    /**
     * Traduce la respuesta de error a algo que sirva en pantalla.
     * Dataico responde a veces `{"errors":[{"path":[...],"error":"..."}]}` y
     * a veces un `{"message":"..."}` suelto.
     */
    private function mensajeDeError(int $status, ?array $body, string $raw): string
    {
        if ($body === null) {
            return "HTTP {$status}: ".mb_substr(trim($raw), 0, 400);
        }

        if (! empty($body['errors']) && is_array($body['errors'])) {
            $partes = [];
            foreach ($body['errors'] as $e) {
                if (is_string($e)) {
                    $partes[] = $e;

                    continue;
                }
                $campo = isset($e['path']) ? implode('.', (array) $e['path']) : '';
                $detalle = $e['error'] ?? $e['message'] ?? json_encode($e, JSON_UNESCAPED_UNICODE);
                $partes[] = $campo !== '' ? "{$campo}: {$detalle}" : (string) $detalle;
            }

            return "HTTP {$status}: ".mb_substr(implode(' | ', $partes), 0, 900);
        }

        $msg = $body['message'] ?? $body['error'] ?? json_encode($body, JSON_UNESCAPED_UNICODE);

        return "HTTP {$status}: ".mb_substr((string) $msg, 0, 900);
    }

    /**
     * Deja pasar el tiempo mínimo entre una llamada y la siguiente.
     *
     * Vive aquí y no en el comando porque los envíos salen por varios caminos
     * —el comando manual, el cierre diario, la cola— y el límite lo pone
     * Dataico para todos por igual.
     */
    private function respirar(): void
    {
        $pausa = (int) config('dataico.pausa_ms');

        if ($pausa <= 0) {
            return;
        }

        $falta = self::$ultimaLlamada + $pausa * 1000 - (int) (microtime(true) * 1e6);

        if ($falta > 0) {
            usleep($falta);
        }

        self::$ultimaLlamada = (int) (microtime(true) * 1e6);
    }

    /** Microsegundos del último envío de este proceso. */
    private static int $ultimaLlamada = 0;

    /** ¿El fallo amerita reintento posterior, o es un dato malo? */
    public static function esReintentable(?int $status): bool
    {
        return $status === null || $status >= 500 || $status === 429;
    }
}
