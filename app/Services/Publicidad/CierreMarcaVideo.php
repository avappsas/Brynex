<?php

namespace App\Services\Publicidad;

use App\Models\Aliado;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cierre de marca que se pega al final de cada Reel.
 *
 * Va sobre un CLIP DE VIDEO REAL de fondo (dos asesores en oficina, generado una sola vez
 * con Veo y cacheado) en vez de sobre un fondo plano de color. La diferencia no es estética:
 * una tarjeta de logo se siente "fin del contenido" y la gente desliza; caras humanas
 * sostienen la mirada y dan respaldo, que es justo lo que tiene que transmitir el cierre.
 *
 * El texto entra por MOMENTOS, no todo junto, y entre uno y otro pasa un destello que barre
 * la pantalla. Leer cuatro mensajes apilados cuesta trabajo; leerlos de a uno, con algo que
 * se mueve entremedio, no.
 *
 *   1. Los años de experiencia — la especificidad es lo que da credibilidad: un número se
 *      puede verificar, "profesionales calificados" lo dice cualquiera.
 *   2. El equipo — quién te va a atender.
 *   3. La cobertura — dónde están y hasta dónde llegan.
 *   4. El llamado a la acción.
 *
 * El WhatsApp queda fijo abajo TODO el tiempo: es el dato que tiene que sobrevivir aunque
 * la persona solo vea dos segundos.
 *
 * Todo el texto se rasteriza con GD dibujando letra por letra, porque GD no tiene tracking
 * y sin él la tipografía se ve "escrita" y no compuesta — misma técnica que FlyerPlanBuilder.
 */
class CierreMarcaVideo
{
    private const FUENTE        = 'resources/fonts/Poppins-Bold.ttf';
    private const FUENTE_MEDIA  = 'resources/fonts/Poppins-Medium.ttf';
    private const FUENTE_SEMI   = 'resources/fonts/Poppins-SemiBold.ttf';
    private const FUENTE_SCRIPT = 'resources/fonts/KaushanScript-Regular.ttf';

    private const ANCHO = 720;
    private const ALTO  = 1280;

    /** Fondo de video reutilizable, generado con Veo una sola vez por aliado. */
    private const FONDO_REL = 'publicidad/cierres/fondo_asesores_%d.mp4';

    /**
     * @param int    $anios   Años de experiencia. Va en publicidad: usar la cifra real.
     * @param string $ciudad  Ciudad sede.
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    public static function obtener(
        Aliado $aliado,
        int $anios = 12,
        string $ciudad = 'Cali',
        float $segundos = 8.0,
        bool $regenerar = false
    ): array {
        $firma = md5($anios . '|' . $ciudad . '|' . $segundos . '|' . ($aliado->color_primario ?? ''));
        $rutaRelativa = "publicidad/cierres/cierre_{$aliado->id}_{$firma}.mp4";
        $rutaAbsoluta = Storage::disk('public')->path($rutaRelativa);

        if (!$regenerar && Storage::disk('public')->exists($rutaRelativa)) {
            return ['ok' => true, 'path' => $rutaAbsoluta, 'error' => null];
        }

        Storage::disk('public')->makeDirectory('publicidad/cierres');

        try {
            return self::construir($aliado, $anios, $ciudad, $segundos, $rutaAbsoluta);
        } catch (\Throwable $e) {
            return ['ok' => false, 'path' => null, 'error' => $e->getMessage()];
        }
    }

    /** Ruta del clip de fondo; null si todavía no se ha generado para este aliado. */
    public static function rutaFondo(int $aliadoId): ?string
    {
        $rel = sprintf(self::FONDO_REL, $aliadoId);

        return Storage::disk('public')->exists($rel) ? Storage::disk('public')->path($rel) : null;
    }

    /** @return array{ok: bool, path: ?string, error: ?string} */
    private static function construir(Aliado $aliado, int $anios, string $ciudad, float $segundos, string $destino): array
    {
        $fondo = self::rutaFondo($aliado->id);
        if (!$fondo) {
            return ['ok' => false, 'path' => null, 'error' => 'Falta el clip de fondo de asesores. Generarlo primero (ver rutaFondo).'];
        }

        $logoRel = $aliado->logo_marca_claro ?: $aliado->logo;
        if (!$logoRel || !Storage::disk('public')->exists($logoRel)) {
            return ['ok' => false, 'path' => null, 'error' => 'El aliado no tiene logo cargado.'];
        }
        $logoAbs = Storage::disk('public')->path($logoRel);

        $tmp = sys_get_temp_dir();
        $id  = Str::random(10);

        // Un PNG por momento + la barra fija del WhatsApp + el destello de transición.
        $capas = [
            'velo'    => "{$tmp}/c_velo_{$id}.png",
            'm1'      => "{$tmp}/c_m1_{$id}.png",
            'm2'      => "{$tmp}/c_m2_{$id}.png",
            'm3'      => "{$tmp}/c_m3_{$id}.png",
            'm4'      => "{$tmp}/c_m4_{$id}.png",
            'barra'   => "{$tmp}/c_barra_{$id}.png",
            'rayo'    => "{$tmp}/c_rayo_{$id}.png",
        ];

        self::pintarVelo($aliado, $capas['velo']);
        self::momentoAnios($aliado, $anios, $capas['m1']);
        self::momentoAsesores($aliado, $capas['m2']);
        self::momentoCobertura($aliado, $ciudad, $capas['m3']);
        self::momentoLlamado($aliado, $capas['m4']);
        self::pintarBarraWhatsapp($aliado, $capas['barra']);
        self::pintarRayo($capas['rayo']);

        // Ventanas de cada momento. Se solapan 0,2s con el destello para que el corte no
        // se sienta seco.
        $u = $segundos / 8.0;   // permite escalar la secuencia si se cambia la duración
        $m = [
            ['in' => 0.35 * $u, 'out' => 2.20 * $u],
            ['in' => 2.45 * $u, 'out' => 4.30 * $u],
            ['in' => 4.55 * $u, 'out' => 6.30 * $u],
            ['in' => 6.55 * $u, 'out' => $segundos],
        ];
        $rayos = [2.25 * $u, 4.35 * $u, 6.35 * $u];

        $f = [];
        // Fondo: recorte a 9:16, leve zoom y saturación para que se vea de campaña.
        // El clip de Veo ya viene 9:16, pero se fuerza el tamaño exacto por si cambia.
        $f[] = '[0:v]scale=' . self::ANCHO . ':' . self::ALTO . ':force_original_aspect_ratio=increase'
            . ',crop=' . self::ANCHO . ':' . self::ALTO
            . ',eq=saturation=1.12:contrast=1.05,setsar=1[bgraw]';
        // Velo degradado: sin esto el texto blanco se pierde sobre la ropa clara.
        $f[] = '[1:v]format=rgba[velo]';
        $f[] = '[bgraw][velo]overlay=0:0[bg]';

        $prev = 'bg';
        foreach ([2, 3, 4, 5] as $i => $entrada) {
            $ini = round($m[$i]['in'], 2);
            $fin = round($m[$i]['out'], 2);
            $dur = round($fin - $ini, 2);
            $sal = round(max($ini + 0.2, $fin - 0.3), 2);
            $et  = "mm{$i}";
            // enable acota el momento a su ventana; los fades lo hacen entrar y salir suave.
            $f[] = "[{$entrada}:v]format=rgba,fade=in:st={$ini}:d=0.35:alpha=1,fade=out:st={$sal}:d=0.3:alpha=1[{$et}]";
            $f[] = "[{$prev}][{$et}]overlay=0:0:enable='between(t,{$ini},{$fin})'[c{$i}]";
            $prev = "c{$i}";
        }

        // Destellos de transición entre momentos: barren en diagonal.
        foreach ($rayos as $k => $t0) {
            $t0 = round($t0, 2);
            $t1 = round($t0 + 0.45, 2);
            $et = "ry{$k}";
            $f[] = "[6:v]format=rgba,fade=in:st={$t0}:d=0.12:alpha=1,fade=out:st=" . round($t0 + 0.25, 2) . ":d=0.2:alpha=1[{$et}]";
            $f[] = "[{$prev}][{$et}]overlay=x='-500+2600*(t-{$t0})':y=0:enable='between(t,{$t0},{$t1})'[r{$k}]";
            $prev = "r{$k}";
        }

        // Logo arriba a la IZQUIERDA: centrado le quedaba encima de la cara del asesor, que
        // es justo lo que da el respaldo. Barra de WhatsApp abajo. Ambos, todo el tiempo.
        $anchoLogo = (int) round(self::ANCHO * 0.26);
        $f[] = "[7:v]format=rgba,scale={$anchoLogo}:-1,fade=in:st=0:d=0.5:alpha=1[logo]";
        $f[] = "[{$prev}][logo]overlay=38:44[conlogo]";
        $f[] = '[8:v]format=rgba,fade=in:st=0.6:d=0.5:alpha=1[barra]';
        $f[] = '[conlogo][barra]overlay=0:0[out]';

        // ── Audio ────────────────────────────────────────────────────────────
        // Se parte del ambiente que trae el propio clip de Veo (audio generado, sin problema
        // de licencia) y encima se sintetizan los golpes con FFmpeg. Deliberadamente NO se
        // usa música de terceros: sin licencia, Meta silencia el post o lo penaliza, y el
        // cierre se reutiliza en todas las piezas, así que el riesgo se multiplicaría.
        $a = [];
        $a[] = '[0:a]aformat=channel_layouts=stereo,volume=0.85,afade=t=in:st=0:d=0.3,afade=t=out:st=' . round($segundos - 0.6, 2) . ':d=0.6[amb]';

        // Golpe grave cuando aterriza el número: es el dato que queremos que se fije.
        $a[] = '[9:a]atrim=0:0.55,aformat=channel_layouts=stereo,volume=1.5,afade=t=out:st=0:d=0.55,adelay=350|350[hit]';

        // Barrido en cada transición, sincronizado con el destello visual.
        $mezcla = '[amb][hit]';
        foreach ($rayos as $k => $t0) {
            $ms = (int) round($t0 * 1000);
            $ent = 10 + $k;   // entradas 10, 11, 12
            $a[] = "[{$ent}:a]atrim=0:0.6,aformat=channel_layouts=stereo,volume=0.9,afade=t=in:st=0:d=0.12,afade=t=out:st=0.2:d=0.4,adelay={$ms}|{$ms}[w{$k}]";
            $mezcla .= "[w{$k}]";
        }
        $a[] = $mezcla . 'amix=inputs=' . (2 + count($rayos)) . ':duration=first:dropout_transition=0:normalize=0,aformat=channel_layouts=stereo,loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000,alimiter=limit=0.97[aout]';

        $f = array_merge($f, $a);

        $ffmpeg = config('services.ffmpeg.bin', 'ffmpeg');

        $resultado = Process::timeout(240)->run([
            $ffmpeg, '-y',
            '-stream_loop', '-1', '-t', (string) $segundos, '-i', $fondo,
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['velo'],
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['m1'],
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['m2'],
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['m3'],
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['m4'],
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['rayo'],
            '-loop', '1', '-t', (string) $segundos, '-i', $logoAbs,
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['barra'],
            // [9] golpe grave del número y [10..12] barridos de transición: se sintetizan
            // aquí mismo, no son archivos de audio con licencia de terceros.
            '-f', 'lavfi', '-i', 'sine=frequency=76:duration=1:sample_rate=48000',
            '-f', 'lavfi', '-i', 'anoisesrc=color=pink:amplitude=0.5:duration=1:sample_rate=48000',
            '-f', 'lavfi', '-i', 'anoisesrc=color=pink:amplitude=0.5:duration=1:sample_rate=48000',
            '-f', 'lavfi', '-i', 'anoisesrc=color=pink:amplitude=0.5:duration=1:sample_rate=48000',
            '-filter_complex', implode(';', $f),
            '-map', '[out]', '-map', '[aout]',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30',
            '-c:a', 'aac', '-shortest',
            $destino,
        ]);

        foreach ($capas as $ruta) {
            @unlink($ruta);
        }

        if (!$resultado->successful()) {
            return ['ok' => false, 'path' => null, 'error' => 'FFmpeg: ' . Str::limit($resultado->errorOutput(), 600)];
        }

        return ['ok' => true, 'path' => $destino, 'error' => null];
    }

    // ── Capas ────────────────────────────────────────────────────────────────

    /** Velo oscuro con el tinte de la marca: sin esto el texto blanco se pierde. */
    private static function pintarVelo(Aliado $aliado, string $destino): void
    {
        [$r, $g, $b] = self::rgb($aliado);
        $img = self::lienzo();

        for ($y = 0; $y < self::ALTO; $y++) {
            $p = $y / self::ALTO;
            // Más opaco arriba (donde va el logo) y sobre todo abajo (donde vive el texto).
            $op = 0.30 + 0.55 * max(0, ($p - 0.35) / 0.65) ** 1.4 + 0.22 * max(0, (0.22 - $p) / 0.22);
            $alpha = (int) max(0, min(127, 127 - $op * 127));
            imagefilledrectangle($img, 0, $y, self::ANCHO, $y, imagecolorallocatealpha($img, (int) ($r * 0.28), (int) ($g * 0.28), (int) ($b * 0.42), $alpha));
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    private static function momentoAnios(Aliado $aliado, int $anios, string $destino): void
    {
        $img = self::lienzo();
        $cx  = (int) (self::ANCHO / 2);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $oroAlto = imagecolorallocate($img, 255, 228, 146);
        $oroBajo = imagecolorallocate($img, 208, 150, 40);

        // La etiqueta va MUY por encima del número: antes se la comía la tilde de la Ñ.
        self::centrado($img, 'MÁS DE', $cx, 612, 24, imagecolorallocatealpha($img, 255, 255, 255, 25), self::FUENTE_MEDIA, 10.0);

        $y = 762;
        self::sombra($img, "{$anios} AÑOS", $cx, $y, 112, self::FUENTE, -3.0);
        self::centrado($img, "{$anios} AÑOS", $cx, $y + 5, 112, $oroBajo, self::FUENTE, -3.0);
        self::centrado($img, "{$anios} AÑOS", $cx, $y, 112, $oroAlto, self::FUENTE, -3.0);

        self::regla($img, $cx, $y + 34, 100, imagecolorallocatealpha($img, 255, 255, 255, 55));
        self::centrado($img, 'DE EXPERIENCIA', $cx, $y + 84, 26, $blanco, self::FUENTE_MEDIA, 5.5);

        imagepng($img, $destino);
        imagedestroy($img);
    }

    private static function momentoAsesores(Aliado $aliado, string $destino): void
    {
        $img = self::lienzo();
        $cx  = (int) (self::ANCHO / 2);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $oro    = imagecolorallocate($img, 255, 228, 146);

        self::centrado($img, 'ASESORES', $cx, 700, 66, $blanco, self::FUENTE, -1.5);
        self::centrado($img, 'CALIFICADOS', $cx, 772, 66, $oro, self::FUENTE, -1.5);
        self::regla($img, $cx, 806, 100, imagecolorallocatealpha($img, 255, 255, 255, 55));
        self::centrado($img, 'Te acompañamos en todo el trámite', $cx, 860, 34, $blanco, self::FUENTE_SCRIPT, 0);

        imagepng($img, $destino);
        imagedestroy($img);
    }

    private static function momentoCobertura(Aliado $aliado, string $ciudad, string $destino): void
    {
        $img = self::lienzo();
        $cx  = (int) (self::ANCHO / 2);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $oro    = imagecolorallocate($img, 255, 228, 146);

        self::centrado($img, 'ESTAMOS EN', $cx, 672, 24, imagecolorallocatealpha($img, 255, 255, 255, 25), self::FUENTE_MEDIA, 9.0);
        self::sombra($img, mb_strtoupper($ciudad), $cx, 754, 88, self::FUENTE, -2.0);
        self::centrado($img, mb_strtoupper($ciudad), $cx, 754, 88, $oro, self::FUENTE, -2.0);
        self::regla($img, $cx, 788, 100, imagecolorallocatealpha($img, 255, 255, 255, 55));

        foreach (['CONVENIO CON TODAS LAS EPS', 'A NIVEL NACIONAL'] as $i => $linea) {
            self::centrado($img, $linea, $cx, 838 + $i * 40, 25, $blanco, self::FUENTE_MEDIA, 3.2);
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    private static function momentoLlamado(Aliado $aliado, string $destino): void
    {
        $img = self::lienzo();
        $cx  = (int) (self::ANCHO / 2);

        self::centrado($img, 'Afíliate ya', $cx, 706, 54, imagecolorallocate($img, 255, 228, 146), self::FUENTE_SCRIPT, 0);

        // Cápsula: se mide el texto CON su tracking y se centra la cápsula sobre ese ancho,
        // que es lo que antes quedaba desalineado.
        $texto = 'ESCRÍBENOS AL WHATSAPP';
        $tam = 30;
        $tracking = 1.6;
        $anchoTxt = self::anchoTexto($texto, $tam, self::FUENTE, $tracking);
        $padX = 44;
        $yBase = 800;
        self::capsula(
            $img,
            $cx - (int) round($anchoTxt / 2) - $padX,
            $yBase - 40,
            $cx + (int) round($anchoTxt / 2) + $padX,
            $yBase + 18,
            imagecolorallocate($img, 255, 255, 255)
        );
        self::centrado($img, $texto, $cx, $yBase, $tam, self::colorMarcaOscuro($img, $aliado), self::FUENTE, $tracking);

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /**
     * Barra fija del WhatsApp, visible los 8 segundos. Lleva la palabra WhatsApp y un ícono
     * dibujado: un número suelto no le dice a nadie por dónde escribir.
     */
    private static function pintarBarraWhatsapp(Aliado $aliado, string $destino): void
    {
        $img = self::lienzo();
        $cx  = (int) (self::ANCHO / 2);

        $wa = \App\Models\WhatsappConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
        $numero = $wa?->numero_telefono ? preg_replace('/\D/', '', $wa->numero_telefono) : null;
        if (!$numero) {
            imagepng($img, $destino);
            imagedestroy($img);
            return;
        }
        if (str_starts_with($numero, '57')) {
            $numero = substr($numero, 2);
        }
        // Agrupado: diez dígitos corridos no se retienen de una pasada.
        $legible = trim(preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1 $2 $3', $numero));

        $tam = 32;
        $tracking = 2.0;
        $etiqueta = 'WhatsApp';
        $tamEtiq = 24;
        $anchoTxt = self::anchoTexto($legible, $tam, self::FUENTE, $tracking);
        $anchoEtiq = self::anchoTexto($etiqueta, $tamEtiq, self::FUENTE_SEMI, 1.0);
        $diamIcono = 44;
        $gap = 14;
        $anchoTotal = $diamIcono + $gap + max($anchoTxt, $anchoEtiq);

        $y = 1120;
        $x0 = $cx - (int) round($anchoTotal / 2);

        // Píldora oscura translúcida de fondo, para que se lea sobre cualquier fotograma.
        self::capsula($img, $x0 - 32, $y - 52, $x0 + $anchoTotal + 32, $y + 26, imagecolorallocatealpha($img, 0, 0, 0, 58));

        self::iconoWhatsapp($img, $x0 + (int) ($diamIcono / 2), $y - 12, $diamIcono);
        // La palabra encima del numero: deja claro por que canal escribir.
        self::texto($img, $etiqueta, $x0 + $diamIcono + $gap, $y - 26, $tamEtiq,
            imagecolorallocate($img, 168, 240, 198), self::FUENTE_SEMI, 1.0);
        self::texto($img, $legible, $x0 + $diamIcono + $gap, $y + 8, $tam,
            imagecolorallocate($img, 255, 255, 255), self::FUENTE, $tracking);

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /** Ícono de WhatsApp: círculo verde con el auricular. */
    private static function iconoWhatsapp($img, int $cx, int $cy, int $diam): void
    {
        $verde = imagecolorallocate($img, 37, 211, 102);
        $blanco = imagecolorallocate($img, 255, 255, 255);

        imagefilledellipse($img, $cx, $cy, $diam, $diam, $verde);

        // Auricular estilizado: dos trazos gruesos en diagonal dentro del círculo.
        // Auricular clasico: dos bocinas gruesas unidas por un puente en diagonal. Se lee
        // mejor a este tamano que un trazo fino, que a 44px se convierte en un borron.
        $u = $diam / 100;
        $grosor = max(4, (int) (14 * $u));
        imagesetthickness($img, $grosor);
        imageline($img, (int) ($cx - 20 * $u), (int) ($cy - 18 * $u), (int) ($cx + 18 * $u), (int) ($cy + 20 * $u), $blanco);
        imagefilledellipse($img, (int) ($cx - 22 * $u), (int) ($cy - 20 * $u), (int) (20 * $u), (int) (20 * $u), $blanco);
        imagefilledellipse($img, (int) ($cx + 20 * $u), (int) ($cy + 22 * $u), (int) (20 * $u), (int) (20 * $u), $blanco);
        imagesetthickness($img, 1);
    }

    /** Franja diagonal luminosa que barre la pantalla entre momento y momento. */
    private static function pintarRayo(string $destino): void
    {
        $w = 300;
        $h = self::ALTO;
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocatealpha($img, 0, 0, 0, 127));

        for ($x = 0; $x < $w; $x++) {
            $i = 1 - abs(($x - $w / 2) / ($w / 2));
            $alpha = (int) (127 - 92 * ($i ** 2.2));
            imageline($img, $x, 0, (int) ($x - $h * 0.34), $h, imagecolorallocatealpha($img, 255, 255, 255, $alpha));
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    // ── Utilidades de dibujo ─────────────────────────────────────────────────

    private static function lienzo()
    {
        $img = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, self::ANCHO, self::ALTO, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        return $img;
    }

    private static function rgb(Aliado $aliado): array
    {
        $hex = ltrim($aliado->color_primario ?: '#1e3a8a', '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function colorMarcaOscuro($img, Aliado $aliado): int
    {
        [$r, $g, $b] = self::rgb($aliado);

        return imagecolorallocate($img, (int) ($r * 0.7), (int) ($g * 0.7), (int) ($b * 0.7));
    }

    private static function regla($img, int $cx, int $y, int $semiancho, int $color): void
    {
        imagefilledrectangle($img, $cx - $semiancho, $y, $cx + $semiancho, $y + 2, $color);
    }

    /**
     * Dibuja letra por letra para poder aplicar tracking: GD no tiene espaciado entre
     * caracteres y sin él la tipografía se ve "escrita" y no compuesta.
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

    private static function sombra($img, string $texto, int $cx, int $y, int $tam, string $fuente, float $tracking = 0): void
    {
        $sombra = imagecolorallocatealpha($img, 0, 0, 0, 95);
        $ancho = self::anchoTexto($texto, $tam, $fuente, $tracking);
        $x = (int) round($cx - $ancho / 2);
        foreach ([[2, 4], [-2, 4], [0, 6], [3, 5]] as [$dx, $dy]) {
            self::texto($img, $texto, $x + $dx, $y + $dy, $tam, $sombra, $fuente, $tracking);
        }
    }

    private static function capsula($img, int $x0, int $y0, int $x1, int $y1, int $color): void
    {
        $r = (int) (($y1 - $y0) / 2);
        imagefilledrectangle($img, $x0 + $r, $y0, $x1 - $r, $y1, $color);
        imagefilledellipse($img, $x0 + $r, (int) (($y0 + $y1) / 2), $r * 2, $y1 - $y0, $color);
        imagefilledellipse($img, $x1 - $r, (int) (($y0 + $y1) / 2), $r * 2, $y1 - $y0, $color);
    }
}
