<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Storage;

/**
 * Sobrepone el logo del aliado en una esquina de las imágenes generadas por IA —
 * ni Gemini ni Imagen pueden reproducir el logo real de la marca, así que se agrega
 * después por composición de imagen (GD), igual para ilustración que para fotorrealista.
 * Sin disco de fondo (se veía como un sticker pegado): en su lugar, una sombra suave
 * detrás del logo, como una marca de agua de agencia real.
 */
class LogoWatermarker
{
    private const FUENTE = __DIR__ . '/../../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    /**
     * Aplica el logo (con su nombre al lado, igual que el encabezado del canvas) sobre la
     * imagen en $rutaImagen (storage/app/public), si el aliado tiene logo. Falla en silencio.
     */
    public static function aplicar(string $rutaImagen, ?string $rutaLogo, ?string $nombreAliado = null): void
    {
        if (!$rutaLogo || !Storage::disk('public')->exists($rutaImagen) || !Storage::disk('public')->exists($rutaLogo)) {
            return;
        }

        try {
            $pathImagen = Storage::disk('public')->path($rutaImagen);
            $pathLogo   = Storage::disk('public')->path($rutaLogo);

            $imagen = self::cargar($pathImagen);
            $logo   = self::cargar($pathLogo);
            if (!$imagen || !$logo) return;

            $anchoImg   = imagesx($imagen);
            $altoImg    = imagesy($imagen);
            $margen     = (int) round(min($anchoImg, $altoImg) * 0.045);
            $tamanoLogo = (int) round(min($anchoImg, $altoImg) * 0.15);
            $px = $anchoImg - $margen - $tamanoLogo;
            $py = $altoImg - $margen - $tamanoLogo;

            // Logo redimensionado a un canvas propio (con su transparencia real intacta).
            $logoRedim = imagecreatetruecolor($tamanoLogo, $tamanoLogo);
            imagealphablending($logoRedim, false);
            imagesavealpha($logoRedim, true);
            $transparente = imagecolorallocatealpha($logoRedim, 0, 0, 0, 127);
            imagefilledrectangle($logoRedim, 0, 0, $tamanoLogo, $tamanoLogo, $transparente);

            $anchoLogoOrig = imagesx($logo);
            $altoLogoOrig  = imagesy($logo);
            $escala = min($tamanoLogo / $anchoLogoOrig, $tamanoLogo / $altoLogoOrig);
            $wDestino = (int) round($anchoLogoOrig * $escala);
            $hDestino = (int) round($altoLogoOrig * $escala);
            imagecopyresampled(
                $logoRedim, $logo,
                (int) (($tamanoLogo - $wDestino) / 2), (int) (($tamanoLogo - $hDestino) / 2), 0, 0,
                $wDestino, $hDestino, $anchoLogoOrig, $altoLogoOrig
            );

            // Sombra real con la SILUETA del logo (a partir de su propio canal alfa), no una
            // caja detrás — se dibuja en un lienzo aparte con margen para el desenfoque, se
            // desenfoca, y se compone antes que el logo. Sin ningún fondo sólido.
            $margenSombra = (int) round($tamanoLogo * 0.18);
            $offsetX = (int) round($tamanoLogo * 0.03);
            $offsetY = (int) round($tamanoLogo * 0.045);
            $tamSombra = $tamanoLogo + $margenSombra * 2;

            $sombra = imagecreatetruecolor($tamSombra, $tamSombra);
            imagealphablending($sombra, false);
            imagesavealpha($sombra, true);
            $transSombra = imagecolorallocatealpha($sombra, 0, 0, 0, 127);
            imagefilledrectangle($sombra, 0, 0, $tamSombra, $tamSombra, $transSombra);
            imagealphablending($sombra, true);

            for ($y = 0; $y < $tamanoLogo; $y++) {
                for ($x = 0; $x < $tamanoLogo; $x++) {
                    $alphaOrig = (imagecolorat($logoRedim, $x, $y) >> 24) & 0x7F;
                    if ($alphaOrig >= 110) continue; // prácticamente transparente, no aporta sombra
                    $alphaSombra = min(105, $alphaOrig + 45);
                    $col = imagecolorallocatealpha($sombra, 0, 0, 0, $alphaSombra);
                    imagesetpixel($sombra, $margenSombra + $x + $offsetX, $margenSombra + $y + $offsetY, $col);
                }
            }
            imagefilter($sombra, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($sombra, IMG_FILTER_GAUSSIAN_BLUR);

            imagealphablending($imagen, true);
            imagecopy($imagen, $sombra, $px - $margenSombra, $py - $margenSombra, 0, 0, $tamSombra, $tamSombra);
            imagecopy($imagen, $logoRedim, $px, $py, 0, 0, $tamanoLogo, $tamanoLogo);

            // Nombre del aliado junto al logo (a la izquierda), igual que el encabezado del
            // canvas — texto blanco con una sombra oscura detrás (offset simple + desenfoque)
            // para que se lea sobre cualquier fondo, sin ninguna caja.
            if ($nombreAliado && is_file(self::FUENTE)) {
                $texto = mb_strtoupper($nombreAliado);
                $tamanoFuente = max(11, (int) round($tamanoLogo * 0.24));
                $caja = imagettfbbox($tamanoFuente, 0, self::FUENTE, $texto);
                $anchoTexto = abs($caja[2] - $caja[0]);
                $xTexto = $px - 14 - $anchoTexto;
                $yTexto = (int) round($py + $tamanoLogo / 2 + $tamanoFuente / 3);

                if ($xTexto > 4) { // solo si cabe sin salirse por la izquierda
                    $negro = imagecolorallocatealpha($imagen, 0, 0, 0, 40);
                    imagettftext($imagen, $tamanoFuente, 0, $xTexto + 1, $yTexto + 2, $negro, self::FUENTE, $texto);
                    $blanco = imagecolorallocate($imagen, 255, 255, 255);
                    imagettftext($imagen, $tamanoFuente, 0, $xTexto, $yTexto, $blanco, self::FUENTE, $texto);
                }
            }

            self::guardar($imagen, $pathImagen);

            imagedestroy($imagen);
            imagedestroy($logo);
            imagedestroy($logoRedim);
            imagedestroy($sombra);
        } catch (\Throwable $e) {
            // No bloquear la generación de la pieza si el watermark falla — se publica sin logo.
        }
    }

    private static function cargar(string $path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => @imagecreatefrompng($path),
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'webp' => @imagecreatefromwebp($path),
            default => null,
        };
    }

    private static function guardar($imagen, string $path): void
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        match ($ext) {
            'png' => imagepng($imagen, $path),
            'jpg', 'jpeg' => imagejpeg($imagen, $path, 92),
            'webp' => imagewebp($imagen, $path, 92),
            default => null,
        };
    }
}
