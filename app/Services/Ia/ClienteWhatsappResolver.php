<?php

namespace App\Services\Ia;

use App\Models\Cliente;
use App\Models\WhatsappConversacion;

/**
 * Resuelve qué Cliente corresponde a quien escribe por WhatsApp, con el mismo criterio
 * de seguridad en todas las tools que consultan datos personales/financieros:
 *
 * - Si el cliente (o quien escribe en su nombre) da una cédula explícita, esa cédula ES
 *   la verificación: se busca directo por cédula, sin importar de qué número escriban
 *   (permite casos legítimos como un familiar o un tercero consultando a nombre de otro).
 *
 * DECISIÓN CONSCIENTE (ago-2026), no la "corrijas" sin hablarlo: cualquiera, desde
 * cualquier número, puede pedir los datos de cualquier cédula del aliado — contratos,
 * saldos, préstamos, cuentas de cobro y el envío de la planilla PILA. En Colombia la
 * cédula está impresa en cada contrato y cada factura, así que no es un secreto; se
 * asume igual como credencial suficiente porque el caso del familiar que pide la
 * planilla pesa más que el riesgo. `es_propia` existe y se calcula, pero se usa SOLO
 * para redactar la respuesta ("tu planilla" vs "la planilla de Fulano"), nunca para
 * autorizar. Si algún día se quiere cerrar, la vía es graduar por sensibilidad con ese
 * mismo campo, no exigir el número del titular.
 * - Si no dan cédula, se resuelve por el número de WhatsApp. Si ese número está
 *   registrado para más de una persona, se exige la cédula antes de identificar a nadie.
 */
class ClienteWhatsappResolver
{
    /**
     * @return array{cliente: ?Cliente, requiere_cedula: bool, es_propia: bool}
     */
    public static function resolver(WhatsappConversacion $conversacion, ?string $cedulaInput): array
    {
        $numeroLimpio = preg_replace('/[^0-9]/', '', $conversacion->wa_contact_id);
        $matchTelefono = function ($q) use ($numeroLimpio) {
            $q->where('celular', $numeroLimpio)
              ->orWhere('celular', '+57' . $numeroLimpio)
              ->orWhere('celular', 'like', '%' . substr($numeroLimpio, -10));
        };

        $cedulaLimpia = preg_replace('/[^0-9]/', '', (string) $cedulaInput);

        if ($cedulaLimpia) {
            $cliente = Cliente::where('aliado_id', $conversacion->aliado_id)
                ->where('cedula', $cedulaLimpia)
                ->first();

            // ¿La cédula dada corresponde a este mismo número, o pregunta por alguien más?
            $esPropia = $cliente && Cliente::where('aliado_id', $conversacion->aliado_id)
                ->where('cedula', $cliente->cedula)
                ->where($matchTelefono)
                ->exists();

            return ['cliente' => $cliente, 'requiere_cedula' => false, 'es_propia' => $esPropia];
        }

        $candidatos = Cliente::where('aliado_id', $conversacion->aliado_id)
            ->where($matchTelefono)
            ->get();

        $cedulasCandidatas = $candidatos->pluck('cedula')->unique();

        if ($cedulasCandidatas->count() > 1) {
            return ['cliente' => null, 'requiere_cedula' => true, 'es_propia' => false];
        }

        // Resuelto directo por teléfono (sin cédula explícita): siempre es su propia identidad.
        return ['cliente' => $candidatos->first(), 'requiere_cedula' => false, 'es_propia' => (bool) $candidatos->first()];
    }

    public static function notaRequiereCedula(): string
    {
        return 'Este número de WhatsApp está registrado para varias personas. Pídele al cliente su número de '
            . 'cédula para verificar de quién se trata, y vuelve a llamar esta herramienta con esa cédula antes '
            . 'de dar cualquier información personal.';
    }
}
