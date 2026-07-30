<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Monta sobre el video CRUDO de Veo (que ya trae su propio movimiento de cámara) el texto
 * animado + el logo del aliado — mismo tratamiento visual que LogoWatermarker::aplicar()
 * ya usa en fotos estáticas.
 *
 * El texto se renderiza con GD (imagettftext, igual que FlyerPlanBuilder) a PNGs transparentes
 * — NO con el filtro `drawtext` de FFmpeg — porque el build de ffmpeg de Homebrew en esta
 * máquina (y potencialmente el del servidor) no viene compilado con libfreetype/fontconfig, así
 * que `drawtext` no está disponible (confirmado: `ffmpeg -filters` no lo lista). Generar el
 * texto con GD y solo pedirle a FFmpeg que lo componga con `overlay` evita depender de esas
 * flags opcionales de compilación.
 *
 * Requiere el binario `ffmpeg`/`ffprobe` en el PATH del servidor — ver config('services.ffmpeg').
 */
class VideoOverlayFfmpeg
{
    private const FUENTE = 'resources/fonts/Poppins-Bold.ttf';

    /**
     * @param string[] $frases 2-3 frases cortas para el texto animado en pantalla.
     * @return array{ok: bool, videoPath: ?string, posterPath: ?string, error: ?string}
     */
    public static function aplicar(
        string $rutaVideoBruto,
        array $frases,
        ?string $rutaLogoClaro,
        ?array $recorte,
        ?string $colorPrimario,
        string $destinoVideoAbsoluto,
        string $destinoPosterAbsoluto
    ): array {
        $binario = config('services.ffmpeg.binario', 'ffmpeg');
        $ffprobe = config('services.ffmpeg.ffprobe', 'ffprobe');

        $dimensiones = self::obtenerDimensiones($ffprobe, $rutaVideoBruto);
        if (!$dimensiones) {
            return ['ok' => false, 'videoPath' => null, 'posterPath' => null, 'error' => 'No se pudo leer el video generado por Veo (ffprobe).'];
        }
        [$ancho, $alto, $duracion] = $dimensiones;

        $frases = array_values(array_filter($frases));
        $rutasTemp = [];

        $args = [$binario, '-y', '-i', $rutaVideoBruto];
        $entradaIdx = 1;
        $filtrosCapas = [];
        $mapaOverlay = '[0:v]';

        // Un PNG transparente por frase, repartidas en tramos iguales — SOLO hasta antes de que
        // aparezca el logo (deja ~2.6s finales libres), para que nunca coincidan en el tiempo:
        // así el texto puede ir abajo (lejos de la cara, que en un plano medio/cercano suele
        // estar arriba) sin encimarse nunca con el círculo del logo en la esquina.
        $duracionTextos = max(1, $duracion - 2.6);
        $tramo = count($frases) > 0 ? $duracionTextos / count($frases) : 0;
        foreach ($frases as $i => $frase) {
            $ini = round($i * $tramo, 2);
            $fin = round($ini + $tramo, 2);

            $rutaPng = self::generarPngTexto($frase, $ancho, $alto, $colorPrimario);
            $rutasTemp[] = $rutaPng;

            $args = array_merge($args, ['-loop', '1', '-t', (string) $duracion, '-i', $rutaPng]);

            $fadeOutInicio = max($ini, $fin - 0.25);
            $capa = "capa{$i}";
            $filtrosCapas[] = "[{$entradaIdx}:v]format=rgba,fade=in:st={$ini}:d=0.25:alpha=1,fade=out:st={$fadeOutInicio}:d=0.25:alpha=1[{$capa}]";

            $salida = "v{$i}";
            $filtrosCapas[] = "{$mapaOverlay}[{$capa}]overlay=0:0:enable='between(t,{$ini},{$fin})'[{$salida}]";
            $mapaOverlay = "[{$salida}]";
            $entradaIdx++;
        }

        // Círculo del logo, superpuesto al final del clip.
        $rutaOverlayLogo = $rutaLogoClaro
            ? LogoWatermarker::generarOverlayTransparente($rutaLogoClaro, $recorte, $ancho, $alto)
            : null;

        if ($rutaOverlayLogo) {
            $rutasTemp[] = $rutaOverlayLogo;
            $inicioLogo = max(0, $duracion - 2.3);
            $args = array_merge($args, ['-loop', '1', '-t', (string) $duracion, '-i', $rutaOverlayLogo]);
            $filtrosCapas[] = "[{$entradaIdx}:v]format=rgba,fade=in:st={$inicioLogo}:d=0.4:alpha=1[logo]";
            $filtrosCapas[] = "{$mapaOverlay}[logo]overlay=0:0:enable='gte(t,{$inicioLogo})'[vout]";
            $mapaOverlay = '[vout]';
            $entradaIdx++;
        } elseif (!empty($frases)) {
            // Sin logo pero con texto: renombrar la última salida del encadenado a [vout].
            $filtrosCapas[] = "{$mapaOverlay}null[vout]";
            $mapaOverlay = '[vout]';
        } else {
            $filtrosCapas[] = '[0:v]null[vout]';
            $mapaOverlay = '[vout]';
        }

        $filtroCompleto = implode(';', array_filter($filtrosCapas));

        $args = array_merge($args, [
            '-filter_complex', $filtroCompleto,
            '-map', $mapaOverlay,
            '-map', '0:a?',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '20',
            '-c:a', 'aac', '-b:a', '128k',
            $destinoVideoAbsoluto,
        ]);

        $resultado = Process::timeout(300)->run($args);

        foreach ($rutasTemp as $ruta) {
            if (is_file($ruta)) @unlink($ruta);
        }

        if (!$resultado->successful()) {
            return ['ok' => false, 'videoPath' => null, 'posterPath' => null, 'error' => 'FFmpeg falló al componer el video: ' . mb_substr($resultado->errorOutput(), -500)];
        }

        $poster = Process::timeout(30)->run([$binario, '-y', '-i', $destinoVideoAbsoluto, '-vframes', '1', '-q:v', '3', $destinoPosterAbsoluto]);
        if (!$poster->successful() || !is_file($destinoPosterAbsoluto)) {
            return ['ok' => true, 'videoPath' => $destinoVideoAbsoluto, 'posterPath' => null, 'error' => null];
        }

        return ['ok' => true, 'videoPath' => $destinoVideoAbsoluto, 'posterPath' => $destinoPosterAbsoluto, 'error' => null];
    }

    /**
     * Une varios clips CRUDOS (mismo formato, ej. varias escenas de Veo) en un solo video, en
     * orden — para armar anuncios de más de una escena con un corte simple entre ellas, en vez
     * de la extensión de Veo (que solo es un plano continuo, más cara, y solo en Standard).
     * Re-codifica en vez de copiar el stream, para no depender de que los clips de entrada
     * compartan exactamente los mismos parámetros de códec.
     *
     * @param string[] $rutasClipsAbsolutas En el orden en que deben quedar unidos.
     * @return array{ok: bool, error: ?string}
     */
    public static function concatenar(array $rutasClipsAbsolutas, string $destinoAbsoluto): array
    {
        $binario = config('services.ffmpeg.binario', 'ffmpeg');

        $listaTemp = sys_get_temp_dir() . '/' . Str::random(20) . '_lista_concat.txt';
        $lineas = array_map(fn (string $ruta) => "file '" . str_replace("'", "'\\''", $ruta) . "'", $rutasClipsAbsolutas);
        file_put_contents($listaTemp, implode("\n", $lineas));

        $resultado = Process::timeout(180)->run([
            $binario, '-y', '-f', 'concat', '-safe', '0', '-i', $listaTemp,
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '20',
            '-c:a', 'aac', '-b:a', '128k',
            $destinoAbsoluto,
        ]);

        @unlink($listaTemp);

        if (!$resultado->successful()) {
            return ['ok' => false, 'error' => 'FFmpeg falló al unir los clips: ' . mb_substr($resultado->errorOutput(), -500)];
        }

        return ['ok' => true, 'error' => null];
    }

    /** @return array{0:int,1:int,2:float}|null [ancho, alto, duracion_seg] */
    private static function obtenerDimensiones(string $ffprobe, string $rutaVideo): ?array
    {
        $resultado = Process::timeout(30)->run([
            $ffprobe, '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height:format=duration',
            '-of', 'csv=p=0:s=,',
            $rutaVideo,
        ]);

        if (!$resultado->successful()) {
            return null;
        }

        // Salida típica en dos líneas: "720,1280" (stream) y "8.033000" (format).
        $lineas = array_filter(array_map('trim', explode("\n", trim($resultado->output()))));
        $ancho = $alto = null;
        $duracion = null;
        foreach ($lineas as $linea) {
            if (str_contains($linea, ',') && preg_match('/^(\d+),(\d+)$/', $linea, $m)) {
                $ancho = (int) $m[1];
                $alto  = (int) $m[2];
            } elseif (is_numeric($linea)) {
                $duracion = (float) $linea;
            }
        }

        if (!$ancho || !$alto || !$duracion) {
            return null;
        }

        return [$ancho, $alto, $duracion];
    }

    /**
     * Renderiza UNA frase como PNG transparente del tamaño del video: pastilla redondeada en
     * el color de marca + texto blanco encima, centrada horizontalmente en el tercio inferior
     * (fuera de la esquina donde cae el círculo del logo). Se dibuja todo a 2x y se reduce con
     * `imagecopyresampled` — supersampling — para que las esquinas de la pastilla y el texto
     * salgan suavizados (GD no antialiasa `imagefilledellipse`/`imagettftext` a tamaño normal).
     */
    private static function generarPngTexto(string $frase, int $ancho, int $alto, ?string $colorPrimario): string
    {
        $factor = 2;
        $anchoG = $ancho * $factor;
        $altoG  = $alto * $factor;

        $lienzo = imagecreatetruecolor($anchoG, $altoG);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefilledrectangle($lienzo, 0, 0, $anchoG, $altoG, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
        imagealphablending($lienzo, true);

        $rutaFuente = base_path(self::FUENTE);
        $tamFuente  = max(22, (int) round($altoG * 0.034));
        $anchoUtil  = (int) round($anchoG * 0.78);
        $lineas     = self::envolver($frase, $anchoUtil, $tamFuente, $rutaFuente);
        $alturaLinea = (int) round($tamFuente * 1.3);

        // Ancho real de la pastilla = el de la línea más larga.
        $anchoMaxLinea = 0;
        foreach ($lineas as $linea) {
            $caja = imagettfbbox($tamFuente, 0, $rutaFuente, $linea);
            $anchoMaxLinea = max($anchoMaxLinea, $caja[2] - $caja[0]);
        }

        $padX = (int) round($tamFuente * 0.85);
        $padY = (int) round($tamFuente * 0.55);
        $wPastilla = $anchoMaxLinea + $padX * 2;
        $hPastilla = count($lineas) * $alturaLinea + $padY * 2 - (int) round($alturaLinea - $tamFuente);
        $xPastilla = (int) round(($anchoG - $wPastilla) / 2);
        // Tercio inferior — en un plano medio/cercano la cara suele estar en el tercio superior,
        // así el texto no la tapa. No hace falta esquivar el logo por posición: como el texto ya
        // se corta ~2.6s antes de que el logo aparezca (ver $duracionTextos en aplicar()), nunca
        // coinciden en el tiempo aunque compartan la misma esquina de la pantalla.
        $yPastilla = (int) round($altoG * 0.74);

        [$r, $g, $b] = self::hexARgb($colorPrimario ?: '#2563eb');

        // Pastilla con bordes difuminados (glow, no un borde duro) + sombra suave debajo — se
        // dibuja en un canvas aparte con margen para que el blur tenga espacio hacia donde
        // esparcirse (si no, se corta seco contra el borde del lienzo temporal).
        $margenBlur = (int) round($tamFuente * 1.1);
        $wTemp = $wPastilla + $margenBlur * 2;
        $hTemp = $hPastilla + $margenBlur * 2;
        $temp = imagecreatetruecolor($wTemp, $hTemp);
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        imagefilledrectangle($temp, 0, 0, $wTemp, $hTemp, imagecolorallocatealpha($temp, 0, 0, 0, 127));
        imagealphablending($temp, true);

        $colorSombra = imagecolorallocatealpha($temp, 0, 0, 0, 95);
        self::pastillaRedondeada($temp, $margenBlur, (int) round($margenBlur + $tamFuente * 0.15), $wPastilla, $hPastilla, (int) round($hPastilla / 2), $colorSombra);
        for ($i = 0; $i < 3; $i++) {
            imagefilter($temp, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $colorFondo = imagecolorallocatealpha($temp, $r, $g, $b, 10);
        self::pastillaRedondeada($temp, $margenBlur, $margenBlur, $wPastilla, $hPastilla, (int) round($hPastilla / 2), $colorFondo);
        for ($i = 0; $i < 2; $i++) {
            imagefilter($temp, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($lienzo, $temp, $xPastilla - $margenBlur, $yPastilla - $margenBlur, 0, 0, $wTemp, $hTemp);
        imagedestroy($temp);

        // Texto NÍTIDO encima, sin blur.
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $yTexto = $yPastilla + $padY + $tamFuente;
        foreach ($lineas as $i => $linea) {
            $caja = imagettfbbox($tamFuente, 0, $rutaFuente, $linea);
            $anchoLinea = $caja[2] - $caja[0];
            $x = (int) round(($anchoG - $anchoLinea) / 2);
            $y = $yTexto + $i * $alturaLinea;
            imagettftext($lienzo, $tamFuente, 0, $x, $y, $blanco, $rutaFuente, $linea);
        }

        $final = imagecreatetruecolor($ancho, $alto);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        imagefilledrectangle($final, 0, 0, $ancho, $alto, imagecolorallocatealpha($final, 0, 0, 0, 127));
        imagecopyresampled($final, $lienzo, 0, 0, 0, 0, $ancho, $alto, $anchoG, $altoG);
        imagedestroy($lienzo);

        $rutaTemp = sys_get_temp_dir() . '/' . Str::random(20) . '_texto_video.png';
        imagepng($final, $rutaTemp);
        imagedestroy($final);

        return $rutaTemp;
    }

    /** Rectángulo con esquinas totalmente redondas (píldora) — mismo patrón que FlyerPlanBuilder::rectRedondeado. */
    private static function pastillaRedondeada($lienzo, int $x, int $y, int $w, int $h, int $r, $color): void
    {
        $r = min($r, (int) round($h / 2));
        imagefilledrectangle($lienzo, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($lienzo, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        $d = $r * 2;
        imagefilledellipse($lienzo, $x + $r, $y + $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $w - $r, $y + $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $r, $y + $h - $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $w - $r, $y + $h - $r, $d, $d, $color);
    }

    private static function hexARgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** Reparte el texto en líneas que quepan en $anchoMax, midiendo con la misma fuente/tamaño reales. */
    private static function envolver(string $texto, int $anchoMax, int $tamFuente, string $rutaFuente): array
    {
        $palabras = explode(' ', $texto);
        $lineas = [];
        $actual = '';

        foreach ($palabras as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            $caja = imagettfbbox($tamFuente, 0, $rutaFuente, $prueba);
            $ancho = $caja[2] - $caja[0];

            if ($ancho > $anchoMax && $actual !== '') {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas ?: [$texto];
    }
}
