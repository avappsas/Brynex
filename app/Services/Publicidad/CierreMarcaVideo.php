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

    /**
     * Marca de WhatsApp, archivo oficial. Antes se dibujaba con primitivas de GD y no se
     * reconocia: a 44px la silueta se leia como un pin de ubicacion. Una marca registrada
     * hay que usarla como es.
     */
    private const MARCA_WHATSAPP = 'resources/marcas/whatsapp.png';

    /** Retardo antes de que arranque la voz, y aire que se deja despues de la ultima palabra. */
    private const RETARDO_VOZ = 0.12;
    private const COLA_VOZ    = 0.55;

    /** Geometría de la barra inferior, compartida entre la pastilla y los logos animados. */
    private const Y_BARRA    = 1140;
    private const ALTO_BARRA = 56;

    private const ANCHO = 720;
    private const ALTO  = 1280;

    /**
     * Variantes del cierre, para que no salga siempre el mismo y la gente deje de verlo. Se
     * turnan por dia; cada una tiene su fondo (generado una vez con Veo y cacheado) y su
     * guion.
     *
     * `logo_pared` marca los fondos que traen un panel vacio en la pared: ahi se compone el
     * logo REAL encima. No se le pide el letrero a Veo porque destroza el texto --- en las
     * pruebas devolvio "CURSTRA ORIGRMN" en vez de una palabra.
     */
    private const VARIANTES = [
        1 => [
            'fondo'      => 'publicidad/cierres/fondo_asesores_%d.mp4',
            'logo_pared' => false,
            // El clip ya trae la voz de la propia asesora, sincronizada con sus labios, y el
            // letrero BRYGAR renderizado en la pared del set. No se le monta locucion encima:
            // eso era justo lo que hacia que la boca no cuadrara con lo que se escuchaba.
            'voz_propia' => true,
        ],
    ];

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
        bool $regenerar = false,
        ?int $variante = null
    ): array {
        $variante = $variante ?: self::varianteDelDia();
        $firma = md5($variante . '|' . $anios . '|' . $ciudad . '|' . $segundos . '|' . ($aliado->color_primario ?? ''));
        $rutaRelativa = "publicidad/cierres/cierre_{$aliado->id}_{$firma}.mp4";
        $rutaAbsoluta = Storage::disk('public')->path($rutaRelativa);

        if (!$regenerar && Storage::disk('public')->exists($rutaRelativa)) {
            return ['ok' => true, 'path' => $rutaAbsoluta, 'error' => null];
        }

        Storage::disk('public')->makeDirectory('publicidad/cierres');

        try {
            return self::construir($aliado, $anios, $ciudad, $segundos, $rutaAbsoluta, $variante);
        } catch (\Throwable $e) {
            return ['ok' => false, 'path' => null, 'error' => $e->getMessage()];
        }
    }

    /** Variante que toca hoy. Alterna por dia del anio, asi no sale siempre la misma. */
    public static function varianteDelDia(): int
    {
        $claves = array_keys(self::VARIANTES);

        return $claves[(int) now('America/Bogota')->dayOfYear % count($claves)];
    }

    /** Ruta del clip de fondo de una variante; null si todavía no se ha generado. */
    public static function rutaFondo(int $aliadoId, int $variante = 1): ?string
    {
        $def = self::VARIANTES[$variante] ?? self::VARIANTES[1];
        $rel = sprintf($def['fondo'], $aliadoId);

        return Storage::disk('public')->exists($rel) ? Storage::disk('public')->path($rel) : null;
    }

    /**
     * Locución en español del cierre. Se genera una vez y se cachea: el guion es siempre el
     * mismo, así que no tiene sentido volver a pedirla en cada pieza.
     *
     * El guion se escribe COMO DEBE SONAR, no como se escribe: "Brigar" y "doce" en letras,
     * porque el TTS lee literal y de otro modo saldría deletreado o con acento extranjero.
     *
     * @return array{ok: bool, path: ?string, error: ?string}
     */
    public static function locucion(Aliado $aliado, int $anios, string $ciudad, bool $regenerar = false, int $variante = 1): array
    {
        $rel = "publicidad/cierres/locucion_{$aliado->id}_" . md5($variante . '|' . $anios . '|' . $ciudad) . '.wav';
        $abs = Storage::disk('public')->path($rel);

        if (!$regenerar && Storage::disk('public')->exists($rel)) {
            return ['ok' => true, 'path' => $abs, 'error' => null];
        }

        $apiKey = \App\Models\IaConfiguracionAliado::paraAliado($aliado->id)->gemini_api_key;
        if (!$apiKey) {
            return ['ok' => false, 'path' => null, 'error' => 'No hay clave de Gemini para generar la locución.'];
        }

        Storage::disk('public')->makeDirectory('publicidad/cierres');

        $def = self::VARIANTES[$variante] ?? self::VARIANTES[1];
        $guion = strtr($def['guion'], [
            '{anios}'  => (string) $anios,
            '{ciudad}' => $ciudad,
            '{marca}'  => self::comoSuena($aliado->nombre),
        ]);

        $r = LocucionIaService::generar(
            $apiKey,
            $guion,
            $abs,
            LocucionIaService::VOZ_FEMENINA,
            'Léelo con energía, cercano y convincente, en español colombiano, ritmo ágil de comercial de radio'
        );

        return ['ok' => $r['ok'], 'path' => $r['ok'] ? $abs : null, 'error' => $r['error']];
    }

    /** Duración en segundos de un archivo de audio; 0 si no se puede leer. */
    private static function duracionAudio(string $ruta): float
    {
        $ffprobe = config('services.ffmpeg.ffprobe', 'ffprobe');
        $r = Process::timeout(30)->run([
            $ffprobe, '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $ruta,
        ]);

        return $r->successful() ? (float) trim($r->output()) : 0.0;
    }

    /** El TTS lee literal: la marca se escribe fonéticamente para que no la deletree. */
    private static function comoSuena(string $nombre): string
    {
        return match (mb_strtoupper($nombre)) {
            'BRYGAR' => 'Brigar',
            default  => $nombre,
        };
    }

    /** @return array{ok: bool, path: ?string, error: ?string} */
    private static function construir(Aliado $aliado, int $anios, string $ciudad, float $segundos, string $destino, int $variante = 1): array
    {
        $def = self::VARIANTES[$variante] ?? self::VARIANTES[1];
        $fondo = self::rutaFondo($aliado->id, $variante);
        if (!$fondo) {
            return ['ok' => false, 'path' => null, 'error' => 'Falta el clip de fondo de asesores. Generarlo primero (ver rutaFondo).'];
        }

        $logoRel = $aliado->logo_marca_claro ?: $aliado->logo;
        if (!$logoRel || !Storage::disk('public')->exists($logoRel)) {
            return ['ok' => false, 'path' => null, 'error' => 'El aliado no tiene logo cargado.'];
        }
        $logoAbs = Storage::disk('public')->path($logoRel);

        // Locución en español. Si falla, el cierre sale igual con solo la base musical:
        // es preferible una pieza muda a no tener pieza.
        $vozPropia = !empty($def['voz_propia']);
        $rutaVoz = null;
        if (!$vozPropia) {
            $voz = self::locucion($aliado, $anios, $ciudad, false, $variante);
            $rutaVoz = $voz['ok'] ? $voz['path'] : null;
        }

        // El video se estira para que la voz quepa ENTERA. Antes se cortaba la última
        // palabra: el TTS no da una duración exacta —depende de cómo lea el guion— así que
        // fijar 8 segundos a mano siempre iba a fallar con algún texto. Se mide y se ajusta.
        if ($rutaVoz) {
            $durVoz = self::duracionAudio($rutaVoz);
            if ($durVoz > 0) {
                $necesario = $durVoz + self::RETARDO_VOZ + self::COLA_VOZ;
                if ($necesario > $segundos) {
                    $segundos = round($necesario, 2);
                }
            }
        }

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
            'wa'      => "{$tmp}/c_wa_{$id}.png",
        ];

        self::pintarVelo($aliado, $capas['velo']);
        self::momentoAnios($aliado, $anios, $capas['m1']);
        self::momentoAsesores($aliado, $capas['m2']);
        self::momentoCobertura($aliado, $ciudad, $capas['m3']);
        // Sin cuarto momento: la asesora ya dice "¡Escríbenos ya!" en el clip, y ponerlo
        // tambien en pantalla repetia el mismo llamado dos veces. La barra inferior, visible
        // todo el tiempo, es la que indica por donde escribir.
        self::momentoCierreLimpio($capas['m4']);
        self::pintarBarraWhatsapp($aliado, $capas['barra']);
        self::pintarRayo($capas['rayo']);
        self::pintarIconoSuelto($capas['wa']);

        // Ventanas de cada momento. Se solapan 0,2s con el destello para que el corte no
        // se sienta seco.
        $u = $segundos / 8.0;   // permite escalar la secuencia si se cambia la duración
        $m = [
            ['in' => 0.35 * $u, 'out' => 2.20 * $u],
            ['in' => 2.45 * $u, 'out' => 4.30 * $u],
            // El tercero se estira hasta el final: al quitar el cuarto momento, cortarlo a
            // los 6,3s dejaba casi dos segundos de video sin ningun mensaje en pantalla.
            ['in' => 4.55 * $u, 'out' => $segundos],
            ['in' => $segundos, 'out' => $segundos],
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
        if (false) {
            // El fondo trae un panel vacio en la pared: el logo va AHI, como si fuera el
            // letrero de la oficina. Se le baja la opacidad y se difumina apenas para que
            // se integre con la profundidad de campo del video en vez de verse pegado.
            $anchoLogo = (int) round(self::ANCHO * 0.30);
            $f[] = "[7:v]format=rgba,scale={$anchoLogo}:-1,boxblur=1:1,colorchannelmixer=aa=0.88,fade=in:st=0.2:d=0.6:alpha=1[logo]";
            $f[] = "[{$prev}][logo]overlay=W-w-58:118[conlogo]";
        } else {
            // Sin logo arriba: ya va en la barra inferior junto al numero, y repetirlo solo
            // le quita escena al video --- que es lo que en realidad sostiene la mirada.
            $f[] = "[{$prev}]null[conlogo]";
        }
        $f[] = '[8:v]format=rgba,fade=in:st=0.6:d=0.5:alpha=1[barra]';
        $f[] = '[conlogo][barra]overlay=0:0[conbarra]';

        // Los dos logos de la barra entran escalando con un rebote amortiguado y despues
        // laten muy suave: es lo que hace que la franja se sienta viva y no un pie de pagina.
        $pos = self::posicionesLogosBarra($aliado);
        $lado = self::ALTO_BARRA;
        $yLogo = self::Y_BARRA - $lado + 12;

        $f[] = "[15:v]format=rgba,scale={$lado}:{$lado},"
            . "scale=w='iw*(0.55+0.45*(1-exp(-7*max(0,t-0.7))))+iw*0.05*sin(2.2*t)*exp(-0.7*max(0,t-1.4))':h=-1:eval=frame,"
            . "fade=in:st=0.7:d=0.35:alpha=1[walogo]";
        $f[] = "[conbarra][walogo]overlay=x='{$pos['wa']}+({$lado}-overlay_w)/2':y='{$yLogo}+({$lado}-overlay_h)/2':eval=frame[conwa]";

        $ladoMarca = (int) round($lado * 1.45);
        $f[] = "[16:v]format=rgba,scale={$ladoMarca}:-1,"
            . "scale=w='iw*(0.55+0.45*(1-exp(-7*max(0,t-0.95))))+iw*0.05*sin(2.2*t)*exp(-0.7*max(0,t-1.65))':h=-1:eval=frame,"
            . "fade=in:st=0.95:d=0.35:alpha=1[marcalogo]";
        // El logo va mas grande que su hueco y se centra sobre el, sobresaliendo un poco de
        // la pastilla: asi pesa visualmente sin desalinear el numero.
        $cxMarca = $pos['marca'] + (int) ($lado / 2);
        $cyMarca = self::Y_BARRA - (int) ($lado / 2) + 12;
        $f[] = "[conwa][marcalogo]overlay=x='{$cxMarca}-overlay_w/2':y='{$cyMarca}-overlay_h/2':eval=frame[out]";

        // ── Audio ────────────────────────────────────────────────────────────
        // Se parte del ambiente que trae el propio clip de Veo (audio generado, sin problema
        // de licencia) y encima se sintetizan los golpes con FFmpeg. Deliberadamente NO se
        // usa música de terceros: sin licencia, Meta silencia el post o lo penaliza, y el
        // cierre se reutiliza en todas las piezas, así que el riesgo se multiplicaría.
        $a = [];

        if ($vozPropia) {
            // La voz viene del clip y está sincronizada con los labios: se respeta tal cual y
            // solo se nivela, para que empareje con el Reel de contenido en el corte.
            $a[] = '[0:a]aformat=channel_layouts=stereo,volume=1.3,'
                . 'afade=t=in:st=0:d=0.15,afade=t=out:st=' . round($segundos - 0.5, 2) . ':d=0.5,'
                . 'loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000,alimiter=limit=0.97[aout]';
        } else {
            // Sin voz propia se descarta el audio del clip (Veo habla en inglés por su cuenta)
            // y se arma una base sintetizada, neutra de idioma y sin problema de licencia.
            $a[] = '[13:a]aformat=channel_layouts=stereo,volume=0.045,'
                . 'tremolo=f=2:d=0.35,afade=t=in:st=0:d=0.5,afade=t=out:st=' . round($segundos - 1.0, 2) . ':d=1.0[base1]';
            $a[] = '[14:a]aformat=channel_layouts=stereo,volume=0.03,'
                . 'afade=t=in:st=0:d=0.8,afade=t=out:st=' . round($segundos - 1.0, 2) . ':d=1.0[base2]';
            $a[] = '[base1][base2]amix=inputs=2:duration=first:normalize=0[amb]';
            $a[] = '[9:a]atrim=0:0.55,aformat=channel_layouts=stereo,volume=0.45,afade=t=out:st=0:d=0.55,adelay=350|350[hit]';

            $mezcla = '[amb][hit]';
            foreach ($rayos as $k => $t0) {
                $ms = (int) round($t0 * 1000);
                $ent = 10 + $k;
                $a[] = "[{$ent}:a]atrim=0:0.38,aformat=channel_layouts=stereo,"
                    . "highpass=f=900,lowpass=f=7000,"
                    . "volume=0.16,afade=t=in:st=0:d=0.06,afade=t=out:st=0.10:d=0.28,adelay={$ms}|{$ms}[w{$k}]";
                $mezcla .= "[w{$k}]";
            }

            if ($rutaVoz) {
                $a[] = $mezcla . 'amix=inputs=' . (2 + count($rayos)) . ':duration=first:dropout_transition=0:normalize=0,volume=0.12[lecho]';
                $a[] = '[15:a]aformat=channel_layouts=stereo:sample_rates=48000,'
                    . 'acompressor=threshold=0.12:ratio=4:attack=8:release=180,'
                    . 'volume=4.0,adelay=' . (int) (self::RETARDO_VOZ * 1000) . '|' . (int) (self::RETARDO_VOZ * 1000) . '[voz]';
                $a[] = '[lecho][voz]amix=inputs=2:duration=first:dropout_transition=0:normalize=0,'
                    . 'aformat=channel_layouts=stereo,loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000,alimiter=limit=0.97[aout]';
            } else {
                $a[] = $mezcla . 'amix=inputs=' . (2 + count($rayos)) . ':duration=first:dropout_transition=0:normalize=0,aformat=channel_layouts=stereo,loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000,alimiter=limit=0.97[aout]';
            }
        }

        $f = array_merge($f, $a);

        $ffmpeg = config('services.ffmpeg.bin', 'ffmpeg');

        $resultado = Process::timeout(240)->run(array_merge([
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
            // [13][14] base musical: dos graves en quinta (110 Hz y 165 Hz).
            '-f', 'lavfi', '-t', (string) $segundos, '-i', 'sine=frequency=110:sample_rate=48000',
            '-f', 'lavfi', '-t', (string) $segundos, '-i', 'sine=frequency=165:sample_rate=48000',
            // [15] ícono de WhatsApp y [16] logo de la marca, para la barra animada.
            '-loop', '1', '-t', (string) $segundos, '-i', $capas['wa'],
            '-loop', '1', '-t', (string) $segundos, '-i', $logoAbs,
        ], $rutaVoz ? ['-i', $rutaVoz] : [], [
            '-filter_complex', implode(';', $f),
            '-map', '[out]', '-map', '[aout]',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30',
            '-c:a', 'aac', '-shortest',
            $destino,
        ]));

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

        self::centrado($img, 'ESTAMOS EN', $cx, 604, 24, imagecolorallocatealpha($img, 255, 255, 255, 25), self::FUENTE_MEDIA, 9.0);
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

    /** Cuarto momento vacío: el filtro espera 4 capas, pero aquí no se dibuja nada. */
    private static function momentoCierreLimpio(string $destino): void
    {
        $img = self::lienzo();
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
        // Agrupado: diez digitos corridos no se retienen en un cierre de segundos.
        $legible = trim(preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1 $2 $3', $numero));

        $tam = 33;
        $tracking = 2.0;
        $anchoTxt = self::anchoTexto($legible, $tam, self::FUENTE, $tracking);
        $hueco = self::ALTO_BARRA;   // espacio reservado a cada logo

        $anchoTotal = $hueco + 14 + $anchoTxt + 46 + $hueco;
        $x0 = $cx - (int) round($anchoTotal / 2);
        $y  = self::Y_BARRA;

        // Pastilla oscura translucida: el texto tiene que leerse sobre cualquier fotograma.
        self::capsula($img, $x0 - 26, $y - 56, $x0 + $anchoTotal + 26, $y + 26, imagecolorallocatealpha($img, 0, 0, 0, 52));

        self::texto($img, 'WhatsApp', $x0 + $hueco + 16, $y - 30, 18,
            imagecolorallocate($img, 168, 240, 198), self::FUENTE_SEMI, 1.4);
        self::texto($img, $legible, $x0 + $hueco + 16, $y + 14, $tam,
            imagecolorallocate($img, 255, 255, 255), self::FUENTE, $tracking);

        imagepng($img, $destino);
        imagedestroy($img);
    }

    /** Posicion X donde va cada logo de la barra: [whatsapp, marca]. */
    private static function posicionesLogosBarra(Aliado $aliado): array
    {
        $wa = \App\Models\WhatsappConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
        $numero = preg_replace('/\D/', '', $wa?->numero_telefono ?? '');
        if (str_starts_with($numero, '57')) {
            $numero = substr($numero, 2);
        }
        $legible = trim(preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1 $2 $3', $numero));

        $anchoTxt = self::anchoTexto($legible, 33, self::FUENTE, 2.0);
        $hueco = self::ALTO_BARRA;
        $anchoTotal = $hueco + 14 + $anchoTxt + 46 + $hueco;
        $x0 = (int) (self::ANCHO / 2) - (int) round($anchoTotal / 2);

        return ['wa' => $x0, 'marca' => $x0 + $anchoTotal - $hueco];
    }

    /**
     * Ícono de WhatsApp dibujado como la marca real: burbuja verde con la cola apuntando
     * abajo-izquierda y el auricular blanco dentro. La version anterior era un arco fino que
     * a 44px se leia como un pin de ubicacion, no como WhatsApp.
     */
    private static function iconoWhatsapp($img, int $cx, int $cy, int $diam): void
    {
        $verde  = imagecolorallocate($img, 37, 211, 102);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $u = $diam / 100;   // todas las medidas en % del diametro, para que escale

        // Cuerpo de la burbuja.
        imagefilledellipse($img, $cx, $cy, $diam, $diam, $verde);

        // Cola: triangulo hacia abajo-izquierda, lo que distingue a WhatsApp de un circulo.
        $cola = [
            (int) ($cx - 30 * $u), (int) ($cy + 28 * $u),
            (int) ($cx - 8 * $u),  (int) ($cy + 42 * $u),
            (int) ($cx - 4 * $u),  (int) ($cy + 20 * $u),
        ];
        imagefilledpolygon($img, $cola, $verde);

        // Auricular: dos bulbos gruesos unidos por un puente en diagonal. Las proporciones
        // importan mas que el detalle: a este tamano lo que se reconoce es la silueta.
        $grosorPuente = max(5, (int) (17 * $u));
        imagesetthickness($img, $grosorPuente);
        imageline(
            $img,
            (int) ($cx - 14 * $u), (int) ($cy - 13 * $u),
            (int) ($cx + 13 * $u), (int) ($cy + 14 * $u),
            $blanco
        );
        imagesetthickness($img, 1);

        $bulbo = (int) (30 * $u);
        imagefilledellipse($img, (int) ($cx - 17 * $u), (int) ($cy - 16 * $u), $bulbo, $bulbo, $blanco);
        imagefilledellipse($img, (int) ($cx + 16 * $u), (int) ($cy + 17 * $u), $bulbo, $bulbo, $blanco);

        // Muescas verdes: le dan al auricular la curvatura caracteristica en vez de dejarlo
        // como una barra con dos pelotas.
        $muesca = (int) (17 * $u);
        imagefilledellipse($img, (int) ($cx - 27 * $u), (int) ($cy - 5 * $u), $muesca, $muesca, $verde);
        imagefilledellipse($img, (int) ($cx + 6 * $u), (int) ($cy + 27 * $u), $muesca, $muesca, $verde);
    }

    /** El ícono oficial de WhatsApp, como capa suelta para poder animarlo. */
    private static function pintarIconoSuelto(string $destino): void
    {
        $origen = base_path(self::MARCA_WHATSAPP);

        if (is_file($origen)) {
            copy($origen, $destino);
            return;
        }

        // Sin el archivo oficial se cae al dibujo propio: peor, pero mejor que una barra
        // con un hueco vacío donde deberia ir el icono.
        $lado = 200;
        $img = imagecreatetruecolor($lado, $lado);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, $lado, $lado, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);
        self::iconoWhatsapp($img, (int) ($lado / 2), (int) ($lado / 2), (int) ($lado * 0.92));
        imagepng($img, $destino);
        imagedestroy($img);
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
