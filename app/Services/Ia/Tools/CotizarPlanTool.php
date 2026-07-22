<?php

namespace App\Services\Ia\Tools;

use App\Models\ConfiguracionAliado;
use App\Models\ConfiguracionBrynex;
use App\Models\PlanContrato;
use App\Models\TipoModalidad;
use App\Services\CotizadorService;

class CotizarPlanTool implements IaToolInterface
{
    /** Modalidad "Dependiente E" — la que se usa por defecto si el cliente no especifica otra. */
    private const MODALIDAD_DEPENDIENTE_ID = 0;

    public function nombre(): string
    {
        return 'cotizar_plan';
    }

    public function descripcion(): string
    {
        return 'Calcula el valor mensual de un plan de seguridad social usando los precios configurados para el '
            . 'aliado actual. Identifica el plan por SUS COMPONENTES (qué quiere el cliente: EPS/salud, ARL/ARP, '
            . 'AFP/pensión, CCF/caja de compensación) — no le preguntes el nombre exacto del plan, tradúcelo tú '
            . 'de lo que diga. Úsala cuando pregunten "cuánto cuesta", "cotízame", o pidan un valor.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'incluye_eps'     => ['type' => 'boolean', 'description' => 'El cliente quiere EPS / salud.'],
                'incluye_arl'     => ['type' => 'boolean', 'description' => 'El cliente quiere ARL / ARP / riesgos laborales.'],
                'incluye_pension' => ['type' => 'boolean', 'description' => 'El cliente quiere AFP / fondo de pensión.'],
                'incluye_caja'    => ['type' => 'boolean', 'description' => 'El cliente quiere CCF / caja de compensación.'],
                'tipo_modalidad'  => ['type' => 'string', 'description' => 'Tipo de vinculación (ej: "Dependiente", "Independiente", "Tiempo Parcial"). Si el cliente no lo menciona, OMÍTELO — se usa "Dependiente" por defecto.'],
                'salario'         => ['type' => 'number', 'description' => 'Salario o IBC mensual en pesos colombianos. Si el cliente no da un valor, OMÍTELO — se usa el salario mínimo configurado por defecto.'],
                'nivel_arl'       => ['type' => 'integer', 'description' => 'Nivel de riesgo ARL (1 a 5). Pregúntaselo siempre al cliente; si no sabe, omite este campo y se usará el nivel 1 (el más bajo) con una aclaración.'],
                'dias'            => ['type' => 'integer', 'description' => 'Días a cotizar en el mes (1-30). Por defecto 30 (mes completo).'],
            ],
            'required' => ['incluye_eps', 'incluye_arl', 'incluye_pension', 'incluye_caja'],
        ];
    }

    public function ejecutar(array $input, array $contexto): array
    {
        $alidoId = $contexto['aliado_id'] ?? null;

        $componentes = [
            'incluye_eps'     => (bool) ($input['incluye_eps'] ?? false),
            'incluye_arl'     => (bool) ($input['incluye_arl'] ?? false),
            'incluye_pension' => (bool) ($input['incluye_pension'] ?? false),
            'incluye_caja'    => (bool) ($input['incluye_caja'] ?? false),
        ];

        if (!in_array(true, $componentes, true)) {
            return ['error' => 'No identifiqué qué quiere el cliente (EPS, ARL, pensión, caja). Pregúntale cuáles de esos necesita.'];
        }

        [$plan, $coincidenciaExacta] = $this->resolverPlan($componentes);

        if (!$plan) {
            return [
                'error'              => 'No tenemos ningún plan configurado con esa combinación.',
                'planes_disponibles' => PlanContrato::where('activo', true)->pluck('nombre'),
            ];
        }

        $tipoModalidad = $this->resolverModalidad($input['tipo_modalidad'] ?? null);
        if (!$tipoModalidad) {
            $disponibles = TipoModalidad::activos()->get()->map->nombre;
            return ['error' => 'No encontré esa modalidad/tipo de vinculación.', 'modalidades_disponibles' => $disponibles];
        }

        $salario = isset($input['salario']) && $input['salario'] > 0
            ? (float) $input['salario']
            : ConfiguracionBrynex::salarioMinimo();

        $cfg = ConfiguracionAliado::paraAliado($alidoId, $plan->id);

        $resultado = CotizadorService::calcular([
            'tipo_modalidad_id' => $tipoModalidad->id,
            'plan_id'           => $plan->id,
            'n_arl'             => (int) ($input['nivel_arl'] ?? 1),
            'salario'           => $salario,
            'administracion'    => (float) ($cfg->administracion ?? 0),
            'admon_asesor'      => (float) ($cfg->admon_asesor ?? 0),
            'seguro'            => (float) ($cfg->seguro_valor ?? 0),
            'dias'              => (int) ($input['dias'] ?? 30),
        ], $alidoId);

        $resultado['coincidencia_exacta'] = $coincidenciaExacta;
        $resultado['salario_usado']       = $salario;
        $resultado['nivel_arl_usado']     = (int) ($input['nivel_arl'] ?? 1);
        $resultado['nivel_arl_default']   = !isset($input['nivel_arl']);

        if (!$coincidenciaExacta) {
            $resultado['nota_plan'] = "No tenemos exactamente esa combinación; el más cercano disponible es \"{$plan->nombre}\". Confírmale al cliente si le sirve antes de darlo por definitivo.";
        }

        if (!$plan->incluye_pension && ConfiguracionBrynex::reglaAfpObligatorio()) {
            $resultado['nota_afp'] = 'Este plan sin fondo de pensión (AFP) aplica para quienes están exentos por '
                . 'edad (mujeres desde 50 años, hombres desde 55 años). Coméntaselo al cliente de forma informativa '
                . '(sin preguntar su edad/género): si no cumple esa condición, igual se le puede ofrecer el plan.';
        }

        return $resultado;
    }

    /**
     * Busca el plan cuyos componentes coincidan EXACTAMENTE con lo pedido. Si no existe,
     * busca el plan más cercano que incluya AL MENOS lo pedido (superset con menos extras).
     *
     * @return array{0: ?PlanContrato, 1: bool} [plan, esCoincidenciaExacta]
     */
    private function resolverPlan(array $componentes): array
    {
        $exacto = PlanContrato::where('activo', true)
            ->where('incluye_eps', $componentes['incluye_eps'])
            ->where('incluye_arl', $componentes['incluye_arl'])
            ->where('incluye_pension', $componentes['incluye_pension'])
            ->where('incluye_caja', $componentes['incluye_caja'])
            ->first();

        if ($exacto) {
            return [$exacto, true];
        }

        // Buscar planes que incluyan AL MENOS lo pedido (superset), y quedarnos con
        // el que tenga menos componentes extra (el más ajustado a lo pedido).
        $candidatos = PlanContrato::where('activo', true)->get()->filter(function ($p) use ($componentes) {
            return (!$componentes['incluye_eps']     || $p->incluye_eps)
                && (!$componentes['incluye_arl']     || $p->incluye_arl)
                && (!$componentes['incluye_pension'] || $p->incluye_pension)
                && (!$componentes['incluye_caja']    || $p->incluye_caja);
        });

        if ($candidatos->isEmpty()) {
            return [null, false];
        }

        $extras = fn ($p) => (int) $p->incluye_eps + (int) $p->incluye_arl + (int) $p->incluye_pension + (int) $p->incluye_caja;
        $mejor = $candidatos->sortBy($extras)->first();

        return [$mejor, false];
    }

    private function resolverModalidad(?string $texto): ?TipoModalidad
    {
        if (!$texto) {
            return TipoModalidad::find(self::MODALIDAD_DEPENDIENTE_ID);
        }

        return TipoModalidad::activos()
            ->where(function ($q) use ($texto) {
                $q->where('tipo_modalidad', 'like', "%{$texto}%")
                  ->orWhere('observacion', 'like', "%{$texto}%");
            })
            ->first();
    }
}
