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
 * ⚠️ Esta integración no pudo probarse contra la API real de Gemini en esta sesión (no había
 * ninguna clave de Gemini disponible en el entorno) — el request está construido según el
 * contrato documentado de la Generative Language API (generateContent con `responseModalities`
 * incluyendo IMAGE), pero se recomienda verificarla con una clave real antes de confiar en ella
 * en producción. Si el contrato de la API cambió, ajustar solo este archivo.
 */
class GeminiImagenGenerator
{
    private const MODELO = 'gemini-2.5-flash-image';
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * @return array{ok: bool, rutas: string[], error: ?string}
     */
    public static function generarVariantes(string $apiKey, string $prompt, int $cantidad = 2): array
    {
        $rutas = [];

        for ($i = 0; $i < $cantidad; $i++) {
            try {
                $resp = Http::timeout(60)->post(self::BASE_URL . '/' . self::MODELO . ':generateContent?key=' . $apiKey, [
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
