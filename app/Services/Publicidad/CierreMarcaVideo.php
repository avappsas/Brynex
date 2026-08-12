<?php

namespace App\Services\Publicidad;

use App\Models\Aliado;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cierre de marca que se pega al final de cada Reel.
 *
 * Se arma con FFmpeg + GD y no con Veo por dos razones: un modelo de video no puede
 * reproducir el logo real ni escribir texto legible —por eso ya existen LogoWatermarker y
 * VideoOverlayFfmpeg—, y el cierre es siempre idéntico, así que generarlo con IA cada vez
 * sería pagar USD 0,40 por un clip repetido. Se genera una vez y se cachea por aliado.
 *
 * ORDEN DE LOS MENSAJES (esto es lo que decide si genera confianza o se siente a relleno):
 *   1. El número de años, GRANDE y primero. La especificidad es lo que da credibilidad:
 *      "12 años" se puede verificar, "profesionales calificados" lo dice cualquiera y no
 *      significa nada. Además un número grande frena el scroll.
 *   2. Qué hace por quién, en lenguaje de la calle, no de folleto.
 *   3. El logo real, que es donde vive el respaldo visual de la marca.
 *   4. El llamado a la acción, al final, cuando ya se ganó la confianza.
 *
 * Nada de esto es estático: el fondo respira, el logo entra con volumen y le pasa un
 * destello encima. En un formato donde la gente desliza, lo que no se mueve se salta.
 */
class CierreMarcaVideo
{
    private const FUENTE = 'resources/fonts/Poppins-Bold.ttf';
    private const ANCHO  = 720;
    private const ALTO   = 1280;

    /**
     * @param int    $anios    Años de experiencia a mostrar. Va en publicidad: usar el real.
     * @param string $promesa  Frase de respaldo bajo el número.
     * @param float  $segundos Duración. 3-4s es lo sano en Reels (ver nota de retención abajo).
     *
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    public static function obtener(
        Aliado $aliado,
        int $anios = 12,
        string $promesa = 'RESPALDANDO A TRABAJADORES COLOMBIANOS',
        float $segundos = 4.0,
        bool $regenerar = false
    ): array {
        $firma = md5($anios . '|' . $promesa . '|' . $segundos . '|' . ($aliado->color_primario ?? ''));
        $rutaRelativa = "publicidad/cierres/cierre_{$aliado->id}_{$firma}.mp4";
        $rutaAbsoluta = Storage::disk('public')->path($rutaRelativa);

        if (!$regenerar && Storage::disk('public')->exists($rutaRelativa)) {
            return ['ok' => true, 'path' => $rutaAbsoluta, 'error' => null];
        }

        Storage::disk('public')->makeDirectory('publicidad/cierres');

        try {
            return self::construir($aliado, $anios, $promesa, $segundos, $rutaAbsoluta);
        } catch (\Throwable $e) {
            return ['ok' => false, 'path' => null, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, path: ?string, error: ?string} */
    private static function construir(Aliado $aliado, int $anios, string $promesa, float $segundos, string $destino): array
    {
        $tmp = sys_get_temp_dir();
        $id  = Str::random(10);

        $fondoPng  = "{$tmp}/cierre_fondo_{$id}.png";
        $texto1Png = "{$tmp}/cierre_t1_{$id}.png";   // años + promesa
        $texto2Png = "{$tmp}/cierre_t2_{$id}.png";   // CTA + WhatsApp
        $brilloPng = "{$tmp}/cierre_brillo_{$id}.png";

        self::pintarFondo($aliado, $fondoPng);
        self::pintarBloqueConfianza($aliado, $anios, $promesa, $texto1Png);
        self::pintarLlamado($aliado, $texto2Png);
        self::pintarBrillo($brilloPng);

        $logoRel = $aliado->logo_marca_claro ?: $aliado->logo;
        if (!$logoRel || !Storage::disk('public')->exists($logoRel)) {
            return ['ok' => false, 'path' => null, 'error' => 'El aliado no tiene logo cargado.'];
        }
        $logoAbs = Storage::disk('public')->path($logoRel);

        $ffmpeg = config('services.ffmpeg.bin', 'ffmpeg');
        $anchoLogo = (int) round(self::ANCHO * 0.46);

        // Tiempos de entrada, escalonados para que el ojo lea en orden y nunca haya un
        // momento sin movimiento.
        $tCta = round($segundos * 0.45, 2);

        $filtro = implode(';', [
            // Fondo con un zoom lentísimo: evita que la imagen se sienta congelada.
            "[0:v]scale=" . (self::ANCHO * 2) . ":-1,zoompan=z='1.02+0.03*on/(25*{$segundos})':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=" . self::ANCHO . 'x' . self::ALTO . ",setsar=1[bg]",

            // Logo: entra escalando con rebote amortiguado + vaivén de rotación que decae.
            "[1:v]format=rgba,scale={$anchoLogo}:-1[l0]",
            "[l0]rotate='0.055*sin(4.2*t)*exp(-1.8*t)':c=none:ow=rotw(iw):oh=roth(ih)[l1]",
            "[l1]scale=w='iw*(0.78+0.22*(1-exp(-5*t)))':h=-1:eval=frame[l2]",
            "[l2]fade=in:st=0:d=0.3:alpha=1[logo]",
            "[bg][logo]overlay=(W-w)/2:(H-h)/2-190:eval=frame[c1]",

            // Destello diagonal que barre el logo: da sensación de material, no de PNG pegado.
            "[3:v]format=rgba,fade=in:st=0.35:d=0.2:alpha=1,fade=out:st=1.05:d=0.3:alpha=1[br]",
            "[c1][br]overlay=x='-360+900*t':y=(H-h)/2-190:eval=frame[c2]",

            // Bloque de confianza (años + promesa).
            "[2:v]format=rgba,fade=in:st=0.45:d=0.45:alpha=1[t1]",
            "[c2][t1]overlay=0:0[c3]",

            // Llamado a la acción, al final.
            "[4:v]format=rgba,fade=in:st={$tCta}:d=0.4:alpha=1[t2]",
            "[c3][t2]overlay=0:0[out]",
        ]);

        $resultado = Process::timeout(180)->run([
            $ffmpeg, '-y',
            '-loop', '1', '-t', (string) $segundos, '-i', $fondoPng,
            '-loop', '1', '-t', (string) $segundos, '-i', $logoAbs,
            '-loop', '1', '-t', (string) $segundos, '-i', $texto1Png,
            '-loop', '1', '-t', (string) $segundos, '-i', $brilloPng,
            '-loop', '1', '-t', (string) $segundos, '-i', $texto2Png,
            // Silencio: concatenar con un clip que sí trae audio falla si a este le falta.
            '-f', 'lavfi', '-t', (string) $segundos, '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
            '-filter_complex', $filtro,
            '-map', '[out]', '-map', '5:a',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30',
            '-c:a', 'aac', '-shortest',
            $destino,
        ]);

        foreach ([$fondoPng, $texto1Png, $texto2Png, $brilloPng] as $f) {
            @unlink($f);
        }

        if (!$resultado->successful()) {
            return ['ok' => false, 'path' => null, 'error' => 'FFmpeg: ' . Str::limit($resultado->errorOutput(), 500)];
        }

        return ['ok' => true, 'path' => $destino, 'error' => null];
    }

    /**
     * Fondo: degradado diagonal profundo del color de marca + resplandor radial detrás de
     * donde va el logo, para que el logo "brille" en vez de estar pegado sobre un color plano.
     */
    private static function pintarFondo(Aliado $aliado, string $destino): void
    {
        $hex = ltrim($aliado->color_primario ?: '#1e3a8a', '#');
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        $w = self::ANCHO;
        $h = self::ALTO;
        $img = imagecreatetruecolor($w, $h);

        $cx = $w / 2;
        $cy = $h * 0.36;                 // donde queda el logo
        $radio = $h * 0.45;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x += 2) {
                // Base: degradado diagonal (más oscuro arriba-izquierda).
                $d = ($x / $w) * 0.35 + ($y / $h) * 0.65;
                $f = 0.30 + 0.75 * $d;

                // Resplandor radial detrás del logo.
                $dist = sqrt((($x - $cx) ** 2) + (($y - $cy) ** 2));
                if ($dist < $radio) {
                    $f += 0.42 * (1 - $dist / $radio) ** 2;
                }

                $color = imagecolorallocate(
                    $img,
                    (int) min(255, $r * $f + 12),
                    (int) min(255, $g * $f + 12),
                    (int) min(255, $b * $f + 20)
                );
                imagesetpixel($img, $x, $y, $color);
                imagesetpixel($img, $x + 1, $y, $color);
            }
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /** Franja diagonal translúcida que barre el logo. */
    private static function pintarBrillo(string $destino): void
    {
        $w = 220;
        $h = (int) (self::ALTO * 0.55);
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocatealpha($img, 0, 0, 0, 127));

        for ($x = 0; $x < $w; $x++) {
            // Pico en el centro de la franja, translúcido en los bordes.
            $intensidad = 1 - abs(($x - $w / 2) / ($w / 2));
            $alpha = (int) (127 - 68 * ($intensidad ** 2));
            $color = imagecolorallocatealpha($img, 255, 255, 255, $alpha);
            imageline($img, $x, 0, (int) ($x - $h * 0.30), $h, $color);
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /** Número de años (grande) + la promesa debajo. */
    private static function pintarBloqueConfianza(Aliado $aliado, int $anios, string $promesa, string $destino): void
    {
        $img = self::lienzoTransparente();
        $fuente = base_path(self::FUENTE);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $dorado = imagecolorallocate($img, 255, 214, 102);

        // El número, protagonista.
        self::centrar($img, $fuente, "+{$anios} AÑOS", 96, (int) (self::ALTO * 0.615), $dorado, true);
        // La promesa, en dos líneas si hace falta.
        foreach (self::partirEnLineas($promesa, 26) as $i => $linea) {
            self::centrar($img, $fuente, $linea, 30, (int) (self::ALTO * 0.665) + $i * 40, $blanco);
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /** Llamado a la acción + WhatsApp. */
    private static function pintarLlamado(Aliado $aliado, string $destino): void
    {
        $img = self::lienzoTransparente();
        $fuente = base_path(self::FUENTE);
        $blanco = imagecolorallocate($img, 255, 255, 255);

        $wa = \App\Models\WhatsappConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
        $numero = $wa?->numero_telefono ? preg_replace('/\D/', '', $wa->numero_telefono) : null;
        if ($numero && str_starts_with($numero, '57')) {
            $numero = substr($numero, 2);
        }

        // Cápsula clara detrás del CTA, para que salte sobre el fondo.
        $yCta = (int) (self::ALTO * 0.815);
        $texto = 'ESCRÍBENOS YA';
        $caja = imagettfbbox(40, 0, $fuente, $texto);
        $anchoTxt = abs($caja[4] - $caja[0]);
        $padX = 46;
        $x0 = (int) ((self::ANCHO - $anchoTxt) / 2) - $padX;
        $x1 = (int) ((self::ANCHO + $anchoTxt) / 2) + $padX;
        self::capsula($img, $x0, $yCta - 52, $x1, $yCta + 20, imagecolorallocate($img, 255, 255, 255));

        // Texto del CTA en el color de la marca, sobre la cápsula blanca.
        $hex = ltrim($aliado->color_primario ?: '#1e3a8a', '#');
        $colorMarca = imagecolorallocate(
            $img,
            (int) (hexdec(substr($hex, 0, 2)) * 0.75),
            (int) (hexdec(substr($hex, 2, 2)) * 0.75),
            (int) (hexdec(substr($hex, 4, 2)) * 0.75)
        );
        self::centrar($img, $fuente, $texto, 40, $yCta, $colorMarca);

        if ($numero) {
            self::centrar($img, $fuente, 'WhatsApp ' . $numero, 32, $yCta + 78, $blanco, true);
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    private static function lienzoTransparente()
    {
        $img = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, self::ANCHO, self::ALTO, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        return $img;
    }

    private static function centrar($img, string $fuente, string $texto, int $tam, int $y, int $color, bool $sombra = false): void
    {
        $caja = imagettfbbox($tam, 0, $fuente, $texto);
        $ancho = abs($caja[4] - $caja[0]);
        $x = (int) ((self::ANCHO - $ancho) / 2);

        if ($sombra) {
            imagettftext($img, $tam, 0, $x + 3, $y + 3, imagecolorallocatealpha($img, 0, 0, 0, 75), $fuente, $texto);
        }
        imagettftext($img, $tam, 0, $x, $y, $color, $fuente, $texto);
    }

    /** Rectángulo de esquinas redondeadas. */
    private static function capsula($img, int $x0, int $y0, int $x1, int $y1, int $color): void
    {
        $r = (int) (($y1 - $y0) / 2);
        imagefilledrectangle($img, $x0 + $r, $y0, $x1 - $r, $y1, $color);
        imagefilledellipse($img, $x0 + $r, (int) (($y0 + $y1) / 2), $r * 2, $y1 - $y0, $color);
        imagefilledellipse($img, $x1 - $r, (int) (($y0 + $y1) / 2), $r * 2, $y1 - $y0, $color);
    }

    /** @return string[] */
    private static function partirEnLineas(string $texto, int $max): array
    {
        $palabras = explode(' ', $texto);
        $lineas = [];
        $actual = '';

        foreach ($palabras as $p) {
            if (mb_strlen($actual . ' ' . $p) > $max && $actual !== '') {
                $lineas[] = $actual;
                $actual = $p;
                continue;
            }
            $actual = $actual === '' ? $p : $actual . ' ' . $p;
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas;
    }
}
