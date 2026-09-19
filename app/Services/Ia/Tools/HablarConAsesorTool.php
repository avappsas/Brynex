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

        // Lo que el modelo diga después sale de aquí más que de las reglas generales del prompt:
        // con solo la regla, en las pruebas seguía diciendo "ya dejé registrado que inicias en
        // octubre" o "te dejamos agendado". Lo único que pasó es que una persona fue avisada.
        return [
            'ok'      => true,
            'mensaje' => 'Una persona del equipo quedó avisada y continuará esta conversación. Dile al cliente '
                . 'SOLO eso, en una o dos frases: que le pasaste su caso a un asesor y que lo va a contactar. '
                . 'NO digas que algo quedó registrado, agendado, programado, reservado ni guardado, ni prometas '
                . 'un día u hora: nada de eso se hizo.',
        ];
    }
}
