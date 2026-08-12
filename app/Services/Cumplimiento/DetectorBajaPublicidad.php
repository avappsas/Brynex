<?php

namespace App\Services\Cumplimiento;

/**
 * Detecta si un mensaje entrante es una petición de NO recibir más publicidad, para honrar
 * la baja sin depender de que el cliente toque el botón de la plantilla o de que el
 * Asistente IA interprete bien la intención.
 *
 * El diseño es deliberadamente conservador, porque en este negocio varias palabras que en
 * otro contexto significarían "baja" aquí significan otra cosa completamente distinta:
 *
 *   "cancelar"  → en Colombia es PAGAR. "el valor que debo cancelar", "ya cancelé",
 *                 "voy a cancelar por Nequi". Bloquear por esta palabra sacaría de las
 *                 comunicaciones justo a quien está avisando que va a pagar.
 *   "retiro"    → es un trámite del servicio: "me retiran de la seguridad social",
 *                 "voy a hacer el retiro del afiliado Marvin".
 *   "dar de baja" → retirar a un empleado de la afiliación, no una baja publicitaria.
 *   "baja"      → además aparece dentro de otras palabras: "no estoy traBAJAndo".
 *
 * Por eso: nunca se busca por subcadena, solo frases autorreferentes sobre RECIBIR
 * MENSAJES, y las palabras sueltas ("BAJA", "STOP") únicamente cuando son el mensaje
 * completo. Ante la duda, no se marca: un falso negativo lo resuelve el Asistente IA con
 * `no_contactar`, mientras que un falso positivo silencia a un cliente sin que él lo sepa.
 */
class DetectorBajaPublicidad
{
    /**
     * Frases que sí son una baja. Se evalúan sobre el texto normalizado (sin tildes,
     * minúsculas, espacios colapsados) y siempre con límites de palabra.
     */
    private const PATRONES = [
        // "no me escriban / escriba / escribas más", "no me vuelvan a escribir"
        '/\bno me (vuelvan? a )?(escrib|contact|molest)\w*/u',
        // "no me manden / envíen (más) mensajes|publicidad|nada"
        '/\bno me (mand|envi)\w*\b.{0,20}\b(mensaj|public|informacion|promo|nada|mas)\w*/u',
        // "no me llamen / llame más"
        '/\bno me llam\w+/u',
        // "no quiero recibir ...", "no quiero más mensajes/publicidad"
        '/\bno (quiero|deseo)\b.{0,25}\b(recibir|mensaj|public|informacion|promo)\w*/u',
        // "dejen de escribirme / enviarme / mandarme / molestar"
        '/\bdej(en|a|e)\b.{0,10}\bde\b.{0,15}\b(escrib|envi|mand|molest)\w*/u',
        // "borren / eliminen MIS DATOS" (mis datos es lo que lo hace inequívoco)
        '/\b(borr|elimin|quit)\w*\b.{0,15}\bmis datos\b/u',
        // "quítenme / sáquenme de la lista", "no me incluyan en la lista"
        '/\b(quit|saqu|sacar|saca)\w*\b.{0,20}\bde la lista\b/u',
        // "no más mensajes / publicidad / promociones"
        '/\bno mas\b.{0,10}\b(mensaj|public|informacion|promo)\w*/u',
        // Convenciones internacionales explícitas
        '/\b(desuscribir\w*|unsubscribe)\b/u',
    ];

    /**
     * Palabras que solo valen como baja si son TODO el mensaje. "baja" suelta dentro de una
     * frase es ambigua (o es parte de otra palabra); sola, es la convención que la propia
     * plantilla invita a responder.
     */
    private const PALABRAS_SOLAS = ['baja', 'stop', 'bajar', 'salir', 'eliminar', 'remover'];

    /**
     * Frases con las que alguien ya bloqueado pide volver a recibir información. Solo se
     * evalúan cuando la persona ESTÁ bloqueada, así que no hay riesgo de que se confundan
     * con una conversación normal: "sí" suelto solo cuenta en ese contexto, como respuesta
     * al acuse de baja que le acabamos de mandar.
     */
    private const PATRONES_REACTIVACION = [
        '/\bfue un error\b/u',
        '/\bme equivoqu\w+/u',
        '/\bsi quiero (recibir|seguir|que me)\b/u',
        '/\b(reactiv|reactiven|reactivar)\w*/u',
        '/\b(vuelvan|vuelva|pueden) a (escribir|enviar|mandar)\w*/u',
        '/\bno era mi intencion\b/u',
        '/\bsi quiero informacion\b/u',
    ];

    private const PALABRAS_REACTIVACION = ['si quiero', 'si', 'reactivar', 'volver'];

    /** ¿Un cliente bloqueado está pidiendo que le volvamos a escribir? */
    public static function esReactivacion(?string $texto): bool
    {
        $t = self::normalizar($texto);
        if ($t === '') {
            return false;
        }

        if (in_array($t, self::PALABRAS_REACTIVACION, true)) {
            return true;
        }

        foreach (self::PATRONES_REACTIVACION as $patron) {
            if (preg_match($patron, $t)) {
                return true;
            }
        }

        return false;
    }

    public static function esPeticionDeBaja(?string $texto): bool
    {
        $t = self::normalizar($texto);
        if ($t === '') {
            return false;
        }

        if (in_array($t, self::PALABRAS_SOLAS, true)) {
            return true;
        }

        foreach (self::PATRONES as $patron) {
            if (preg_match($patron, $t)) {
                return true;
            }
        }

        return false;
    }

    /** Fragmento que disparó la detección, para dejarlo como prueba en la bitácora. */
    public static function motivo(?string $texto): ?string
    {
        $t = self::normalizar($texto);
        if ($t === '') {
            return null;
        }

        if (in_array($t, self::PALABRAS_SOLAS, true)) {
            return "El mensaje completo fue \"{$t}\".";
        }

        foreach (self::PATRONES as $patron) {
            if (preg_match($patron, $t, $m)) {
                return 'Escribió: "' . trim($m[0]) . '".';
            }
        }

        return null;
    }

    /** Minúsculas, sin tildes y con espacios colapsados, para que las variantes no importen. */
    private static function normalizar(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto), 'UTF-8');
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u', 'ñ' => 'n',
        ]);
        // Signos fuera, salvo los que separan palabras.
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);

        return trim(preg_replace('/\s+/u', ' ', $t));
    }
}
