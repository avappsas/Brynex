<?php

namespace App\Services\Ia;

use Illuminate\Support\Facades\Http;

/**
 * Pasa a texto las notas de voz que llegan por WhatsApp, con Gemini.
 *
 * Existe porque quien contesta un anuncio desde el celular manda audio, y hasta sep-2026 la
 * IA respondía "no puedo escuchar notas de voz" y dejaba la conversación congelada esperando
 * a que alguien abriera el panel. El primer asesor que llegó por la pieza #90 respondió con
 * una nota de voz y estuvo un día sin respuesta.
 *
 * Se manda el audio en la misma petición (inline) en vez de subirlo a la Files API: las notas
 * de WhatsApp son de segundos y pesan poco, y una sola llamada es una cosa menos que pueda
 * fallar a mitad de camino.
 */
class TranscripcionAudioService
{
    private const MODELO = 'gemini-2.5-flash';

    private const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /** Más que esto no se manda inline; la API rechaza peticiones grandes. */
    private const MAX_BYTES = 15 * 1024 * 1024;

    /**
     * @return array{ok: bool, texto: ?string, error: ?string}
     */
    public static function transcribir(string $apiKey, string $rutaAudio, ?string $mimeType = null): array
    {
        if (! is_file($rutaAudio)) {
            return ['ok' => false, 'texto' => null, 'error' => 'El archivo de audio no está en disco.'];
        }

        if (filesize($rutaAudio) > self::MAX_BYTES) {
            return ['ok' => false, 'texto' => null, 'error' => 'El audio pesa demasiado para transcribirlo.'];
        }

        // WhatsApp manda las notas de voz en ogg/opus, pero el mime llega con parámetros
        // ("audio/ogg; codecs=opus") que la API no acepta.
        $mime = $mimeType ? trim(explode(';', $mimeType)[0]) : 'audio/ogg';

        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(120)
                ->post(self::BASE.'/models/'.self::MODELO.':generateContent', [
                    'contents' => [[
                        'parts' => [
                            [
                                'text' => 'Transcribe literalmente este audio en español. '
                                    .'Devuelve SOLO la transcripción, sin comillas, sin comentarios y sin '
                                    .'describir el audio. Si no se entiende nada o no hay voz, responde '
                                    .'exactamente: SIN_VOZ',
                            ],
                            ['inline_data' => [
                                'mime_type' => $mime,
                                'data' => base64_encode(file_get_contents($rutaAudio)),
                            ]],
                        ],
                    ]],
                    // Transcribir no es opinar: sin creatividad se pega más a lo que se dijo.
                    'generationConfig' => ['temperature' => 0],
                ]);

            if (! $resp->successful()) {
                return ['ok' => false, 'texto' => null, 'error' => 'Gemini respondió '.$resp->status().': '.mb_substr($resp->body(), 0, 200)];
            }

            $texto = trim((string) $resp->json('candidates.0.content.parts.0.text'));

            if ($texto === '' || $texto === 'SIN_VOZ') {
                return ['ok' => false, 'texto' => null, 'error' => 'El audio no traía voz entendible.'];
            }

            return ['ok' => true, 'texto' => $texto, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'texto' => null, 'error' => $e->getMessage()];
        }
    }
}
