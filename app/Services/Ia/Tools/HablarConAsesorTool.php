<?php

namespace App\Services\Ia\Tools;

use App\Models\WhatsappConversacion;

/**
 * Solo disponible en el canal WhatsApp. Transfiere la conversación a un humano:
 * silencia al asistente en esa conversación (bot_activo = false) hasta que un
 * agente la reactive manualmente desde el chat en vivo.
 */
class HablarConAsesorTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'hablar_con_asesor';
    }

    public function descripcion(): string
    {
        return 'Transfiere la conversación a un asesor humano y deja de responder automáticamente en ella. '
            . 'Úsala cuando el cliente lo pida explícitamente, o cuando el tema requiere atención humana '
            . '(quejas, negociaciones, algo que ninguna otra herramienta resolvió).';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'motivo' => ['type' => 'string', 'description' => 'Resumen breve (una frase) de por qué se transfiere, para que el asesor humano tenga contexto al abrir la conversación.'],
            ],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        if (!empty($contexto['wa_conversacion_id'])) {
            $motivo = trim((string) ($input['motivo'] ?? '')) ?: 'El cliente pidió hablar con un asesor.';
            WhatsappConversacion::find($contexto['wa_conversacion_id'])?->escalarAHumano($motivo);
        }

        return [
            'ok'      => true,
            'mensaje' => 'Un asesor humano continuará esta conversación. Despídete brevemente y avísale al '
                . 'cliente que en breve será atendido.',
        ];
    }
}
