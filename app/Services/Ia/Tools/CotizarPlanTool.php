<?php

namespace App\Services\Ia\Tools;

use App\Models\ConfiguracionAliado;
use App\Models\PlanContrato;
use App\Models\TipoModalidad;
use App\Services\CotizadorService;

class CotizarPlanTool implements IaToolInterface
{
    public function nombre(): string
    {
        return 'cotizar_plan';
    }

    public function descripcion(): string
    {
        return 'Calcula el valor mensual de un plan de seguridad social (EPS, ARL, pensión, caja, administración, IVA) '
            . 'usando los precios y porcentajes configurados para el aliado actual. Úsala cuando pregunten '
            . '"cuánto cuesta", "cotízame", o pidan un valor de un plan.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'plan'            => ['type' => 'string', 'description' => 'Nombre o código del plan (ej: "Integral", "Básico"). Si no lo sabes, pide al usuario que elija uno de los disponibles.'],
                'tipo_modalidad'  => ['type' => 'string', 'description' => 'Nombre de la modalidad/tipo de vinculación (ej: "Dependiente", "Independiente", "Tiempo Parcial").'],
                'salario'         => ['type' => 'number', 'description' => 'Salario o IBC base mensual en pesos colombianos.'],
                'nivel_arl'       => ['type' => 'integer', 'description' => 'Nivel de riesgo ARL (1 a 5). Por defecto 1.'],
                'dias'            => ['type' => 'integer', 'description' => 'Días a cotizar en el mes (1-30). Por defecto 30 (mes completo).'],
            ],
            'required' => ['plan', 'tipo_modalidad', 'salario'],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $alidoId = $contexto['aliado_id'] ?? null;

        $plan = PlanContrato::where('activo', true)
            ->where(function ($q) use ($input) {
                $q->where('nombre', 'like', '%' . ($input['plan'] ?? '') . '%')
                  ->orWhere('codigo', 'like', '%' . ($input['plan'] ?? '') . '%');
            })
            ->first();

        if (!$plan) {
            $disponibles = PlanContrato::where('activo', true)->pluck('nombre');
            return ['error' => 'No encontré ese plan.', 'planes_disponibles' => $disponibles];
        }

        $tipoModalidad = TipoModalidad::activos()
            ->where(function ($q) use ($input) {
                $texto = $input['tipo_modalidad'] ?? '';
                $q->where('tipo_modalidad', 'like', "%{$texto}%")
                  ->orWhere('observacion', 'like', "%{$texto}%");
            })
            ->first();

        if (!$tipoModalidad) {
            $disponibles = TipoModalidad::activos()->get()->map->nombre;
            return ['error' => 'No encontré esa modalidad/tipo de vinculación.', 'modalidades_disponibles' => $disponibles];
        }

        $cfg = ConfiguracionAliado::paraAliado($alidoId, $plan->id);

        $resultado = CotizadorService::calcular([
            'tipo_modalidad_id' => $tipoModalidad->id,
            'plan_id'           => $plan->id,
            'n_arl'             => (int) ($input['nivel_arl'] ?? 1),
            'salario'           => (float) ($input['salario'] ?? 0),
            'administracion'    => (float) ($cfg->administracion ?? 0),
            'admon_asesor'      => (float) ($cfg->admon_asesor ?? 0),
            'seguro'            => (float) ($cfg->seguro_valor ?? 0),
            'dias'              => (int) ($input['dias'] ?? 30),
        ], $alidoId);

        return $resultado;
    }
}
