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
    private const FUENTE        = 'resources/fonts/Poppins-Bold.ttf';
    private const FUENTE_MEDIA  = 'resources/fonts/Poppins-Medium.ttf';
    private const FUENTE_SEMI   = 'resources/fonts/Poppins-SemiBold.ttf';
    private const FUENTE_SCRIPT = 'resources/fonts/KaushanScript-Regular.ttf';
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
                $f = 0.22 + 0.82 * $d;

                // Resplandor radial detrás del logo.
                $dist = sqrt((($x - $cx) ** 2) + (($y - $cy) ** 2));
                if ($dist < $radio) {
                    $f += 0.62 * (1 - $dist / $radio) ** 2;
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

    /**
     * Bloque de confianza. La jerarquía es lo que hace que se vea diseñado y no escrito:
     * una etiqueta diminuta con las letras muy separadas, el número enorme con las letras
     * apretadas, una línea fina que ancla, y la promesa en un peso más liviano. El contraste
     * fuerte entre tamaños y entre trackings es justo lo que le falta a un texto "de Word",
     * donde todo va del mismo tamaño y con el espaciado que venga por defecto.
     */
    private static function pintarBloqueConfianza(Aliado $aliado, int $anios, string $promesa, string $destino): void
    {
        $img = self::lienzoTransparente();
        $cx  = (int) (self::ANCHO / 2);

        $blanco    = imagecolorallocate($img, 255, 255, 255);
        $tenue     = imagecolorallocatealpha($img, 255, 255, 255, 18);
        $linea     = imagecolorallocatealpha($img, 255, 255, 255, 60);
        $doradoAlt = imagecolorallocate($img, 255, 226, 140);
        $doradoBaj = imagecolorallocate($img, 214, 158, 46);

        // Etiqueta pequeñísima, muy abierta: el recurso clásico para que se lea "diseñado".
        self::centrado($img, 'MÁS DE', $cx, (int) (self::ALTO * 0.545), 22, $tenue, self::FUENTE_MEDIA, 9.0);

        // El número: enorme y con tracking negativo. Se dibuja en dos capas de dorado
        // (una más oscura desplazada abajo) para que tenga volumen sin necesidad de un
        // degradado real, que GD haría lento a este tamaño.
        $y = (int) (self::ALTO * 0.635);
        self::sombraMultiple($img, "{$anios} AÑOS", $cx, $y, 104, self::FUENTE, -3.0);
        self::centrado($img, "{$anios} AÑOS", $cx, $y + 4, 104, $doradoBaj, self::FUENTE, -3.0);
        self::centrado($img, "{$anios} AÑOS", $cx, $y, 104, $doradoAlt, self::FUENTE, -3.0);

        // Línea fina como ancla entre el número y la promesa.
        $anchoLinea = 90;
        imagefilledrectangle($img, $cx - $anchoLinea, $y + 26, $cx + $anchoLinea, $y + 28, $linea);

        // La promesa, peso liviano y letras abiertas: contrasta con el número y respira.
        foreach (self::partirEnLineas($promesa, 30) as $i => $linea) {
            self::centrado($img, $linea, $cx, $y + 68 + $i * 34, 21, $blanco, self::FUENTE_MEDIA, 3.4);
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /** Llamado a la acción en cápsula + WhatsApp, con una línea manuscrita que humaniza. */
    private static function pintarLlamado(Aliado $aliado, string $destino): void
    {
        $img = self::lienzoTransparente();
        $cx  = (int) (self::ANCHO / 2);
        $blanco = imagecolorallocate($img, 255, 255, 255);

        $wa = \App\Models\WhatsappConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
        $numero = $wa?->numero_telefono ? preg_replace('/\D/', '', $wa->numero_telefono) : null;
        if ($numero && str_starts_with($numero, '57')) {
            $numero = substr($numero, 2);
        }

        // La línea manuscrita rompe la rigidez de todo-mayúsculas y da calidez — es el mismo
        // recurso que ya usan los flyers para la frase emocional.
        self::centrado($img, 'Los mejores asesores', $cx, (int) (self::ALTO * 0.762), 40,
            imagecolorallocate($img, 255, 226, 140), self::FUENTE_SCRIPT, 0);

        // Cápsula blanca: el CTA tiene que saltar sobre el fondo de color.
        $yCta = (int) (self::ALTO * 0.845);
        $texto = 'ESCRÍBENOS YA';
        $anchoTxt = self::anchoTexto($texto, 38, self::FUENTE, 2.0);
        $padX = 52;
        $x0 = $cx - (int) ($anchoTxt / 2) - $padX;
        $x1 = $cx + (int) ($anchoTxt / 2) + $padX;
        self::capsula($img, $x0, $yCta - 48, $x1, $yCta + 18, $blanco);

        $hex = ltrim($aliado->color_primario ?: '#1e3a8a', '#');
        $colorMarca = imagecolorallocate(
            $img,
            (int) (hexdec(substr($hex, 0, 2)) * 0.72),
            (int) (hexdec(substr($hex, 2, 2)) * 0.72),
            (int) (hexdec(substr($hex, 4, 2)) * 0.72)
        );
        self::centrado($img, $texto, $cx, $yCta, 38, $colorMarca, self::FUENTE, 2.0);

        if ($numero) {
            // Agrupado (320 540 0870): un número corrido de 10 dígitos no se retiene de una
            // pasada, y este cierre dura segundos.
            $legible = trim(preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1 $2 $3', $numero));
            self::sombraMultiple($img, $legible, $cx, $yCta + 84, 34, self::FUENTE_SEMI, 3.0);
            self::centrado($img, $legible, $cx, $yCta + 84, 34, $blanco, self::FUENTE_SEMI, 3.0);
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

    /**
     * Dibuja letra por letra para poder aplicar tracking. GD no tiene espaciado entre
     * caracteres: `imagettftext` escupe la cadena con el kerning por defecto de la fuente, y
     * eso es exactamente lo que hace que un texto se vea "escrito" y no "compuesto". Misma
     * técnica que FlyerPlanBuilder::texto().
     */
    private static function texto($img, string $texto, int $x, int $y, int $tam, int $color, string $fuente, float $tracking = 0): void
    {
        $ruta = base_path($fuente);

        if (abs($tracking) < 0.01) {
            imagettftext($img, $tam, 0, $x, $y, $color, $ruta, $texto);
            return;
        }

        $cursor = (float) $x;
        foreach (preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $letra) {
            imagettftext($img, $tam, 0, (int) round($cursor), $y, $color, $ruta, $letra);
            $caja = imagettfbbox($tam, 0, $ruta, $letra);
            $cursor += ($caja[2] - $caja[0]) + $tracking;
        }
    }

    private static function centrado($img, string $texto, int $cx, int $y, int $tam, int $color, string $fuente, float $tracking = 0): void
    {
        $ancho = self::anchoTexto($texto, $tam, $fuente, $tracking);
        self::texto($img, $texto, (int) round($cx - $ancho / 2), $y, $tam, $color, $fuente, $tracking);
    }

    /** Ancho real contando el tracking con el que se va a dibujar. */
    private static function anchoTexto(string $texto, int $tam, string $fuente, float $tracking = 0): int
    {
        $ruta = base_path($fuente);

        if (abs($tracking) < 0.01) {
            $caja = imagettfbbox($tam, 0, $ruta, $texto);
            return (int) abs($caja[2] - $caja[0]);
        }

        $ancho = 0.0;
        foreach (preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $letra) {
            $caja = imagettfbbox($tam, 0, $ruta, $letra);
            $ancho += ($caja[2] - $caja[0]) + $tracking;
        }

        return (int) round($ancho - $tracking);
    }

    /** Sombra en varias direcciones: despega el texto del fondo sin verse como un borde duro. */
    private static function sombraMultiple($img, string $texto, int $cx, int $y, int $tam, string $fuente, float $tracking = 0): void
    {
        $sombra = imagecolorallocatealpha($img, 0, 0, 0, 92);
        foreach ([[2, 3], [-2, 3], [3, 4], [0, 5]] as [$dx, $dy]) {
            $ancho = self::anchoTexto($texto, $tam, $fuente, $tracking);
            self::texto($img, $texto, (int) round($cx - $ancho / 2) + $dx, $y + $dy, $tam, $sombra, $fuente, $tracking);
        }
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
