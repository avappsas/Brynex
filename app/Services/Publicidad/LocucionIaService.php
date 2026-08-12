<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Http;

/**
 * Locución en español para las piezas de video, con los modelos TTS de Gemini.
 *
 * Se usa esto y no la voz que genera Veo dentro del clip por dos razones: Veo decide el
 * idioma por su cuenta —y venía hablando en inglés frente a un público colombiano— y no
 * permite controlar qué se dice ni cuándo. Acá el guion es exacto y se puede sincronizar con
 * lo que aparece en pantalla.
 *
 * La API devuelve PCM crudo (24 kHz, 16 bits, mono, sin cabecera), así que hay que envolverlo
 * en un WAV a mano antes de que FFmpeg lo pueda leer.
 */
class LocucionIaService
{
    private const MODELO = 'gemini-2.5-flash-preview-tts';
    private const BASE   = 'https://generativelanguage.googleapis.com/v1beta';

    /** Voces de Gemini que suenan naturales en español latino. */
    public const VOZ_FEMENINA = 'Kore';
    public const VOZ_MASCULINA = 'Charon';

    private const TASA_MUESTREO = 24000;

    /**
     * Genera la locución y la deja como WAV en $destinoWav.
     *
     * @param string $guion       Lo que se debe decir, ya escrito como debe sonar.
     * @param string $indicacion  Cómo decirlo (tono, ritmo).
     * @return array{ok: bool, error: ?string}
     */
    public static function generar(
        string $apiKey,
        string $guion,
        string $destinoWav,
        string $voz = self::VOZ_FEMENINA,
        string $indicacion = 'Léelo con energía cálida y cercana, en español colombiano neutro, con ritmo ágil de publicidad'
    ): array {
        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(120)
                ->post(self::BASE . '/models/' . self::MODELO . ':generateContent', [
                    'contents' => [[
                        'parts' => [['text' => $indicacion . ': ' . $guion]],
                    ]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => [
                            'voiceConfig' => [
                                'prebuiltVoiceConfig' => ['voiceName' => $voz],
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'No se pudo conectar con el TTS: ' . $e->getMessage()];
        }

        if (!$resp->successful()) {
            return ['ok' => false, 'error' => $resp->json('error.message') ?? ('HTTP ' . $resp->status())];
        }

        $b64 = $resp->json('candidates.0.content.parts.0.inlineData.data');
        if (!$b64) {
            return ['ok' => false, 'error' => 'El TTS no devolvió audio.'];
        }

        $pcm = base64_decode($b64);
        if ($pcm === false || $pcm === '') {
            return ['ok' => false, 'error' => 'El audio devuelto no se pudo decodificar.'];
        }

        file_put_contents($destinoWav, self::envolverEnWav($pcm));

        return ['ok' => true, 'error' => null];
    }

    /**
     * Gemini entrega PCM pelado (sin cabecera). Se le antepone una cabecera WAV de 44 bytes
     * para que FFmpeg lo reconozca sin tener que pasarle los parámetros a mano.
     */
    private static function envolverEnWav(string $pcm, int $canales = 1, int $bits = 16): string
    {
        $tasa       = self::TASA_MUESTREO;
        $byteRate   = $tasa * $canales * ($bits / 8);
        $blockAlign = $canales * ($bits / 8);

        return 'RIFF'
            . pack('V', 36 + strlen($pcm))
            . 'WAVEfmt '
            . pack('V', 16)          // tamaño del bloque fmt
            . pack('v', 1)           // PCM
            . pack('v', $canales)
            . pack('V', $tasa)
            . pack('V', $byteRate)
            . pack('v', $blockAlign)
            . pack('v', $bits)
            . 'data'
            . pack('V', strlen($pcm))
            . $pcm;
    }
}
