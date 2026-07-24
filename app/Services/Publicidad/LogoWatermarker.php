<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Storage;

/**
 * Sobrepone el logo COMPLETO del aliado (ícono + nombre + eslogan, ya diseñado como una sola
 * pieza de marca) en una esquina de las imágenes generadas por IA — ni Gemini ni Imagen
 * pueden reproducir el logo real, así que se agrega después por composición de imagen (GD).
 *
 * Dos variantes posibles por aliado: `logo` (oscuro/de color, para fondos claros) y
 * `logo_oscuro` (claro/blanco, para fondos oscuros). Se elige automáticamente según el
 * brillo REAL de la esquina de cada foto (no una regla fija) — así se lee bien sin importar
 * si esa pieza en particular salió clara u oscura ahí. Si el aliado no subió la variante
 * clara, siempre usa `logo` como respaldo.
 */
class LogoWatermarker
{
    /**
     * @param ?string $rutaLogoClaro  Logo oscuro/de color — para fondos CLAROS (campo `logo`).
     * @param ?string $rutaLogoOscuro Logo claro/blanco — para fondos OSCUROS (campo `logo_oscuro`).
     */
    public static function aplicar(string $rutaImagen, ?string $rutaLogoClaro, ?string $rutaLogoOscuro = null): void
    {
        if (!$rutaLogoClaro || !Storage::disk('public')->exists($rutaImagen) || !Storage::disk('public')->exists($rutaLogoClaro)) {
            return;
        }

        try {
            $pathImagen = Storage::disk('public')->path($rutaImagen);
            $imagen = self::cargar($pathImagen);
            if (!$imagen) return;

            $anchoImg = imagesx($imagen);
            $altoImg  = imagesy($imagen);
            $margen   = (int) round(min($anchoImg, $altoImg) * 0.04);

            // Tamaño del logo por ANCHO (el lockup completo es horizontal), con tope de alto.
            $anchoLogo = (int) round($anchoImg * 0.28);
            $altoLogoMax = (int) round($altoImg * 0.12);

            // Primero se necesita saber las proporciones reales del logo para calcular dónde
            // cae la esquina y poder medir su brillo — se usa `logo` (claro) como referencia
            // de proporción, asumiendo que ambas variantes comparten el mismo tamaño.
            $logoRef = self::cargar(Storage::disk('public')->path($rutaLogoClaro));
            if (!$logoRef) return;
            $escala = min($anchoLogo / imagesx($logoRef), $altoLogoMax / imagesy($logoRef));
            $wLogo = (int) round(imagesx($logoRef) * $escala);
            $hLogo = (int) round(imagesy($logoRef) * $escala);
            imagedestroy($logoRef);

            $px = $anchoImg - $margen - $wLogo;
            $py = $altoImg - $margen - $hLogo;

            $fondoOscuro = self::esquinaOscura($imagen, $px, $py, $wLogo, $hLogo);
            $rutaElegida = ($fondoOscuro && $rutaLogoOscuro && Storage::disk('public')->exists($rutaLogoOscuro))
                ? $rutaLogoOscuro
                : $rutaLogoClaro;

            $logo = self::cargar(Storage::disk('public')->path($rutaElegida));
            if (!$logo) return;

            // Logo redimensionado a su propio canvas, con transparencia real intacta.
            $logoRedim = imagecreatetruecolor($wLogo, $hLogo);
            imagealphablending($logoRedim, false);
            imagesavealpha($logoRedim, true);
            $transparente = imagecolorallocatealpha($logoRedim, 0, 0, 0, 127);
            imagefilledrectangle($logoRedim, 0, 0, $wLogo, $hLogo, $transparente);
            imagecopyresampled($logoRedim, $logo, 0, 0, 0, 0, $wLogo, $hLogo, imagesx($logo), imagesy($logo));

            // Sombra real con la SILUETA del logo (a partir de su propio canal alfa) — sigue
            // el contorno exacto, sin ninguna caja ni fondo sólido detrás.
            $margenSombra = (int) round($hLogo * 0.35);
            $offsetX = (int) round($hLogo * 0.04);
            $offsetY = (int) round($hLogo * 0.06);
            $tamSombraW = $wLogo + $margenSombra * 2;
            $tamSombraH = $hLogo + $margenSombra * 2;

            $sombra = imagecreatetruecolor($tamSombraW, $tamSombraH);
            imagealphablending($sombra, false);
            imagesavealpha($sombra, true);
            $transSombra = imagecolorallocatealpha($sombra, 0, 0, 0, 127);
            imagefilledrectangle($sombra, 0, 0, $tamSombraW, $tamSombraH, $transSombra);
            imagealphablending($sombra, true);

            for ($y = 0; $y < $hLogo; $y++) {
                for ($x = 0; $x < $wLogo; $x++) {
                    $alphaOrig = (imagecolorat($logoRedim, $x, $y) >> 24) & 0x7F;
                    if ($alphaOrig >= 110) continue;
                    $alphaSombra = min(100, $alphaOrig + 45);
                    $col = imagecolorallocatealpha($sombra, 0, 0, 0, $alphaSombra);
                    imagesetpixel($sombra, $margenSombra + $x + $offsetX, $margenSombra + $y + $offsetY, $col);
                }
            }
            imagefilter($sombra, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($sombra, IMG_FILTER_GAUSSIAN_BLUR);

            imagealphablending($imagen, true);
            imagecopy($imagen, $sombra, $px - $margenSombra, $py - $margenSombra, 0, 0, $tamSombraW, $tamSombraH);
            imagecopy($imagen, $logoRedim, $px, $py, 0, 0, $wLogo, $hLogo);

            self::guardar($imagen, $pathImagen);

            imagedestroy($imagen);
            imagedestroy($logo);
            imagedestroy($logoRedim);
            imagedestroy($sombra);
        } catch (\Throwable $e) {
            // No bloquear la generación de la pieza si el watermark falla — se publica sin logo.
        }
    }

    /** ¿La región donde va el logo es, en promedio, oscura? Muestreo en cuadrícula (barato). */
    private static function esquinaOscura($imagen, int $x, int $y, int $w, int $h): bool
    {
        $anchoImg = imagesx($imagen);
        $altoImg  = imagesy($imagen);
        $puntos = 8;
        $suma = 0;
        $n = 0;

        for ($i = 0; $i < $puntos; $i++) {
            for ($j = 0; $j < $puntos; $j++) {
                $px = min($anchoImg - 1, max(0, $x + (int) round($w * $i / ($puntos - 1))));
                $py = min($altoImg - 1, max(0, $y + (int) round($h * $j / ($puntos - 1))));
                $rgb = imagecolorat($imagen, $px, $py);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $suma += 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $n++;
            }
        }

        return $n > 0 && ($suma / $n) < 130;
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
