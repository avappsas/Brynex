<?php

namespace App\Services\Publicidad;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Convierte capturas de pantalla en una escena de video.
 *
 * Existe porque Veo no sabe escribir texto legible: pedirle "una pantalla de software"
 * devuelve letras inventadas que cualquiera con experiencia detecta como falsas. Para
 * convencer a un asesor de que la plataforma es real hay que mostrarla de verdad.
 *
 * Cada captura entra con un zoom lento (efecto Ken Burns) y cruza con la siguiente. El
 * movimiento no es decoración: una imagen fija dentro de un video se siente como un error de
 * reproducción, y en Reels la gente desliza.
 */
class ClipDeCapturas
{
    private const ANCHO = 720;

    private const ALTO = 1280;

    /**
     * @param  string[]  $rutasImagenes  Capturas en el orden en que deben aparecer.
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    public static function generar(array $rutasImagenes, float $segundos, string $destinoAbsoluto): array
    {
        $rutasImagenes = array_values(array_filter($rutasImagenes, 'is_file'));
        if (empty($rutasImagenes)) {
            return ['ok' => false, 'path' => null, 'error' => 'No se encontró ninguna captura en disco.'];
        }

        $binario = config('services.ffmpeg.binario', 'ffmpeg');
        $porImagen = $segundos / count($rutasImagenes);
        $fps = 30;
        $cuadros = max(2, (int) round($porImagen * $fps));

        $args = [$binario, '-y'];
        foreach ($rutasImagenes as $ruta) {
            $args[] = '-loop';
            $args[] = '1';
            $args[] = '-t';
            $args[] = (string) round($porImagen, 3);
            $args[] = '-i';
            $args[] = $ruta;
        }

        $filtros = [];
        foreach ($rutasImagenes as $i => $_) {
            // Las capturas son apaisadas y el Reel es vertical: se ajustan al ancho y se
            // centran sobre un fondo del color de marca en vez de recortarlas, que cortaría
            // justo la parte que queremos que se vea.
            $filtros[] = "[{$i}:v]scale=".self::ANCHO.':-2:force_original_aspect_ratio=decrease,'
                .'pad='.self::ANCHO.':'.self::ALTO.':(ow-iw)/2:(oh-ih)/2:color=0x0f172a,'
                ."zoompan=z='min(zoom+0.0008,1.12)':d={$cuadros}:s=".self::ANCHO.'x'.self::ALTO.":fps={$fps},"
                .'setsar=1[v'.$i.']';
        }

        if (count($rutasImagenes) === 1) {
            $filtros[] = '[v0]null[vout]';
        } else {
            // Cruce suave entre capturas: el corte seco entre pantallas se siente a
            // presentación de diapositivas, no a producto en movimiento.
            $prev = 'v0';
            $acumulado = $porImagen;
            for ($i = 1; $i < count($rutasImagenes); $i++) {
                $salida = $i === count($rutasImagenes) - 1 ? 'vout' : "x{$i}";
                $offset = round(max(0.1, $acumulado - 0.4), 3);
                $filtros[] = "[{$prev}][v{$i}]xfade=transition=fade:duration=0.4:offset={$offset}[{$salida}]";
                $prev = $salida;
                $acumulado += $porImagen - 0.4;
            }
        }

        $args = array_merge($args, [
            '-filter_complex', implode(';', $filtros),
            '-map', '[vout]',
            '-t', (string) round($segundos, 3),
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '20',
            '-r', (string) $fps,
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
