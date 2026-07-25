<?php

namespace App\Services\Publicidad;

use App\Models\Aliado;
use Illuminate\Support\Facades\Storage;

/**
 * Arma el flyer promocional de un plan: foto del plan arriba (generada por IA, distinta según
 * el plan) y abajo un panel con el nombre comercial, los servicios incluidos, el precio REAL
 * y el botón de WhatsApp. Se compone con GD — igual que LogoWatermarker — porque los modelos
 * de imagen no saben escribir texto ni cifras confiables, y aquí el precio no puede fallar.
 *
 * Formato 1080x1350 (4:5), el vertical del feed de Instagram/Facebook en celular.
 */
class FlyerPlanBuilder
{
    private const ANCHO = 1080;
    private const ALTO  = 1350;

    /** Dónde termina la foto y empieza el panel de contenido. */
    private const Y_PANEL = 742;

    // Poppins (licencia OFL, incluida en el repo para que se vea igual en local y en el
    // servidor). DejaVu, la que trae dompdf, es legible pero no tiene terminado de marca.
    private const FUENTE_BOLD    = 'resources/fonts/Poppins-Bold.ttf';
    private const FUENTE_MEDIA   = 'resources/fonts/Poppins-SemiBold.ttf';
    private const FUENTE_REGULAR = 'resources/fonts/Poppins-Medium.ttf';

    /**
     * @param string $rutaHero Ruta (disk public) de la imagen generada para este plan.
     * @param array  $datos    ['nombre','gancho','servicios'=>[],'valor_mensual','costo_afiliacion','en_promocion','whatsapp']
     * @return ?string Ruta (disk public) del flyer generado, o null si algo falló.
     */
    public static function construir(string $rutaHero, Aliado $aliado, array $datos): ?string
    {
        if (!Storage::disk('public')->exists($rutaHero)) {
            return null;
        }

        try {
            $lienzo = imagecreatetruecolor(self::ANCHO, self::ALTO);
            imagealphablending($lienzo, true);
            imagesavealpha($lienzo, true);

            [$r, $g, $b] = self::hexARgb($aliado->color_primario ?: '#2563eb');
            // Base oscura derivada de la marca: el texto blanco siempre contrasta.
            $oscuro = self::mezclar([$r, $g, $b], [12, 18, 32], 0.72);

            self::pintarHero($lienzo, $rutaHero);
            self::pintarPanel($lienzo, $oscuro, [$r, $g, $b]);
            self::pintarContenido($lienzo, $datos, [$r, $g, $b], $oscuro);
            self::pintarLogo($lienzo, $aliado);

            $destino = 'publicidad/flyers/' . uniqid('flyer_', true) . '.png';
            Storage::disk('public')->makeDirectory('publicidad/flyers');
            imagepng($lienzo, Storage::disk('public')->path($destino), 6);
            imagedestroy($lienzo);

            return $destino;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Foto de fondo recortada "cover" en la zona superior, con degradado hacia el panel. */
    private static function pintarHero($lienzo, string $rutaHero): void
    {
        $hero = self::cargar(Storage::disk('public')->path($rutaHero));
        if (!$hero) {
            return;
        }

        $altoHero = self::Y_PANEL + 60; // se mete un poco bajo el panel para que el degradado tape el corte
        $escala   = max(self::ANCHO / imagesx($hero), $altoHero / imagesy($hero));
        $wDest    = (int) round(imagesx($hero) * $escala);
        $hDest    = (int) round(imagesy($hero) * $escala);

        imagecopyresampled(
            $lienzo, $hero,
            (int) round((self::ANCHO - $wDest) / 2), 0,
            0, 0,
            $wDest, $hDest, imagesx($hero), imagesy($hero)
        );
        imagedestroy($hero);
    }

    /** Panel inferior con degradado desde transparente (sobre la foto) hasta el color sólido. */
    private static function pintarPanel($lienzo, array $oscuro, array $marca): void
    {
        // Transición suave sobre la foto: 150px de degradado antes del panel.
        $inicioFade = self::Y_PANEL - 150;
        for ($y = $inicioFade; $y < self::Y_PANEL; $y++) {
            $t = ($y - $inicioFade) / 150;
            $alpha = (int) round(127 - ($t * $t) * 127);
            $col = imagecolorallocatealpha($lienzo, $oscuro[0], $oscuro[1], $oscuro[2], $alpha);
            imageline($lienzo, 0, $y, self::ANCHO, $y, $col);
        }

        // Cuerpo del panel: del color base a un tono ligeramente más profundo abajo.
        $fondoAbajo = self::mezclar($oscuro, [0, 0, 0], 0.25);
        for ($y = self::Y_PANEL; $y < self::ALTO; $y++) {
            $t = ($y - self::Y_PANEL) / (self::ALTO - self::Y_PANEL);
            $col = imagecolorallocate(
                $lienzo,
                (int) round($oscuro[0] + ($fondoAbajo[0] - $oscuro[0]) * $t),
                (int) round($oscuro[1] + ($fondoAbajo[1] - $oscuro[1]) * $t),
                (int) round($oscuro[2] + ($fondoAbajo[2] - $oscuro[2]) * $t)
            );
            imageline($lienzo, 0, $y, self::ANCHO, $y, $col);
        }

        // Filete de marca que separa foto y panel.
        $filete = imagecolorallocate($lienzo, $marca[0], $marca[1], $marca[2]);
        imagefilledrectangle($lienzo, 0, self::Y_PANEL - 5, self::ANCHO, self::Y_PANEL - 1, $filete);
    }

    private static function pintarContenido($lienzo, array $datos, array $marca, array $oscuro): void
    {
        $margen = 72;
        $anchoUtil = self::ANCHO - $margen * 2;
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $acento = imagecolorallocate($lienzo, ...self::aclarar($marca, 0.35));
        $tenue  = imagecolorallocate($lienzo, 193, 203, 221);

        // Posiciones fijas: imagettftext ancla en la LÍNEA BASE, así que se calculan de
        // antemano para que nada se monte encima de lo siguiente.
        $yTitulo = self::Y_PANEL + 104;
        $yFilete = self::Y_PANEL + 124;
        $yGancho = self::Y_PANEL + 178;
        $yChips  = self::Y_PANEL + 208;
        $yEtiqueta = self::Y_PANEL + 306;
        $yPrecio   = self::Y_PANEL + 396;
        $ySubprecio = self::Y_PANEL + 436;

        // ── Nombre comercial del plan ─────────────────────────────────────
        // Tracking negativo: en mayúsculas grandes las letras quedan mejor juntas.
        $nombre = mb_strtoupper($datos['nombre']);
        $tamTitulo = self::tamanoQueCabe($nombre, $anchoUtil, 58, 34, self::FUENTE_BOLD, -1.5);
        self::texto($lienzo, $nombre, $margen, $yTitulo, $tamTitulo, $blanco, self::FUENTE_BOLD, -1.5);

        // Subrayado corto de acento bajo el título.
        imagefilledrectangle($lienzo, $margen, $yFilete, $margen + 96, $yFilete + 7, $acento);

        // ── Gancho (siempre en una línea: se achica hasta que quepa) ───────
        self::texto($lienzo, $datos['gancho'], $margen, $yGancho, self::tamanoQueCabe($datos['gancho'], $anchoUtil, 28, 18, self::FUENTE_REGULAR), $tenue, self::FUENTE_REGULAR);

        // ── Servicios incluidos, con chulo ────────────────────────────────
        self::pintarChips($lienzo, $datos['servicios'], $margen, $yChips, $anchoUtil, $marca, $oscuro, $blanco);

        // ── Precio ────────────────────────────────────────────────────────
        $afiliacion = (float) ($datos['costo_afiliacion'] ?? 0);
        $mensual    = (float) ($datos['valor_mensual'] ?? 0);

        // Etiquetas en versalitas espaciadas: es lo que más "termina" un diseño.
        if ($afiliacion > 0) {
            // El primer mes solo cobra afiliación: es el gancho real, y debajo el valor mensual.
            self::texto($lienzo, 'AFÍLIATE ESTE MES POR', $margen, $yEtiqueta, 21, $acento, self::FUENTE_BOLD, 3.5);
            $precio = '$' . self::pesos($afiliacion);
            $sub = 'y desde el mes siguiente $' . self::pesos($mensual) . ' al mes';
        } else {
            self::texto($lienzo, 'DESDE', $margen, $yEtiqueta, 21, $acento, self::FUENTE_BOLD, 3.5);
            $precio = '$' . self::pesos($mensual);
            $sub = 'al mes · sin costo de afiliación';
        }

        // Sombra suave bajo la cifra: le da peso y la despega del fondo.
        $tamPrecio = self::tamanoQueCabe($precio, $anchoUtil, 82, 54, self::FUENTE_BOLD, -2);
        $sombra = imagecolorallocatealpha($lienzo, 0, 0, 0, 95);
        self::texto($lienzo, $precio, $margen + 3, $yPrecio + 4, $tamPrecio, $sombra, self::FUENTE_BOLD, -2);
        self::texto($lienzo, $precio, $margen, $yPrecio, $tamPrecio, $blanco, self::FUENTE_BOLD, -2);

        // El precio de la ARL cambia por nivel de riesgo: sin decir cuál, el valor engaña.
        if (!empty($datos['nivel_arl'])) {
            $sub .= ' · ARL riesgo ' . $datos['nivel_arl'];
        }
        self::texto($lienzo, $sub, $margen, $ySubprecio, self::tamanoQueCabe($sub, $anchoUtil, 25, 16, self::FUENTE_REGULAR), $tenue, self::FUENTE_REGULAR);

        // Sello de promoción, arriba a la derecha del panel.
        if (!empty($datos['en_promocion'])) {
            self::pintarSello($lienzo, 'PROMO', self::ANCHO - $margen, self::Y_PANEL + 56);
        }

        // ── Botón de WhatsApp, anclado abajo ──────────────────────────────
        if (!empty($datos['whatsapp'])) {
            self::pintarBotonWhatsapp($lienzo, $datos['whatsapp'], $margen, self::ALTO - 134, $anchoUtil);
        }
    }

    /**
     * Fila de chips "✓ EPS". Si no caben a lo ancho, se achican juntos antes que salirse.
     * El fondo se calcula plano (mezcla ya resuelta contra el panel) en vez de usar alfa:
     * con alfa, las esquinas redondeadas se superponen y se ven círculos más oscuros.
     */
    private static function pintarChips($lienzo, array $servicios, int $x0, int $y, int $anchoMax, array $marca, array $oscuro, int $blanco): void
    {
        $tam = 24;
        $separacion = 16;

        // Achicar hasta que la fila entera quepa.
        while ($tam > 15 && self::anchoFilaChips($servicios, $tam, $separacion) > $anchoMax) {
            $tam -= 1;
        }

        $altura = (int) round($tam * 2.4);
        $fondo  = imagecolorallocate($lienzo, ...self::mezclar($oscuro, $marca, 0.55));
        $verde  = imagecolorallocate($lienzo, 74, 222, 128);
        $x = $x0;

        foreach ($servicios as $servicio) {
            $anchoTexto = self::anchoTexto($servicio, $tam, self::FUENTE_BOLD);
            $ancho = $anchoTexto + $altura + 44;

            self::rectRedondeado($lienzo, $x, $y, $ancho, $altura, (int) round($altura / 2), $fondo);

            // Chulo dibujado a mano (dos trazos): no depende del glifo de la fuente.
            $cx = $x + (int) round($altura * 0.52);
            $cy = $y + (int) round($altura / 2);
            $u  = $tam / 24;
            imagesetthickness($lienzo, max(3, (int) round(5 * $u)));
            imageline($lienzo, (int) ($cx - 10 * $u), (int) $cy, (int) ($cx - 3 * $u), (int) ($cy + 8 * $u), $verde);
            imageline($lienzo, (int) ($cx - 3 * $u), (int) ($cy + 8 * $u), (int) ($cx + 11 * $u), (int) ($cy - 9 * $u), $verde);
            imagesetthickness($lienzo, 1);

            self::texto($lienzo, $servicio, $x + $altura + 16, $cy + (int) round($tam * 0.36), $tam, $blanco, self::FUENTE_BOLD);

            $x += $ancho + $separacion;
        }
    }

    private static function anchoFilaChips(array $servicios, int $tam, int $separacion): int
    {
        $altura = (int) round($tam * 2.4);
        $total = -$separacion;
        foreach ($servicios as $s) {
            $total += self::anchoTexto($s, $tam, self::FUENTE_BOLD) + $altura + 44 + $separacion;
        }
        return $total;
    }

    /** Logo del aliado arriba a la izquierda, sobre la foto. */
    private static function pintarLogo($lienzo, Aliado $aliado): void
    {
        $margen = 52;

        // La foto es impredecible arriba (cielo claro o interior oscuro), así que se mide el
        // brillo real de esa esquina y se elige la variante que contrasta — con respaldo al
        // logo normal si el aliado no subió la variante o el archivo no está en este disco.
        // El recorte configurado describe el LOCKUP de marca (ícono + nombre + eslogan). Aplicarlo
        // al logo cuadrado de respaldo duplica parte del ícono, así que solo va con los lockups.
        $zonaClara = self::zonaClara($lienzo, $margen, $margen, 280, 110);
        $candidatos = $zonaClara
            ? [[$aliado->logo_marca_claro, true], [$aliado->logo, false], [$aliado->logo_oscuro, true]]
            : [[$aliado->logo_oscuro, true], [$aliado->logo_marca_claro, true], [$aliado->logo, false]];

        $rutaLogo = null;
        $esLockup = false;
        foreach ($candidatos as [$candidato, $lockup]) {
            if ($candidato && Storage::disk('public')->exists($candidato)) {
                $rutaLogo = $candidato;
                $esLockup = $lockup;
                break;
            }
        }
        if (!$rutaLogo) {
            return;
        }

        $logo = LogoWatermarker::cargarConRecorte(
            Storage::disk('public')->path($rutaLogo),
            $esLockup ? $aliado->logo_marca_recorte : null
        );
        if (!$logo) {
            return;
        }

        $escala = min(280 / imagesx($logo), 110 / imagesy($logo));
        $w = (int) round(imagesx($logo) * $escala);
        $h = (int) round(imagesy($logo) * $escala);

        $redim = imagecreatetruecolor($w, $h);
        imagealphablending($redim, false);
        imagesavealpha($redim, true);
        imagefilledrectangle($redim, 0, 0, $w, $h, imagecolorallocatealpha($redim, 0, 0, 0, 127));
        imagecopyresampled($redim, $logo, 0, 0, 0, 0, $w, $h, imagesx($logo), imagesy($logo));

        imagealphablending($lienzo, true);
        imagecopy($lienzo, $redim, $margen, $margen, 0, 0, $w, $h);

        imagedestroy($logo);
        imagedestroy($redim);
    }

    private static function pintarBotonWhatsapp($lienzo, string $numero, int $x, int $y, int $ancho): void
    {
        $altura = 96;
        $verde  = imagecolorallocate($lienzo, 37, 211, 102);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);

        self::rectRedondeado($lienzo, $x, $y, $ancho, $altura, 48, $verde);

        $texto = 'Escríbenos: ' . $numero;
        $tam   = self::tamanoQueCabe($texto, $ancho - 160, 30, 18);
        $anchoTexto = self::anchoTexto($texto, $tam, self::FUENTE_BOLD);

        // Globo de chat + texto, centrados juntos como un solo bloque.
        $anchoBloque = $anchoTexto + 78;
        $inicio = (int) ($x + ($ancho - $anchoBloque) / 2);
        $cy = (int) ($y + $altura / 2);

        // Globo blanco con su colita, dibujado a mano (no hay glifo confiable para esto).
        $gw = 44; $gh = 36;
        $gx = $inicio; $gy = $cy - (int) ($gh / 2) - 3;
        self::rectRedondeado($lienzo, $gx, $gy, $gw, $gh, 11, $blanco);
        imagefilledpolygon($lienzo, [
            $gx + 12, $gy + $gh - 2,
            $gx + 30, $gy + $gh - 2,
            $gx + 15, $gy + $gh + 12,
        ], $blanco);
        // Tres puntitos verdes dentro del globo.
        foreach ([13, 22, 31] as $dx) {
            imagefilledellipse($lienzo, $gx + $dx, $gy + (int) ($gh / 2), 6, 6, $verde);
        }

        self::texto($lienzo, $texto, $inicio + 78, (int) ($cy + $tam * 0.36), $tam, $blanco, self::FUENTE_BOLD);
    }

    private static function pintarSello($lienzo, string $texto, int $xDerecha, int $y): void
    {
        $tam  = 26;
        $ancho = self::anchoTexto($texto, $tam, self::FUENTE_BOLD) + 52;
        $altura = 60;
        $x = $xDerecha - $ancho;

        $fondo  = imagecolorallocate($lienzo, 250, 204, 21);
        $tinta  = imagecolorallocate($lienzo, 30, 27, 15);
        self::rectRedondeado($lienzo, $x, $y, $ancho, $altura, 12, $fondo);
        self::texto($lienzo, $texto, $x + 26, (int) ($y + $altura / 2 + $tam / 2 - 2), $tam, $tinta, self::FUENTE_BOLD);
    }

    // ─── Utilidades de dibujo ─────────────────────────────────────────────

    private static function rectRedondeado($lienzo, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        $r = (int) min($r, $w / 2, $h / 2);
        imagefilledrectangle($lienzo, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($lienzo, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        $d = $r * 2;
        imagefilledellipse($lienzo, $x + $r, $y + $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $w - $r, $y + $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $r, $y + $h - $r, $d, $d, $color);
        imagefilledellipse($lienzo, $x + $w - $r, $y + $h - $r, $d, $d, $color);
    }

    /**
     * Dibuja texto. Con $tracking != 0 se pinta letra por letra para controlar el espaciado
     * (GD no lo soporta nativo): positivo separa —para versalitas— y negativo junta, que es
     * lo que necesitan los títulos grandes en mayúsculas.
     */
    private static function texto($lienzo, string $texto, int $x, int $y, int $tam, int $color, string $fuente, float $tracking = 0): void
    {
        $ruta = base_path($fuente);

        if (abs($tracking) < 0.01) {
            imagettftext($lienzo, $tam, 0, $x, $y, $color, $ruta, $texto);
            return;
        }

        $cursor = (float) $x;
        foreach (preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $letra) {
            imagettftext($lienzo, $tam, 0, (int) round($cursor), $y, $color, $ruta, $letra);
            $caja = imagettfbbox($tam, 0, $ruta, $letra);
            $cursor += ($caja[2] - $caja[0]) + $tracking;
        }
    }

    /** ¿La región es clara en promedio? Muestreo en cuadrícula, para elegir variante de logo. */
    private static function zonaClara($imagen, int $x, int $y, int $w, int $h): bool
    {
        $suma = 0;
        $n = 0;
        for ($i = 0; $i < $w; $i += 8) {
            for ($j = 0; $j < $h; $j += 8) {
                $c = @imagecolorat($imagen, min($x + $i, self::ANCHO - 1), min($y + $j, self::ALTO - 1));
                $suma += (($c >> 16) & 0xFF) * 0.299 + (($c >> 8) & 0xFF) * 0.587 + ($c & 0xFF) * 0.114;
                $n++;
            }
        }
        return $n > 0 && ($suma / $n) > 140;
    }

    /** Ancho real del texto, contando el tracking con el que se va a dibujar. */
    private static function anchoTexto(string $texto, int $tam, string $fuente, float $tracking = 0): int
    {
        $ruta = base_path($fuente);

        if (abs($tracking) < 0.01) {
            $caja = imagettfbbox($tam, 0, $ruta, $texto);
            return (int) abs($caja[2] - $caja[0]);
        }

        $total = 0.0;
        foreach (preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $letra) {
            $caja = imagettfbbox($tam, 0, $ruta, $letra);
            $total += ($caja[2] - $caja[0]) + $tracking;
        }
        return (int) round(max(0, $total - $tracking));
    }

    /** Baja el tamaño de fuente hasta que el texto quepa en el ancho dado. */
    private static function tamanoQueCabe(string $texto, int $anchoMax, int $tamInicial, int $tamMin, string $fuente = self::FUENTE_BOLD, float $tracking = 0): int
    {
        for ($tam = $tamInicial; $tam > $tamMin; $tam--) {
            if (self::anchoTexto($texto, $tam, $fuente, $tracking) <= $anchoMax) {
                return $tam;
            }
        }
        return $tamMin;
    }

    private static function pesos(float $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexARgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [37, 99, 235];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** Mezcla $a hacia $b en proporción $t (0=a, 1=b). */
    private static function mezclar(array $a, array $b, float $t): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $t),
            (int) round($a[1] + ($b[1] - $a[1]) * $t),
            (int) round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }

    private static function aclarar(array $rgb, float $t): array
    {
        return self::mezclar($rgb, [255, 255, 255], $t);
    }

    private static function cargar(string $path)
    {
        $info = @getimagesize($path);
        return match ($info['mime'] ?? null) {
            'image/png'  => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => null,
        };
    }
}
