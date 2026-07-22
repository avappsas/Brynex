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
            'plan'                 => $resultado['plan_nombre'] ?? null,
            'modalidad'            => $resultado['tipo_modalidad_nombre'] ?? null,
            'valor_mensual_total'  => $resultado['total'] ?? null,
            'dias_cotizados'       => $resultado['dias'] ?? null,
            'salario_usado'        => $resultado['salario_usado'] ?? null,
            'nivel_arl_usado'      => $resultado['nivel_arl_usado'] ?? null,
            'nivel_arl_default'    => $resultado['nivel_arl_default'] ?? null,
            'coincidencia_exacta'  => $resultado['coincidencia_exacta'] ?? true,
            'nota'                 => 'Este valor incluye seguridad social y administración. Si el cliente quiere '
                . 'proceder con la afiliación o hablar el detalle con alguien, ofrécele pasar con un asesor (hablar_con_asesor).',
        ];

        if (!empty($resultado['nota_plan'])) {
            $salida['nota_plan'] = $resultado['nota_plan'];
        }
        if (!empty($resultado['nota_afp'])) {
            $salida['nota_afp'] = $resultado['nota_afp'];
        }

        return $salida;
    }
}
