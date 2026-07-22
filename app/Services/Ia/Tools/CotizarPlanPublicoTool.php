<?php

namespace App\Services\Ia\Tools;

/**
 * Versión "de cara al cliente" de cotizar_plan, usada únicamente en el canal WhatsApp.
 * Delega el cálculo real en CotizarPlanTool (misma lógica, mismos precios) pero devuelve
 * solo el valor final: nunca expone el desglose interno (EPS/ARL/pensión/caja) ni la
 * comisión del asesor, que son datos operativos internos del aliado.
 */
class CotizarPlanPublicoTool implements IaToolInterface
{
    private CotizarPlanTool $interno;

    public function __construct()
    {
        $this->interno = new CotizarPlanTool();
    }

    public function nombre(): string
    {
        return 'cotizar_plan';
    }

    public function descripcion(): string
    {
        return $this->interno->descripcion();
    }

    public function schema(): array
    {
        return $this->interno->schema();
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $resultado = $this->interno->ejecutar($input, $contexto);

        if (isset($resultado['error'])) {
            return $resultado;
        }

        $salida = [
            'plan'                      => $resultado['plan_nombre'] ?? null,
            'modalidad'                 => $resultado['tipo_modalidad_nombre'] ?? null,
            'fecha_afiliacion'          => $resultado['fecha_afiliacion'] ?? null,
            'valor_mensual_total'       => $resultado['total'] ?? null,
            'costo_afiliacion_sugerido' => $resultado['costo_afiliacion_sugerido'] ?? null,
            'dias_cotizados'            => $resultado['dias'] ?? null,
            'salario_usado'             => $resultado['salario_usado'] ?? null,
            'nivel_arl_usado'           => $resultado['nivel_arl_usado'] ?? null,
            'nivel_arl_default'         => $resultado['nivel_arl_default'] ?? null,
            'coincidencia_exacta'       => $resultado['coincidencia_exacta'] ?? true,
        ];

        if (!empty($resultado['plan_pago_inicial'])) {
            $salida['plan_pago_inicial'] = $resultado['plan_pago_inicial'];
            $salida['nota'] = 'valor_mensual_total es el cobro recurrente desde mes_3_nombre en adelante (ya incluye '
                . 'seguridad social y administración). NO lo abrumes con el desglose completo en la primera '
                . 'respuesta: da solo valor_mensual_total y una frase corta de que el primer mes cuesta menos (sin '
                . 'cifras todavía). Si pregunta por el primer mes, duda por el precio, o está listo para avanzar, '
                . 'ahí sí da el desglose completo usando los NOMBRES DE MES reales (mes_1_nombre, mes_2_nombre, '
                . 'mes_3_nombre), no digas "mes 1/2/3": mes_1_afiliacion es TODO lo que paga en mes_1_nombre (solo '
                . 'la afiliación, sin SS ni administración); mes_2_valor es lo que paga en mes_2_nombre (los '
                . 'mes_2_dias_proporcionales días del mes vencido + administración completa); desde mes_3_nombre ya '
                . 'paga valor_mensual_total completo. Preséntalo como valor de referencia habitual ("normalmente '
                . 'empiezas pagando solo $X"), no como cifra cerrada — el asesor confirma el valor final al afiliar '
                . '(hablar_con_asesor).';
        } else {
            $salida['nota'] = 'valor_mensual_total ya incluye seguridad social y administración — es el cobro '
                . 'recurrente de cada mes. costo_afiliacion_sugerido es un cobro APARTE y de una sola vez, '
                . 'preséntalo siempre como un valor de referencia habitual, nunca como una cifra cerrada — el valor '
                . 'final lo confirma un asesor humano al momento de afiliar (hablar_con_asesor).';
        }

        if (!empty($resultado['nota_plan'])) {
            $salida['nota_plan'] = $resultado['nota_plan'];
        }
        if (!empty($resultado['nota_afp'])) {
            $salida['nota_afp'] = $resultado['nota_afp'];
        }

        return $salida;
    }
}
