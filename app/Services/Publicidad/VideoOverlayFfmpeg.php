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
    /**
     * Mismo objetivo de sonoridad que el cierre de marca (ver CierreMarcaVideo): así el
     * contenido y la cola corporativa suenan al mismo nivel y no hay salto de volumen a mitad
     * de la pieza. Medido antes de esto: el contenido iba 4,5 dB por debajo del cierre.
     */
    private const NORMALIZACION = 'loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000,alimiter=limit=0.95';

    public static function aplicar(
        string $rutaVideoBruto,
        array $frases,
        ?string $rutaLogoClaro,
        ?array $recorte,
        ?string $colorPrimario,
        string $destinoVideoAbsoluto,
        string $destinoPosterAbsoluto,
        ?string $rutaNarracion = null
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

        // Narración en off, cuando el clip no trae a nadie hablando. El ambiente NO se quita:
        // se agacha a un tercio y queda debajo, que es lo que hace que la escena siga
        // sintiéndose real en vez de un video mudo con una voz encima.
        $mapaAudio = '0:a?';
        $tieneAudio = self::tieneAudio($ffprobe, $rutaVideoBruto);

        if ($rutaNarracion && is_file($rutaNarracion)) {
            $args[] = '-i';
            $args[] = $rutaNarracion;
            $idxVoz = $entradaIdx;

            // Medio segundo de aire antes de que arranque la voz: entrar en el fotograma uno
            // suena atropellado y se pierde la primera palabra.
            // La voz se normaliza SOLA antes de mezclar. Normalizar solo la mezcla final la
            // deja a merced del ambiente: con un clip de calle ruidosa, loudnorm baja todo
            // junto y la narradora se vuelve a hundir. Así queda al mismo nivel siempre,
            // tenga el clip el fondo que tenga.
            $filtrosCapas[] = "[{$idxVoz}:a]adelay=500|500,aformat=sample_fmts=fltp:sample_rates=48000:channel_layouts=stereo,"
                . 'loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000[voz]';

            if ($tieneAudio) {
                $filtrosCapas[] = '[0:a]volume=0.32,aformat=sample_fmts=fltp:sample_rates=48000:channel_layouts=stereo[amb]';
                // `duration=first` para que la voz no alargue el clip si el TTS se pasa de largo.
                $filtrosCapas[] = '[amb][voz]amix=inputs=2:duration=first:dropout_transition=0[mez]';
            } else {
                $filtrosCapas[] = '[voz]anull[mez]';
            }

            $filtrosCapas[] = '[mez]' . self::NORMALIZACION . '[aout]';
            $mapaAudio = '[aout]';
        } elseif ($tieneAudio) {
            // También sin narración: el clip de Veo trae el volumen que le da la gana y el
            // cierre está normalizado a -16 LUFS, así que sin esto se oye un salto al pasar
            // de una parte a la otra.
            $filtrosCapas[] = '[0:a]' . self::NORMALIZACION . '[aout]';
            $mapaAudio = '[aout]';
        }

        $filtroCompleto = implode(';', array_filter($filtrosCapas));

        $args = array_merge($args, [
            '-filter_complex', $filtroCompleto,
            '-map', $mapaOverlay,
            '-map', $mapaAudio,
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

    /**
     * Pega el cierre de marca al final de una pieza, con una transición real en vez de un
     * corte seco.
     *
     * Se usa `xfade` con un destello a blanco, no un fundido común: es el mismo recurso que
     * el propio cierre usa entre sus momentos, así la costura se lee como parte de la pieza
     * y no como dos videos pegados. El audio cruza con `acrossfade` a la vez, porque si solo
     * cruzara la imagen se notaría el salto en el sonido.
     *
     * Los dos clips deben coincidir en fps y formato de pixel para que xfade funcione: el
     * contenido de Veo viene a 24 fps y el cierre a 30, así que se normalizan aquí.
     *
     * @return array{ok: bool, error: ?string}
     */
    public static function pegarCierre(
        string $rutaContenido,
        string $rutaCierre,
        string $destinoAbsoluto,
        float $transicion = 0.5
    ): array {
        $binario  = config('services.ffmpeg.binario', 'ffmpeg');
        $ffprobe  = config('services.ffmpeg.ffprobe', 'ffprobe');

        $dim = self::obtenerDimensiones($ffprobe, $rutaContenido);
        if (!$dim) {
            return ['ok' => false, 'error' => 'No se pudo leer la duración del video de contenido.'];
        }
        [$ancho, $alto, $duracion] = $dim;

        // El cruce arranca antes de que termine el contenido; si la pieza fuera más corta
        // que la transición, se recorta para no pedirle a xfade un offset negativo.
        $cruce  = min($transicion, max(0.2, $duracion - 0.2));
        $offset = round(max(0, $duracion - $cruce), 3);

        $filtro = implode(';', [
            "[0:v]fps=30,scale={$ancho}:{$alto},setsar=1,format=yuv420p,settb=AVTB[v0]",
            "[1:v]fps=30,scale={$ancho}:{$alto},setsar=1,format=yuv420p,settb=AVTB[v1]",
            "[v0][v1]xfade=transition=fadewhite:duration={$cruce}:offset={$offset}[v]",
            '[0:a]aformat=sample_fmts=fltp:sample_rates=48000:channel_layouts=stereo[a0]',
            '[1:a]aformat=sample_fmts=fltp:sample_rates=48000:channel_layouts=stereo[a1]',
            "[a0][a1]acrossfade=d={$cruce}:c1=tri:c2=tri[a]",
        ]);

        $resultado = Process::timeout(300)->run([
            $binario, '-y',
            '-i', $rutaContenido,
            '-i', $rutaCierre,
            '-filter_complex', $filtro,
            '-map', '[v]', '-map', '[a]',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'veryfast', '-crf', '20',
            '-c:a', 'aac', '-b:a', '128k',
            $destinoAbsoluto,
        ]);

        if (!$resultado->successful()) {
            return ['ok' => false, 'error' => 'FFmpeg falló al pegar el cierre: ' . mb_substr($resultado->errorOutput(), -500)];
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
            $anchoMaxLinea = max($anchoMaxLinea, self::anchoConTracking($linea, $tamFuente, $rutaFuente, self::TRACKING));
        }

        // El alto se calcula con la TINTA real, no con el tamaño de fuente: los rasgos que
        // bajan —la "g" de "aseguradas", el punto final— viven por debajo de la línea base y la
        // fórmula anterior los ignoraba. Dejaba 24 px de aire arriba y 8 abajo, y el texto se
        // veía pegado al borde aunque técnicamente cupiera.
        $cajaPrimera = imagettfbbox($tamFuente, 0, $rutaFuente, $lineas[0]);
        $cajaUltima  = imagettfbbox($tamFuente, 0, $rutaFuente, $lineas[count($lineas) - 1]);
        $subePrimera = abs($cajaPrimera[7]);           // cuánto sube la primera línea
        $bajaUltima  = max(0, $cajaUltima[1]);          // cuánto baja la última

        $padX = (int) round($tamFuente * 0.95);
        $padY = (int) round($tamFuente * 0.50);
        $wPastilla = $anchoMaxLinea + $padX * 2;
        $altoTinta = $subePrimera + (count($lineas) - 1) * $alturaLinea + $bajaUltima;
        $hPastilla = $altoTinta + $padY * 2;
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

        // Radio acotado: con la mitad del alto, tres líneas convierten la pastilla en un óvalo
        // gigante que se lee amateur. Un rectángulo bien redondeado se ve compuesto.
        $radio = (int) round(min($hPastilla / 2, $tamFuente * 1.15));

        $colorSombra = imagecolorallocatealpha($temp, 0, 0, 0, 95);
        self::pastillaRedondeada($temp, $margenBlur, (int) round($margenBlur + $tamFuente * 0.15), $wPastilla, $hPastilla, $radio, $colorSombra);
        for ($i = 0; $i < 3; $i++) {
            imagefilter($temp, IMG_FILTER_GAUSSIAN_BLUR);
        }

        // Opaca del todo. Antes iba con alfa 10 y se veía sólida solo porque los rectángulos
        // superpuestos pintaban el color dos veces; al arreglar las muescas apagando la mezcla
        // esa opacidad de rebote desapareció y el texto quedó compitiendo con el fondo. La
        // pastilla existe justamente para que el texto se lea sobre cualquier escena.
        $colorFondo = imagecolorallocate($temp, $r, $g, $b);
        self::pastillaRedondeada($temp, $margenBlur, $margenBlur, $wPastilla, $hPastilla, $radio, $colorFondo);
        // Un solo pase de desenfoque: con dos, el borde se come tanto la forma que la pastilla
        // pierde cuerpo. El suavizado real ya lo da el supersampling de 2x al reducir.
        imagefilter($temp, IMG_FILTER_GAUSSIAN_BLUR);

        imagecopy($lienzo, $temp, $xPastilla - $margenBlur, $yPastilla - $margenBlur, 0, 0, $wTemp, $hTemp);
        imagedestroy($temp);

        // Texto NÍTIDO encima, sin blur.
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        // La primera línea base se coloca desde la tinta, no desde el tamaño de fuente: así el
        // aire de arriba y el de abajo quedan iguales y el bloque se ve centrado de verdad.
        $yTexto = $yPastilla + $padY + $subePrimera;
        foreach ($lineas as $i => $linea) {
            $anchoLinea = self::anchoConTracking($linea, $tamFuente, $rutaFuente, self::TRACKING);
            $x = (int) round(($anchoG - $anchoLinea) / 2);
            $y = $yTexto + $i * $alturaLinea;
            self::textoConTracking($lienzo, $linea, $x, $y, $tamFuente, $blanco, $rutaFuente, self::TRACKING);
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
    /**
     * Tracking (espaciado entre letras). GD no lo tiene: `imagettftext` dibuja la cadena de
     * corrido y con una bold display eso se ve "escrito" en vez de compuesto. Se dibuja letra
     * por letra — misma técnica que el cierre de marca, ver CierreMarcaVideo::texto().
     *
     * Negativo a propósito: en titulares grandes, apretar levemente es lo que los hace ver de
     * diseño. En cuerpos pequeños sería ilegible; aquí el texto nunca baja de ~40 px.
     */
    private const TRACKING = -1.5;

    /**
     * Dibuja con tracking sin romper la composición de la fuente.
     *
     * La forma ingenua —dibujar letra por letra y avanzar el ancho de cada una— parece
     * funcionar y no: `imagettfbbox` mide la TINTA, no el avance, así que ignora los costados
     * de cada letra y el kerning entre pares. Medido sobre "ARL desde" a 86 px, la cadena
     * quedaba 43 px más angosta que compuesta, y las palabras se pegaban ("al mes" se leía
     * "almes").
     *
     * Aquí cada letra se posiciona midiendo el PREFIJO completo hasta ella, que es texto real
     * compuesto por la fuente: conserva avances y kerning, y el tracking se suma aparte.
     */
    private static function textoConTracking($lienzo, string $texto, int $x, int $y, int $tam, int $color, string $fuente, float $tracking): void
    {
        $letras = preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($letras as $i => $letra) {
            if ($letra === ' ') {
                continue;
            }

            $prefijo = mb_substr($texto, 0, $i, 'UTF-8');
            $corrido = $prefijo === '' ? 0 : self::anchoTinta($prefijo, $tam, $fuente);
            imagettftext($lienzo, $tam, 0, (int) round($x + $corrido + $i * $tracking), $y, $color, $fuente, $letra);
        }
    }

    private static function anchoConTracking(string $texto, int $tam, string $fuente, float $tracking): int
    {
        $largo = max(1, mb_strlen($texto, 'UTF-8'));

        return (int) round(self::anchoTinta($texto, $tam, $fuente) + ($largo - 1) * $tracking);
    }

    private static function anchoTinta(string $texto, int $tam, string $fuente): int
    {
        $caja = imagettfbbox($tam, 0, $fuente, $texto);

        return $caja[2] - $caja[0];
    }

    private static function pastillaRedondeada($lienzo, int $x, int $y, int $w, int $h, int $r, $color): void
    {
        $mezclaPrevia = imagealphablending($lienzo, false);

        $d = $r * 2;
        imagefilledrectangle($lienzo, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($lienzo, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($lienzo, $x + $r, $y + $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $w - $r, $y + $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $r, $y + $h - $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $w - $r, $y + $h - $r, $d, $d, $color);

        imagealphablending($lienzo, $mezclaPrevia);
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

    /** ¿El clip trae pista de audio? Sin esto, filtrar [0:a] en un video mudo revienta FFmpeg. */
    private static function tieneAudio(string $ffprobe, string $ruta): bool
    {
        $r = Process::timeout(30)->run([
            $ffprobe, '-v', 'error', '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_type', '-of', 'csv=p=0', $ruta,
        ]);

        return $r->successful() && str_contains($r->output(), 'audio');
    }
}
