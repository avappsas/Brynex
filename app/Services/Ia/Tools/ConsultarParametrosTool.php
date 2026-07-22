<?php

namespace App\Services\Ia\Tools;

use App\Models\ArlTarifa;
use App\Models\ConfiguracionAliado;
use App\Models\ConfiguracionBrynex;
use App\Models\PlanContrato;

class ConsultarParametrosTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'consultar_parametros';
    }

    public function descripcion(): string
    {
        return 'Consulta los parámetros y precios configurados actualmente para el aliado: administración, '
            . 'costo de afiliación, seguro, tarifas ARL por nivel, y porcentajes globales de seguridad social '
            . '(salud, pensión, caja, IVA). Úsala para preguntas sobre precios o porcentajes configurados.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $alidoId = $contexto['aliado_id'] ?? null;

        $configsPorPlan = ConfiguracionAliado::where('aliado_id', $alidoId)
            ->with('plan:id,nombre')
            ->get()
            ->map(fn ($c) => [
                'plan'             => $c->plan->nombre ?? 'General (todos los planes)',
                'administracion'   => (float) $c->administracion,
                'admon_asesor'     => (float) $c->admon_asesor,
                'costo_afiliacion' => (float) $c->costo_afiliacion,
                'seguro_valor'     => (float) $c->seguro_valor,
            ]);

        $arl = ArlTarifa::where('aliado_id', $alidoId)
            ->orWhereNull('aliado_id')
            ->orderBy('nivel')
            ->get()
            ->groupBy('nivel')
            ->map(fn ($grupo) => (float) ($grupo->firstWhere('aliado_id', $alidoId)->porcentaje ?? $grupo->first()->porcentaje));

        return [
            'planes_disponibles'   => PlanContrato::where('activo', true)->pluck('nombre'),
            'configuracion_planes' => $configsPorPlan,
            'tarifas_arl_pct'      => $arl,
            'porcentajes_globales' => [
                'salud_dependiente'      => ConfiguracionBrynex::pctSaludDependiente(),
                'pension_dependiente'    => ConfiguracionBrynex::pctPensionDependiente(),
                'caja_dependiente'       => ConfiguracionBrynex::pctCajaDependiente(),
                'salud_independiente'    => ConfiguracionBrynex::pctSaludIndependiente(),
                'pension_independiente'  => ConfiguracionBrynex::pctPensionIndependiente(),
                'caja_independiente_alto'=> ConfiguracionBrynex::pctCajaIndependienteAlto(),
                'caja_independiente_bajo'=> ConfiguracionBrynex::pctCajaIndependienteBajo(),
                'iva'                    => ConfiguracionBrynex::porcentajeIva(),
                'salario_minimo'         => ConfiguracionBrynex::salarioMinimo(),
            ],
        ];
    }
}
