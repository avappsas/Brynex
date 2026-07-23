<?php

namespace App\Services\Ia\Tools;

use App\Models\MarketingBloqueado;
use App\Models\WhatsappConversacion;

/**
 * Solo canal WhatsApp. Bloquea al cliente de futuras campañas de marketing/publicidad —
 * no afecta conversaciones de servicio (cobros, soporte, planillas). Se usa cuando el
 * cliente pide explícitamente no recibir más publicidad, o confirma que quiere ser
 * eliminado después de que la IA le explique el origen de sus datos.
 */
class NoContactarTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'no_contactar';
    }

    public function descripcion(): string
    {
        return 'Bloquea al cliente de futuras campañas de marketing para que nunca más reciba publicidad de este '
            . 'aliado. Úsala cuando pida explícitamente no ser contactado, o cuando confirme que quiere ser '
            . 'eliminado después de que le expliques el origen de sus datos. No la uses para clientes con un '
            . 'trámite o servicio activo que solo están haciendo una pregunta.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'motivo' => ['type' => 'string', 'description' => 'Resumen breve de por qué se bloquea (ej: "pidió no ser contactado", "se quejó del origen de sus datos").'],
            ],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $waConversacionId = $contexto['wa_conversacion_id'] ?? null;
        $conversacion = $waConversacionId ? WhatsappConversacion::find($waConversacionId) : null;

        if (!$conversacion) {
            return ['ok' => false, 'mensaje' => 'No se pudo identificar la conversación para bloquear.'];
        }

        $motivo = trim((string) ($input['motivo'] ?? '')) ?: 'Pidió no ser contactado.';

        MarketingBloqueado::bloquear(
            $conversacion->aliado_id,
            $conversacion->wa_contact_id,
            'ia',
            $motivo,
            null,
            $conversacion->id
        );

        return [
            'ok'      => true,
            'mensaje' => 'Número bloqueado de futuras campañas de marketing. Confírmaselo al cliente en una '
                . 'frase breve, con tono de disculpa, sin insistir en nada más ni ofrecerle otra cosa.',
        ];
    }
}
