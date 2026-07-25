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
     * @param ?array  $recorte Opcional {icono_ancho_pct, wordmark_y_inicio_pct, wordmark_y_fin_pct}
     *   — layout ícono-izquierda + texto-derecha (nombre arriba, eslogan abajo): recompone
     *   ícono completo + SOLO el nombre (sin el eslogan, que se vuelve ilegible al achicar).
     *   Si no se pasa, usa el logo completo tal cual — comportamiento seguro por defecto para
     *   cualquier aliado cuyo logo no siga ese layout específico.
     */
    public static function aplicar(string $rutaImagen, ?string $rutaLogoClaro, ?string $rutaLogoOscuro = null, ?array $recorte = null): void
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
            $logoRef = self::cargarConRecorte(Storage::disk('public')->path($rutaLogoClaro), $recorte);
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

            $logo = self::cargarConRecorte(Storage::disk('public')->path($rutaElegida), $recorte);
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

    /** Carga el logo y, si hay recorte configurado, recompone ícono completo + solo el nombre (sin eslogan). */
    private static function cargarConRecorte(string $path, ?array $recorte)
    {
        $origen = self::cargar($path);
        if (!$origen || !$recorte) {
            return $origen;
        }

        $anchoSrc = imagesx($origen);
        $altoSrc  = imagesy($origen);
        $xIcono   = (int) round($anchoSrc * ((float) ($recorte['icono_ancho_pct'] ?? 0) / 100));
        $yIni     = (int) round($altoSrc * ((float) ($recorte['wordmark_y_inicio_pct'] ?? 0) / 100));
        $yFin     = (int) round($altoSrc * ((float) ($recorte['wordmark_y_fin_pct'] ?? 100) / 100));

        if ($xIcono <= 0 || $xIcono >= $anchoSrc || $yFin <= $yIni) {
            return $origen; // config inválida, usar el logo completo tal cual
        }

        // Ícono: columna izquierda, alto completo.
        $icono = imagecreatetruecolor($xIcono, $altoSrc);
        imagealphablending($icono, false);
        imagesavealpha($icono, true);
        $trans = imagecolorallocatealpha($icono, 0, 0, 0, 127);
        imagefilledrectangle($icono, 0, 0, $xIcono, $altoSrc, $trans);
        imagecopy($icono, $origen, 0, 0, 0, 0, $xIcono, $altoSrc);

        // Wordmark: columna derecha, solo la franja del nombre (sin el eslogan debajo).
        $anchoTexto = $anchoSrc - $xIcono;
        $altoTexto  = $yFin - $yIni;
        $wordmark = imagecreatetruecolor($anchoTexto, $altoTexto);
        imagealphablending($wordmark, false);
        imagesavealpha($wordmark, true);
        $trans2 = imagecolorallocatealpha($wordmark, 0, 0, 0, 127);
        imagefilledrectangle($wordmark, 0, 0, $anchoTexto, $altoTexto, $trans2);
        imagecopy($wordmark, $origen, 0, 0, $xIcono, $yIni, $anchoTexto, $altoTexto);
        imagedestroy($origen);

        // Escalar el wordmark a ~42% del alto del ícono y unir lado a lado con un margen.
        $altoWordmarkFinal  = (int) round($altoSrc * 0.42);
        $anchoWordmarkFinal = (int) round($anchoTexto * ($altoWordmarkFinal / $altoTexto));
        $wordmarkResize = imagecreatetruecolor($anchoWordmarkFinal, $altoWordmarkFinal);
        imagealphablending($wordmarkResize, false);
        imagesavealpha($wordmarkResize, true);
        $trans3 = imagecolorallocatealpha($wordmarkResize, 0, 0, 0, 127);
        imagefilledrectangle($wordmarkResize, 0, 0, $anchoWordmarkFinal, $altoWordmarkFinal, $trans3);
        imagecopyresampled($wordmarkResize, $wordmark, 0, 0, 0, 0, $anchoWordmarkFinal, $altoWordmarkFinal, $anchoTexto, $altoTexto);
        imagedestroy($wordmark);

        $gap = (int) round($xIcono * 0.04);
        $anchoFinal = $xIcono + $gap + $anchoWordmarkFinal;
        $final = imagecreatetruecolor($anchoFinal, $altoSrc);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        $trans4 = imagecolorallocatealpha($final, 0, 0, 0, 127);
        imagefilledrectangle($final, 0, 0, $anchoFinal, $altoSrc, $trans4);
        imagealphablending($final, true);
        imagecopy($final, $icono, 0, 0, 0, 0, $xIcono, $altoSrc);
        $yWordmark = (int) round(($altoSrc - $altoWordmarkFinal) / 2);
        imagecopy($final, $wordmarkResize, $xIcono + $gap, $yWordmark, 0, 0, $anchoWordmarkFinal, $altoWordmarkFinal);

        imagedestroy($icono);
        imagedestroy($wordmarkResize);

        return $final;
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
