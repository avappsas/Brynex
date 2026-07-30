<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sobrepone el logo COMPLETO del aliado (ícono + nombre + eslogan, ya diseñado como una sola
 * pieza de marca) en una esquina de las imágenes generadas por IA — ni Gemini ni Imagen
 * pueden reproducir el logo real, así que se agrega después por composición de imagen (GD).
 *
 * El logo se apoya sobre un círculo de vidrio esmerilado (mismo tratamiento del "hero-glow"
 * de la página pública del aliado) en vez de flotar suelto sobre la foto: así se lee bien
 * sobre cualquier fondo con un solo logo (`logo_marca_claro`), sin necesitar una variante
 * oscura ni adivinar el brillo de la esquina.
 */
class LogoWatermarker
{
    /**
     * @param ?string $rutaLogoClaro Logo de marca (campo `logo_marca_claro`), el que se dibuja siempre.
     * @param ?array  $recorte Opcional, dos formas mutuamente excluyentes según el layout del logo:
     *   - Vertical (ícono arriba + nombre debajo, eslogan/subtítulo al final): {alto_util_pct}
     *     — conserva solo ese % superior del alto, cortando el eslogan que se vuelve ilegible
     *     al achicar.
     *   - Horizontal (ícono a la izquierda + nombre y eslogan a la derecha):
     *     {icono_ancho_pct, wordmark_y_inicio_pct, wordmark_y_fin_pct} — recompone ícono
     *     completo + SOLO el nombre (sin el eslogan).
     *   Si no se pasa nada, usa el logo completo tal cual — comportamiento seguro por defecto
     *   para cualquier aliado cuyo logo no siga ninguno de esos layouts.
     * @param ?string $colorPrimario Hex de marca del aliado, para teñir sutilmente el círculo.
     */
    public static function aplicar(string $rutaImagen, ?string $rutaLogoClaro, ?array $recorte = null, ?string $colorPrimario = null): void
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
            $margen   = (int) round(min($anchoImg, $altoImg) * 0.05);

            $logo = self::cargarConRecorte(Storage::disk('public')->path($rutaLogoClaro), $recorte);
            if (!$logo) return;

            self::componerLogoEnCirculo($imagen, $anchoImg, $altoImg, $logo, $colorPrimario);

            self::guardar($imagen, $pathImagen);

            imagedestroy($imagen);
            imagedestroy($logo);
        } catch (\Throwable $e) {
            // No bloquear la generación de la pieza si el watermark falla — se publica sin logo.
        }
    }

    /**
     * Genera SOLO el círculo de vidrio + logo (sin ningún fondo) como un PNG transparente del
     * tamaño exacto de un lienzo dado — para poder superponerlo sobre un VIDEO con FFmpeg (que
     * no puede ejecutar GD), reusando el mismo tratamiento visual que `aplicar()` ya aplica
     * sobre fotos estáticas. Devuelve la ruta absoluta del PNG temporal, o null si falla.
     */
    public static function generarOverlayTransparente(string $rutaLogoClaro, ?array $recorte, ?string $colorPrimario, int $anchoLienzo, int $altoLienzo): ?string
    {
        if (!Storage::disk('public')->exists($rutaLogoClaro)) {
            return null;
        }

        $logo = self::cargarConRecorte(Storage::disk('public')->path($rutaLogoClaro), $recorte);
        if (!$logo) return null;

        $lienzo = imagecreatetruecolor($anchoLienzo, $altoLienzo);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefilledrectangle($lienzo, 0, 0, $anchoLienzo, $altoLienzo, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));

        self::componerLogoEnCirculo($lienzo, $anchoLienzo, $altoLienzo, $logo, $colorPrimario);

        $rutaTemp = sys_get_temp_dir() . '/' . Str::random(20) . '_logo_overlay.png';
        imagepng($lienzo, $rutaTemp);

        imagedestroy($lienzo);
        imagedestroy($logo);

        return $rutaTemp;
    }

    /**
     * Dibuja el círculo de vidrio + logo sobre el lienzo dado (foto existente o canvas en
     * blanco) — misma lógica de tamaño/posición para ambos casos, calculada siempre sobre el
     * logo REAL que se va a dibujar (nunca una referencia distinta), así jamás se deforma.
     */
    private static function componerLogoEnCirculo($lienzo, int $anchoLienzo, int $altoLienzo, $logo, ?string $colorPrimario): void
    {
        $margen = (int) round(min($anchoLienzo, $altoLienzo) * 0.05);

        // Tamaño del logo por ANCHO, con tope de alto.
        $anchoLogo   = (int) round($anchoLienzo * 0.24);
        $altoLogoMax = (int) round($altoLienzo * 0.10);
        $escala = min($anchoLogo / imagesx($logo), $altoLogoMax / imagesy($logo));
        $wLogo = (int) round(imagesx($logo) * $escala);
        $hLogo = (int) round(imagesy($logo) * $escala);

        $diametro = (int) round(max($wLogo, $hLogo) * 1.55);
        $cx = $anchoLienzo - $margen - (int) round($diametro / 2);
        $cy = $altoLienzo - $margen - (int) round($diametro / 2);

        imagealphablending($lienzo, true);
        self::pintarSombraCirculo($lienzo, $cx, $cy, $diametro);
        self::pintarCirculoVidrio($lienzo, $cx, $cy, $diametro, $colorPrimario);

        // Logo redimensionado a su propio canvas, con transparencia real intacta.
        $logoRedim = imagecreatetruecolor($wLogo, $hLogo);
        imagealphablending($logoRedim, false);
        imagesavealpha($logoRedim, true);
        $transparente = imagecolorallocatealpha($logoRedim, 0, 0, 0, 127);
        imagefilledrectangle($logoRedim, 0, 0, $wLogo, $hLogo, $transparente);
        imagecopyresampled($logoRedim, $logo, 0, 0, 0, 0, $wLogo, $hLogo, imagesx($logo), imagesy($logo));

        $px = $cx - (int) round($wLogo / 2);
        $py = $cy - (int) round($hLogo / 2);

        imagealphablending($lienzo, true);
        imagecopy($lienzo, $logoRedim, $px, $py, 0, 0, $wLogo, $hLogo);
        imagedestroy($logoRedim);
    }

    /**
     * Sombra suave y difusa debajo del círculo, para despegarlo de la foto — bien esparcida
     * (mucho margen + muchas pasadas de blur, porque IMG_FILTER_GAUSSIAN_BLUR de GD es débil
     * y con solo 1-2 pasadas deja un semicírculo oscuro de borde duro en vez de un degradado).
     */
    private static function pintarSombraCirculo($imagen, int $cx, int $cy, int $diametro): void
    {
        $margenSombra = (int) round($diametro * 0.45);
        $tamSombra = $diametro + $margenSombra * 2;

        $sombra = imagecreatetruecolor($tamSombra, $tamSombra);
        imagealphablending($sombra, false);
        imagesavealpha($sombra, true);
        imagefilledrectangle($sombra, 0, 0, $tamSombra, $tamSombra, imagecolorallocatealpha($sombra, 0, 0, 0, 127));
        imagealphablending($sombra, true);
        $colorSombra = imagecolorallocatealpha($sombra, 0, 0, 0, 108);
        imagefilledellipse($sombra, (int) round($tamSombra / 2), (int) round($tamSombra / 2 + $diametro * 0.05), (int) round($diametro * 0.94), (int) round($diametro * 0.94), $colorSombra);
        for ($i = 0; $i < 10; $i++) {
            imagefilter($sombra, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($imagen, $sombra, $cx - (int) round($tamSombra / 2), $cy - (int) round($tamSombra / 2), 0, 0, $tamSombra, $tamSombra);
        imagedestroy($sombra);
    }

    /**
     * Círculo translúcido tipo "vidrio esmerilado", teñido sutilmente con el color de marca —
     * SIN borde (el borde previo se dibujaba con `imagefilledarc`, que GD no suaviza, y se veía
     * pixelado/poco profesional). Se probó además un acabado "esfera de vidrio" con brillo
     * especular + sombra interna, pero se descartó (plano se ve mejor) — el círculo se dibuja
     * por supersampling: 4x más grande y reducido con `imagecopyresampled`, porque GD no
     * suaviza `imagefilledellipse` a tamaño normal.
     */
    private static function pintarCirculoVidrio($imagen, int $cx, int $cy, int $diametro, ?string $colorPrimario): void
    {
        [$r, $g, $b] = self::mezclar([255, 255, 255], self::hexARgb($colorPrimario ?: '#2563eb'), 0.85);

        $factor = 4;
        $grande = $diametro * $factor;
        $centro = (int) round($grande / 2);

        $temp = imagecreatetruecolor($grande, $grande);
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        imagefilledrectangle($temp, 0, 0, $grande, $grande, imagecolorallocatealpha($temp, 0, 0, 0, 127));
        $colorCirculo = imagecolorallocatealpha($temp, $r, $g, $b, 40);
        imagefilledellipse($temp, $centro, $centro, $grande, $grande, $colorCirculo);

        $circulo = imagecreatetruecolor($diametro, $diametro);
        imagealphablending($circulo, false);
        imagesavealpha($circulo, true);
        imagefilledrectangle($circulo, 0, 0, $diametro, $diametro, imagecolorallocatealpha($circulo, 0, 0, 0, 127));
        imagecopyresampled($circulo, $temp, 0, 0, 0, 0, $diametro, $diametro, $grande, $grande);
        imagedestroy($temp);

        imagealphablending($imagen, true);
        imagecopy($imagen, $circulo, $cx - (int) round($diametro / 2), $cy - (int) round($diametro / 2), 0, 0, $diametro, $diametro);
        imagedestroy($circulo);
    }

    private static function hexARgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** Mezcla dos colores RGB según una proporción (0 = puro $b, 1 = puro $a). */
    private static function mezclar(array $a, array $b, float $prop): array
    {
        return [
            (int) round($a[0] * $prop + $b[0] * (1 - $prop)),
            (int) round($a[1] * $prop + $b[1] * (1 - $prop)),
            (int) round($a[2] * $prop + $b[2] * (1 - $prop)),
        ];
    }

    /** Carga el logo y, si hay recorte configurado, recompone ícono completo + solo el nombre (sin eslogan). */
    public static function cargarConRecorte(string $path, ?array $recorte)
    {
        $origen = self::cargar($path);
        if (!$origen || !$recorte) {
            return $origen;
        }

        if (isset($recorte['alto_util_pct'])) {
            return self::recortarVertical($origen, (float) $recorte['alto_util_pct']);
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

    /** Layout vertical (ícono arriba + nombre debajo): conserva solo el % superior del alto, cortando el eslogan/subtítulo final. */
    private static function recortarVertical($origen, float $altoUtilPct)
    {
        $anchoSrc = imagesx($origen);
        $altoSrc  = imagesy($origen);
        $altoUtil = (int) round($altoSrc * ($altoUtilPct / 100));

        if ($altoUtil <= 0 || $altoUtil >= $altoSrc) {
            return $origen; // config inválida, usar el logo completo tal cual
        }

        $recortado = imagecreatetruecolor($anchoSrc, $altoUtil);
        imagealphablending($recortado, false);
        imagesavealpha($recortado, true);
        $trans = imagecolorallocatealpha($recortado, 0, 0, 0, 127);
        imagefilledrectangle($recortado, 0, 0, $anchoSrc, $altoUtil, $trans);
        imagecopy($recortado, $origen, 0, 0, 0, 0, $anchoSrc, $altoUtil);
        imagedestroy($origen);

        return $recortado;
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
