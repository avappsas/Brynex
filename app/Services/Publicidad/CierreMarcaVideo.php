<?php

namespace App\Services\Publicidad;

use App\Models\Aliado;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genera el cierre de marca que se pega al final de cada Reel: fondo con el color del aliado,
 * su logo REAL animado (entra escalando con un vaivén suave que da sensación de volumen) y el
 * llamado a la acción.
 *
 * Se arma con FFmpeg y no con Veo por dos razones: un modelo de video no puede reproducir el
 * logo real ni escribir texto legible —por eso existen LogoWatermarker y esta clase—, y
 * además el cierre es SIEMPRE el mismo, así que generarlo con IA cada vez sería pagar USD 0,40
 * por un clip idéntico. Se genera una vez, se cachea y se reutiliza.
 *
 * DURACIÓN: el default son 3 segundos a propósito. En Reels el watch-through rate es la señal
 * que más pesa para que Meta reparta el video; una cola de marca larga hace que la gente
 * deslice antes del final y el alcance se desploma. El estándar en formato corto es 2-3s.
 * Se puede subir, pero conviene saber lo que cuesta.
 *
 * El texto se rasteriza con GD (imagettftext) igual que en VideoOverlayFfmpeg, porque el
 * `drawtext` de FFmpeg depende de libfreetype y no está garantizado en estos builds.
 */
class CierreMarcaVideo
{
    private const FUENTE = 'resources/fonts/Poppins-Bold.ttf';
    private const ANCHO  = 720;
    private const ALTO   = 1280;

    /**
     * Devuelve la ruta absoluta del cierre listo, generándolo si hace falta.
     *
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    public static function obtener(Aliado $aliado, float $segundos = 3.0, bool $regenerar = false): array
    {
        $rutaRelativa = "publicidad/cierres/cierre_{$aliado->id}_" . str_replace('.', '_', (string) $segundos) . '.mp4';
        $rutaAbsoluta = Storage::disk('public')->path($rutaRelativa);

        if (!$regenerar && Storage::disk('public')->exists($rutaRelativa)) {
            return ['ok' => true, 'path' => $rutaAbsoluta, 'error' => null];
        }

        Storage::disk('public')->makeDirectory('publicidad/cierres');

        try {
            return self::construir($aliado, $segundos, $rutaAbsoluta);
        } catch (\Throwable $e) {
            return ['ok' => false, 'path' => null, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, path: ?string, error: ?string} */
    private static function construir(Aliado $aliado, float $segundos, string $destino): array
    {
        $tmp = sys_get_temp_dir();
        $id  = Str::random(10);

        $fondoPng = "{$tmp}/cierre_fondo_{$id}.png";
        $textoPng = "{$tmp}/cierre_texto_{$id}.png";

        self::pintarFondo($aliado, $fondoPng);
        self::pintarTexto($aliado, $textoPng);

        // El logo del aliado: se prefiere la versión para fondo claro/oscuro ya recortada.
        $logoRel = $aliado->logo_marca_claro ?: $aliado->logo;
        if (!$logoRel || !Storage::disk('public')->exists($logoRel)) {
            return ['ok' => false, 'path' => null, 'error' => 'El aliado no tiene logo cargado.'];
        }
        $logoAbs = Storage::disk('public')->path($logoRel);

        $ffmpeg = config('services.ffmpeg.bin', 'ffmpeg');

        // Animación del logo:
        //  · escala de 0.82 a 1.0 con un rebote suave que se estabiliza (sensación de volumen)
        //  · vaivén de rotación de ±3° que decae con el tiempo
        //  · fade de entrada
        // El texto entra medio segundo después, para que el ojo lea primero la marca.
        $anchoLogo = (int) round(self::ANCHO * 0.52);
        $filtro = implode(';', [
            "[1:v]format=rgba,scale={$anchoLogo}:-1[logobase]",
            "[logobase]rotate='0.052*sin(3.6*t)*exp(-1.6*t)':c=none:ow=rotw(iw):oh=roth(ih)[logorot]",
            "[logorot]scale=w='iw*(0.82+0.18*(1-exp(-4*t)))':h=-1:eval=frame[logoanim]",
            "[logoanim]fade=in:st=0:d=0.35:alpha=1[logofade]",
            "[0:v][logofade]overlay=(W-w)/2:(H-h)/2-120:eval=frame[conlogo]",
            "[2:v]format=rgba,fade=in:st=0.5:d=0.4:alpha=1[txt]",
            "[conlogo][txt]overlay=0:0[out]",
        ]);

        $resultado = Process::timeout(120)->run([
            $ffmpeg, '-y',
            '-loop', '1', '-t', (string) $segundos, '-i', $fondoPng,
            '-loop', '1', '-t', (string) $segundos, '-i', $logoAbs,
            '-loop', '1', '-t', (string) $segundos, '-i', $textoPng,
            // Pista de silencio: sin audio, concat con un clip que sí lo trae falla o desincroniza.
            '-f', 'lavfi', '-t', (string) $segundos, '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
            '-filter_complex', $filtro,
            '-map', '[out]', '-map', '3:a',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30',
            '-c:a', 'aac', '-shortest',
            $destino,
        ]);

        @unlink($fondoPng);
        @unlink($textoPng);

        if (!$resultado->successful()) {
            return ['ok' => false, 'path' => null, 'error' => 'FFmpeg: ' . Str::limit($resultado->errorOutput(), 400)];
        }

        return ['ok' => true, 'path' => $destino, 'error' => null];
    }

    /** Degradado vertical del color de la marca, con viñeta suave. */
    private static function pintarFondo(Aliado $aliado, string $destino): void
    {
        $hex = ltrim($aliado->color_primario ?: '#1e3a8a', '#');
        [$r, $g, $b] = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        $img = imagecreatetruecolor(self::ANCHO, self::ALTO);

        for ($y = 0; $y < self::ALTO; $y++) {
            // De un 55% de luminosidad arriba a un 100% del color abajo: da profundidad sin
            // competir con el logo, que va centrado.
            $f = 0.55 + 0.45 * ($y / self::ALTO);
            $color = imagecolorallocate(
                $img,
                (int) min(255, $r * $f),
                (int) min(255, $g * $f),
                (int) min(255, $b * $f)
            );
            imageline($img, 0, $y, self::ANCHO, $y, $color);
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /** Llamado a la acción + número de WhatsApp, centrados bajo el logo. */
    private static function pintarTexto(Aliado $aliado, string $destino): void
    {
        $img = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, self::ANCHO, self::ALTO, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        $fuente = base_path(self::FUENTE);
        $blanco = imagecolorallocate($img, 255, 255, 255);

        $wa = \App\Models\WhatsappConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
        $numero = $wa?->numero_telefono ? preg_replace('/\D/', '', $wa->numero_telefono) : null;
        if ($numero && str_starts_with($numero, '57')) {
            $numero = substr($numero, 2);
        }

        $lineas = [
            ['texto' => 'AFÍLIATE YA CON', 'tam' => 38, 'y' => (int) (self::ALTO * 0.60)],
            ['texto' => mb_strtoupper($aliado->nombre), 'tam' => 62, 'y' => (int) (self::ALTO * 0.665)],
        ];
        if ($numero) {
            $lineas[] = ['texto' => 'WhatsApp ' . $numero, 'tam' => 34, 'y' => (int) (self::ALTO * 0.745)];
        }

        foreach ($lineas as $l) {
            $caja = imagettfbbox($l['tam'], 0, $fuente, $l['texto']);
            $ancho = abs($caja[4] - $caja[0]);
            $x = (int) ((self::ANCHO - $ancho) / 2);
            // Sombra suave para que se lea sobre cualquier tono del degradado.
            imagettftext($img, $l['tam'], 0, $x + 2, $l['y'] + 2, imagecolorallocatealpha($img, 0, 0, 0, 70), $fuente, $l['texto']);
            imagettftext($img, $l['tam'], 0, $x, $l['y'], $blanco, $fuente, $l['texto']);
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }
}
