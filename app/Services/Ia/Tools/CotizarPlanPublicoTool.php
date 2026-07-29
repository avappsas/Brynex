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
                . 'seguridad social y administración). Por defecto menciona SOLO costo_afiliacion_sugerido (pago '
                . 'único de afiliación) y valor_mensual_total (mensual) — NUNCA hables de precios proporcionales ni '
                . 'de "primer mes más económico" por iniciativa propia: confunde al cliente con cifras que no pidió. '
                . 'La ÚNICA excepción: si pregunta puntualmente cuánto pagaría el próximo mes si se afilia hoy (o '
                . 'desde una fecha específica), dale solo ese valor puntual (mes_2_valor, con el nombre real del '
                . 'mes: mes_2_nombre) — no el resto del desglose ni los demás meses. Preséntalo como valor de '
                . 'referencia habitual, no como cifra cerrada — el asesor confirma el valor final al afiliar '
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
