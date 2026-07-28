<?php

namespace App\Services\Ia\Tools;

use App\Models\BancoCuenta;
use App\Models\Contrato;
use App\Models\Factura;
use App\Models\WhatsappConversacion;
use App\Services\CobroContratoService;
use App\Services\Ia\ClienteWhatsappResolver;

/**
 * Solo canal WhatsApp. Da información real y específica de la cuenta del cliente que
 * escribe: su plan, cuánto debe pagar este período (o el próximo si ya facturó), saldo
 * pendiente arrastrado, y las cuentas de pago vigentes. Si el número de WhatsApp está
 * registrado para más de una persona, exige verificar con la cédula antes de dar nada.
 *
 * El valor a pagar usa CobroContratoService — la misma lógica que calcula /admin/cobros.
 * Si el cliente tiene varios contratos vigentes, se suman en un solo total (igual que
 * hace la pantalla de cobros).
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
        return 'Consulta la cuenta del cliente CON NOSOTROS: si tiene contrato vigente, su plan, a qué '
            . 'EPS/ARL/fondo de pensión/caja de compensación lo tenemos afiliado, cuánto debe pagarnos este '
            . 'período (o el siguiente si ya facturó), saldo pendiente y las cuentas para pagar. Úsala cuando '
            . 'pregunte cuánto nos debe, cómo pagarnos, o qué plan tiene contratado. '
            . 'NO la uses cuando quiera verificar si sus aportes llegaron de verdad al sistema, si le están '
            . 'pagando completo, o si está activo ante el Estado — eso es chequeo_seguridad_social, que consulta '
            . 'ADRES. Esta herramienta solo sabe lo que hay en nuestra base, no lo que registró el Estado. '
            . 'Si devuelve requiere_cedula=true, pídele la cédula al cliente y vuelve a llamarla con ese dato.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'cedula' => [
                    'type'        => 'string',
                    'description' => 'Cédula de la persona sobre la que se pregunta. Inclúyela SIEMPRE que quien '
                        . 'escribe la mencione en su mensaje (la suya propia, o la de alguien más a quien está '
                        . 'ayudando/consultando) — es la verificación de identidad, tiene prioridad sobre el '
                        . 'número de WhatsApp. También inclúyela si la tool te pidió verificarla '
                        . '(requiere_cedula=true).',
                ],
            ],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $waConversacionId = $contexto['wa_conversacion_id'] ?? null;
        $conversacion = $waConversacionId ? WhatsappConversacion::find($waConversacionId) : null;

        if (!$conversacion) {
            return ['es_cliente' => false, 'nota' => 'No pude verificar el número en este momento.'];
        }

        $aliadoId = $conversacion->aliado_id;

        $resuelto = ClienteWhatsappResolver::resolver($conversacion, $input['cedula'] ?? null);
        if ($resuelto['requiere_cedula']) {
            return ['es_cliente' => null, 'requiere_cedula' => true, 'nota' => ClienteWhatsappResolver::notaRequiereCedula()];
        }

        $cliente = $resuelto['cliente'];
        if (!$cliente) {
            return ['es_cliente' => false, 'nota' => 'No encontré ningún cliente registrado con este número.'];
        }

        // OJO: nunca restringir columnas del eager-load de 'plan' — Contrato::calcularCotizacion()
        // lee $this->plan->incluye_eps/arl/pension/caja internamente; con columnas restringidas
        // esos campos llegan null y el cálculo del valor a pagar da 0 silenciosamente.
        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->where('cedula', $cliente->cedula)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with(['plan', 'eps', 'arl', 'pension', 'caja'])
            ->get();

        $nombreCliente = trim(($cliente->primer_nombre ?? '') . ' ' . ($cliente->primer_apellido ?? ''));

        if ($contratos->isEmpty()) {
            return [
                'es_cliente' => true,
                'nombre'     => $nombreCliente,
                'nota'       => 'Es cliente registrado pero no tiene un contrato vigente activo. Ofrécele pasar con un asesor para revisar su caso.',
            ];
        }

        $mes = (int) now()->month;
        $anio = (int) now()->year;
        $totalAPagar = 0;
        $detalleContratos = [];

        foreach ($contratos as $contrato) {
            $m = $mes;
            $a = $anio;
            $yaFacturado = CobroContratoService::yaFacturado($contrato, $m, $a);

            if ($yaFacturado) {
                $m++;
                if ($m > 12) { $m = 1; $a++; }
            }

            $calc = CobroContratoService::calcular($contrato, $m, $a);
            $totalAPagar += $calc['total'];

            $detalleContratos[] = [
                'contrato_id'              => $contrato->id,
                'plan'                     => $contrato->plan->nombre ?? null,
                'eps'                      => $contrato->eps->nombre ?? null,
                'arl'                      => $contrato->arl->nombre ?? null,
                'fondo_pension'            => $contrato->pension->nombre ?? null,
                'caja_compensacion'        => $contrato->caja->nombre ?? null,
                'ya_facturado_mes_actual'  => $yaFacturado,
                'mes_informado'            => sprintf('%02d/%d', $m, $a),
                'valor'                    => $calc['total'],
            ];
        }

        $saldo = Factura::saldoClienteMesPrevio($aliadoId, (int) $cliente->cedula, $mes, $anio);

        $tienePrestamo = Factura::aliado($aliadoId)
            ->where('cedula', $cliente->cedula)
            ->prestamoPendiente()
            ->exists();

        $cuentas = BancoCuenta::paraCobro($aliadoId)->map(fn ($b) => [
            'banco'         => $b->banco,
            'tipo_cuenta'   => $b->tipo_cuenta,
            'numero_cuenta' => $b->numero_cuenta,
            'llave'         => $b->llave,
            'titular'       => $b->nombre,
        ]);

        $notas = [
            'Estos valores son reales pero preséntalos como informativos — dile al cliente que lo ideal es '
                . 'confirmarlos con un asesor para mayor seguridad. Si hay más de un contrato en detalle_contratos '
                . 'con meses distintos, acláraselo. Después de dar la información, pregúntale si quiere que lo '
                . 'escales con un asesor humano (hablar_con_asesor) o si prefiere que sigas ayudándolo (ej. '
                . 'formas de pago). saldo_pendiente_previo: menciónalo SOLO si es mayor a 0; si es 0 no lo '
                . 'nombres para nada (ni "estás al día" ni el número), no aporta nada al cliente. '
                . 'eps/arl/fondo_pension/caja_compensacion: si el cliente pregunta a qué entidad está afiliado, '
                . 'usa estos campos; si alguno viene null es porque su plan no incluye eso, dilo así en vez de '
                . 'inventar una entidad. '
                . 'es_propia indica si es la cuenta de quien escribe (di "tu cuenta"/"debes") o de alguien más '
                . '(nombra a la persona, ej. "María debe...", nunca digas "tu"/"tú" en ese caso).',
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
            'es_propia'              => $resuelto['es_propia'],
            'nombre'                 => $nombreCliente,
            'valor_a_pagar'          => $totalAPagar,
            'detalle_contratos'      => $detalleContratos,
            'saldo_pendiente_previo' => $saldo['pendiente'],
            'tiene_saldo_a_favor'    => $saldo['a_favor'] > 0,
            'tiene_prestamo_activo'  => $tienePrestamo,
            'cuentas_pago'           => $cuentas,
            'nota'                   => implode(' ', $notas),
        ];
    }
}
