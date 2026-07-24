<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genera imágenes publicitarias con la API de Gemini (Google Generative Language API),
 * como alternativa a las plantillas por canvas. Requiere una clave de Gemini configurada
 * en ia_configuracion_aliado.gemini_api_key — independiente de la clave del asistente
 * conversacional (que solo soporta claude|openai).
 *
 * Dos modelos disponibles (misma clave, sin cuenta ni facturación adicional):
 * - ILUSTRACION (gemini-2.5-flash-image, "Nano Banana"): estilo flat design, ya verificado
 *   en producción (publica en Facebook/Instagram sin problema).
 * - FOTORREALISTA (gemini-3-pro-image): modelo premium de Google recomendado para fotografía
 *   de personas realista, mejor consistencia de marca — aún sin verificar con una clave real
 *   en esta sesión, confirmar la primera vez que se use.
 * (Nota: Google anunció el retiro de la familia Imagen para el 17 de agosto de 2026 y
 * recomienda migrar a estos modelos de Gemini — por eso no se integró Imagen).
 */
class GeminiImagenGenerator
{
    public const MODELO_ILUSTRACION  = 'gemini-2.5-flash-image';
    public const MODELO_FOTORREALISTA = 'gemini-3-pro-image';

    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * @return array{ok: bool, rutas: string[], error: ?string}
     */
    public static function generarVariantes(string $apiKey, string $prompt, int $cantidad = 2, string $modelo = self::MODELO_ILUSTRACION): array
    {
        $rutas = [];

        for ($i = 0; $i < $cantidad; $i++) {
            try {
                $resp = Http::timeout(60)->post(self::BASE_URL . '/' . $modelo . ':generateContent?key=' . $apiKey, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseModalities' => ['IMAGE'],
                    ],
                ]);
            } catch (\Throwable $e) {
                return ['ok' => false, 'rutas' => $rutas, 'error' => 'No se pudo conectar con Gemini: ' . $e->getMessage()];
            }

            if (!$resp->successful()) {
                $mensaje = $resp->json('error.message') ?? "HTTP {$resp->status()}";
                return ['ok' => false, 'rutas' => $rutas, 'error' => "Gemini respondió con error: {$mensaje}"];
            }

            $base64 = data_get($resp->json(), 'candidates.0.content.parts.0.inlineData.data');
            if (!$base64) {
                return ['ok' => false, 'rutas' => $rutas, 'error' => 'Gemini no devolvió una imagen en la respuesta.'];
            }

            $nombre = 'publicidad/ia/' . Str::random(20) . '.png';
            Storage::disk('public')->put($nombre, base64_decode($base64));
            $rutas[] = $nombre;
        }

        return ['ok' => true, 'rutas' => $rutas, 'error' => null];
    }
}
