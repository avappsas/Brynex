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
     * Aplica una insignia (tarjeta clara redondeada, con sombra propia, logo arriba y nombre
     * del aliado debajo — como el icono+nombre de una página de Facebook) en una esquina de
     * la imagen en $rutaImagen (storage/app/public), si el aliado tiene logo. Falla en silencio.
     * Todo se compone en un lienzo aparte y se pega una sola vez, para que quede alineado.
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

            $anchoImg = imagesx($imagen);
            $altoImg  = imagesy($imagen);
            $margen   = (int) round(min($anchoImg, $altoImg) * 0.045);

            // Logo redimensionado a su propio canvas, con transparencia real intacta.
            $tamanoLogo = (int) round(min($anchoImg, $altoImg) * 0.12);
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

            // Medidas del texto (si hay nombre y fuente disponibles).
            $texto = null;
            $tamanoFuente = max(11, (int) round($tamanoLogo * 0.26));
            $anchoTexto = 0;
            if ($nombreAliado && is_file(self::FUENTE)) {
                $texto = mb_strtoupper($nombreAliado);
                $caja = imagettfbbox($tamanoFuente, 0, self::FUENTE, $texto);
                $anchoTexto = abs($caja[2] - $caja[0]);
            }

            // Tarjeta: padding alrededor, logo arriba, nombre debajo.
            $padX = (int) round($tamanoLogo * 0.32);
            $padY = (int) round($tamanoLogo * 0.28);
            $gap  = $texto ? (int) round($tamanoLogo * 0.14) : 0;
            $altoTexto = $texto ? (int) round($tamanoFuente * 1.15) : 0;
            $radio = (int) round($tamanoLogo * 0.22);

            $anchoTarjeta = max($tamanoLogo, $anchoTexto) + $padX * 2;
            $altoTarjeta  = $padY + $tamanoLogo + $gap + $altoTexto + $padY;

            // ── Lienzo de la tarjeta (fondo claro sólido, logo y texto encima) ──
            $tarjeta = imagecreatetruecolor($anchoTarjeta, $altoTarjeta);
            imagealphablending($tarjeta, false);
            imagesavealpha($tarjeta, true);
            $transTarjeta = imagecolorallocatealpha($tarjeta, 0, 0, 0, 127);
            imagefilledrectangle($tarjeta, 0, 0, $anchoTarjeta, $altoTarjeta, $transTarjeta);
            $blancoTarjeta = imagecolorallocatealpha($tarjeta, 255, 255, 255, 6); // ~95% opaco
            self::rectanguloRedondeado($tarjeta, 0, 0, $anchoTarjeta - 1, $altoTarjeta - 1, $radio, $blancoTarjeta);

            imagealphablending($tarjeta, true);
            $xLogo = (int) round(($anchoTarjeta - $tamanoLogo) / 2);
            imagecopy($tarjeta, $logoRedim, $xLogo, $padY, 0, 0, $tamanoLogo, $tamanoLogo);
            if ($texto) {
                $xTexto = (int) round(($anchoTarjeta - $anchoTexto) / 2);
                $yTexto = $padY + $tamanoLogo + $gap + $tamanoFuente;
                $oscuro = imagecolorallocate($tarjeta, 30, 41, 59); // slate-800, buen contraste sobre blanco
                imagettftext($tarjeta, $tamanoFuente, 0, $xTexto, $yTexto, $oscuro, self::FUENTE, $texto);
            }

            // ── Sombra de la tarjeta (silueta del rectángulo redondeado, desenfocada) ──
            $margenSombra = (int) round($radio * 1.3);
            $offsetSombra = (int) round($tamanoLogo * 0.05);
            $tamSombraW = $anchoTarjeta + $margenSombra * 2;
            $tamSombraH = $altoTarjeta + $margenSombra * 2;

            $sombra = imagecreatetruecolor($tamSombraW, $tamSombraH);
            imagealphablending($sombra, false);
            imagesavealpha($sombra, true);
            $transSombra = imagecolorallocatealpha($sombra, 0, 0, 0, 127);
            imagefilledrectangle($sombra, 0, 0, $tamSombraW, $tamSombraH, $transSombra);
            $colorSombra = imagecolorallocatealpha($sombra, 15, 23, 42, 78);
            self::rectanguloRedondeado(
                $sombra,
                $margenSombra, $margenSombra + $offsetSombra,
                $margenSombra + $anchoTarjeta - 1, $margenSombra + $offsetSombra + $altoTarjeta - 1,
                $radio, $colorSombra
            );
            imagefilter($sombra, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($sombra, IMG_FILTER_GAUSSIAN_BLUR);

            // ── Componer todo sobre la imagen final, en la esquina ──
            $px = $anchoImg - $margen - $anchoTarjeta;
            $py = $altoImg - $margen - $altoTarjeta;
            imagealphablending($imagen, true);
            imagecopy($imagen, $sombra, $px - $margenSombra, $py - $margenSombra, 0, 0, $tamSombraW, $tamSombraH);
            imagecopy($imagen, $tarjeta, $px, $py, 0, 0, $anchoTarjeta, $altoTarjeta);

            self::guardar($imagen, $pathImagen);

            imagedestroy($imagen);
            imagedestroy($logo);
            imagedestroy($logoRedim);
            imagedestroy($tarjeta);
            imagedestroy($sombra);
        } catch (\Throwable $e) {
            // No bloquear la generación de la pieza si el watermark falla — se publica sin logo.
        }
    }

    /** Rectángulo relleno con esquinas redondeadas (banda cruzada + arcos en las esquinas). */
    private static function rectanguloRedondeado($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
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
