<?php

namespace App\Services\Publicidad;

/**
 * Cómo se escriben las siglas y la marca cuando alguien las va a DECIR en voz alta.
 *
 * Los modelos de voz —el de Veo y el TTS— leen literal lo que se les escriba. "ARL" lo
 * deletrean con una pausa entre letra y letra ("A... R... L"), que en un anuncio de ocho
 * segundos se come medio mensaje y suena a robot leyendo una placa. Escrito como suena, sale
 * corrido.
 *
 * Todo vive aquí y no dentro de cada prompt porque antes estaba repetido en un prompt, faltaba
 * en otro (los videos de más de 8 segundos no tenían ninguna regla) y no se aplicaba a las
 * frases del cierre de marca. Cambiar cómo suena una sigla es UNA línea, no una cacería.
 */
class PronunciacionEsp
{
    /**
     * Sigla → cómo se escribe para que se lea corrido.
     *
     * Ojo con la R: en español la letra se llama "ere" (suave), no "erre" — "a erre ele" suena
     * a otra cosa. Y va pegado a propósito: separado en tres palabras el modelo mete pausa.
     */
    public const SIGLAS = [
        'ARL' => 'a ereele',
        'EPS' => 'epeese',
        'AFP' => 'aefepe',
        'IBC' => 'ibece',
    ];

    /** Marcas cuyo nombre escrito no se lee como suena. */
    public const MARCAS = [
        'BRYGAR' => 'Brigar',
    ];

    /** Reescribe un texto que alguien va a decir en voz alta. */
    public static function paraLocucion(string $texto): string
    {
        foreach (self::MARCAS as $marca => $suena) {
            $texto = preg_replace('/\b' . preg_quote($marca, '/') . '\b/iu', $suena, $texto);
        }

        foreach (self::SIGLAS as $sigla => $suena) {
            // Solo la sigla suelta: sin \b, "PILA" pisaría "pilar" y "EPS" pisaría "EPSs".
            $texto = preg_replace('/\b' . preg_quote($sigla, '/') . '\b/u', $suena, $texto);
        }

        return $texto;
    }

    /**
     * El bloque de reglas que se le mete al prompt cuando es la IA —y no nosotros— la que
     * redacta la frase hablada. Se arma desde el mismo mapa para que no se desincronicen.
     */
    public static function reglaParaPrompt(string $marca): string
    {
        $ejemplos = collect(self::SIGLAS)
            ->filter(fn ($suena, $sigla) => $sigla !== $suena)
            ->map(fn ($suena, $sigla) => "{$sigla} → \"{$suena}\"")
            ->implode(', ');

        $comoSuena = self::MARCAS[mb_strtoupper($marca)] ?? $marca;

        return 'PRONUNCIACIÓN — REGLA OBLIGATORIA en la frase hablada (el modelo de voz lee literal lo que se '
            . 'escriba, así que hay que escribirlo como debe sonar):' . "\n"
            . '  · Siglas escritas COMO SUENAN y PEGADAS, para que se lean corridas y sin pausa entre letras: '
            . $ejemplos . '. Nunca las escribas con las letras sueltas ni separadas por espacios o puntos '
            . '("A R L", "A.R.L.", "a ere ele"): así el modelo mete una pausa en cada letra. '
            . 'Ojo: la R se llama "ere" (suave), NO "erre".' . "\n"
            . "  · El nombre de la marca {$marca} se escribe \"{$comoSuena}\" cuando lo diga en voz alta, "
            . 'para que no lo lea deletreado ni con acento extranjero.' . "\n"
            . 'Todo esto aplica SOLO a la frase que la persona pronuncia; en la descripción de la escena en '
            . 'inglés las siglas y la marca van escritas normales.' . "\n"
            // La escritura sola no basta: al modelo de video hay que DECIRLE que lo lea
            // corrido, si no deletrea igual aunque venga escrito como suena.
            . '  · Agrega en el prompt en inglés la indicación: "spoken fluidly and naturally, '
            . 'never spelling out letters and never pausing between them".';
    }
}
