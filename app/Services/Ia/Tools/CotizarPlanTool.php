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
            . 'pagar desde el exterior, marca desde_exterior. El sistema elige la modalidad correcta solo. '
            . 'Si el cliente trabaja por días y gana menos del salario mínimo, pregúntale cada cuántos días le '
            . 'pagan (7/14/21/30) y usa tiempo_parcial_dias. Si además está muy interesado en los subsidios/'
            . 'beneficios de la caja de compensación y ya mostró que el valor normal no le sirve, puedes activar '
            . 'ofrecer_plan_economico_caja para un plan más económico especial (NUNCA lo menciones de entrada). '
            . 'Si el cliente solo necesita ARL para poder trabajar (exigencia de un contratante) y el precio '
            . 'normal es un obstáculo, después de decirle el valor normal puedes activar '
            . 'activar_gestion_arl_descuento para ofrecerle un plan B más económico (sin planilla mensual). '
            . 'Si lo que quiere es pagarle la salud a otra persona que no es de su núcleo familiar (un sobrino, '
            . 'un amigo, un padre no beneficiario), marca paga_por_otra_persona: se cotiza como planilla aparte '
            . 'y ahí no se exige pensión. Y si el cliente necesita EPS, NO está exento de pensión y ya dijo que no '
            . 'le alcanza para pagarla, puedes activar ofrecer_estrategia_ingreso_retiro para plantearle un esquema '
            . 'donde paga pocos días al mes y no pierde el servicio de salud.'
            . "\n\n" . $this->glosarioPlanes();
    }

    /**
     * Glosario "para qué sirve cada plan" + dónde aplica la condición de AFP obligatorio,
     * leído directo de planes_contrato.descripcion (editable por el entrenador/admin) para
     * que la IA siempre lo tenga presente sin depender de que lo recuerde de otra parte.
     */
    private function glosarioPlanes(): string
    {
        $planes = PlanContrato::where('activo', true)
            ->whereNotNull('descripcion')
            ->orderBy('id')
            ->get(['nombre', 'descripcion']);

        if ($planes->isEmpty()) {
            return '';
        }

        $lineas = $planes->map(fn (PlanContrato $plan) => "- {$plan->nombre}: {$plan->descripcion}");

        return "Para qué sirve cada plan (interno, nunca leas el nombre exacto al cliente):\n"
            . $lineas->implode("\n");
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
                'tiempo_parcial_dias' => ['type' => 'integer', 'description' => 'SOLO si el cliente trabaja por días y gana menos del salario mínimo: cada cuántos días le pagan (7, 14, 21 o 30). Cambia cómo se cotiza pensión y caja (el ARL siempre se cobra el mes completo). Omite si no aplica.'],
                'ofrecer_plan_economico_caja' => ['type' => 'boolean', 'description' => 'true SOLO si el cliente de tiempo parcial está especialmente interesado en los subsidios/beneficios de la caja de compensación Y ya mostró que el valor normal no le sirve — activa un plan especial más económico. NUNCA lo ofrezcas de entrada ni lo menciones si no se cumple lo anterior.'],
                'cliente_exento_pension' => ['type' => 'boolean', 'description' => 'NO preguntes esto de entrada. Déjalo vacío en la primera cotización: si quien escribe ya es cliente, el sistema lo resuelve solo con su ficha. Solo actívalo (true) cuando la respuesta anterior te haya devuelto pregunta_exencion_pension, se lo hayas preguntado, y el cliente haya confirmado que ya está pensionado, es hombre de 55+ / mujer de 50+, o tiene documento CE/PT/PP/PE/PA. Ser pensionado exime sin importar edad, género ni documento.'],
                'paga_por_otra_persona' => ['type' => 'boolean', 'description' => 'true si el cliente quiere pagar la SALUD de otra persona que NO pertenece a su núcleo familiar (ej. un sobrino, un amigo, un padre no beneficiario) — normalmente él ya tiene su propia planilla. Se cotiza como una planilla aparte solo para esa persona. En este caso no se exige pensión.'],
                'ofrecer_estrategia_ingreso_retiro' => ['type' => 'boolean', 'description' => 'true SOLO si se cumplen las TRES condiciones: (1) el cliente necesita EPS, (2) NO está exento de pensión, y (3) ya dijo que no tiene con qué pagar el aporte completo de pensión. Activa una estrategia donde se afilia normal el primer mes y desde el segundo se pagan pocos días de planilla (afiliándolo en paralelo por otra razón social), para que nunca pierda el servicio de EPS. NUNCA la ofrezcas de entrada ni si el cliente sí puede pagar el plan normal.'],
                'activar_gestion_arl_descuento' => ['type' => 'boolean', 'description' => 'true SOLO cuando el cliente pide Solo ARL (sin nada más), ya conoce el precio normal, y necesita la afiliación solo para poder trabajar (exigencia de un contratante) mostrando que el precio es un obstáculo. Da un 25% de descuento en un plan sin planilla mensual (solo afiliación). SIEMPRE di primero el precio normal.'],
                'salario'         => ['type' => 'number', 'description' => 'Salario o IBC mensual en pesos colombianos. Si el cliente no da un valor, OMÍTELO — se usa el salario mínimo configurado por defecto.'],
                'nivel_arl'       => ['type' => 'integer', 'description' => 'Nivel de riesgo ARL (1 a 5). Pregúntaselo siempre al cliente; si no sabe, omite este campo y se usará el nivel 1 (el más bajo) con una aclaración.'],
                'dias'            => ['type' => 'integer', 'description' => 'Días a cotizar en el mes (1-30). Por defecto 30 (mes completo). No confundir con tiempo_parcial_dias.'],
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

        $esUpc = (bool) ($input['paga_por_otra_persona'] ?? false);

        // Ambos casos son planillas sin empleador local: el exterior porque no hay empresa que
        // aporte, y la UPC porque es una planilla aparte para alguien fuera del núcleo familiar.
        // Sin esto, "Solo EPS" ni siquiera es candidato (resolverPlan lo excluye para dependientes).
        if ($desdeExterior || $esUpc) {
            $esIndependiente = true;
        }

        $tiempoParcialDias = (int) ($input['tiempo_parcial_dias'] ?? 0) ?: null;
        $ofrecerPlanEconomicoCaja = (bool) ($input['ofrecer_plan_economico_caja'] ?? false);
        $ingresoRetiro = (bool) ($input['ofrecer_estrategia_ingreso_retiro'] ?? false);

        // Regla real (Configuración → Modalidades → "AFP obligatorio"): si el cliente pide un
        // plan sin pensión, primero se mira su ficha (si ya es cliente identificado) y solo si
        // ahí no se puede determinar se le pregunta. Nunca se asume que califica.
        $exencionConfirmada = (bool) ($input['cliente_exento_pension'] ?? false);
        $motivoExencionFicha = null;
        if (!$exencionConfirmada) {
            $motivoExencionFicha = $this->motivoExencionDesdeFicha($contexto);
            $exencionConfirmada  = $motivoExencionFicha !== null;
        }

        $seAgregoPensionPorNormativa = false;
        if (CotizacionPublicaService::requiereConfirmarExencionPension($componentes, $exencionConfirmada, $desdeExterior, $esUpc)) {
            $componentes['incluye_pension'] = true;
            $seAgregoPensionPorNormativa = true;
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
        $tipoModalidad = CotizacionPublicaService::resolverModalidadPermitida(
            $plan, $esIndependiente, $desdeExterior, $tiempoParcialDias, $ofrecerPlanEconomicoCaja, $esUpc, $ingresoRetiro
        );
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

        if ($seAgregoPensionPorNormativa) {
            $resultado['nota_afp'] = 'El cliente pidió un plan SIN pensión, pero en este esquema la pensión es '
                . 'obligatoria salvo excepción, y su ficha no confirma ninguna. Se cotizó CON pensión incluida (el '
                . 'valor ya lo refleja) para no darle un precio que no se le puede honrar.';
            $resultado['pregunta_exencion_pension'] = [
                'preguntar' => 'Antes de cerrar el precio, pregúntale de forma natural y en UN solo mensaje si se '
                    . 'da alguno de estos tres casos: (1) ya está pensionado, (2) es hombre de 55 años o más / mujer '
                    . 'de 50 o más, (3) es extranjero con cédula de extranjería, permiso de protección temporal, '
                    . 'permiso especial o pasaporte. No le expliques la normativa ni le pidas la edad exacta: basta '
                    . 'con saber si aplica alguno.',
                'si_aplica' => 'Vuelve a llamar cotizar_plan con cliente_exento_pension=true y los mismos '
                    . 'componentes (sin pensión) para darle el valor más bajo.',
                'si_no_aplica' => 'El valor ya cotizado es el correcto: explícale que en su caso la pensión va '
                    . 'incluida por normativa, sin entrar en tecnicismos.',
            ];
        } elseif ($motivoExencionFicha && !$plan->incluye_pension) {
            $resultado['nota_afp'] = "Se pudo omitir la pensión porque la ficha del cliente indica que {$motivoExencionFicha}. "
                . 'No necesitas preguntarle nada sobre esto ni mencionarle el motivo.';
        } elseif (!$plan->incluye_pension && $plan->id !== 2 && !$desdeExterior && !$esUpc) {
            $resultado['nota_afp'] = 'Este plan sin pensión solo aplica porque el cliente confirmó estar ya pensionado, '
                . 'ser hombre 55+, mujer 50+, o tener documento CE/PT/PP/PE/PA. Si eso cambia, hay que cotizar con '
                . 'pensión incluida.';
        }

        if ($esUpc) {
            $resultado['nota_upc'] = 'Cotizado como planilla aparte para una persona fuera del núcleo familiar del '
                . 'cliente. Aquí no se exige aporte a pensión. Recuérdale que es una planilla independiente de la suya.';
        }

        if ($tiempoParcialDias) {
            $resultado['nota_tiempo_parcial'] = "Cotizado por días (cada {$tiempoParcialDias} días) — el ARL se "
                . 'cobra el mes completo, pensión y caja se prorratean a esos días.';
        }

        // Estrategia Ingreso-Retiro: el valor cotizado es el del primer mes (afiliación normal);
        // desde el segundo se pagan pocos días, así que se calcula ese valor recurrente aparte.
        if ($ingresoRetiro) {
            $diasSiguientes = 6;
            $recurrente = CotizacionPublicaService::cotizar($plan, $tipoModalidad, $alidoId, [
                'salario'   => $salario,
                'nivel_arl' => (int) ($input['nivel_arl'] ?? 1),
                'dias'      => $diasSiguientes,
            ]);
            $resultado['plan_estrategia_ingreso_retiro'] = [
                'valor_primer_mes'      => $resultado['total'],
                'dias_meses_siguientes' => $diasSiguientes,
                'valor_meses_siguientes' => $recurrente['total'],
                'nota' => 'Estrategia para que el cliente NO pierda el servicio de EPS cuando no puede pagar el '
                    . 'aporte completo de pensión: el primer mes se afilia normal (queda con servicio de una vez), y '
                    . "desde el segundo mes se pagan solo {$diasSiguientes} días de planilla, afiliándolo en paralelo "
                    . 'por otra razón social para repetirlo el mes siguiente. Explícale claramente que la planilla NO '
                    . 'será de 30 días. Ofrécela solo si el cliente no está exento de pensión y ya dijo que no le '
                    . 'alcanza para el plan normal.',
            ];
        }

        if ($tipoModalidad->id === (int) \App\Services\CotizacionPublicaService::MODALIDAD_GESTION_ARL_ID) {
            $resultado['nota_gestion_arl'] = 'Este valor es solo la afiliación/radicado a la ARL, SIN planilla '
                . 'mensual — explícaselo así al cliente, no es un plan de pago recurrente.';
        }

        if ((bool) ($input['activar_gestion_arl_descuento'] ?? false) && $componentes === ['incluye_eps' => false, 'incluye_arl' => true, 'incluye_pension' => false, 'incluye_caja' => false]) {
            $descuento = CotizacionPublicaService::cotizarGestionArlConDescuento($alidoId, (int) ($input['nivel_arl'] ?? 1));
            $resultado['plan_b_gestion_arl'] = [
                'valor_normal'    => $descuento['valor_normal'],
                'valor_descuento' => $descuento['valor_descuento'],
                'porcentaje'      => $descuento['porcentaje'],
                'nota'            => 'SIEMPRE di primero el valor normal. Este plan B es solo la afiliación a la ARL '
                    . '(sin planilla mensual) con 25% de descuento — ofrécelo solo si el cliente mostró que el precio '
                    . 'normal es un obstáculo para poder trabajar.',
            ];
        }

        return $resultado;
    }

    /**
     * Si quien escribe ya está identificado como cliente, su ficha decide la exención de
     * pensión sin preguntarle nada (ahí ya está si es pensionado, su documento y su edad).
     * Devuelve el motivo, o null si no hay cliente o no califica por ficha.
     */
    private function motivoExencionDesdeFicha(array $contexto): ?string
    {
        $conversacionId = $contexto['wa_conversacion_id'] ?? null;
        if (!$conversacionId) {
            return null;
        }

        $conversacion = \App\Models\WhatsappConversacion::find($conversacionId);
        if (!$conversacion) {
            return null;
        }

        $resuelto = \App\Services\Ia\ClienteWhatsappResolver::resolver($conversacion, null);

        return $resuelto['cliente']?->motivoExencionAfp();
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
