<?php

namespace App\Services\Adres;

/**
 * Recompone el captcha de ADRES para que se pueda leer en WhatsApp.
 *
 * El recorte original mide 281x51 (relación 5.5:1). WhatsApp recorta la
 * previsualización de imágenes tan alargadas, así que el cliente que no abra la
 * imagen ve solo el centro y escribe mal el código. Sobre un lienzo de ~1.3:1 la
 * burbuja lo muestra completo.
 *
 * La instrucción NO se dibuja dentro de la imagen: va en el caption del mensaje,
 * para que se pueda traducir, la lea un lector de pantalla, y no dependa de que
 * el servidor tenga fuentes instaladas.
 */
class CaptchaImagen
{
    public const ANCHO = 900;
    public const ALTO  = 700;

    private const MARGEN = 60;
    private const BORDE  = 3;

    /**
     * @param  string  $pngOriginal  Bytes del PNG recortado del formulario.
     * @return string  Bytes del PNG recompuesto.
     */
    public static function componer(string $pngOriginal): string
    {
        $origen = @imagecreatefromstring($pngOriginal);
        if ($origen === false) {
            throw new \RuntimeException('El captcha recibido de ADRES no es una imagen válida.');
        }

        $anchoOrigen = imagesx($origen);
        $altoOrigen  = imagesy($origen);

        $disponibleAncho = self::ANCHO - 2 * self::MARGEN;
        $disponibleAlto  = self::ALTO - 2 * self::MARGEN;
        $escala = min($disponibleAncho / $anchoOrigen, $disponibleAlto / $altoOrigen);

        $anchoFinal = (int) round($anchoOrigen * $escala);
        $altoFinal  = (int) round($altoOrigen * $escala);

        $lienzo = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));

        $x = (int) ((self::ANCHO - $anchoFinal) / 2);
        $y = (int) ((self::ALTO - $altoFinal) / 2);

        // Marco tenue: ayuda a leer el bloque como "aquí está el código" y no
        // como una mancha suelta en medio del blanco.
        $gris = imagecolorallocate($lienzo, 203, 213, 225);
        imagefilledrectangle(
            $lienzo,
            $x - self::BORDE,
            $y - self::BORDE,
            $x + $anchoFinal + self::BORDE,
            $y + $altoFinal + self::BORDE,
            $gris
        );

        imagecopyresampled($lienzo, $origen, $x, $y, 0, 0, $anchoFinal, $altoFinal, $anchoOrigen, $altoOrigen);

        ob_start();
        imagepng($lienzo, null, 9);
        $bytes = ob_get_clean();

        imagedestroy($lienzo);
        imagedestroy($origen);

        return $bytes;
    }
}
