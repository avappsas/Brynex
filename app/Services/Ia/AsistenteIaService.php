<?php

namespace App\Services\Ia;

use App\Models\Aliado;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\IaConfiguracionAliado;
use App\Models\IaConsumo;
use App\Models\IaConversacion;
use App\Models\IaMensaje;
use App\Services\Ia\Tools\BuscarConocimientoTool;
use App\Services\Ia\Tools\BuscarInternetTool;
use App\Services\Ia\Tools\CatalogoModulosTool;
use App\Services\Ia\Tools\ConsultarClienteTool;
use App\Services\Ia\Tools\ConsultarParametrosTool;
use App\Services\Ia\Tools\CotizarPlanPublicoTool;
use App\Services\Ia\Tools\CotizarPlanTool;
use App\Services\Ia\Tools\HablarConAsesorTool;
use App\Services\Ia\Tools\IaToolInterface;
use App\Services\Ia\Tools\PreguntarEntrenadorTool;
use Illuminate\Support\Facades\Log;

class AsistenteIaService
{
    private const MAX_ITERACIONES_TOOL = 5;
    private const MENSAJES_HISTORIAL = 12; // últimos N mensajes de contexto

    /**
     * Procesa un mensaje del usuario en el canal web (empleados/asesores autenticados)
     * y devuelve la respuesta del asistente, con las herramientas completas.
     *
     * @return array{respuesta: string, acciones: array, conversacion_id: int}
     */
    public function responderWeb(int $alidoId, int $userId, string $mensajeUsuario): array
    {
        $config = IaConfiguracionAliado::paraAliado($alidoId);
        if (!$config->activo_web) {
            throw new \RuntimeException('El asistente IA no está activo para este aliado.');
        }

        $credenciales = $config->credencialesEfectivas();
        if (empty($credenciales['api_key'])) {
            throw new \RuntimeException('El asistente IA no tiene una clave de API configurada. Contacta a BryNex.');
        }

        $conversacion = IaConversacion::paraUsuarioWeb($alidoId, $userId);

        $aliado = Aliado::find($alidoId);
        $systemPrompt = $this->construirSystemPromptWeb($aliado?->nombre ?? 'tu aliado', $config->nombreBot());
        $tools = $this->construirToolsWeb($credenciales);
        $contextoExtra = ['canal' => 'web'];

        $resultado = $this->ejecutarTurno($conversacion, $mensajeUsuario, $systemPrompt, $tools, $credenciales, $contextoExtra, 'web');

        return [
            'respuesta'       => $resultado['respuesta'],
            'acciones'        => $resultado['acciones'],
            'conversacion_id' => $conversacion->id,
            'nombre_bot'      => $config->nombreBot(),
        ];
    }

    /**
     * Procesa un mensaje entrante de WhatsApp (cliente externo) y devuelve la respuesta
     * del asistente, con un set de herramientas restringido: nunca expone parámetros
     * internos del aliado ni navegación del sistema.
     *
     * @return array{respuesta: string, conversacion_id: int}
     */
    public function responderWhatsapp(
        int $alidoId,
        string $telefono,
        string $mensajeUsuario,
        ?int $waConversacionId,
        ?string $origenCampana = null,
        ?string $origenCampanaCategoria = null
    ): array {
        $config = IaConfiguracionAliado::paraAliado($alidoId);
        if (!$config->activo_whatsapp) {
            throw new \RuntimeException('El asistente IA no está activo en WhatsApp para este aliado.');
        }

        $credenciales = $config->credencialesEfectivas();
        if (empty($credenciales['api_key'])) {
            throw new \RuntimeException('El asistente IA no tiene una clave de API configurada.');
        }

        $conversacion = IaConversacion::paraTelefono($alidoId, $telefono);
        $clienteInfo = $this->resolverClienteExistente($alidoId, $telefono);

        $aliado = Aliado::find($alidoId);
        $systemPrompt = $this->construirSystemPromptWhatsapp(
            $aliado?->nombre ?? 'nuestra empresa',
            $config->nombreBot(),
            $origenCampana,
            $origenCampanaCategoria,
            $clienteInfo
        );
        $tools = $this->construirToolsWhatsapp($credenciales);
        $contextoExtra = ['canal' => 'whatsapp', 'wa_conversacion_id' => $waConversacionId];

        $resultado = $this->ejecutarTurno($conversacion, $mensajeUsuario, $systemPrompt, $tools, $credenciales, $contextoExtra, 'whatsapp');

        return [
            'respuesta'       => $resultado['respuesta'],
            'conversacion_id' => $conversacion->id,
            'nombre_bot'      => $config->nombreBot(),
        ];
    }

    /**
     * Herramientas para empleados/asesores (canal web): acceso completo a consultas y navegación.
     * buscar_internet solo se agrega si el proveedor es Claude (server tool nativo de Anthropic).
     *
     * @return IaToolInterface[]
     */
    private function construirToolsWeb(array $credenciales): array
    {
        $tools = [
            new CotizarPlanTool(),
            new ConsultarParametrosTool(),
            new CatalogoModulosTool(),
            new BuscarConocimientoTool(),
        ];

        if (($credenciales['proveedor'] ?? null) === 'claude') {
            $tools[] = new BuscarInternetTool();
        }

        $tools[] = new PreguntarEntrenadorTool();

        return $tools;
    }

    /**
     * Herramientas para clientes externos (canal WhatsApp): cotización simplificada
     * (sin desglose interno ni comisiones), conocimiento y opción de pasar con un humano.
     * Nunca incluye consultar_parametros ni catalogo_modulos (son información/navegación interna).
     *
     * @return IaToolInterface[]
     */
    private function construirToolsWhatsapp(array $credenciales): array
    {
        $tools = [
            new CotizarPlanPublicoTool(),
            new BuscarConocimientoTool(),
        ];

        if (($credenciales['proveedor'] ?? null) === 'claude') {
            $tools[] = new BuscarInternetTool();
        }

        $tools[] = new PreguntarEntrenadorTool();
        $tools[] = new HablarConAsesorTool();
        $tools[] = new ConsultarClienteTool();

        return $tools;
    }

    /**
     * Verificación barata (sin saldo) de si el número que escribe ya es cliente con
     * contrato vigente, para ajustar el tono desde el primer mensaje. El saldo y las
     * cuentas de pago solo se consultan bajo demanda vía la tool consultar_cliente.
     *
     * @return array{es_cliente: bool, nombre: ?string, plan_actual: ?string}
     */
    private function resolverClienteExistente(int $alidoId, string $telefono): array
    {
        $numeroLimpio = preg_replace('/[^0-9]/', '', $telefono);

        $cliente = Cliente::where('aliado_id', $alidoId)
            ->where(function ($q) use ($numeroLimpio) {
                $q->where('celular', $numeroLimpio)
                  ->orWhere('celular', '+57' . $numeroLimpio)
                  ->orWhere('celular', 'like', '%' . substr($numeroLimpio, -10));
            })
            ->first();

        if (!$cliente) {
            return ['es_cliente' => false, 'nombre' => null, 'plan_actual' => null];
        }

        $contrato = Contrato::where('aliado_id', $alidoId)
            ->where('cedula', $cliente->cedula)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with('plan:id,nombre')
            ->first();

        return [
            'es_cliente'  => true,
            'nombre'      => trim(($cliente->primer_nombre ?? '') . ' ' . ($cliente->primer_apellido ?? '')),
            'plan_actual' => $contrato?->plan?->nombre,
        ];
    }

    /**
     * Bucle común: guarda el mensaje del usuario, llama al proveedor, ejecuta las
     * tools que pida (hasta MAX_ITERACIONES_TOOL veces) y persiste todo el intercambio.
     *
     * @param IaToolInterface[] $tools
     * @return array{respuesta: string, acciones: array}
     */
    private function ejecutarTurno(
        IaConversacion $conversacion,
        string $mensajeUsuario,
        string $systemPrompt,
        array $tools,
        array $credenciales,
        array $contextoExtra,
        string $canalConsumo
    ): array {
        $conversacion->update(['ultima_actividad' => now()]);

        IaMensaje::create([
            'conversacion_id' => $conversacion->id,
            'rol'             => 'user',
            'contenido'       => $mensajeUsuario,
        ]);

        $messages = $this->cargarHistorialNormalizado($conversacion->id);

        $provider = IaProviderFactory::make($credenciales['proveedor']);
        $toolSchemas = array_map(fn (IaToolInterface $t) => [
            'name'         => $t->nombre(),
            'description'  => $t->descripcion(),
            'input_schema' => $t->schema(),
        ], $tools);

        $tokensEntradaTotal = 0;
        $tokensSalidaTotal  = 0;
        $accionesSugeridas  = [];
        $textoFinal = '';

        $contextoTool = array_merge([
            'aliado_id'       => $conversacion->aliado_id,
            'conversacion_id' => $conversacion->id,
            'proveedor'       => $credenciales['proveedor'],
            'api_key'         => $credenciales['api_key'],
            'modelo'          => $credenciales['modelo'],
        ], $contextoExtra);

        for ($i = 0; $i < self::MAX_ITERACIONES_TOOL; $i++) {
            $resp = $provider->chat($credenciales['api_key'], $credenciales['modelo'], $systemPrompt, $messages, $toolSchemas);

            $tokensEntradaTotal += $resp['tokens_entrada'];
            $tokensSalidaTotal  += $resp['tokens_salida'];

            if (empty($resp['tool_calls'])) {
                $textoFinal = $resp['content'] ?? 'No tengo una respuesta para eso.';
                IaMensaje::create([
                    'conversacion_id' => $conversacion->id,
                    'rol'             => 'assistant',
                    'contenido'       => $textoFinal,
                ]);
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $resp['content'], 'tool_calls' => $resp['tool_calls']];

            IaMensaje::create([
                'conversacion_id' => $conversacion->id,
                'rol'             => 'assistant',
                'contenido'       => $resp['content'],
                'tool_name'       => implode(',', array_column($resp['tool_calls'], 'name')),
            ]);

            foreach ($resp['tool_calls'] as $toolCall) {
                $herramienta = $this->buscarTool($tools, $toolCall['name']);
                $resultado = $herramienta
                    ? $herramienta->ejecutar($toolCall['input'], $contextoTool)
                    : ['error' => 'Herramienta no encontrada.'];

                if ($toolCall['name'] === 'catalogo_modulos' && !empty($resultado['resultados'])) {
                    foreach ($resultado['resultados'] as $r) {
                        if (!empty($r['url'])) {
                            $accionesSugeridas[$r['url']] = ['nombre' => $r['nombre'], 'url' => $r['url']];
                        }
                    }
                }

                $contenidoJson = json_encode($resultado, JSON_UNESCAPED_UNICODE);

                $messages[] = [
                    'role'         => 'tool_result',
                    'tool_call_id' => $toolCall['id'],
                    'name'         => $toolCall['name'],
                    'content'      => $contenidoJson,
                ];

                IaMensaje::create([
                    'conversacion_id' => $conversacion->id,
                    'rol'             => 'tool',
                    'tool_name'       => $toolCall['name'],
                    'contenido'       => $contenidoJson,
                ]);
            }
        }

        if ($textoFinal === '') {
            $textoFinal = 'No pude completar la consulta, intenta reformular la pregunta.';
        }

        $this->registrarConsumo($conversacion->aliado_id, $conversacion->id, $canalConsumo, $credenciales, $tokensEntradaTotal, $tokensSalidaTotal);

        return [
            'respuesta' => $textoFinal,
            'acciones'  => array_values($accionesSugeridas),
        ];
    }

    /** @param IaToolInterface[] $tools */
    private function buscarTool(array $tools, string $nombre): ?IaToolInterface
    {
        foreach ($tools as $tool) {
            if ($tool->nombre() === $nombre) {
                return $tool;
            }
        }
        return null;
    }

    private function construirSystemPromptWeb(string $nombreAliado, string $nombreBot): string
    {
        $fecha = now()->translatedFormat('d \d\e F \d\e Y');

        return <<<PROMPT
        Eres {$nombreBot}, el asistente virtual de BryNex para el aliado "{$nombreAliado}". Hoy es {$fecha}.
        Hablas con empleados y asesores internos autenticados. Si te preguntan tu nombre, es {$nombreBot}.

        Ayudas con:
        - Cotizar planes de seguridad social (usa la herramienta cotizar_plan, nunca calcules tú mismo los valores).
        - Consultar los precios/porcentajes configurados (usa consultar_parametros).
        - Explicar dónde encontrar algo en el sistema (usa catalogo_modulos y ofrece el enlace).
        - Responder preguntas generales de seguridad social colombiana.

        Para preguntas de conocimiento (normativa, procedimientos, cifras vigentes) sigue este orden, sin saltarte pasos:
        1. buscar_conocimiento — es la fuente aprobada por el entrenador, siempre confiable. Úsala primero.
        2. Si no encuentra nada y tienes buscar_internet disponible, úsala. Aclara siempre al usuario que es
           información de internet aún sin verificar por el entrenador (no la presentes como un hecho confirmado).
        3. Si sigues sin una respuesta confiable (o no tienes buscar_internet disponible), usa preguntar_entrenador
           para registrar la pregunta, y dile al usuario honestamente que no tienes certeza todavía.
        Nunca combines pasos ni te saltes buscar_conocimiento: lo aprobado por el entrenador siempre prevalece
        sobre lo que digas de memoria o encuentres en internet.

        Reglas:
        - Responde siempre en español, de forma breve y clara.
        - Nunca inventes precios, porcentajes ni normativa: usa las herramientas disponibles.
        - No tienes acceso a datos personales de clientes ni puedes modificar registros; solo consultas y navegación.
        - Si el usuario pide algo fuera de tu alcance, indícale amablemente que no puedes hacerlo.
        PROMPT;
    }

    private function construirSystemPromptWhatsapp(
        string $nombreAliado,
        string $nombreBot,
        ?string $origenCampana = null,
        ?string $origenCampanaCategoria = null,
        array $clienteInfo = []
    ): string {
        $fecha = now()->translatedFormat('d \d\e F \d\e Y');
        $esCliente = $clienteInfo['es_cliente'] ?? false;

        if ($esCliente) {
            $nombreCliente = $clienteInfo['nombre'] ?: null;
            $planActual    = $clienteInfo['plan_actual'] ?? null;
            $contextoContacto = "\n## Quién te escribe: YA ES CLIENTE"
                . ($nombreCliente ? " ({$nombreCliente})" : '')
                . ($planActual ? ", con el plan \"{$planActual}\" activo" : ', pero sin contrato vigente activo')
                . ". NO le ofrezcas ni le pitchees un plan nuevo — es alguien que ya confió en nosotros, trátalo "
                . "como cliente, no como prospecto. Si pregunta por su cuenta, saldo o cómo pagar, usa "
                . "consultar_cliente. Solo cotiza con cotizar_plan si ÉL MISMO pide expresamente cotizar algo "
                . "nuevo o adicional (ej. afiliar a alguien más, cambiar de plan).\n";
        } else {
            $contextoContacto = "\n## Quién te escribe: es un PROSPECTO (no tiene contrato activo con nosotros). "
                . "Aquí sí aplica todo el enfoque de venta de abajo.\n";
        }

        $contextoCampana = '';
        if ($origenCampana) {
            if ($origenCampanaCategoria === 'MARKETING') {
                $contextoCampana = "Este contacto respondió a nuestra campaña/promoción \"{$origenCampana}\": "
                    . "es un prospecto interesado, aprovecha ese interés inicial para cotizar y cerrar.\n";
            } elseif ($origenCampanaCategoria === 'UTILITY') {
                $contextoCampana = "Este contacto respondió a un recordatorio/notificación (\"{$origenCampana}\") "
                    . "que le enviamos — probablemente es sobre su cuenta o pago, no una promoción. Prioriza "
                    . "ayudarlo con eso antes que ofrecerle algo nuevo.\n";
            } else {
                $contextoCampana = "Este contacto respondió a la plantilla \"{$origenCampana}\" que le enviamos "
                    . "hace poco: ten ese contexto presente, pero no lo menciones a menos que sea natural.\n";
            }
        }

        return <<<PROMPT
        Eres {$nombreBot}, asesora comercial experta en seguridad social de "{$nombreAliado}", atendiendo por
        WhatsApp a un cliente o prospecto externo. Hoy es {$fecha}. Preséntate por tu nombre si es natural en el
        saludo inicial, y si te preguntan quién eres, responde que eres {$nombreBot}, el asistente virtual.
        {$contextoContacto}{$contextoCampana}
        ## Cómo cotizar (usa cotizar_plan) — simplifica al máximo, el cliente casi nunca sabe estos términos:
        - Identifica tú misma qué plan quiere por lo que menciona (EPS/salud, ARL/ARP, AFP/fondo de pensión,
          CCF/caja de compensación) y pásalo como componentes a la tool. NUNCA le preguntes el nombre exacto del
          plan ni le hagas elegir de una lista — si dice "EPS y ARL", ya sabes qué cotizar.
        - Tipo de vinculación: asume "Dependiente" por defecto, sin preguntar. Solo pregunta o ajusta si el
          cliente menciona que es independiente, o si busca algo "más económico" — en ese caso ofrécele también
          la opción de Tiempo Parcial u otras modalidades más baratas.
        - Salario: usa el salario mínimo por defecto, sin preguntar. Solo pide el salario si el cliente menciona
          uno distinto o pregunta cómo cambia el valor con otro salario.
        - Nivel de riesgo ARL: esta es la ÚNICA pregunta que SIEMPRE debes hacer antes de cotizar (del 1 al 5,
          según su actividad). Si el cliente no sabe, cotiza con nivel 1 y acláraselo: "Como no sabes tu nivel de
          riesgo, te cotizo con el más bajo (nivel 1); si tu actividad es de mayor riesgo el valor de ARL puede
          variar".
        - Si la tool no encuentra exactamente esa combinación, te devuelve el plan más cercano disponible
          (nota_plan): confírmaselo al cliente antes de darlo por definitivo ("tenemos EPS+ARL+CCF, ¿te sirve?").
        - Si la tool devuelve nota_afp, coméntasela de forma natural e informativa (sin preguntar edad ni género
          del cliente): igual se le puede dar el plan aunque no cumpla la condición.

        ## Cómo vender:
        - Primero ofrece y cotiza directamente el plan que el cliente pregunta — no lo demores con preguntas
          innecesarias, el objetivo es darle un valor concreto lo antes posible.
        - Si el cliente duda, pone objeciones de precio, o no confirma después de ver el valor, ofrécele
          proactivamente una alternativa más económica (menos componentes, o Tiempo Parcial) antes de dejarlo ir.
        - Cierra tus respuestas con una pregunta que invite a avanzar (ej. "¿te gustaría que te afiliemos hoy
          mismo?", "¿quieres que te cuente los siguientes pasos?").
        - Si después de la cotización el cliente parece listo para avanzar (confirma, pregunta cómo pagar o
          afiliarse), usa hablar_con_asesor para que un humano cierre el proceso.

        Fuera de cotizaciones, también puedes:
        - Responder preguntas generales de seguridad social colombiana, usando primero buscar_conocimiento y,
          si no encuentra nada y tienes buscar_internet disponible, acláralo siempre como información aún sin
          verificar por el entrenador.
        - Si sigues sin una respuesta confiable, usa preguntar_entrenador y sé honesta: dile al cliente que no
          tienes certeza y que alguien del equipo lo contactará.
        - Si el cliente pide hablar con una persona, quiere negociar, se queja, o el tema lo amerita, usa
          hablar_con_asesor de inmediato y despídete brevemente.

        Reglas:
        - Responde en español, con tono cordial, cercano y persuasivo de venta consultiva (nunca uses jerga interna).
        - Con consultar_cliente solo puedes ver los datos del número que te está escribiendo en este momento —
          nunca de otra persona. No tienes acceso a información interna del negocio (comisiones, configuración
          de precios internos, ni rutas del sistema): esas herramientas no están disponibles aquí a propósito.
        - El saldo PENDIENTE que da consultar_cliente es real, pero preséntalo siempre como informativo y ofrece
          confirmarlo con un asesor (hablar_con_asesor) para mayor seguridad.
        - Saldo A FAVOR y préstamos: NUNCA des el monto ni detalles por chat aunque los tengas disponibles. Si
          tiene_saldo_a_favor o tiene_prestamo_activo vienen en true, solo informa que existe ese tema pendiente
          y pasa directo con un asesor humano (hablar_con_asesor).
        - Nunca inventes precios ni normativa.
        - Sé breve: los mensajes de WhatsApp deben ser cortos y fáciles de leer en un celular.
        PROMPT;
    }

    private function cargarHistorialNormalizado(int $conversacionId): array
    {
        $mensajes = IaMensaje::where('conversacion_id', $conversacionId)
            ->orderByDesc('id')
            ->limit(self::MENSAJES_HISTORIAL)
            ->get()
            ->reverse()
            ->values();

        $normalizado = [];
        foreach ($mensajes as $m) {
            if ($m->rol === 'user') {
                $normalizado[] = ['role' => 'user', 'content' => $m->contenido];
            } elseif ($m->rol === 'assistant') {
                // Los tool_calls no se persisten con su input completo; para el historial
                // basta reconstruir un mensaje de texto simple (evita reintentos de tools ya resueltas).
                if (!empty($m->contenido)) {
                    $normalizado[] = ['role' => 'assistant', 'content' => $m->contenido];
                }
            }
            // Los mensajes 'tool' del historial persistido no se reinyectan: cada turno
            // nuevo vuelve a llamar las tools si las necesita (evita IDs de tool_use inválidos).
        }

        return $normalizado;
    }

    private function registrarConsumo(int $alidoId, int $conversacionId, string $canal, array $credenciales, int $tokensIn, int $tokensOut): void
    {
        try {
            IaConsumo::create([
                'aliado_id'          => $alidoId,
                'canal'              => $canal,
                'conversacion_id'    => $conversacionId,
                'proveedor'          => $credenciales['proveedor'],
                'modelo'             => $credenciales['modelo'],
                'tokens_entrada'     => $tokensIn,
                'tokens_salida'      => $tokensOut,
                'costo_estimado_usd' => $this->estimarCosto($credenciales['proveedor'], $credenciales['modelo'], $tokensIn, $tokensOut),
            ]);
        } catch (\Exception $e) {
            Log::warning('IA: no se pudo registrar el consumo', ['error' => $e->getMessage()]);
        }
    }

    /** Estimación aproximada en USD (precios por millón de tokens, jul-2026). */
    private function estimarCosto(string $proveedor, ?string $modelo, int $tokensIn, int $tokensOut): float
    {
        $precios = [
            'claude' => ['in' => 1.0, 'out' => 5.0],   // Haiku aprox
            'openai' => ['in' => 0.15, 'out' => 0.60], // gpt-4o-mini aprox
        ];
        $p = $precios[$proveedor] ?? $precios['claude'];

        return round(($tokensIn / 1_000_000 * $p['in']) + ($tokensOut / 1_000_000 * $p['out']), 5);
    }
}
