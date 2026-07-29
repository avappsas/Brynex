<?php

namespace App\Services\Ia\Providers;

use App\Services\Ia\IaProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements IaProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function chat(string $apiKey, string $modelo, string $systemPrompt, array $messages, array $tools): array
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'          => $this->traducirMensajes($messages),
        ];

        if (!empty($tools)) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(fn ($t) => [
                    'name'        => $t['name'],
                    'description' => $t['description'],
                    'parameters'  => $this->traducirSchema($t['input_schema']),
                ], $tools),
            ]];
        }

        $response = Http::timeout(30)
            ->post(self::BASE_URL . '/' . $modelo . ':generateContent?key=' . $apiKey, $payload);

        if (!$response->successful()) {
            Log::warning('IA Gemini: error en la API', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Error consultando Gemini: ' . $response->status());
        }

        $data = $response->json();
        $parts = data_get($data, 'candidates.0.content.parts', []);

        $texto = null;
        $toolCalls = [];
        foreach ($parts as $i => $part) {
            if (isset($part['text'])) {
                $texto = ($texto ?? '') . $part['text'];
            } elseif (isset($part['functionCall'])) {
                // Gemini no da un id de llamada (a diferencia de Claude/OpenAI) — se sintetiza uno
                // solo para el turno actual, usado únicamente para emparejar el tool_result que sigue.
                $toolCalls[] = [
                    'id'    => 'gemini_call_' . $i,
                    'name'  => $part['functionCall']['name'],
                    'input' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        return [
            'content'        => $texto,
            'tool_calls'     => $toolCalls,
            'tokens_entrada' => (int) data_get($data, 'usageMetadata.promptTokenCount', 0),
            // Los tokens de "pensamiento" (thinking) se facturan como salida, igual que Claude.
            'tokens_salida'  => (int) data_get($data, 'usageMetadata.candidatesTokenCount', 0)
                + (int) data_get($data, 'usageMetadata.thoughtsTokenCount', 0),
        ];
    }

    private function traducirMensajes(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'user') {
                $out[] = ['role' => 'user', 'parts' => [['text' => $m['content']]]];
            } elseif ($m['role'] === 'assistant') {
                $parts = [];
                if (!empty($m['content'])) {
                    $parts[] = ['text' => $m['content']];
                }
                foreach ($m['tool_calls'] ?? [] as $tc) {
                    $parts[] = ['functionCall' => ['name' => $tc['name'], 'args' => $tc['input']]];
                }
                $out[] = ['role' => 'model', 'parts' => $parts];
            } elseif ($m['role'] === 'tool_result') {
                $out[] = [
                    'role'  => 'function',
                    'parts' => [[
                        'functionResponse' => [
                            'name'     => $m['name'],
                            'response' => ['result' => json_decode($m['content'], true)],
                        ],
                    ]],
                ];
            }
        }
        return $out;
    }

    /** Traduce un JSON Schema (tipos en minúscula) al formato que espera Gemini (tipos en MAYÚSCULA). */
    private function traducirSchema(array $schema): array
    {
        $out = [];
        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_string($value)) {
                $out[$key] = strtoupper($value);
            } elseif ($key === 'properties' && is_array($value)) {
                $out[$key] = array_map(fn ($p) => $this->traducirSchema($p), $value);
            } elseif ($key === 'items' && is_array($value)) {
                $out[$key] = $this->traducirSchema($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
