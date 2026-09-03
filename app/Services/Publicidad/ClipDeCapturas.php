<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Convierte capturas de pantalla en una escena de video vertical.
 *
 * Existe porque Veo no sabe escribir texto legible: pedirle "una pantalla de software"
 * devuelve letras inventadas que cualquiera con experiencia detecta como falsas. Para
 * convencer a un asesor de que la plataforma es real hay que mostrarla de verdad.
 *
 * Las capturas son apaisadas y el Reel es vertical. Encajarlas enteras dentro del cuadro
 * las deja en una franja del alto de un dedo, ilegible en un teléfono; por eso se escalan a
 * TODO el alto y se recorta una columna, revelando el resto con un paneo lento. Así el texto
 * de la interfaz se lee y además hay movimiento, que es lo que evita que el espectador
 * sienta que el video se congeló y deslice.
 */
class ClipDeCapturas
{
    private const ANCHO = 720;

    private const ALTO = 1280;

    private const FPS = 30;

    /** Cuánto dura el cruce entre una captura y la siguiente. */
    private const CRUCE = 0.5;

    /**
     * @param  array<int, string|array{archivo: string, desde?: float, hasta?: float, zoom?: float}>  $capturas
     *                                                                                                           Ruta de la imagen, o la ruta más el tramo del paneo (`desde`/`hasta`, fracción
     *                                                                                                           del ancho sobrante), el `zoom` sobre el alto del cuadro y `arriba` (0 pega el
     *                                                                                                           recorte al borde superior, 1 al inferior).
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    public static function generar(array $capturas, float $segundos, string $destinoAbsoluto): array
    {
        $capturas = array_values(array_filter(array_map(
            fn ($c) => is_array($c) ? $c : ['archivo' => $c],
            $capturas
        ), fn ($c) => is_file($c['archivo'])));

        if (empty($capturas)) {
            return ['ok' => false, 'path' => null, 'error' => 'No se encontró ninguna captura en disco.'];
        }

        $n = count($capturas);
        // Los cruces se comen tiempo: sin compensarlos el clip sale más corto que lo pedido y
        // la narración se desalinea con las escenas siguientes.
        $porImagen = ($segundos + ($n - 1) * self::CRUCE) / $n;

        $binario = config('services.ffmpeg.binario', 'ffmpeg');
        $args = [$binario, '-y'];
        foreach ($capturas as $c) {
            array_push($args, '-loop', '1', '-t', (string) round($porImagen, 3), '-i', $c['archivo']);
        }

        $filtros = [];
        foreach ($capturas as $i => $c) {
            $zoom = (float) ($c['zoom'] ?? 1.0);
            $desde = (float) ($c['desde'] ?? 0.12);
            $hasta = (float) ($c['hasta'] ?? 0.42);
            $alto = (int) round(self::ALTO * $zoom);
            // Los paneles tienen el contenido arriba y aire abajo: anclar el recorte al borde
            // superior evita regalarle medio cuadro a fondo vacío.
            $arriba = (float) ($c['arriba'] ?? 0.5);

            // El recorte se mueve con `t`: de `desde` a `hasta` del ancho que sobra. Si la
            // captura no sobresale (imagen angosta), max(...) deja el recorte quieto en 0 en
            // vez de pedirle a FFmpeg una x negativa, que aborta el render.
            $filtros[] = "[{$i}:v]scale=-2:{$alto},"
                .'crop='.self::ANCHO.':'.self::ALTO
                .":x='max(0,(in_w-out_w)*(".$desde.'+('.($hasta - $desde).")*min(t/{$porImagen},1)))':y=(in_h-out_h)*{$arriba},"
                .'fps='.self::FPS.',setsar=1,format=yuv420p[v'.$i.']';
        }

        if ($n === 1) {
            $filtros[] = '[v0]null[vout]';
        } else {
            $prev = 'v0';
            $acumulado = $porImagen;
            for ($i = 1; $i < $n; $i++) {
                $salida = $i === $n - 1 ? 'vout' : "x{$i}";
                $offset = round(max(0.1, $acumulado - self::CRUCE), 3);
                $filtros[] = "[{$prev}][v{$i}]xfade=transition=fade:duration=".self::CRUCE.":offset={$offset}[{$salida}]";
                $prev = $salida;
                $acumulado += $porImagen - self::CRUCE;
            }
        }

        $args = array_merge($args, [
            '-filter_complex', implode(';', $filtros),
            '-map', '[vout]',
            '-t', (string) round($segundos, 3),
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '20',
            '-r', (string) self::FPS,
            $destinoAbsoluto,
        ]);

        $r = Process::timeout(300)->run($args);

        if (! $r->successful() || ! is_file($destinoAbsoluto)) {
            return ['ok' => false, 'path' => null, 'error' => 'FFmpeg no pudo armar el clip de capturas: '.mb_substr($r->errorOutput(), -300)];
        }

        return ['ok' => true, 'path' => $destinoAbsoluto, 'error' => null];
    }

    /** Ruta temporal para el clip, con nombre irrepetible. */
    public static function rutaTemporal(): string
    {
        return sys_get_temp_dir().'/capturas_'.Str::random(12).'.mp4';
    }
}
