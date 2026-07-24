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
    /** Aplica el logo sobre la imagen en $rutaImagen (storage/app/public), si el aliado tiene logo. Falla en silencio. */
    public static function aplicar(string $rutaImagen, ?string $rutaLogo): void
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

            // Sombra proyectada suave detrás del logo (varias pasadas con offset y
            // opacidad decreciente, aproximando un desenfoque) — le da profundidad sin
            // necesitar un fondo sólido que se vea pegado sobre la foto.
            imagealphablending($imagen, true);
            for ($o = 6; $o > 0; $o--) {
                $alpha = 90 + (int) (30 * (6 - $o) / 6);
                $color = imagecolorallocatealpha($imagen, 0, 0, 0, min(120, $alpha));
                imagefilledrectangle($imagen, $px - $o + 3, $py - $o + 5, $px + $tamanoLogo + $o + 3, $py + $tamanoLogo + $o + 5, $color);
            }

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

            imagecopy($imagen, $logoRedim, $px, $py, 0, 0, $tamanoLogo, $tamanoLogo);

            self::guardar($imagen, $pathImagen);

            imagedestroy($imagen);
            imagedestroy($logo);
            imagedestroy($logoRedim);
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
