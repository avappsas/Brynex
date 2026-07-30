<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sobrepone el logo COMPLETO del aliado (ícono + nombre + eslogan, ya diseñado como una sola
 * pieza de marca) en una esquina de las imágenes generadas por IA — ni Gemini ni Imagen
 * pueden reproducir el logo real, así que se agrega después por composición de imagen (GD).
 *
 * El logo se dibuja tal cual (sin fondo/círculo agregado) — el logo del aliado ya trae su
 * propio fondo circular diseñado.
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
     */
    public static function aplicar(string $rutaImagen, ?string $rutaLogoClaro, ?array $recorte = null): void
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

            $logo = self::cargarConRecorte(Storage::disk('public')->path($rutaLogoClaro), $recorte);
            if (!$logo) return;

            self::componerLogo($imagen, $anchoImg, $altoImg, $logo);

            self::guardar($imagen, $pathImagen);

            imagedestroy($imagen);
            imagedestroy($logo);
        } catch (\Throwable $e) {
            // No bloquear la generación de la pieza si el watermark falla — se publica sin logo.
        }
    }

    /**
     * Genera SOLO el logo (sin ningún fondo) como un PNG transparente del tamaño exacto de un
     * lienzo dado — para poder superponerlo sobre un VIDEO con FFmpeg (que no puede ejecutar
     * GD), reusando el mismo tratamiento visual que `aplicar()` ya aplica sobre fotos estáticas.
     * Devuelve la ruta absoluta del PNG temporal, o null si falla.
     */
    public static function generarOverlayTransparente(string $rutaLogoClaro, ?array $recorte, int $anchoLienzo, int $altoLienzo): ?string
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

        self::componerLogo($lienzo, $anchoLienzo, $altoLienzo, $logo);

        $rutaTemp = sys_get_temp_dir() . '/' . Str::random(20) . '_logo_overlay.png';
        imagepng($lienzo, $rutaTemp);

        imagedestroy($lienzo);
        imagedestroy($logo);

        return $rutaTemp;
    }

    /**
     * Dibuja el logo sobre el lienzo dado (foto existente o canvas en blanco) — misma lógica de
     * tamaño/posición para ambos casos, calculada siempre sobre el logo REAL que se va a dibujar
     * (nunca una referencia distinta), así jamás se deforma.
     */
    private static function componerLogo($lienzo, int $anchoLienzo, int $altoLienzo, $logo): void
    {
        $margen = (int) round(min($anchoLienzo, $altoLienzo) * 0.05);

        // Tamaño del logo por ANCHO, con tope de alto.
        $anchoLogo   = (int) round($anchoLienzo * 0.24);
        $altoLogoMax = (int) round($altoLienzo * 0.10);
        $escala = min($anchoLogo / imagesx($logo), $altoLogoMax / imagesy($logo));
        $wLogo = (int) round(imagesx($logo) * $escala);
        $hLogo = (int) round(imagesy($logo) * $escala);

        $px = $anchoLienzo - $margen - $wLogo;
        $py = $altoLienzo - $margen - $hLogo;

        // Logo redimensionado a su propio canvas, con transparencia real intacta.
        $logoRedim = imagecreatetruecolor($wLogo, $hLogo);
        imagealphablending($logoRedim, false);
        imagesavealpha($logoRedim, true);
        $transparente = imagecolorallocatealpha($logoRedim, 0, 0, 0, 127);
        imagefilledrectangle($logoRedim, 0, 0, $wLogo, $hLogo, $transparente);
        imagecopyresampled($logoRedim, $logo, 0, 0, 0, 0, $wLogo, $hLogo, imagesx($logo), imagesy($logo));

        imagealphablending($lienzo, true);
        imagecopy($lienzo, $logoRedim, $px, $py, 0, 0, $wLogo, $hLogo);
        imagedestroy($logoRedim);
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
