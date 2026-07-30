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

        $prompt = "Eres director creativo de anuncios en video para {$nombreAliado}, una agencia de afiliación a seguridad social "
            . 'en Colombia (EPS, ARL, pensión, caja de compensación). Escribe UN SOLO prompt para un modelo de IA de '
            . 'texto-a-video (Veo 3.1) que produzca un video LLAMATIVO tipo TESTIMONIO A CÁMARA — no una escena ambiental '
            . 'pasiva, sino una persona colombiana común mirando directo a la cámara y HABLANDO, como si grabara un '
            . 'video para redes sociales. El prompt debe describir en inglés (los modelos de video entienden mejor la '
            . 'dirección de escena en inglés): quién es la persona, dónde está, la cámara (handheld cercano, estilo '
            . 'selfie-video o entrevista) y su expresión/energía (cercana, genuina, entusiasta) — Y debe incluir, '
            . 'TEXTUAL y entre comillas dentro del mismo prompt, la frase EXACTA que la persona dice en VOZ ALTA en '
            . "ESPAÑOL COLOMBIANO (no en inglés): una frase corta (máx. 15 palabras), clara y natural, que mencione "
            . "explícitamente cotizar o afiliarse a seguridad social, relacionada con: {$contexto} — para que cualquiera "
            . 'que vea el video entienda de qué se trata sin necesitar texto en pantalla. Ejemplo de estructura (no la '
            . 'copies literal, adapta el contenido): A close-up handheld shot of a Colombian woman in her 30s in her '
            . 'kitchen, speaking directly and warmly to the camera, she says in Spanish: "¿Ya cotizaste tu seguridad '
            . 'social este mes? Yo lo hice en cinco minutos." Máximo 70 palabras en total. NO menciones texto en '
            . 'pantalla, subtítulos, logos, ni marcas — eso se agrega después por separado. Responde ÚNICAMENTE con '
            . 'el prompt en sí, sin explicación, sin bloque de código.';

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
     * Frases CORTAS (3-6 palabras) tipo llamado a la acción, para el texto animado que
     * VideoOverlayFfmpeg monta sobre el video — distinto de generarVariantes() (que redacta
     * el copy largo del post, no texto en pantalla).
     *
     * @return array{ok: bool, frases: string[], error: ?string}
     */
    public static function generarFrasesVideo(int $aliadoId, string $nombreAliado, string $contexto, int $cantidad = 3): array
    {
        $config = IaConfiguracionAliado::paraAliado($aliadoId);
        $credenciales = $config->credencialesEfectivas();

        if (empty($credenciales['api_key'])) {
            return ['ok' => false, 'frases' => [], 'error' => 'No hay una clave de IA configurada para este aliado (ver Asistente Virtual).'];
        }

        $prompt = "Eres redactor publicitario de {$nombreAliado}, una agencia de afiliación a seguridad social en Colombia. "
            . "Escribe {$cantidad} frases MUY CORTAS (máximo 6 palabras cada una, español colombiano) para animar como "
            . 'texto en pantalla sobre un video publicitario, en este orden: la primera plantea el problema/necesidad, '
            . "la última es un llamado a la acción claro para cotizar. Contexto: {$contexto}. Sin emojis, sin inventar "
            . 'precios. Responde ÚNICAMENTE con un array JSON de strings, sin texto adicional ni bloque de código. '
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

        return ['ok' => true, 'frases' => array_values(array_filter(array_map('strval', $frases))), 'error' => null];
    }
}
