<?php

namespace App\Services\Ia\Tools;

use App\Models\IaPreguntaEntrenador;

class PreguntarEntrenadorTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'preguntar_entrenador';
    }

    public function descripcion(): string
    {
        return 'Registra una pregunta para que un humano (el entrenador) la responda, cuando ni buscar_conocimiento '
            . 'ni buscar_internet dieron una respuesta confiable. Es tu último recurso: úsala en vez de inventar '
            . 'una respuesta. Dile al usuario que no tienes certeza y que la pregunta quedó registrada.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'pregunta' => ['type' => 'string', 'description' => 'La pregunta tal como la necesitas responder, en tus propias palabras (sé específico).'],
            ],
            'required' => ['pregunta'],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        IaPreguntaEntrenador::create([
            'aliado_id'       => $contexto['aliado_id'] ?? null,
            'conversacion_id' => $contexto['conversacion_id'] ?? null,
            'pregunta'        => $input['pregunta'] ?? '',
            'estado'          => 'pendiente',
        ]);

        return [
            'registrada' => true,
            'mensaje'    => 'La pregunta quedó registrada para el entrenador. Informa al usuario que no tienes certeza todavía y que en breve habrá una respuesta verificada.',
        ];
    }
}
