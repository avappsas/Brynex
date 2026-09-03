<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Http;

/**
 * Genera video publicitario con Veo (Google, misma API/clave de Gemini que ya usa
 * GeminiImagenGenerator) — modelo generativo real: el movimiento de cámara/escena es propio
 * del clip, no un efecto aplicado después (a diferencia de una foto estática).
 *
 * Es una operación de larga duración (predictLongRunning): iniciar() responde de inmediato
 * con un operation_name; hay que sondear consultarEstado() hasta que termine (1-3 min típico
 * para un clip de 8s) y luego descargar() el mp4 resultante. Por eso todo el pipeline vive en
 * el comando `videos:procesar` (cron), nunca bloqueando una request HTTP.
 */
class VeoVideoGenerator
{
    public const MODELO_LITE     = 'veo-3.1-lite-generate-preview';
    public const MODELO_STANDARD = 'veo-3.1-generate-preview';

    /** Precio oficial por segundo en 720p (ai.google.dev/gemini-api/docs/pricing, jul-2026). */
    private const PRECIO_SEG_LITE     = 0.05;
    private const PRECIO_SEG_STANDARD = 0.40;

    /** Costo estimado en USD de generar un clip — para mostrarlo en el panel de admin. */
    public static function costoEstimadoUsd(string $modelo, int $duracionSeg): float
    {
        $precioSeg = $modelo === self::MODELO_STANDARD ? self::PRECIO_SEG_STANDARD : self::PRECIO_SEG_LITE;
        return round($precioSeg * $duracionSeg, 2);
    }

    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * @return array{ok: bool, operationName: ?string, error: ?string}
     */
    public static function iniciar(string $apiKey, string $prompt, string $modelo, string $aspectRatio = '9:16', string $resolucion = '720p', int $duracionSeg = 8): array
    {
        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(30)
                ->post(self::BASE_URL . "/models/{$modelo}:predictLongRunning", [
                    'instances' => [
                        ['prompt' => $prompt],
                    ],
                    'parameters' => [
                        'aspectRatio'      => $aspectRatio,
                        'resolution'       => $resolucion,
                        'durationSeconds'  => $duracionSeg,
                        'personGeneration' => 'allow_all',
                    ],
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'operationName' => null, 'error' => 'No se pudo conectar con Veo: ' . $e->getMessage()];
        }

        if (!$resp->successful()) {
            $mensaje = $resp->json('error.message') ?? "HTTP {$resp->status()}";
            return ['ok' => false, 'operationName' => null, 'error' => "Veo respondió con error: {$mensaje}"];
        }

        $nombreOperacion = $resp->json('name');
        if (!$nombreOperacion) {
            return ['ok' => false, 'operationName' => null, 'error' => 'Veo no devolvió un operation_name.'];
        }

        return ['ok' => true, 'operationName' => $nombreOperacion, 'error' => null];
    }

    /**
     * @return array{ok: bool, done: bool, videoUri: ?string, error: ?string}
     */
    public static function consultarEstado(string $apiKey, string $operationName): array
    {
        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(30)
                ->get(self::BASE_URL . '/' . $operationName);
        } catch (\Throwable $e) {
            return ['ok' => false, 'done' => false, 'videoUri' => null, 'error' => 'No se pudo consultar el estado en Veo: ' . $e->getMessage()];
        }

        if (!$resp->successful()) {
            $mensaje = $resp->json('error.message') ?? "HTTP {$resp->status()}";
            return ['ok' => false, 'done' => false, 'videoUri' => null, 'error' => "Veo respondió con error: {$mensaje}"];
        }

        if (!$resp->json('done')) {
            return ['ok' => true, 'done' => false, 'videoUri' => null, 'error' => null];
        }

        $errorOperacion = $resp->json('error.message');
        if ($errorOperacion) {
            return ['ok' => false, 'done' => true, 'videoUri' => null, 'error' => "Veo no pudo generar el video: {$errorOperacion}"];
        }

        $uri = $resp->json('response.generateVideoResponse.generatedSamples.0.video.uri');
        if (!$uri) {
            // Cuando Veo filtra el contenido devuelve el motivo en `raiMediaFilteredReasons` y
            // el código lo tiraba: se perdían ciclos adivinando qué había molestado, cuando
            // Veo lo estaba diciendo. El motivo suele ser preciso ("un problema con el audio")
            // y además aclara si hubo cobro.
            $motivos = (array) $resp->json('response.generateVideoResponse.raiMediaFilteredReasons');
            $detalle = $motivos ? ' Veo dijo: ' . implode(' | ', $motivos) : '';

            return ['ok' => false, 'done' => true, 'videoUri' => null,
                'error' => 'Veo terminó pero no devolvió un video utilizable.' . $detalle];
        }

        return ['ok' => true, 'done' => true, 'videoUri' => $uri, 'error' => null];
    }

    /** Descarga el mp4 final al path absoluto indicado. */
    public static function descargar(string $apiKey, string $videoUri, string $destinoAbsoluto): bool
    {
        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(120)->get($videoUri);
        } catch (\Throwable $e) {
            return false;
        }

        if (!$resp->successful()) {
            return false;
        }

        return (bool) file_put_contents($destinoAbsoluto, $resp->body());
    }
}
