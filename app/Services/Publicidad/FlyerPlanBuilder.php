<?php

namespace App\Services\Publicidad;

use App\Models\Aliado;
use Illuminate\Support\Facades\Storage;

/**
 * Arma el flyer promocional de un plan: encabezado con la marca, titular con una palabra
 * manuscrita, precio destacado, foto del plan, tarjetas de los servicios incluidos, mensaje
 * de cobertura nacional con el mapa de Colombia y llamado a la acción.
 *
 * Se compone con GD (no con IA) porque el precio y los nombres no pueden salir mal: los
 * modelos de imagen deforman texto y cifras. La IA solo aporta la fotografía.
 *
 * Formato 1080x1350 (4:5), el vertical del feed de Instagram/Facebook en celular.
 */
class FlyerPlanBuilder
{
    private const ANCHO = 1080;
    private const ALTO  = 1350;
    private const MARGEN = 64;

    // Poppins (licencia OFL, versionada en el repo para que se vea igual en local y en el
    // servidor). Kaushan Script aporta la palabra manuscrita del titular.
    private const FUENTE_BOLD    = 'resources/fonts/Poppins-Bold.ttf';
    private const FUENTE_MEDIA   = 'resources/fonts/Poppins-SemiBold.ttf';
    private const FUENTE_REGULAR = 'resources/fonts/Poppins-Medium.ttf';
    private const FUENTE_SCRIPT  = 'resources/fonts/KaushanScript-Regular.ttf';

    /** Qué explica cada servicio en su tarjeta. */
    private const DESCRIPCION_SERVICIO = [
        'EPS'     => 'Salud para ti y los tuyos',
        'ARL'     => 'Cubierto ante accidentes',
        'Pensión' => 'Tu futuro asegurado',
        'Caja'    => 'Subsidios y recreación',
    ];

    private static array $paleta = [];

    /** Plantillas disponibles: misma información, distinta puesta en página. */
    public const ESTILOS = [
        'claro'     => 'Claro — foto al lado, fondo blanco con acentos',
        'inmersivo' => 'Inmersivo — foto a toda página con texto encima',
        'bloque'    => 'Bloque — foto arriba y panel de color abajo',
    ];

    /**
     * Las que entran en la rotación automática del piloto. "Bloque" queda disponible para
     * elegirla a mano, pero no sale sola.
     */
    public const ESTILOS_ROTACION = ['claro', 'inmersivo'];

    /**
     * @param string $rutaHero Ruta (disk public) de la imagen generada para este plan.
     * @param array  $datos    ['nombre','titular','gancho','servicios','valor_mensual',
     *                          'costo_afiliacion','en_promocion','nivel_arl','cta','estilo']
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

            self::prepararPaleta($lienzo, $aliado);

            $estilo = $datos['estilo'] ?? 'claro';
            match ($estilo) {
                'inmersivo' => self::componerInmersivo($lienzo, $rutaHero, $aliado, $datos),
                'bloque'    => self::componerBloque($lienzo, $rutaHero, $aliado, $datos),
                default     => self::componerClaro($lienzo, $rutaHero, $aliado, $datos),
            };

            $destino = 'publicidad/flyers/' . uniqid('flyer_', true) . '.png';
            Storage::disk('public')->makeDirectory('publicidad/flyers');
            imagepng($lienzo, Storage::disk('public')->path($destino), 6);
            imagedestroy($lienzo);

            return $destino;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Plantilla 1: fondo claro, foto en tarjeta al lado del texto. */
    private static function componerClaro($lienzo, string $rutaHero, Aliado $aliado, array $datos): void
    {
        imagefilledrectangle($lienzo, 0, 0, self::ANCHO, self::ALTO, self::$paleta['fondo']);
        self::pintarFondo($lienzo);

        $yCabecera   = self::pintarCabecera($lienzo, $aliado);
        $yBeneficios = self::pintarBloqueSuperior($lienzo, $rutaHero, $datos, $yCabecera);
        $yCobertura  = self::pintarTarjetasServicios($lienzo, $datos['servicios'], $yBeneficios);
        self::pintarCobertura($lienzo, $yCobertura);
        self::pintarLlamado($lienzo, $datos['cta'] ?? '¡Afíliate o Trasládate Ya!');
    }

    /** Plantilla 2: foto a toda página, oscurecida abajo, con el texto encima. */
    private static function componerInmersivo($lienzo, string $rutaHero, Aliado $aliado, array $datos): void
    {
        self::pintarFotoCompleta($lienzo, $rutaHero);

        // Velo oscuro: un toque arriba para el logo y una rampa fuerte desde el centro hacia
        // abajo. Sin esta profundidad el texto blanco se pierde sobre las zonas claras de la
        // foto (la foto es impredecible, así que el velo tiene que ser generoso).
        $navy = self::$paleta['rgb_navy'];
        $inicioRampa = (int) round(self::ALTO * 0.30);
        $finRampa    = (int) round(self::ALTO * 0.60);

        for ($y = 0; $y < self::ALTO; $y++) {
            $arriba = max(0.0, 0.58 * (1 - $y / 340) ** 1.2);
            $abajo  = min(1.0, max(0.0, ($y - $inicioRampa) / ($finRampa - $inicioRampa))) * 0.93;
            $opacidad = min(0.95, max($arriba, $abajo));
            if ($opacidad <= 0.01) continue;

            $col = imagecolorallocatealpha($lienzo, $navy[0], $navy[1], $navy[2], (int) round(127 - $opacidad * 127));
            imageline($lienzo, 0, $y, self::ANCHO, $y, $col);
        }

        self::pintarCabeceraSobreFoto($lienzo, $aliado);

        // Titular + precio + servicios, apilados en la mitad inferior.
        $y = 706;
        [$fuerte, $script] = $datos['titular'] ?? [mb_strtoupper($datos['nombre']), ''];
        $anchoUtil = self::ANCHO - self::MARGEN * 2;

        $tamFuerte = self::tamanoQueCabe($fuerte, $anchoUtil, 52, 30, self::FUENTE_BOLD, -0.5);
        self::texto($lienzo, $fuerte, self::MARGEN, $y, $tamFuerte, self::$paleta['blanco'], self::FUENTE_BOLD, -0.5);

        if ($script !== '') {
            $tamScript = self::tamanoQueCabe($script, $anchoUtil, 80, 40, self::FUENTE_SCRIPT);
            $y += (int) round($tamScript * 1.02) + 16;
            self::texto($lienzo, $script, self::MARGEN - 4, $y, $tamScript, self::$paleta['acentoClaro'], self::FUENTE_SCRIPT);
        }

        $y += 46;
        $altoChips = self::pintarFilaServiciosPlana($lienzo, $datos['servicios'], self::MARGEN, $y);

        $y += $altoChips + 52;
        self::pintarPrecioEnLinea($lienzo, self::MARGEN, $y, $datos);

        self::pintarLlamado($lienzo, $datos['cta'] ?? '¡Afíliate o Trasládate Ya!');
    }

    /** Plantilla 3: foto arriba a sangre y panel de color sólido abajo. */
    private static function componerBloque($lienzo, string $rutaHero, Aliado $aliado, array $datos): void
    {
        $yCorte = 612;

        self::pintarFotoRecorte($lienzo, $rutaHero, 0, 0, self::ANCHO, $yCorte + 60);

        // Velo superior: la cabecera va sobre la foto y esta puede ser clara justo ahí.
        $navy = self::$paleta['rgb_navy'];
        for ($y = 0; $y < 240; $y++) {
            $opacidad = 0.58 * (1 - $y / 240) ** 1.4;
            if ($opacidad <= 0.01) continue;
            $col = imagecolorallocatealpha($lienzo, $navy[0], $navy[1], $navy[2], (int) round(127 - $opacidad * 127));
            imageline($lienzo, 0, $y, self::ANCHO, $y, $col);
        }

        // Panel inferior en color de marca oscuro, con el borde superior inclinado.
        $navy = self::$paleta['navy'];
        imagefilledpolygon($lienzo, [
            0, $yCorte + 54,
            self::ANCHO, $yCorte - 26,
            self::ANCHO, self::ALTO,
            0, self::ALTO,
        ], $navy);

        self::pintarCabeceraSobreFoto($lienzo, $aliado);

        $anchoUtil = self::ANCHO - self::MARGEN * 2;
        $y = $yCorte + 118;

        [$fuerte, $script] = $datos['titular'] ?? [mb_strtoupper($datos['nombre']), ''];
        $tamFuerte = self::tamanoQueCabe($fuerte, $anchoUtil, 46, 28, self::FUENTE_BOLD, -0.5);
        self::texto($lienzo, $fuerte, self::MARGEN, $y, $tamFuerte, self::$paleta['blanco'], self::FUENTE_BOLD, -0.5);

        if ($script !== '') {
            $tamScript = self::tamanoQueCabe($script, $anchoUtil, 70, 36, self::FUENTE_SCRIPT);
            $y += (int) round($tamScript * 1.02) + 12;
            self::texto($lienzo, $script, self::MARGEN - 4, $y, $tamScript, self::$paleta['acentoClaro'], self::FUENTE_SCRIPT);
        }

        $y += 42;
        $altoChips = self::pintarFilaServiciosPlana($lienzo, $datos['servicios'], self::MARGEN, $y);

        $y += $altoChips + 48;
        self::pintarPrecioEnLinea($lienzo, self::MARGEN, $y, $datos);

        self::pintarLlamado($lienzo, $datos['cta'] ?? '¡Afíliate o Trasládate Ya!');
    }

    private static function prepararPaleta($lienzo, Aliado $aliado): void
    {
        [$r, $g, $b] = self::hexARgb($aliado->color_primario ?: '#2563eb');
        $marca  = [$r, $g, $b];
        $navy   = self::mezclar($marca, [10, 15, 30], 0.74);

        self::$paleta = [
            'rgb_marca'  => $marca,
            'rgb_navy'   => $navy,
            'fondo'      => imagecolorallocate($lienzo, 255, 255, 255),
            'marca'      => imagecolorallocate($lienzo, ...$marca),
            'navy'       => imagecolorallocate($lienzo, ...$navy),
            'tinta'      => imagecolorallocate($lienzo, ...self::mezclar($navy, [0, 0, 0], 0.15)),
            'gris'       => imagecolorallocate($lienzo, 108, 122, 145),
            'suave'      => imagecolorallocate($lienzo, ...self::mezclar($marca, [255, 255, 255], 0.92)),
            'borde'      => imagecolorallocate($lienzo, ...self::mezclar($marca, [255, 255, 255], 0.80)),
            'blanco'     => imagecolorallocate($lienzo, 255, 255, 255),
            'verde'      => imagecolorallocate($lienzo, 22, 163, 74),
            // Sobre el azul oscuro de la caja de precio, un tono claro de marca para las
            // etiquetas: contrasta sin robarle atención a la cifra.
            'acentoClaro' => imagecolorallocate($lienzo, ...self::mezclar($marca, [255, 255, 255], 0.55)),
        ];
    }

    /**
     * Fondo decorado: degradado suave de arriba a abajo, una forma grande de marca detrás de
     * la foto y una retícula de puntos. Da presencia sin competir con el contenido.
     */
    private static function pintarFondo($lienzo): void
    {
        $arriba = self::mezclar(self::$paleta['rgb_marca'], [255, 255, 255], 0.90);
        $abajo  = [255, 255, 255];

        // Degradado vertical: arranca con un velo de marca y se abre a blanco hacia el centro.
        $altoDegradado = (int) round(self::ALTO * 0.62);
        for ($y = 0; $y < $altoDegradado; $y++) {
            $t = ($y / $altoDegradado) ** 0.75;
            $col = imagecolorallocate($lienzo, ...self::mezclar($arriba, $abajo, $t));
            imageline($lienzo, 0, $y, self::ANCHO, $y, $col);
        }

        // Círculo de marca detrás de la foto (queda parcialmente fuera del lienzo).
        $circulo = imagecolorallocate($lienzo, ...self::mezclar(self::$paleta['rgb_marca'], [255, 255, 255], 0.80));
        imagefilledellipse($lienzo, self::ANCHO - 40, 470, 760, 760, $circulo);

        // Retícula de puntos: textura sutil abajo a la izquierda.
        $punto = imagecolorallocate($lienzo, ...self::mezclar(self::$paleta['rgb_marca'], [255, 255, 255], 0.62));
        for ($fila = 0; $fila < 5; $fila++) {
            for ($col = 0; $col < 7; $col++) {
                imagefilledellipse($lienzo, 42 + $col * 26, self::ALTO - 250 + $fila * 26, 6, 6, $punto);
            }
        }

        // Franja de marca al pie: asienta el botón y cierra la composición.
        $franja = imagecolorallocate($lienzo, ...self::mezclar(self::$paleta['rgb_marca'], [255, 255, 255], 0.88));
        imagefilledrectangle($lienzo, 0, self::ALTO - 176, self::ANCHO, self::ALTO, $franja);
    }

    /** Logo + nombre + eslogan. Devuelve la Y donde termina. */
    private static function pintarCabecera($lienzo, Aliado $aliado): int
    {
        $x = self::MARGEN;
        $yBase = 58;
        $altoLogo = 116;

        $logo = self::cargarLogo($aliado);
        if ($logo) {
            $escala = min(290 / imagesx($logo), $altoLogo / imagesy($logo));
            $w = (int) round(imagesx($logo) * $escala);
            $h = (int) round(imagesy($logo) * $escala);

            $redim = imagecreatetruecolor($w, $h);
            imagealphablending($redim, false);
            imagesavealpha($redim, true);
            imagefilledrectangle($redim, 0, 0, $w, $h, imagecolorallocatealpha($redim, 0, 0, 0, 127));
            imagecopyresampled($redim, $logo, 0, 0, 0, 0, $w, $h, imagesx($logo), imagesy($logo));
            imagealphablending($lienzo, true);
            imagecopy($lienzo, $redim, $x, $yBase, 0, 0, $w, $h);

            imagedestroy($logo);
            imagedestroy($redim);
            $x += $w + 24;
        }

        // Nombre de la empresa y, si lo tiene configurado, su eslogan debajo.
        $anchoTexto = self::ANCHO - $x - self::MARGEN;
        $nombre  = mb_strtoupper($aliado->nombre);
        $eslogan = trim((string) $aliado->eslogan);

        // Sin eslogan el nombre se centra verticalmente contra el logo, para que no quede
        // colgando de la línea superior.
        $yNombre = $eslogan !== '' ? $yBase + 58 : $yBase + 72;
        $tam = self::tamanoQueCabe($nombre, $anchoTexto, 36, 20, self::FUENTE_BOLD, 1.5);
        self::texto($lienzo, $nombre, $x, $yNombre, $tam, self::$paleta['navy'], self::FUENTE_BOLD, 1.5);

        if ($eslogan !== '') {
            self::texto($lienzo, $eslogan, $x, $yBase + 90, self::tamanoQueCabe($eslogan, $anchoTexto, 17, 12, self::FUENTE_REGULAR, 0.6), self::$paleta['gris'], self::FUENTE_REGULAR, 0.6);
        }

        return $yBase + $altoLogo + 14;
    }

    /**
     * Titular + precio a la izquierda y foto a la derecha. Devuelve la Y donde termina el bloque.
     */
    private static function pintarBloqueSuperior($lienzo, string $rutaHero, array $datos, int $y): int
    {
        $anchoCol = 470;
        $xFoto    = self::MARGEN + $anchoCol + 28;
        $anchoFoto = self::ANCHO - $xFoto - self::MARGEN;
        $altoFoto  = 560;

        self::pintarFoto($lienzo, $rutaHero, $xFoto, $y, $anchoFoto, $altoFoto);

        // ── Titular: parte fuerte en mayúsculas + palabra manuscrita ──────
        [$fuerte, $script] = $datos['titular'] ?? [mb_strtoupper($datos['nombre']), ''];

        $yT = $y + 58;
        $tamFuerte = self::tamanoQueCabe($fuerte, $anchoCol, 44, 26, self::FUENTE_BOLD, -0.5);
        self::texto($lienzo, $fuerte, self::MARGEN, $yT, $tamFuerte, self::$paleta['navy'], self::FUENTE_BOLD, -0.5);

        if ($script !== '') {
            // La manuscrita tiene ascendentes altas: sin este respiro se monta sobre la
            // línea de arriba (imagettftext ancla en la base, no en la caja del glifo).
            $tamScript = self::tamanoQueCabe($script, $anchoCol + 20, 74, 38, self::FUENTE_SCRIPT);
            $yT += (int) round($tamScript * 1.02) + 18;
            self::texto($lienzo, $script, self::MARGEN - 4, $yT, $tamScript, self::$paleta['marca'], self::FUENTE_SCRIPT);
            $yT += (int) round($tamScript * 0.34);
        }

        // Gancho de apoyo: ocupa el aire entre el titular y el precio, y de paso da el
        // argumento de venta en una lectura corta.
        $yT += 54;
        foreach (array_slice(self::envolver($datos['gancho'], $anchoCol, 21, self::FUENTE_REGULAR), 0, 3) as $linea) {
            self::texto($lienzo, $linea, self::MARGEN, $yT, 21, self::$paleta['gris'], self::FUENTE_REGULAR, 0.2);
            $yT += 32;
        }

        // ── Recuadro de precio, anclado al pie de la columna ──────────────
        $altoCaja = !empty($datos['nivel_arl']) ? 268 : 240;
        $yCaja = $y + $altoFoto - $altoCaja;
        self::pintarCajaPrecio($lienzo, self::MARGEN, $yCaja, $anchoCol, $altoCaja, $datos);

        return $y + $altoFoto + 30;
    }

    /** Foto con esquinas redondeadas y sombra suave. */
    private static function pintarFoto($lienzo, string $ruta, int $x, int $y, int $w, int $h): void
    {
        $foto = self::cargar(Storage::disk('public')->path($ruta));
        if (!$foto) {
            return;
        }

        // Sombra: rectángulo redondeado gris claro, desplazado.
        $sombra = imagecolorallocatealpha($lienzo, ...array_merge(self::$paleta['rgb_navy'], [108]));
        self::rectRedondeado($lienzo, $x + 6, $y + 10, $w, $h, 34, $sombra);

        // Recorte "cover" al tamaño del marco.
        $escala = max($w / imagesx($foto), $h / imagesy($foto));
        $wDest = (int) round(imagesx($foto) * $escala);
        $hDest = (int) round(imagesy($foto) * $escala);
        $marco = imagecreatetruecolor($w, $h);
        imagecopyresampled($marco, $foto, (int) round(($w - $wDest) / 2), (int) round(($h - $hDest) / 2), 0, 0, $wDest, $hDest, imagesx($foto), imagesy($foto));
        imagedestroy($foto);

        // Máscara de esquinas: se pintan del color de fondo sobre las esquinas del marco.
        self::recortarEsquinas($marco, 34, self::$paleta['rgb_navy']);

        imagecopy($lienzo, $marco, $x, $y, 0, 0, $w, $h);
        imagedestroy($marco);
    }

    /**
     * Redondea las esquinas de una imagen pintando fuera del radio con el color de fondo del
     * lienzo. Es la forma barata de conseguir el marco redondeado sin canal alfa por píxel.
     */
    private static function recortarEsquinas($imagen, int $r, array $rgbSombra): void
    {
        $w = imagesx($imagen);
        $h = imagesy($imagen);
        $fondo = imagecolorallocate($imagen, 255, 255, 255);

        $esquinas = [[$r, $r, 0, 0], [$w - $r, $r, $w, 0], [$r, $h - $r, 0, $h], [$w - $r, $h - $r, $w, $h]];
        foreach ($esquinas as [$cx, $cy, $ex, $ey]) {
            for ($x = min($cx, $ex); $x < max($cx, $ex); $x++) {
                for ($y = min($cy, $ey); $y < max($cy, $ey); $y++) {
                    if ((($x - $cx) ** 2 + ($y - $cy) ** 2) > $r * $r) {
                        imagesetpixel($imagen, $x, $y, $fondo);
                    }
                }
            }
        }
    }

    /** Recuadro azul con el valor mensual grande y, si aplica, la afiliación del primer mes. */
    private static function pintarCajaPrecio($lienzo, int $x, int $y, int $w, int $alto, array $datos): void
    {
        $mensual    = (float) ($datos['valor_mensual'] ?? 0);
        $afiliacion = (float) ($datos['costo_afiliacion'] ?? 0);

        self::rectRedondeado($lienzo, $x, $y, $w, $alto, 26, self::$paleta['navy']);

        // Nombre del plan como cintillo dentro de la caja.
        self::texto($lienzo, mb_strtoupper($datos['nombre']), $x + 30, $y + 40, 18, self::$paleta['blanco'], self::FUENTE_MEDIA, 2.2);

        // El gancho comercial es lo que paga HOY: va primero y grande. El valor mensual
        // queda debajo, más pequeño, para que no compita con él.
        if ($afiliacion > 0) {
            $etiqueta = 'AFÍLIATE ESTE MES POR';
            $destacado = '$' . self::pesos($afiliacion);
            $secundario = 'Luego $' . self::pesos($mensual) . ' al mes';
        } else {
            $etiqueta = 'DESDE';
            $destacado = '$' . self::pesos($mensual);
            $secundario = 'al mes · sin costo de afiliación';
        }

        self::texto($lienzo, $etiqueta, $x + 30, $y + 76, 17, self::$paleta['acentoClaro'], self::FUENTE_MEDIA, 2.4);

        // La cifra se ancla en su línea base: hay que dejarle su propio alto por encima,
        // o se monta sobre la etiqueta.
        $tamPrecio = self::tamanoQueCabe($destacado, $w - 60, 82, 46, self::FUENTE_BOLD, -2);
        $yPrecio = $y + 90 + $tamPrecio;
        self::texto($lienzo, $destacado, $x + 30, $yPrecio, $tamPrecio, self::$paleta['blanco'], self::FUENTE_BOLD, -2);

        self::texto($lienzo, $secundario, $x + 30, $yPrecio + 40, self::tamanoQueCabe($secundario, $w - 60, 21, 14, self::FUENTE_MEDIA), self::$paleta['blanco'], self::FUENTE_MEDIA, 0.3);

        if (!empty($datos['nivel_arl'])) {
            $nota = 'ARL riesgo ' . $datos['nivel_arl'];
            self::texto($lienzo, $nota, $x + 30, $yPrecio + 72, 15, self::$paleta['acentoClaro'], self::FUENTE_REGULAR, 0.4);
        }

        if (!empty($datos['en_promocion'])) {
            self::pintarSello($lienzo, 'PROMO', $x + $w - 24, $y - 18);
        }
    }

    /** Tarjetas de los servicios incluidos (1 a 4). Devuelve la Y donde terminan. */
    private static function pintarTarjetasServicios($lienzo, array $servicios, int $y): int
    {
        $n = max(1, count($servicios));
        $separacion = 18;
        $anchoTotal = self::ANCHO - self::MARGEN * 2;
        // Con pocos servicios una tarjeta a todo el ancho se ve desproporcionada: se limita
        // el ancho y la fila se centra.
        $ancho = (int) round(min(268, ($anchoTotal - $separacion * ($n - 1)) / $n));
        $alto  = 200;

        $anchoFila = $ancho * $n + $separacion * ($n - 1);
        $x = (int) round((self::ANCHO - $anchoFila) / 2);
        foreach ($servicios as $servicio) {
            self::rectRedondeado($lienzo, $x, $y, $ancho, $alto, 22, self::$paleta['suave']);
            self::bordeRedondeado($lienzo, $x, $y, $ancho, $alto, 22, self::$paleta['borde']);

            $cx = $x + (int) round($ancho / 2);
            self::pintarIconoServicio($lienzo, $servicio, $cx, $y + 52);

            $tamNombre = self::tamanoQueCabe($servicio, $ancho - 20, 25, 15, self::FUENTE_BOLD);
            self::textoCentrado($lienzo, $servicio, $cx, $y + 112, $tamNombre, self::$paleta['navy'], self::FUENTE_BOLD);

            // Descripción en dos líneas cortas, centrada.
            $desc = self::DESCRIPCION_SERVICIO[$servicio] ?? '';
            $tamDesc = $n >= 4 ? 13 : 15;
            $lineas = self::envolver($desc, $ancho - 24, $tamDesc, self::FUENTE_REGULAR);
            $yd = $y + 140;
            foreach (array_slice($lineas, 0, 2) as $linea) {
                self::textoCentrado($lienzo, $linea, $cx, $yd, $tamDesc, self::$paleta['gris'], self::FUENTE_REGULAR);
                $yd += (int) round($tamDesc * 1.5);
            }

            $x += $ancho + $separacion;
        }

        return $y + $alto + 30;
    }

    /** Foto a sangre cubriendo todo el lienzo (plantilla inmersiva). */
    private static function pintarFotoCompleta($lienzo, string $ruta): void
    {
        self::pintarFotoRecorte($lienzo, $ruta, 0, 0, self::ANCHO, self::ALTO);
    }

    /** Dibuja la foto recortada "cover" en el rectángulo dado, sin marco ni esquinas. */
    private static function pintarFotoRecorte($lienzo, string $ruta, int $x, int $y, int $w, int $h): void
    {
        $foto = self::cargar(Storage::disk('public')->path($ruta));
        if (!$foto) {
            return;
        }

        $escala = max($w / imagesx($foto), $h / imagesy($foto));
        $wDest = (int) round(imagesx($foto) * $escala);
        $hDest = (int) round(imagesy($foto) * $escala);

        imagecopyresampled(
            $lienzo, $foto,
            $x + (int) round(($w - $wDest) / 2), $y + (int) round(($h - $hDest) / 2),
            0, 0, $wDest, $hDest, imagesx($foto), imagesy($foto)
        );
        imagedestroy($foto);
    }

    /** Cabecera para las plantillas con foto de fondo: logo y textos en blanco. */
    private static function pintarCabeceraSobreFoto($lienzo, Aliado $aliado): void
    {
        $x = self::MARGEN;
        $yBase = 52;
        $altoLogo = 106;

        // Sobre foto se prefiere la variante para fondos oscuros.
        $logo = self::cargarLogo($aliado, true);
        if ($logo) {
            $escala = min(280 / imagesx($logo), $altoLogo / imagesy($logo));
            $w = (int) round(imagesx($logo) * $escala);
            $h = (int) round(imagesy($logo) * $escala);

            $redim = imagecreatetruecolor($w, $h);
            imagealphablending($redim, false);
            imagesavealpha($redim, true);
            imagefilledrectangle($redim, 0, 0, $w, $h, imagecolorallocatealpha($redim, 0, 0, 0, 127));
            imagecopyresampled($redim, $logo, 0, 0, 0, 0, $w, $h, imagesx($logo), imagesy($logo));
            imagealphablending($lienzo, true);
            imagecopy($lienzo, $redim, $x, $yBase, 0, 0, $w, $h);

            imagedestroy($logo);
            imagedestroy($redim);
            $x += $w + 22;
        }

        $eslogan = trim((string) $aliado->eslogan);
        $anchoTexto = self::ANCHO - $x - self::MARGEN;
        $nombre = mb_strtoupper($aliado->nombre);

        $tam = self::tamanoQueCabe($nombre, $anchoTexto, 34, 20, self::FUENTE_BOLD, 1.5);
        self::textoConSombra($lienzo, $nombre, $x, $yBase + ($eslogan !== '' ? 54 : 66), $tam, self::$paleta['blanco'], self::FUENTE_BOLD, 1.5);

        if ($eslogan !== '') {
            // En blanco y con sombra: el azul claro se perdía contra las zonas luminosas de
            // la foto, y en la cabecera el velo es tenue a propósito para no tapar la imagen.
            $tamEslogan = self::tamanoQueCabe($eslogan, $anchoTexto, 19, 13, self::FUENTE_MEDIA, 0.6);
            self::textoConSombra($lienzo, $eslogan, $x, $yBase + 86, $tamEslogan, self::$paleta['blanco'], self::FUENTE_MEDIA, 0.6);
        }
    }

    /**
     * Texto con una sombra oscura difusa detrás: es lo que permite leer sobre una fotografía
     * cuyo brillo no controlamos, sin oscurecer toda la imagen.
     */
    private static function textoConSombra($lienzo, string $texto, int $x, int $y, int $tam, int $color, string $fuente, float $tracking = 0): void
    {
        $sombra = imagecolorallocatealpha($lienzo, 0, 0, 0, 88);
        foreach ([[2, 2], [-1, 2], [2, -1], [3, 3]] as [$dx, $dy]) {
            self::texto($lienzo, $texto, $x + $dx, $y + $dy, $tam, $sombra, $fuente, $tracking);
        }
        self::texto($lienzo, $texto, $x, $y, $tam, $color, $fuente, $tracking);
    }

    /**
     * Fila compacta de servicios con chulo, para las plantillas sobre fondo oscuro.
     * Devuelve su alto real (cambia si hubo que achicarla para que quepa).
     */
    private static function pintarFilaServiciosPlana($lienzo, array $servicios, int $x, int $y): int
    {
        $tam = 21;
        $alto = 50;
        $sep = 14;

        // Se achica la fila entera antes que permitir que se salga del margen.
        $anchoMax = self::ANCHO - self::MARGEN * 2;
        $medir = function (int $t) use ($servicios, $sep) {
            $alto = (int) round($t * 2.4);
            $total = -$sep;
            foreach ($servicios as $s) {
                $total += self::anchoTexto($s, $t, self::FUENTE_BOLD) + $alto + 34 + $sep;
            }
            return $total;
        };
        while ($tam > 13 && $medir($tam) > $anchoMax) {
            $tam--;
        }
        $alto = (int) round($tam * 2.4);

        $fondo = imagecolorallocatealpha($lienzo, 255, 255, 255, 96);
        foreach ($servicios as $servicio) {
            $ancho = self::anchoTexto($servicio, $tam, self::FUENTE_BOLD) + $alto + 34;
            self::rectRedondeado($lienzo, $x, $y, $ancho, $alto, (int) round($alto / 2), $fondo);

            $cx = $x + (int) round($alto * 0.52);
            $cy = $y + (int) round($alto / 2);
            $u = $tam / 21;
            imagesetthickness($lienzo, max(3, (int) round(4 * $u)));
            $verde = imagecolorallocate($lienzo, 74, 222, 128);
            imageline($lienzo, (int) ($cx - 8 * $u), (int) $cy, (int) ($cx - 2 * $u), (int) ($cy + 7 * $u), $verde);
            imageline($lienzo, (int) ($cx - 2 * $u), (int) ($cy + 7 * $u), (int) ($cx + 9 * $u), (int) ($cy - 8 * $u), $verde);
            imagesetthickness($lienzo, 1);

            self::texto($lienzo, $servicio, $x + $alto + 12, $cy + (int) round($tam * 0.36), $tam, self::$paleta['blanco'], self::FUENTE_BOLD);
            $x += $ancho + $sep;
        }

        return $alto;
    }

    /** Precio en una línea (sin recuadro), para las plantillas sobre fondo oscuro. */
    private static function pintarPrecioEnLinea($lienzo, int $x, int $y, array $datos): void
    {
        $mensual    = (float) ($datos['valor_mensual'] ?? 0);
        $afiliacion = (float) ($datos['costo_afiliacion'] ?? 0);

        if ($afiliacion > 0) {
            $etiqueta = 'AFÍLIATE ESTE MES POR';
            $destacado = '$' . self::pesos($afiliacion);
            $secundario = 'Luego $' . self::pesos($mensual) . ' al mes';
        } else {
            $etiqueta = 'DESDE';
            $destacado = '$' . self::pesos($mensual);
            $secundario = 'al mes · sin costo de afiliación';
        }
        if (!empty($datos['nivel_arl'])) {
            $secundario .= '  ·  ARL riesgo ' . $datos['nivel_arl'];
        }

        self::texto($lienzo, $etiqueta, $x, $y, 17, self::$paleta['acentoClaro'], self::FUENTE_MEDIA, 2.4);

        $tam = self::tamanoQueCabe($destacado, self::ANCHO - $x * 2, 88, 50, self::FUENTE_BOLD, -2);
        self::texto($lienzo, $destacado, $x, $y + 16 + $tam, $tam, self::$paleta['blanco'], self::FUENTE_BOLD, -2);
        self::texto($lienzo, $secundario, $x, $y + 52 + $tam, self::tamanoQueCabe($secundario, self::ANCHO - $x * 2, 21, 14, self::FUENTE_MEDIA), self::$paleta['blanco'], self::FUENTE_MEDIA, 0.3);
    }

    /** Íconos dibujados a mano: no dependemos de que la fuente traiga glifos. */
    private static function pintarIconoServicio($lienzo, string $servicio, int $cx, int $cy): void
    {
        $marca = self::$paleta['marca'];
        $blanco = self::$paleta['blanco'];
        $r = 30;

        imagefilledellipse($lienzo, $cx, $cy, $r * 2, $r * 2, $marca);

        switch ($servicio) {
            case 'EPS': // cruz médica
                imagefilledrectangle($lienzo, $cx - 6, $cy - 17, $cx + 6, $cy + 17, $blanco);
                imagefilledrectangle($lienzo, $cx - 17, $cy - 6, $cx + 17, $cy + 6, $blanco);
                break;

            case 'ARL': // escudo: hombros rectos arriba y punta abajo
                imagefilledpolygon($lienzo, [
                    $cx - 16, $cy - 17,
                    $cx + 16, $cy - 17,
                    $cx + 16, $cy - 1,
                    $cx,      $cy + 19,
                    $cx - 16, $cy - 1,
                ], $blanco);
                break;

            case 'Pensión': // moneda con el signo de peso (glifo real, se lee sin ambigüedad)
                imagefilledellipse($lienzo, $cx, $cy, 38, 38, $blanco);
                self::textoCentrado($lienzo, '$', $cx, $cy + 11, 28, $marca, self::FUENTE_BOLD);
                break;

            case 'Caja': // casa: techo ancho + cuerpo con puerta
                imagefilledpolygon($lienzo, [$cx, $cy - 20, $cx + 22, $cy - 1, $cx - 22, $cy - 1], $blanco);
                imagefilledrectangle($lienzo, $cx - 15, $cy - 1, $cx + 15, $cy + 18, $blanco);
                imagefilledrectangle($lienzo, $cx - 5, $cy + 6, $cx + 5, $cy + 18, $marca);
                break;
        }
    }

    /** Mensaje de cobertura nacional junto a la silueta de Colombia. */
    private static function pintarCobertura($lienzo, int $y): void
    {
        $xMapa = self::MARGEN + 12;
        self::pintarMapaColombia($lienzo, $xMapa, $y, 92);

        $x = $xMapa + 118;
        $ancho = self::ANCHO - $x - self::MARGEN;

        self::texto($lienzo, 'COBERTURA NACIONAL', $x, $y + 34, 17, self::$paleta['marca'], self::FUENTE_BOLD, 2.4);

        $mensaje = 'Manejamos todas las EPS, cajas de compensación, fondos de pensión y ARL del país.';
        $yl = $y + 66;
        foreach (array_slice(self::envolver($mensaje, $ancho, 19, self::FUENTE_REGULAR), 0, 2) as $linea) {
            self::texto($lienzo, $linea, $x, $yl, 19, self::$paleta['gris'], self::FUENTE_REGULAR, 0.2);
            $yl += 28;
        }
    }

    /**
     * Silueta simplificada de Colombia. Las coordenadas son el contorno real del país
     * (longitud/latitud) normalizado, no un dibujo aproximado: así se reconoce de inmediato.
     */
    private static function pintarMapaColombia($lienzo, int $x, int $y, int $alto): void
    {
        // [longitud, latitud] recorriendo la frontera desde La Guajira en sentido horario.
        $contorno = [
            [-71.66, 12.46], [-72.90, 11.50], [-74.20, 11.20], [-74.85, 10.98], [-75.55, 10.40],
            [-76.90, 8.60], [-77.40, 7.90], [-77.90, 7.20], [-77.30, 5.60], [-77.10, 3.90],
            [-78.80, 1.80], [-78.90, 0.80], [-77.00, 0.40], [-75.30, -0.40], [-74.00, -2.00],
            [-70.30, -3.80], [-69.90, -4.20], [-69.50, -1.50], [-69.80, 1.00], [-67.30, 1.20],
            [-67.90, 3.00], [-67.50, 6.20], [-71.00, 7.00], [-72.90, 9.00], [-71.90, 11.00],
        ];

        $lons = array_column($contorno, 0);
        $lats = array_column($contorno, 1);
        $minLon = min($lons); $maxLon = max($lons);
        $minLat = min($lats); $maxLat = max($lats);

        $escala = $alto / ($maxLat - $minLat);
        $puntos = [];
        foreach ($contorno as [$lon, $lat]) {
            $puntos[] = (int) round($x + ($lon - $minLon) * $escala);
            $puntos[] = (int) round($y + ($maxLat - $lat) * $escala);
        }

        imagefilledpolygon($lienzo, $puntos, self::$paleta['marca']);
    }

    /** Botón de llamado a la acción, anclado abajo, con el ícono de WhatsApp. */
    private static function pintarLlamado($lienzo, string $texto): void
    {
        $alto = 104;
        $y = self::ALTO - $alto - 52;
        $x = self::MARGEN;
        $ancho = self::ANCHO - self::MARGEN * 2;

        self::rectRedondeado($lienzo, $x, $y, $ancho, $alto, (int) round($alto / 2), self::$paleta['marca']);

        $tam = self::tamanoQueCabe($texto, $ancho - 190, 32, 20, self::FUENTE_BOLD, 0.5);
        $anchoTexto = self::anchoTexto($texto, $tam, self::FUENTE_BOLD, 0.5);

        $anchoBloque = $anchoTexto + 78;
        $inicio = (int) round($x + ($ancho - $anchoBloque) / 2);
        $cy = (int) round($y + $alto / 2);

        self::pintarIconoWhatsapp($lienzo, $inicio + 26, $cy, 27);
        self::texto($lienzo, $texto, $inicio + 78, $cy + (int) round($tam * 0.36), $tam, self::$paleta['blanco'], self::FUENTE_BOLD, 0.5);
    }

    /** Globo de chat blanco con su colita: se lee como "mensaje" sin copiar el logo de WhatsApp. */
    private static function pintarIconoWhatsapp($lienzo, int $cx, int $cy, int $r): void
    {
        $blanco = self::$paleta['blanco'];
        $fondo  = self::$paleta['marca'];

        $w = (int) round($r * 2.0);
        $h = (int) round($r * 1.62);
        $x = $cx - (int) round($w / 2);
        $y = $cy - (int) round($h / 2) - 3;

        self::rectRedondeado($lienzo, $x, $y, $w, $h, (int) round($h * 0.32), $blanco);
        imagefilledpolygon($lienzo, [
            $x + (int) ($w * 0.24), $y + $h - 2,
            $x + (int) ($w * 0.58), $y + $h - 2,
            $x + (int) ($w * 0.30), $y + $h + (int) ($r * 0.5),
        ], $blanco);

        $d = max(4, (int) round($r * 0.24));
        foreach ([0.27, 0.5, 0.73] as $f) {
            imagefilledellipse($lienzo, $x + (int) round($w * $f), $y + (int) round($h / 2), $d, $d, $fondo);
        }
    }

    private static function pintarSello($lienzo, string $texto, int $xDerecha, int $y): void
    {
        $tam = 20;
        $ancho = self::anchoTexto($texto, $tam, self::FUENTE_BOLD, 2) + 44;
        $alto = 48;
        $x = $xDerecha - $ancho;

        $fondo = imagecolorallocate($lienzo, 250, 204, 21);
        $tinta = imagecolorallocate($lienzo, 42, 34, 8);
        self::rectRedondeado($lienzo, $x, $y, $ancho, $alto, 12, $fondo);
        self::texto($lienzo, $texto, $x + 22, (int) ($y + $alto / 2 + $tam * 0.36), $tam, $tinta, self::FUENTE_BOLD, 2);
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

    private static function bordeRedondeado($lienzo, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagesetthickness($lienzo, 2);
        imageline($lienzo, $x + $r, $y, $x + $w - $r, $y, $color);
        imageline($lienzo, $x + $r, $y + $h, $x + $w - $r, $y + $h, $color);
        imageline($lienzo, $x, $y + $r, $x, $y + $h - $r, $color);
        imageline($lienzo, $x + $w, $y + $r, $x + $w, $y + $h - $r, $color);
        $d = $r * 2;
        imagearc($lienzo, $x + $r, $y + $r, $d, $d, 180, 270, $color);
        imagearc($lienzo, $x + $w - $r, $y + $r, $d, $d, 270, 360, $color);
        imagearc($lienzo, $x + $r, $y + $h - $r, $d, $d, 90, 180, $color);
        imagearc($lienzo, $x + $w - $r, $y + $h - $r, $d, $d, 0, 90, $color);
        imagesetthickness($lienzo, 1);
    }

    /**
     * Dibuja texto. Con $tracking != 0 se pinta letra por letra para controlar el espaciado
     * (GD no lo soporta nativo): positivo separa —para versalitas— y negativo junta, que es
     * lo que necesitan los titulares grandes en mayúsculas.
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

    private static function textoCentrado($lienzo, string $texto, int $cx, int $y, int $tam, int $color, string $fuente, float $tracking = 0): void
    {
        $ancho = self::anchoTexto($texto, $tam, $fuente, $tracking);
        self::texto($lienzo, $texto, (int) round($cx - $ancho / 2), $y, $tam, $color, $fuente, $tracking);
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

    /** @return string[] */
    private static function envolver(string $texto, int $anchoMax, int $tam, string $fuente): array
    {
        $lineas = [];
        $actual = '';

        foreach (explode(' ', $texto) as $palabra) {
            $prueba = $actual === '' ? $palabra : "{$actual} {$palabra}";
            if (self::anchoTexto($prueba, $tam, $fuente) > $anchoMax && $actual !== '') {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas;
    }

    private static function cargarLogo(Aliado $aliado, bool $paraFondoOscuro = false)
    {
        // El recorte configurado describe el lockup de marca: aplicarlo al logo cuadrado de
        // respaldo lo duplicaría, así que solo se usa con los lockups.
        $candidatos = $paraFondoOscuro
            ? [[$aliado->logo_oscuro, true], [$aliado->logo_marca_claro, true], [$aliado->logo, false]]
            : [[$aliado->logo_marca_claro, true], [$aliado->logo, false], [$aliado->logo_oscuro, true]];

        foreach ($candidatos as [$ruta, $esLockup]) {
            if ($ruta && Storage::disk('public')->exists($ruta)) {
                return LogoWatermarker::cargarConRecorte(
                    Storage::disk('public')->path($ruta),
                    $esLockup ? $aliado->logo_marca_recorte : null
                );
            }
        }
        return null;
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
