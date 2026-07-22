<?php

namespace App\Services\Ia;

/**
 * Contrato que debe cumplir cualquier proveedor de IA (Claude, OpenAI, ...).
 *
 * Formato normalizado de $messages (agnóstico de proveedor):
 *   ['role' => 'user',        'content' => 'texto']
 *   ['role' => 'assistant',   'content' => ?'texto', 'tool_calls' => [['id','name','input'=>array]]]
 *   ['role' => 'tool_result', 'tool_call_id' => string, 'name' => string, 'content' => string]
 *
 * Formato de $tools: [['name','description','input_schema' => JSON Schema array]]
 */
interface IaProviderInterface
{
    /**
     * @return array{
     *   content: ?string,
     *   tool_calls: array<int, array{id:string, name:string, input:array}>,
     *   tokens_entrada: int,
     *   tokens_salida: int
     * }
     */
    public function chat(string $apiKey, string $modelo, string $systemPrompt, array $messages, array $tools): array;
}
