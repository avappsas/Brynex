<?php

namespace App\Services\Ia\Tools;

use App\Models\ConfiguracionBrynex;
use App\Models\PlanContrato;
use App\Models\TipoModalidad;
use App\Services\CotizacionPublicaService;

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
            . 'de lo que diga. Úsala cuando pregunten "cuánto cuesta", "cotízame", o pidan un valor. '
            . 'NUNCA le preguntes al cliente por "modalidad" ni "tipo de vinculación" — eso es interno: deduce de '
            . 'la conversación si es empleado o trabaja por cuenta propia (si no está claro, pregúntalo de forma '
            . 'natural: "¿trabajas como empleado o por cuenta propia?"), y si menciona estar fuera de Colombia o '
            . 'pagar desde el exterior, marca desde_exterior. El sistema elige la modalidad correcta solo.';
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
                'es_independiente' => ['type' => 'boolean', 'description' => 'true si el cliente trabaja por cuenta propia / es independiente; false si es empleado con contrato. Si no está claro en la conversación, OMÍTELO (se asume empleado) — NO uses la palabra "modalidad" con el cliente.'],
                'desde_exterior'  => ['type' => 'boolean', 'description' => 'true si el cliente vive fuera de Colombia o quiere pagar/cotizar desde el exterior (ej. "pagar pensión desde el exterior"). Implica que es independiente.'],
                'salario'         => ['type' => 'number', 'description' => 'Salario o IBC mensual en pesos colombianos. Si el cliente no da un valor, OMÍTELO — se usa el salario mínimo configurado por defecto.'],
                'nivel_arl'       => ['type' => 'integer', 'description' => 'Nivel de riesgo ARL (1 a 5). Pregúntaselo siempre al cliente; si no sabe, omite este campo y se usará el nivel 1 (el más bajo) con una aclaración.'],
                'dias'            => ['type' => 'integer', 'description' => 'Días a cotizar en el mes (1-30). Por defecto 30 (mes completo).'],
                'fecha_afiliacion' => ['type' => 'string', 'description' => 'Fecha en que el cliente quiere afiliarse, formato AAAA-MM-DD. Si el cliente menciona una fecha (ej. "desde el 1 de julio"), inclúyela — cambia cómo se prorratea el segundo mes. Si no menciona ninguna, OMÍTELO — se usa la fecha de hoy.'],
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

        $desdeExterior   = (bool) ($input['desde_exterior'] ?? false);
        $esIndependiente = (bool) ($input['es_independiente'] ?? false);

        // Compatibilidad con el campo viejo tipo_modalidad (texto libre) por si el modelo
        // aún lo manda: solo se usa para deducir el perfil, ya no fija la modalidad directo.
        if (!isset($input['es_independiente']) && !empty($input['tipo_modalidad'])) {
            $legacy = $this->resolverModalidad($input['tipo_modalidad']);
            if ($legacy) {
                $esIndependiente = $legacy->esIndependiente();
                $desdeExterior   = $desdeExterior || (int) $legacy->id === 14;
            }
        }

        // Pagar desde el exterior implica trabajar por cuenta propia (no hay empleador local).
        if ($desdeExterior) {
            $esIndependiente = true;
        }

        [$plan, $coincidenciaExacta] = CotizacionPublicaService::resolverPlan($componentes, $esIndependiente);

        if (!$plan) {
            return [
                'error'              => 'No tenemos ningún plan configurado con esa combinación.',
                'planes_disponibles' => PlanContrato::where('activo', true)->pluck('nombre'),
            ];
        }

        // La modalidad se resuelve contra modalidad_planes (la misma tabla del cotizador
        // admin) — interna, nunca se le pregunta al cliente.
        $tipoModalidad = CotizacionPublicaService::resolverModalidadPermitida($plan, $esIndependiente, $desdeExterior);
        if (!$tipoModalidad) {
            return ['error' => "El plan \"{$plan->nombre}\" requiere asesoría personalizada — dile al cliente que un asesor lo contactará para ese caso."];
        }

        $salario = isset($input['salario']) && $input['salario'] > 0
            ? (float) $input['salario']
            : ConfiguracionBrynex::salarioMinimo();

        $resultado = CotizacionPublicaService::cotizar($plan, $tipoModalidad, $alidoId, [
            'salario'          => $salario,
            'nivel_arl'        => (int) ($input['nivel_arl'] ?? 1),
            'dias'             => (int) ($input['dias'] ?? 30),
            'fecha_afiliacion' => $input['fecha_afiliacion'] ?? null,
        ]);

        $resultado['coincidencia_exacta'] = $coincidenciaExacta;
        $resultado['salario_usado']       = $salario;
        $resultado['nivel_arl_usado']     = (int) ($input['nivel_arl'] ?? 1);
        $resultado['nivel_arl_default']   = !isset($input['nivel_arl']);
        $resultado['modalidad_usada']     = $tipoModalidad->nombre;

        // Si el perfil pedido no coincide con la modalidad final (ej. pidió como empleado
        // pero el plan solo existe para independientes), darle contexto a la IA para que lo
        // explique con naturalidad — sin usar la palabra "modalidad" con el cliente.
        if (!$esIndependiente && $tipoModalidad->esIndependiente()) {
            $resultado['nota_modalidad'] = "Este plan solo se ofrece para trabajadores por cuenta propia (no por empleador). Explícaselo al cliente en lenguaje simple.";
        }
        if ($desdeExterior) {
            $resultado['nota_modalidad'] = "Cotizado con el esquema para colombianos en el exterior. Menciónale que aplica para pagos desde fuera del país.";
        }

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
