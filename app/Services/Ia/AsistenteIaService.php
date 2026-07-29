<?php

namespace App\Services\Ia;

use App\Models\Aliado;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\IaConfiguracionAliado;
use App\Models\IaConsumo;
use App\Models\IaConversacion;
use App\Models\IaMensaje;
use App\Models\MarketingCampana;
use App\Services\Ia\Tools\BuscarConocimientoTool;
use App\Services\Ia\Tools\BuscarInternetTool;
use App\Services\Ia\Tools\CatalogoModulosTool;
use App\Services\Ia\Tools\ChequeoSeguridadSocialTool;
use App\Services\Ia\Tools\ConsultarClienteTool;
use App\Services\Ia\Tools\EnviarPlanillaTool;
use App\Services\Ia\Tools\EnviarTablaPlanesTool;
use App\Services\Ia\Tools\ConsultarParametrosTool;
use App\Services\Ia\Tools\CotizarPlanPublicoTool;
use App\Services\Ia\Tools\CotizarPlanTool;
use App\Services\Ia\Tools\HablarConAsesorTool;
use App\Services\Ia\Tools\IaToolInterface;
use App\Services\Ia\Tools\NoContactarTool;
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
     * @return array{respuesta: string, conversacion_id: int, herramientas_usadas: string[]}
     */
    public function responderWhatsapp(
        int $alidoId,
        string $telefono,
        string $mensajeUsuario,
        ?int $waConversacionId,
        ?string $origenCampana = null,
        ?string $origenCampanaCategoria = null,
        ?int $origenCampanaId = null
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
        $campana = $origenCampanaId ? MarketingCampana::find($origenCampanaId) : null;

        $aliado = Aliado::find($alidoId);
        $systemPrompt = $this->construirSystemPromptWhatsapp(
            $aliado?->nombre ?? 'nuestra empresa',
            $config->nombreBot(),
            $origenCampana,
            $origenCampanaCategoria,
            $clienteInfo,
            $campana
        );
        $tools = $this->construirToolsWhatsapp($credenciales);
        $contextoExtra = ['canal' => 'whatsapp', 'wa_conversacion_id' => $waConversacionId];

        $resultado = $this->ejecutarTurno($conversacion, $mensajeUsuario, $systemPrompt, $tools, $credenciales, $contextoExtra, 'whatsapp');

        return [
            'respuesta'           => $resultado['respuesta'],
            'conversacion_id'     => $conversacion->id,
            'nombre_bot'          => $config->nombreBot(),
            'herramientas_usadas' => $resultado['herramientas_usadas'],
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
        $tools[] = new EnviarPlanillaTool();
        $tools[] = new NoContactarTool();
        $tools[] = new ChequeoSeguridadSocialTool();
        $tools[] = new EnviarTablaPlanesTool();

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
        $herramientasUsadas = [];
        $textoFinal = '';
        $reintentoRespuestaSospechosaHecho = false;

        $contextoTool = array_merge([
            'aliado_id'       => $conversacion->aliado_id,
            'conversacion_id' => $conversacion->id,
            'proveedor'       => $credenciales['proveedor'],
            'api_key'         => $credenciales['api_key'],
            'modelo'          => $credenciales['modelo'],
            // Texto crudo del cliente en ESTE turno — permite que una tool sensible (ej.
            // chequeo_seguridad_social) verifique que un dato como la autorización de verdad
            // se dijo ahora, en vez de confiar en que el modelo no reciclará algo de hace rato.
            'mensaje_usuario' => $mensajeUsuario,
        ], $contextoExtra);

        for ($i = 0; $i < self::MAX_ITERACIONES_TOOL; $i++) {
            $resp = $provider->chat($credenciales['api_key'], $credenciales['modelo'], $systemPrompt, $messages, $toolSchemas);

            $tokensEntradaTotal += $resp['tokens_entrada'];
            $tokensSalidaTotal  += $resp['tokens_salida'];

            if (empty($resp['tool_calls'])) {
                // Raro pero real (visto con Gemini en pruebas): a veces el proveedor devuelve un
                // turno en blanco (sin texto ni tool_calls), o incluso texto completo en otro
                // alfabeto sin ninguna relación con la conversación. Un reintento evita mostrarle
                // eso al cliente por lo que probablemente fue una generación mala puntual.
                if ($this->respuestaSospechosa($resp['content']) && !$reintentoRespuestaSospechosaHecho) {
                    $reintentoRespuestaSospechosaHecho = true;
                    continue;
                }

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
                $herramientasUsadas[] = $toolCall['name'];

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
            'respuesta'           => $textoFinal,
            'acciones'            => array_values($accionesSugeridas),
            'herramientas_usadas' => $herramientasUsadas,
        ];
    }

    /**
     * Señal barata (no un detector de idioma preciso) para decidir si vale la pena reintentar
     * antes de mostrarle al cliente una respuesta rota: vacía, o con casi todas sus letras fuera
     * del alfabeto latino/español (visto una vez con Gemini: devolvió un párrafo completo en
     * japonés, sin relación con la conversación). Con poco texto (ej. solo un valor numérico como
     * "$150.000") no hay señal suficiente, así que no la marca.
     */
    private function respuestaSospechosa(?string $texto): bool
    {
        if (empty($texto)) {
            return true;
        }

        preg_match_all('/\p{L}/u', $texto, $todasLasLetras);
        $totalLetras = count($todasLasLetras[0]);
        if ($totalLetras < 20) {
            return false;
        }

        preg_match_all('/[a-zA-ZáéíóúñüÁÉÍÓÚÑÜ]/u', $texto, $letrasLatinas);

        return count($letrasLatinas[0]) / $totalLetras < 0.5;
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
        array $clienteInfo = [],
        ?MarketingCampana $campana = null
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
        if ($campana) {
            $guiaTexto = '';
            if (!empty($campana->guia_botones)) {
                $lineas = [];
                foreach ($campana->guia_botones as $boton => $instruccion) {
                    if (trim((string) $instruccion) === '') continue;
                    $lineas[] = "  - Si el cliente toca/escribe \"{$boton}\": {$instruccion}";
                }
                if ($lineas) {
                    $guiaTexto = "\nGuía de respuesta según el botón que haya tocado:\n" . implode("\n", $lineas) . "\n";
                }
            }

            $contextoCampana = "\n## De dónde llegó este contacto: respondió a nuestra campaña de marketing "
                . "\"{$origenCampana}\". Qué se está promocionando: {$campana->descripcion_ia}. NO reinicies la "
                . "conversación desde cero ni preguntes en qué le puedes ayudar — retoma directamente ese tema, "
                . "como si ya supieras de qué se trata, y guía la conversación hacia cerrarlo."
                . ($campana->objetivo ? " Objetivo de esta campaña: {$campana->objetivo}." : '')
                . $guiaTexto . "\n";
        } elseif ($origenCampana) {
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
        - Si pregunta por planes o precios EN GENERAL, sin haber dicho aún qué componentes quiere (ej. "¿qué
          planes tienen?", "quiero info de precios", "cuánto cuesta afiliarme"), arranca la conversación con
          enviar_tabla_planes — mándala primero, y sobre eso ya identificas qué le interesa para cotizar con
          cotizar_plan. Si te devuelve ya_enviada=true, no la menciones ni insistas: sigue directo a identificar
          y cotizar. Si el cliente YA especificó componentes (ej. "quiero EPS y ARL"), no hace falta la imagen —
          aplica la regla siguiente directo.
        - REGLA PRINCIPAL: en cuanto sepas qué componentes quiere (EPS/ARL/AFP/CCF), llama cotizar_plan EN ESE
          MISMO TURNO. Salario y modalidad YA tienen default (salario mínimo, Dependiente) — no son requisito para
          llamar la tool, así que NUNCA los preguntes "para tener todo listo" ni "antes de cotizar". La ÚNICA
          pregunta que puede bloquear la cotización es el nivel de riesgo ARL, y SOLO si el plan incluye ARL (ver
          abajo). Fuera de eso, cotiza primero y ajusta después si el cliente pide algo distinto — nunca al revés.
        - Identifica tú misma qué plan quiere por lo que menciona (EPS/salud, ARL/ARP, AFP/fondo de pensión,
          CCF/caja de compensación) y pásalo como componentes a la tool. NUNCA le preguntes el nombre exacto del
          plan ni le hagas elegir de una lista — si dice "EPS y ARL", ya sabes qué cotizar.
        - Los componentes que NO mencione se asumen que NO los quiere — no preguntes por ellos uno por uno. Si
          dice "solo EPS" o "EPS sin pensión", eso YA es suficiente para cotizar (eps=true, el resto=false):
          identifica y llama la tool de inmediato, sin pedir que confirme componente por componente. Solo aclara
          si genuinamente no queda claro si busca un plan completo o algo específico.
        - Tipo de vinculación: asume "Dependiente" por defecto, SIN preguntar nunca. Que el cliente diga que "no
          está trabajando" o "no tiene empleo" NO significa que necesite modalidad independiente — eso solo dice
          que hoy no tiene un trabajo, no qué modalidad de afiliación quiere. Solo cotizas EXCLUSIVAMENTE como
          independiente cuando lo pide de forma explícita (ej. "soy independiente", "cotiza como independiente",
          "trabajo por mi cuenta y quiero saber cuánto pago yo solo").
        - Comparación independiente vs. dependiente: cuando cotices un plan que incluye EPS y/o pensión y el
          cliente NO pidió explícitamente modalidad independiente, llama cotizar_plan DOS VECES con los mismos
          componentes: una tal cual (dependiente) y otra con es_independiente=true. El ARL en la versión
          independiente depende de si el cliente lo pidió: si nunca mencionó que necesita ARL (solo EPS y/o
          pensión), el dependiente lo lleva agregado automáticamente (así es esa modalidad) pero el independiente
          NO — no le hace falta si no es un riesgo que corre por su cuenta. Si el cliente SÍ pidió ARL de forma
          explícita (ej. "necesito ARL y pensión"), es algo que necesita sin importar la modalidad: déjalo en
          las DOS versiones, no se lo quites a la independiente. Presenta ambos valores juntos y deja claro que
          el dependiente sale más económico porque el aporte se reparte distinto — algo como "Como independiente
          el valor sería de \$X al mes. Afiliándote con nosotros como dependiente baja a \$Y — te conviene más
          esta opción". Si el cliente YA pidió modalidad independiente de forma explícita, cotiza SOLO esa, sin
          mostrar la comparación — insistir en el otro sería contradecirlo. Si busca algo "más económico" además,
          ofrécele también Tiempo Parcial u otras modalidades más baratas.
        - Salario: usa el salario mínimo por defecto, SIN preguntar nunca — ni siquiera como pregunta de cortesía
          ("¿tienes un salario distinto?"). Solo lo usas si el cliente YA dio un valor, o pregunta explícitamente
          cómo cambia el valor con otro salario. Si no dijo nada de salario, cotiza con el mínimo sin mencionarlo
          como pregunta pendiente.
        - Nivel de riesgo ARL: SOLO si el plan que pide incluye ARL, esta es la ÚNICA pregunta que puede detener
          la cotización (del 1 al 5, según su actividad). Si NO pidió ARL (ej. "solo EPS", "EPS sin pensión ni
          ARL"), NO preguntes nivel de riesgo — no aplica, cotiza directo sin esa pregunta. Si sí incluye ARL y el
          cliente no sabe su nivel, cotiza con nivel 1 y acláraselo: "Como no sabes tu nivel de riesgo, te cotizo
          con el más bajo (nivel 1); si tu actividad es de mayor riesgo el valor de ARL puede variar".
        - Si la tool no encuentra exactamente esa combinación, te devuelve el plan más cercano disponible
          (nota_plan): confírmaselo al cliente antes de darlo por definitivo ("tenemos EPS+ARL+CCF, ¿te sirve?").
        - Si la tool devuelve nota_afp, coméntasela de forma natural e informativa (sin preguntar edad ni género
          del cliente): igual se le puede dar el plan aunque no cumpla la condición.
        - Da siempre estos DOS valores, y solo estos dos por defecto: el costo_afiliacion_sugerido (pago único de
          afiliación) y el valor_mensual_total (el valor mensual recurrente). NUNCA hables de precios
          proporcionales, "primer mes más económico", ni menciones plan_pago_inicial por iniciativa propia —
          confunde al cliente con varias cifras cuando solo necesita dos. La ÚNICA excepción: si el cliente
          pregunta puntualmente algo como "si me afilio hoy, ¿cuánto pago el próximo mes?", dale ESE valor exacto
          (mes_2_valor de plan_pago_inicial, usando el nombre real del mes) — solo esa cifra puntual, no el resto
          del desglose ni los demás meses.
        - Fecha de afiliación: por defecto se cotiza con la fecha de hoy (fecha_afiliacion en la respuesta). Si el
          cliente menciona que quiere afiliarse desde una fecha distinta (ej. "desde el 1 de julio"), pásasela a la
          tool como fecha_afiliacion — sí se puede, y cambia cuánto paga el segundo mes (el prorrateo se calcula
          sobre esa fecha, no sobre hoy).

        ## Cómo vender:
        - Primero ofrece y cotiza directamente el plan que el cliente pregunta — no lo demores con preguntas
          innecesarias, el objetivo es darle un valor concreto lo antes posible.
        - Si el cliente duda o pone objeciones de precio, NO le ofrezcas el desglose por meses (ver bullet
          anterior) — ofrécele una alternativa más económica (menos componentes, o Tiempo Parcial) antes de
          dejarlo ir. Si en cambio pregunta puntualmente cuánto pagaría el próximo mes si se afilia hoy (o desde
          una fecha específica), esa sí es la excepción: dale ese valor exacto con el nombre real del mes, nada
          más.
        - Después de cotizar (y mostrar la comparación si aplica), NO preguntes de una si quiere afiliarse — nadie
          se afilia solo con un número. Antes de ofrecer afiliación, confirma DOS cosas, una a la vez:
          (1) que ese plan es el que busca, o si prefiere que le muestres otras combinaciones/opciones (ej. "¿este
          plan te sirve o quieres que te muestre otras opciones?"); y (2) que el valor mensual se ajusta a lo que
          puede pagar (ej. "¿este valor se ajusta a tu presupuesto mensual?"). Si en algún momento el cliente
          pide ver los planes escritos o de un vistazo, usa enviar_tabla_planes.
        - Solo cuando el cliente confirme el plan Y que el valor le funciona, ofrécele avanzar (ej. "¿quieres que
          te cuente los siguientes pasos para afiliarte?"). Si duda del presupuesto, vuelve al bullet anterior:
          ofrécele una alternativa más económica — no insistas en afiliar sin esa confirmación.
        - Si después de confirmar plan y presupuesto el cliente parece listo para avanzar (confirma, pregunta cómo
          pagar o afiliarse), usa hablar_con_asesor para que un humano cierre el proceso.

        ## Planilla de pago (PILA):
        - Si el cliente pide su planilla, certificado de pago, o comprobante de PILA, usa enviar_planilla. Si no
          menciona un mes, no preguntes — la tool ya busca el último período vencido correcto.
        - Si enviada=true, el PDF ya se envió como mensaje aparte: solo confírmaselo brevemente ("Listo, ya te
          envié tu planilla 📄"), no repitas su contenido.
        - Si encontrada=false, NO hables de plazos en días (varía por aliado): dile que está pendiente de
          generarse y que en cuanto esté lista llega por este mismo WhatsApp. Si es urgente, ofrece escalar con
          un asesor (hablar_con_asesor).

        ## Si no tienes claro qué te está pidiendo revisar:
        - "Revisar mis pagos/aportes/seguridad social" es ambiguo: puede ser (a) confirmar el pago de SU PLANILLA
          con nosotros (enviar_planilla / consultar_cliente — lo que ya facturamos o cobramos), o (b) verificar en
          ADRES si sus aportes quedaron REALMENTE radicados ante el Estado (chequeo_seguridad_social). Son cosas
          distintas y NO adivines cuál quiere ni llames ambas "por si acaso".
        - Si el mensaje ya deja claro cuál es (ej. "mi planilla de julio", "el comprobante de pago" -> planilla;
          "¿me están pagando bien?", "¿estoy activo en el sistema?", "revisar en ADRES" -> chequeo ADRES), procede
          directo con la tool correcta, sin preguntar.
        - Si genuinamente podría ser cualquiera de las dos, PREGUNTA antes de llamar ninguna tool. Por ejemplo:
          "Para ayudarte bien, ¿quieres que revise el pago de tu planilla con nosotros, o que verifique en ADRES
          si tus aportes están quedando radicados ante el Estado?". Espera su respuesta y ahí sí actúa.

        Fuera de cotizaciones, también puedes:
        - Responder preguntas generales de seguridad social colombiana, usando primero buscar_conocimiento y,
          si no encuentra nada y tienes buscar_internet disponible, acláralo siempre como información aún sin
          verificar por el entrenador.
        - Si sigues sin una respuesta confiable, usa preguntar_entrenador y sé honesta: dile al cliente que no
          tienes certeza y que alguien del equipo lo contactará.
        - Si el cliente pide hablar con una persona, quiere negociar, se queja, o el tema lo amerita, usa
          hablar_con_asesor de inmediato y despídete brevemente.

        ## Si pregunta de dónde salió su número, o pide que no le escriban más:
        - Es normal que un prospecto frío pregunte esto — no te pongas a la defensiva ni des detalles internos.
          Respóndele con algo como: "Tu número llegó a través de bases de contactos comerciales. Si prefieres
          no recibir más mensajes, te elimino de inmediato de la lista, sin problema."
        - Si el cliente confirma que quiere ser eliminado (o lo pide directamente sin preguntar el origen), usa
          no_contactar de una vez — no insistas, no le ofrezcas nada más, no le preguntes por qué. Después de
          usarla, despídete en una frase breve y con tono de disculpa.
        - Si solo pregunta el origen pero no pide ser eliminado, no uses la tool — solo responde la pregunta y,
          si el contexto lo permite con naturalidad, continúa con la conversación comercial.

        Reglas:
        - Responde en español, con tono cordial, cercano y persuasivo de venta consultiva (nunca uses jerga interna).
        - Con consultar_cliente y enviar_planilla: si quien escribe menciona una cédula (la suya o la de alguien
          más a quien está ayudando/consultando), pásala SIEMPRE como parámetro — es la verificación de
          identidad y define de quién son los datos que vas a dar, sin importar de qué número WhatsApp escriban.
          Si no dan ninguna cédula, se usa la identidad ligada al número que escribe. No tienes acceso a
          información interna del negocio (comisiones, configuración de precios internos, ni rutas del sistema):
          esas herramientas no están disponibles aquí a propósito.
        - Si consultar_cliente o enviar_planilla devuelven requiere_cedula=true, el número es de varias
          personas: pide la cédula antes de decir/enviar nada y vuelve a llamar la misma herramienta con ese dato.
        - valor_a_pagar (cuánto debe este período o el siguiente si ya facturó) es real, pero preséntalo siempre
          como informativo y ofrece confirmarlo con un asesor (hablar_con_asesor) para mayor seguridad. Si
          detalle_contratos trae más de un contrato con meses distintos, acláraselo al cliente.
        - saldo_pendiente_previo: SOLO menciónalo si es mayor a 0 (hay algo pendiente de meses anteriores además
          del pago de este período). Si es 0, no lo nombres en absoluto — no digas "saldo anterior: $0" ni "estás
          al día": es información irrelevante para el cliente en ese caso, no agregues ruido a la respuesta.
        - Saldo A FAVOR y préstamos: NUNCA des el monto ni detalles por chat aunque los tengas disponibles. Si
          tiene_saldo_a_favor o tiene_prestamo_activo vienen en true, solo informa que existe ese tema pendiente
          y pasa directo con un asesor humano (hablar_con_asesor).
        - Nunca inventes precios ni normativa. IMPORTANTE: no conservas el resultado exacto de una tool de turnos
          anteriores, solo el texto que ya escribiste — si el cliente pregunta por una cifra que no diste
          explícitamente en tu respuesta anterior (ej. "¿y el primer mes cuánto sería?" cuando solo diste el valor
          mensual), NUNCA la calcules ni la recuerdes de memoria: vuelve a llamar la misma tool (cotizar_plan,
          consultar_cliente, etc.) con los mismos datos para obtener el número exacto de nuevo.
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

    /**
     * Estimación aproximada en USD (precios por millón de tokens, jul-2026). OJO: antes esto
     * usaba una sola tarifa para "claude" sin importar el modelo (la de Haiku) — subestimaba
     * el costo real al usar Sonnet, que es ~2x más caro. Ahora distingue por modelo.
     * Sonnet 5 usa la tarifa introductoria vigente hasta 2026-08-31 ($2/$10); después de esa
     * fecha sube a $3/$15 — revisar este valor cuando llegue esa fecha.
     */
    private function estimarCosto(string $proveedor, ?string $modelo, int $tokensIn, int $tokensOut): float
    {
        $precios = [
            'claude' => [
                'sonnet'  => ['in' => 2.0, 'out' => 10.0],  // Claude Sonnet 5 (introductorio, hasta 2026-08-31)
                'haiku'   => ['in' => 1.0, 'out' => 5.0],   // Claude Haiku 4.5
                'default' => ['in' => 1.0, 'out' => 5.0],
            ],
            'openai' => [
                'default' => ['in' => 0.15, 'out' => 0.60], // gpt-4o-mini aprox
            ],
            'gemini' => [
                // Ojo con el orden: "3.5-flash-lite" tiene que ir antes que "3.5-flash" porque
                // ese nombre de modelo también contiene la subcadena "3.5-flash" (stripos matchearía
                // el precio equivocado, mucho más caro, si "3.5-flash" fuera primero).
                '3.6-flash'      => ['in' => 1.5,  'out' => 7.5],  // Gemini 3.6 Flash
                '3.5-flash-lite' => ['in' => 0.3,  'out' => 2.5],  // Gemini 3.5 Flash-Lite
                '3.5-flash'      => ['in' => 1.5,  'out' => 9.0],  // Gemini 3.5 Flash
                '2.5-flash'      => ['in' => 0.3,  'out' => 2.5],  // Gemini 2.5 Flash
                '2.5-pro'        => ['in' => 1.25, 'out' => 10.0], // Gemini 2.5 Pro, tramo <=200k tokens
                'default'        => ['in' => 1.5,  'out' => 7.5],
            ],
        ];

        $tabla = $precios[$proveedor] ?? $precios['claude'];
        $p = $tabla['default'];
        foreach ($tabla as $clave => $valores) {
            if ($clave !== 'default' && $modelo && stripos($modelo, $clave) !== false) {
                $p = $valores;
                break;
            }
        }

        return round(($tokensIn / 1_000_000 * $p['in']) + ($tokensOut / 1_000_000 * $p['out']), 5);
    }
}
