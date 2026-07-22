<?php

namespace App\Services\Ia\Tools;

use App\Models\BancoCuenta;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Factura;
use App\Models\WhatsappConversacion;

/**
 * Solo canal WhatsApp. Da información real y específica de la cuenta del cliente que
 * escribe: su plan actual, cuánto debe (si aplica) y las cuentas de pago vigentes.
 * El saldo pendiente es real (misma lógica que usa el módulo de Facturación), pero
 * siempre se devuelve con la instrucción de aclararlo como informativo y ofrecer
 * escalar a un asesor humano para confirmarlo.
 *
 * Saldo A FAVOR y préstamos NUNCA se detallan por chat (monto, condiciones, etc.):
 * son temas que requieren revisión humana, así que solo se informa que existen y
 * se dirige al cliente con un asesor.
 */
class ConsultarClienteTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'consultar_cliente';
    }

    public function descripcion(): string
    {
        return 'Consulta si el número que escribe es un cliente con contrato vigente, y si lo es, su plan actual, '
            . 'su saldo (a favor o pendiente de pago) y las cuentas para pagar. Úsala cuando el cliente pregunte '
            . 'por su cuenta, cuánto debe, si está al día, o cómo pagar.';
    }

    public function schema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $waConversacionId = $contexto['wa_conversacion_id'] ?? null;
        $conversacion = $waConversacionId ? WhatsappConversacion::find($waConversacionId) : null;

        if (!$conversacion) {
            return ['es_cliente' => false, 'nota' => 'No pude verificar el número en este momento.'];
        }

        $numeroLimpio = preg_replace('/[^0-9]/', '', $conversacion->wa_contact_id);

        $cliente = Cliente::where('aliado_id', $conversacion->aliado_id)
            ->where(function ($q) use ($numeroLimpio) {
                $q->where('celular', $numeroLimpio)
                  ->orWhere('celular', '+57' . $numeroLimpio)
                  ->orWhere('celular', 'like', '%' . substr($numeroLimpio, -10));
            })
            ->first();

        if (!$cliente) {
            return ['es_cliente' => false, 'nota' => 'No encontré ningún cliente registrado con este número.'];
        }

        $contrato = Contrato::where('aliado_id', $conversacion->aliado_id)
            ->where('cedula', $cliente->cedula)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with('plan:id,nombre')
            ->first();

        if (!$contrato) {
            return [
                'es_cliente' => true,
                'nombre'     => trim(($cliente->primer_nombre ?? '') . ' ' . ($cliente->primer_apellido ?? '')),
                'nota'       => 'Es cliente registrado pero no tiene un contrato vigente activo. Ofrécele pasar con un asesor para revisar su caso.',
            ];
        }

        $saldo = Factura::saldoClienteMesPrevio(
            $conversacion->aliado_id,
            (int) $cliente->cedula,
            (int) now()->month,
            (int) now()->year,
            $contrato->id
        );

        $tienePrestamo = Factura::aliado($conversacion->aliado_id)
            ->where('cedula', $cliente->cedula)
            ->prestamoPendiente()
            ->exists();

        $cuentas = BancoCuenta::paraCobro($conversacion->aliado_id)->map(fn ($b) => [
            'banco'         => $b->banco,
            'tipo_cuenta'   => $b->tipo_cuenta,
            'numero_cuenta' => $b->numero_cuenta,
            'llave'         => $b->llave,
            'titular'       => $b->nombre,
        ]);

        $notas = [
            'El saldo pendiente es real pero preséntalo como informativo — dile al cliente que lo ideal es '
                . 'confirmarlo con un asesor para mayor seguridad. Después de darle la información, pregúntale si '
                . 'quiere que lo escales con un asesor humano (hablar_con_asesor) o si prefiere que sigas '
                . 'ayudándolo con más información (ej. formas de pago).',
        ];
        if ($saldo['a_favor'] > 0) {
            $notas[] = 'Tiene saldo A FAVOR, pero NUNCA le digas el monto ni detalles por chat: solo dile que '
                . 'cuenta con saldo a favor pendiente de revisar y ofrécele pasar con un asesor humano '
                . '(hablar_con_asesor) para los detalles.';
        }
        if ($tienePrestamo) {
            $notas[] = 'Tiene un préstamo asociado, pero NUNCA des montos, cuotas ni condiciones por chat: solo '
                . 'dile que tiene un préstamo activo y ofrécele pasar con un asesor humano (hablar_con_asesor) '
                . 'para revisarlo.';
        }

        return [
            'es_cliente'             => true,
            'nombre'                 => trim(($cliente->primer_nombre ?? '') . ' ' . ($cliente->primer_apellido ?? '')),
            'plan_actual'            => $contrato->plan->nombre ?? null,
            'saldo_pendiente'        => $saldo['pendiente'],
            'tiene_saldo_a_favor'    => $saldo['a_favor'] > 0,
            'tiene_prestamo_activo'  => $tienePrestamo,
            'cuentas_pago'           => $cuentas,
            'nota'                   => implode(' ', $notas),
        ];
    }
}
