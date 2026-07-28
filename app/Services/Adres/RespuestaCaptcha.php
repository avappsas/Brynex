<?php

namespace App\Services\Adres;

/**
 * Decide si lo que escribió el cliente es la respuesta al código de ADRES o es
 * conversación normal.
 *
 * Importa acertar: si se toma por código algo que no lo era, se quema uno de los
 * tres intentos y el cliente no entiende por qué le llega otra imagen. Al revés
 * el costo es bajo — el mensaje sigue a la IA, que puede ayudarle.
 *
 * El primer intento fue "alfanumérico de 4 a 12 tras quitar espacios", y colaba
 * "ya voy" (yavoy) y "no la veo bien" (nolaveobien). De ahí las dos reglas extra:
 * un código no se escribe en más de dos pedazos, y lleva al menos un dígito.
 */
class RespuestaCaptcha
{
    private const LARGO_MIN = 4;
    private const LARGO_MAX = 12;
    private const MAX_TROZOS = 2;

    /** Quita espacios y saltos: el cliente puede escribirlo separado o en varias líneas. */
    public static function normalizar(string $texto): string
    {
        return preg_replace('/\s+/', '', trim($texto)) ?? '';
    }

    public static function pareceCodigo(string $texto): bool
    {
        $original = trim($texto);
        if ($original === '') {
            return false;
        }

        // Nadie escribe un código en tres pedazos; eso ya es una frase.
        if (count(preg_split('/\s+/', $original)) > self::MAX_TROZOS) {
            return false;
        }

        $limpio = self::normalizar($original);

        if (!preg_match('/^[A-Za-z0-9]{' . self::LARGO_MIN . ',' . self::LARGO_MAX . '}$/', $limpio)) {
            return false;
        }

        // Los captchas de ADRES traen dígitos; las palabras sueltas no. Es lo que
        // separa "00779574" de "yavoy". Si algún día saliera un código de puras
        // letras, el mensaje se le pasa a la IA y ella se lo pide de nuevo: falla
        // hacia el lado barato.
        return (bool) preg_match('/\d/', $limpio);
    }
}
