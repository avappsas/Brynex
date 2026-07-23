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
}
