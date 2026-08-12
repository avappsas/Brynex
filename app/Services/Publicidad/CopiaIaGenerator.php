<?php

namespace App\Services\Publicidad;

use App\Models\IaConfiguracionAliado;
use App\Services\Ia\IaProviderFactory;

/**
 * Genera variantes de texto publicitario usando el MISMO proveedor/clave que ya tiene
 * configurado el aliado para su asistente virtual (IaConfiguracionAliado::credencialesEfectivas).
 * No usa IaConversacion/IaMensaje ni tools — es un llamado de un solo turno, sin historial,
 * así que no contamina el historial de chat ni el consumo del asistente conversacional.
 */
class CopiaIaGenerator
{
    /**
     * @return array{ok: bool, variantes: string[], error: ?string}
     */
    public static function generarVariantes(int $aliadoId, string $nombreAliado, string $tipoPieza, string $contexto, int $cantidad = 3): array
    {
        $config = IaConfiguracionAliado::paraAliado($aliadoId);
        $credenciales = $config->credencialesEfectivas();

        if (empty($credenciales['api_key'])) {
            return ['ok' => false, 'variantes' => [], 'error' => 'No hay una clave de IA configurada para este aliado (ver Asistente Virtual).'];
        }

        $prompt = "Eres redactor publicitario de {$nombreAliado}, una agencia de afiliación a seguridad social en Colombia "
            . "(EPS, ARL, pensión, caja de compensación). Escribe {$cantidad} variantes CORTAS de texto publicitario "
            . "(máximo 220 caracteres cada una, español colombiano, tono cercano y profesional, sin emojis excesivos, "
            . "sin inventar precios ni cifras) para una pieza de tipo \"{$tipoPieza}\". Contexto: {$contexto}. "
            . 'Responde ÚNICAMENTE con un array JSON de strings, sin texto adicional ni bloque de código. '
            . 'Ejemplo de formato: ["texto 1", "texto 2", "texto 3"]';

        try {
            $provider = IaProviderFactory::make($credenciales['proveedor']);
            $resp = $provider->chat(
                $credenciales['api_key'],
                $credenciales['modelo'],
                'Respondes ÚNICAMENTE con JSON válido, sin texto adicional ni bloques de código markdown.',
                [['role' => 'user', 'content' => $prompt]],
                []
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'variantes' => [], 'error' => 'Error al generar el texto: ' . $e->getMessage()];
        }

        $texto = trim($resp['content'] ?? '');
        $texto = trim(preg_replace('/^```(?:json)?|```$/m', '', $texto));

        $variantes = json_decode($texto, true);

        if (!is_array($variantes) || empty($variantes)) {
            return $texto !== ''
                ? ['ok' => true, 'variantes' => [$texto], 'error' => null]
                : ['ok' => false, 'variantes' => [], 'error' => 'La IA no devolvió texto utilizable.'];
        }

        return ['ok' => true, 'variantes' => array_values(array_filter(array_map('strval', $variantes))), 'error' => null];
    }

    /**
     * Redacta el prompt cinematográfico para Veo (video con IA) — estilo testimonio a cámara,
     * CON diálogo hablado real (Veo 3.1 sincroniza labios cuando el prompt trae una frase
     * textual entre comillas) — así el video se entiende de inmediato de qué se trata, sin
     * depender solo de imágenes. NUNCA se pide texto en pantalla ni logos (eso lo agrega
     * VideoOverlayFfmpeg después por separado, igual que el logo real en las fotos).
     *
     * @return array{ok: bool, prompt: ?string, error: ?string}
     */
    public static function generarPromptVideo(int $aliadoId, string $nombreAliado, string $contexto): array
    {
        $config = IaConfiguracionAliado::paraAliado($aliadoId);
        $credenciales = $config->credencialesEfectivas();

        if (empty($credenciales['api_key'])) {
            return ['ok' => false, 'prompt' => null, 'error' => 'No hay una clave de IA configurada para este aliado (ver Asistente Virtual).'];
        }

        // La escena sale del tema: un anuncio de ARL tiene que verse en el andamio o en la
        // moto, no en una oficina. Ver CatalogoEscenasVideo.
        $escena = CatalogoEscenasVideo::paraContexto($contexto);

        $prompt = "Eres director creativo de anuncios en video para {$nombreAliado}, una agencia de afiliación a seguridad social "
            . 'en Colombia (EPS, ARL, pensión, caja de compensación). Escribe UN SOLO prompt para un modelo de IA de '
            . 'texto-a-video (Veo 3.1) que produzca un video LLAMATIVO tipo TESTIMONIO A CÁMARA — no una escena ambiental '
            . 'pasiva, sino una persona colombiana común mirando directo a la cámara y HABLANDO, como si grabara un '
            . 'video para redes sociales.' . "\n\n"

            . "PROTAGONISTA Y LUGAR (respétalo, es lo que hace que el espectador se reconozca): {$escena['oficio']}. "
            . 'Tiene que verse EN SU OFICIO, con la ropa, las herramientas y el entorno reales de ese trabajo — nada de '
            . 'oficinas genéricas ni gente de saco y corbata. Aspecto auténtico de colombiano de a pie, no modelo de '
            . 'banco de imágenes.' . "\n\n"

            . "LO QUE TIENE QUE REMOVER: {$escena['emocion']}. La tensión de fondo es esta: {$escena['tension']} "
            . 'La frase hablada debe tocar ESA necesidad concreta, no repetir un eslogan. Que suene a alguien contando '
            . 'algo que le pasó o que le preocupa, no a comercial de televisión. Puede empezar con una pregunta '
            . 'incómoda, una confesión ("yo pensaba que..."), o un alivio ("por fin..."). Evita el tono publicitario '
            . 'alegre y plano: la emoción real vende más que el entusiasmo fingido.' . "\n\n"

            . 'FORMATO DEL PROMPT: describe en inglés (los modelos de video entienden mejor la dirección de escena en '
            . 'inglés) quién es la persona, dónde está y qué está haciendo con las manos, la cámara (handheld cercano, '
            . 'estilo selfie-video o entrevista, vertical 9:16) y la luz. Incluye TEXTUAL y entre comillas, dentro del '
            . 'mismo prompt, la frase EXACTA que dice en VOZ ALTA en ESPAÑOL COLOMBIANO (no en inglés): corta (máx. 15 '
            . "palabras), natural, hablada como habla la gente, relacionada con: {$contexto}. "
            . 'Ejemplo de estructura (NO la copies, es solo el molde): A close-up handheld shot of a Colombian '
            . 'motorcycle delivery rider in his 20s, helmet in hand, catching his breath on a busy street, looking '
            . 'straight at the camera with a serious expression, he says in Spanish: "Me caí en la moto y ahí me di '
            . 'cuenta que no tenía ' . PronunciacionEsp::SIGLAS['ARL'] . '." ' . "\n\n"

            . PronunciacionEsp::reglaParaPrompt($nombreAliado) . "\n\n"

            . 'Máximo 80 palabras en total. NO menciones texto en pantalla, subtítulos, logos ni marcas — eso se agrega '
            . 'después por separado. Responde ÚNICAMENTE con el prompt en sí, sin explicación, sin bloque de código.';

        try {
            $provider = IaProviderFactory::make($credenciales['proveedor']);
            $resp = $provider->chat(
                $credenciales['api_key'],
                $credenciales['modelo'],
                'Respondes ÚNICAMENTE con el texto solicitado, sin comillas ni explicación adicional.',
                [['role' => 'user', 'content' => $prompt]],
                []
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'prompt' => null, 'error' => 'Error al generar el prompt: ' . $e->getMessage()];
        }

        $texto = trim($resp['content'] ?? '');
        $texto = trim($texto, "\"'“” \t\n\r");

        if ($texto === '') {
            return ['ok' => false, 'prompt' => null, 'error' => 'La IA no devolvió un prompt utilizable.'];
        }

        return ['ok' => true, 'prompt' => $texto, 'error' => null];
    }

    /**
     * Igual que generarPromptVideo() pero para piezas de VARIAS escenas (16s = 2 clips, 24s =
     * 3 clips de Veo unidos con un corte simple — ver VideoOverlayFfmpeg::concatenar). Sigue
     * siempre el mismo molde validado: escena 1 = gancho/autoridad (con diálogo hablado),
     * [escena intermedia = proceso/facilidad, sin diálogo, solo si son 3 escenas], última
     * escena = pago emocional (gente disfrutando el resultado, sin diálogo) — el contraste
     * "problema/autoridad → resultado" es lo que ya se validó que funciona bien.
     *
     * @return array{ok: bool, prompts: string[], error: ?string}
     */
    public static function generarPromptsMultiEscena(int $aliadoId, string $nombreAliado, string $contexto, int $numEscenas): array
    {
        $config = IaConfiguracionAliado::paraAliado($aliadoId);
        $credenciales = $config->credencialesEfectivas();

        if (empty($credenciales['api_key'])) {
            return ['ok' => false, 'prompts' => [], 'error' => 'No hay una clave de IA configurada para este aliado (ver Asistente Virtual).'];
        }

        $rolEscenas = $numEscenas === 3
            ? "1) GANCHO/AUTORIDAD: alguien explicando el servicio con autoridad y calidez, CON diálogo hablado. "
              . "2) PROCESO/FACILIDAD: una escena visual que transmita simplicidad/rapidez del trámite — normalmente sin "
              . 'diálogo, salvo que una frase corta ayude a entender el proceso. '
              . '3) PAGO EMOCIONAL: personas disfrutando el resultado/beneficio final — puede tener una frase corta y '
              . 'natural (no forzada) que refuerce el valor recibido, o ir sin diálogo si la imagen ya lo transmite sola.'
            : "1) GANCHO/AUTORIDAD: alguien explicando el servicio con autoridad y calidez, CON diálogo hablado. "
              . '2) PAGO EMOCIONAL: personas disfrutando el resultado/beneficio final — puede tener una frase corta y '
              . 'natural que refuerce el valor recibido, o ir sin diálogo si la imagen ya lo transmite sola.';

        $prompt = "Eres director creativo de anuncios en video para {$nombreAliado}, una agencia de afiliación a seguridad social "
            . "en Colombia (EPS, ARL, pensión, caja de compensación). Vas a escribir {$numEscenas} prompts en inglés para un "
            . "modelo de texto-a-video (Veo 3.1), uno por cada escena de un anuncio de {$numEscenas} cortes, sobre: {$contexto}. "
            . "Cada escena dura 8 segundos. Roles de cada escena en orden: {$rolEscenas} "
            . 'La escena de gancho/autoridad SIEMPRE lleva diálogo. Las demás escenas pueden o no llevar diálogo según '
            . 'convenga — cuando una escena SÍ tenga diálogo, inclúyelo TEXTUAL y entre comillas dentro de su prompt, en '
            . 'ESPAÑOL COLOMBIANO (máx. 15 palabras), natural para ese momento de la historia. No describas texto en '
            . 'pantalla, subtítulos, logos, ni marcas en ninguna escena (eso se agrega después por separado). Cada prompt '
            . 'máximo 60 palabras.' . "\n\n"
            // Faltaba aquí: los videos de más de 8 segundos se arman por escenas y salían sin
            // ninguna regla de pronunciación, así que las siglas se leían deletreadas.
            . PronunciacionEsp::reglaParaPrompt($nombreAliado) . "\n\n"
            . 'Responde ÚNICAMENTE con un array JSON de strings en el orden de las escenas, sin texto '
            . 'adicional ni bloque de código. Ejemplo de formato: ["prompt escena 1", "prompt escena 2"]';

        try {
            $provider = IaProviderFactory::make($credenciales['proveedor']);
            $resp = $provider->chat(
                $credenciales['api_key'],
                $credenciales['modelo'],
                'Respondes ÚNICAMENTE con JSON válido, sin texto adicional ni bloques de código markdown.',
                [['role' => 'user', 'content' => $prompt]],
                []
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'prompts' => [], 'error' => 'Error al generar los prompts: ' . $e->getMessage()];
        }

        $texto = trim($resp['content'] ?? '');
        $texto = trim(preg_replace('/^```(?:json)?|```$/m', '', $texto));

        $prompts = json_decode($texto, true);

        if (!is_array($prompts) || count($prompts) !== $numEscenas) {
            return ['ok' => false, 'prompts' => [], 'error' => 'La IA no devolvió las ' . $numEscenas . ' escenas esperadas.'];
        }

        return ['ok' => true, 'prompts' => array_values(array_map('strval', $prompts)), 'error' => null];
    }

    /**
     * Frases CORTAS (3-6 palabras) tipo llamado a la acción, para el texto animado que
     * VideoOverlayFfmpeg monta sobre el video — distinto de generarVariantes() (que redacta
     * el copy largo del post, no texto en pantalla).
     *
     * @param  bool  $conCierre  El video llevará pegado el cierre de marca, que ya remata con
     *                           "¡Escríbenos ya!" y el WhatsApp en pantalla. En ese caso la
     *                           última frase NO debe ser otro "escríbenos": pedir dos veces lo
     *                           mismo con cuatro segundos de diferencia suena a relleno y le
     *                           quita fuerza al cierre.
     * @param  string[]  $diceElCierre  Lo que el cierre ya dice en pantalla o en voz (ver
     *                                  CierreMarcaVideo::loQueDice). El clip y el cierre se
     *                                  escriben por separado y no se ven entre sí; sin esto
     *                                  salió una pieza que decía "Te mejoramos cualquier
     *                                  cotización" y el cierre lo repetía en pantalla.
     * @return array{ok: bool, frases: string[], error: ?string}
     */
    public static function generarFrasesVideo(
        int $aliadoId,
        string $nombreAliado,
        string $contexto,
        int $cantidad = 3,
        bool $conCierre = false,
        array $diceElCierre = []
    ): array {
        $config = IaConfiguracionAliado::paraAliado($aliadoId);
        $credenciales = $config->credencialesEfectivas();

        if (empty($credenciales['api_key'])) {
            return ['ok' => false, 'frases' => [], 'error' => 'No hay una clave de IA configurada para este aliado (ver Asistente Virtual).'];
        }

        // Mismo resorte emocional que la escena, para que el texto en pantalla y lo que se ve
        // cuenten la misma historia y no vayan cada uno por su lado.
        $escena = CatalogoEscenasVideo::paraContexto($contexto);

        $cierreFrase = $conCierre
            ? '(3) la última DEJA CLARO lo fácil o rápido que es resolverlo con la marca (ej. "Te afiliamos hoy '
              . 'mismo", "Sin filas ni papeleo"). PROHIBIDO que la última frase pida escribir, contactar, llamar o '
              . 'mandar mensaje: el video termina con un cierre de marca que ya lo pide con el WhatsApp en pantalla, '
              . 'y repetirlo aquí lo arruina. Ninguna de las tres frases puede contener "escríbe", "escríba", '
              . '"contáctanos", "llámanos" ni "mándanos".'
            : '(3) la última es el llamado a la acción para afiliarse o cotizar.';

        $evitar = $diceElCierre
            ? "\n\n" . 'NO REPITAS EL CIERRE. Cuatro segundos después de tus frases, el video remata con un cierre '
              . 'de marca que ya dice esto:' . "\n  · " . implode("\n  · ", $diceElCierre) . "\n"
              . 'Tus frases tienen que decir algo DISTINTO: ni la misma idea con otras palabras, ni las mismas '
              . 'palabras clave. Si el cierre ya habla de mejorar cotizaciones, tú no hablas de cotizaciones; si '
              . 'ya habla de rapidez o de no hacer papeleo, tú buscas otro ángulo.'
            : '';

        $prompt = "Eres redactor publicitario de {$nombreAliado}, una agencia de afiliación a seguridad social en Colombia. "
            . "Escribe {$cantidad} frases MUY CORTAS (máximo 6 palabras cada una, español colombiano) para animar como "
            . 'texto en pantalla sobre un video publicitario.' . "\n\n"
            . "Contexto: {$contexto}. La tensión que hay que tocar: {$escena['tension']}" . "\n\n"
            . 'ORDEN OBLIGATORIO: (1) la primera GOLPEA con el problema o el miedo concreto —que el espectador piense '
            . '"eso me puede pasar a mí"—, idealmente una pregunta o una frase incómoda; (2) la del medio muestra la '
            . 'salida o el alivio; ' . $cierreFrase . "\n\n"
            . 'Habla como la gente en la calle, no como un folleto: nada de "soluciones integrales", "bienestar '
            . 'garantizado" ni "protección integral". Frases que un trabajador diría de verdad. Sin emojis, sin '
            . 'inventar precios, sin urgencia falsa (nada de "cupos limitados" ni "solo hoy"). '
            . 'Trata al espectador de TÚ, nunca de USTED (nada de "escríbanos", "cotice", "afíliese"): el resto de '
            . 'la pieza tutea y mezclar los dos tratos se nota. '
            . $evitar . "\n\n"
            . 'Responde ÚNICAMENTE con un array JSON de strings, sin texto adicional ni bloque de código. '
            . 'Ejemplo de formato: ["frase 1", "frase 2", "frase 3"]';

        try {
            $provider = IaProviderFactory::make($credenciales['proveedor']);
            $resp = $provider->chat(
                $credenciales['api_key'],
                $credenciales['modelo'],
                'Respondes ÚNICAMENTE con JSON válido, sin texto adicional ni bloques de código markdown.',
                [['role' => 'user', 'content' => $prompt]],
                []
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'frases' => [], 'error' => 'Error al generar las frases: ' . $e->getMessage()];
        }

        $texto = trim($resp['content'] ?? '');
        $texto = trim(preg_replace('/^```(?:json)?|```$/m', '', $texto));

        $frases = json_decode($texto, true);

        if (!is_array($frases) || empty($frases)) {
            return ['ok' => false, 'frases' => [], 'error' => 'La IA no devolvió frases utilizables.'];
        }

        $frases = array_values(array_filter(array_map('strval', $frases)));

        // El prompt lo pide, pero el modelo se le escapa: la pieza #58 salió con "Escríbanos y
        // cotice sin tanto enredo" cuatro segundos antes de que el cierre dijera "¡Escríbenos
        // ya!". Aquí se corta en seco, sin depender de que el modelo haga caso.
        if ($conCierre) {
            $frases = array_values(array_filter(
                $frases,
                fn (string $f) => !preg_match(
                    '/\b(escr[ií]b|cont[áa]ctanos|cont[áa]ctenos|ll[áa]manos|ll[áa]menos|m[áa]ndanos|m[áa]ndenos|whatsapp)/iu',
                    $f
                )
            ));
        }

        // Misma historia con el contenido del cierre: el Reel #10 traía "Te mejoramos cualquier
        // cotización" el mismo día en que al cierre le tocaba mostrar "TE MEJORAMOS CUALQUIER
        // COTIZACIÓN". Dos palabras con carga en común ya es repetición para el que mira.
        if ($diceElCierre) {
            $delCierre = array_map([self::class, 'palabrasClave'], $diceElCierre);
            $frases = array_values(array_filter($frases, function (string $f) use ($delCierre) {
                $mias = self::palabrasClave($f);
                foreach ($delCierre as $suyas) {
                    if (count(array_intersect($mias, $suyas)) >= 2) {
                        return false;
                    }
                }

                return true;
            }));
        }

        if (empty($frases)) {
            return ['ok' => false, 'frases' => [], 'error' => 'La IA no devolvió frases utilizables.'];
        }

        return ['ok' => true, 'frases' => $frases, 'error' => null];
    }

    /**
     * Palabras con carga de una frase: sin tildes, sin puntuación y sin las que no distinguen
     * nada. Comparar frases enteras no sirve —"Te mejoramos cualquier cotización" y "TE
     * MEJORAMOS CUALQUIER COTIZACIÓN" no son iguales como cadena—, y comparar todas las
     * palabras haría que cualquier "te" o "tu" contara como repetición.
     *
     * @return string[]
     */
    private static function palabrasClave(string $texto): array
    {
        $vacias = ['el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'de', 'del', 'al', 'a',
            'en', 'y', 'o', 'que', 'te', 'tu', 'tus', 'su', 'sus', 'con', 'sin', 'por', 'para',
            'es', 'ya', 'no', 'mas', 'muy', 'lo', 'se', 'me', 'mi', 'todo', 'toda', 'todos',
            'todas', 'cualquier', 'hoy', 'dia', 'mismo', 'antes', 'nivel'];

        $norm = mb_strtolower($texto, 'UTF-8');
        $norm = strtr($norm, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        $norm = preg_replace('/[^a-z0-9\s]/', ' ', $norm);

        $palabras = array_filter(
            preg_split('/\s+/', trim($norm), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $p) => mb_strlen($p) > 2 && !in_array($p, $vacias, true)
        );

        return array_values(array_unique($palabras));
    }
}
