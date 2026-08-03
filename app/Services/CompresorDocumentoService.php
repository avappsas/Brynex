<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Comprime los documentos subidos antes de guardarlos en disco.
 *
 * Los soportes de incapacidades llegan casi siempre como fotos de celular o
 * escaneos de CamScanner: 3-10 MB por página para un documento que se va a leer
 * en pantalla. Sin compresión, una incapacidad con 4 soportes ocupa ~30 MB y el
 * disco del servidor crece a un ritmo que no corresponde con el contenido real.
 *
 * Estrategia por tipo:
 *
 *  - Imágenes (jpg/png/webp): se reescalan a ANCHO_MAX px y se reencodan como
 *    JPEG. Es la ganancia grande — un PNG de 8 MB de una captura de pantalla
 *    baja a ~250 KB sin perder legibilidad del texto.
 *
 *  - PDF: se pasan por Ghostscript remuestreando las imágenes embebidas de los
 *    escáneres a DPI_PDF. Si Ghostscript no está instalado en el servidor el
 *    archivo se guarda tal cual: la compresión es una optimización, nunca un
 *    requisito para que la subida funcione.
 *
 * En todos los casos, si el resultado comprimido no pesa menos que el original
 * se conserva el original. Nunca se guarda un archivo más pesado ni uno corrupto.
 */
class CompresorDocumentoService
{
    /** Ancho máximo en px al que se reescalan las imágenes. */
    private const ANCHO_MAX = 2200;

    /** Calidad JPEG de salida (72 mantiene legible el texto escaneado). */
    private const CALIDAD_JPEG = 72;

    /**
     * Resolución a la que Ghostscript remuestrea las imágenes del PDF.
     *
     * 110 dpi salió de medir contra los PDF reales del servidor. Los perfiles
     * enlatados no sirven acá:
     *
     *   - /ebook (150 dpi) dejó una epicrisis de 32 páginas en 6.22 MB partiendo
     *     de 5.61 MB — la *engordaba* 10%, porque las imágenes ya venían por
     *     debajo del umbral y solo las reencodaba.
     *   - /screen (72 dpi) la bajaba a 1.74 MB pero el texto pequeño quedaba
     *     borroso, y una epicrisis hay que poder leerla y volver a radicarla.
     *
     * A 110 dpi esa misma epicrisis baja a 3.02 MB (47% menos) y a simple vista
     * se lee igual que el original. Medido sobre 12 PDF reales: entre 27% y 89%
     * de ahorro, todos válidos.
     */
    private const DPI_PDF = 110;

    /** Calidad JPEG con la que Ghostscript reencoda las imágenes del PDF. */
    private const CALIDAD_JPEG_PDF = 70;

    private const EXT_IMAGEN = ['jpg', 'jpeg', 'png', 'webp'];

    /** Cache del binario de Ghostscript: false = ya se buscó y no está. */
    private static string|false|null $rutaGs = null;

    /**
     * Comprime el archivo y lo guarda en el disco indicado.
     *
     * Reemplaza a `$file->store($carpeta, $disco)`: devuelve la misma ruta
     * relativa, pero el contenido puede venir recomprimido y la extensión puede
     * cambiar a .jpg si la imagen original era png/webp.
     */
    public function guardar(UploadedFile $file, string $carpeta, string $disco): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $original = @file_get_contents($file->getRealPath());

        if ($original === false) {
            // Sin poder leerlo no hay nada que comprimir: que lo mueva Laravel.
            return $file->store($carpeta, $disco);
        }

        $pesoOriginal = strlen($original);
        $comprimido = null;
        $extFinal = $ext;

        if (in_array($ext, self::EXT_IMAGEN, true)) {
            $comprimido = $this->comprimirImagen($original);
            $extFinal = 'jpg';
        } elseif ($ext === 'pdf') {
            $comprimido = $this->comprimirPdf($file->getRealPath());
        }

        // Si no se pudo comprimir, o quedó igual o más pesado, gana el original.
        if ($comprimido === null || strlen($comprimido) >= $pesoOriginal) {
            $comprimido = $original;
            $extFinal = $ext;
        }

        $ruta = trim($carpeta, '/').'/'.Str::random(40).'.'.$extFinal;
        Storage::disk($disco)->put($ruta, $comprimido);

        if (strlen($comprimido) < $pesoOriginal) {
            Log::info('Documento comprimido', [
                'ruta' => $ruta,
                'original' => $this->humano($pesoOriginal),
                'final' => $this->humano(strlen($comprimido)),
                'ahorro' => round(100 - (strlen($comprimido) / $pesoOriginal * 100)).'%',
            ]);
        }

        return $ruta;
    }

    // ── IMÁGENES (GD) ────────────────────────────────────────────────────────

    /**
     * Reescala a ANCHO_MAX y reencoda como JPEG. Devuelve null si GD no puede
     * con el archivo (formato raro, imagen corrupta, o se queda sin memoria).
     */
    private function comprimirImagen(string $contenido): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $img = @imagecreatefromstring($contenido);
        if ($img === false) {
            return null;
        }

        try {
            $ancho = imagesx($img);
            $alto = imagesy($img);

            if ($ancho > self::ANCHO_MAX) {
                $nuevoAlto = (int) round($alto * (self::ANCHO_MAX / $ancho));
                $escalada = imagescale($img, self::ANCHO_MAX, $nuevoAlto);
                if ($escalada !== false) {
                    imagedestroy($img);
                    $img = $escalada;
                    $ancho = self::ANCHO_MAX;
                    $alto = $nuevoAlto;
                }
            }

            // JPEG no tiene canal alfa: sin este fondo blanco, las zonas
            // transparentes de un PNG salen negras y tapan el documento.
            $lienzo = imagecreatetruecolor($ancho, $alto);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
            imagecopy($lienzo, $img, 0, 0, 0, 0, $ancho, $alto);

            ob_start();
            imagejpeg($lienzo, null, self::CALIDAD_JPEG);
            $salida = ob_get_clean();

            imagedestroy($lienzo);

            return $salida !== false && $salida !== '' ? $salida : null;
        } catch (\Throwable $e) {
            Log::warning('No se pudo comprimir la imagen', ['error' => $e->getMessage()]);

            return null;
        } finally {
            if (is_object($img) || is_resource($img)) {
                @imagedestroy($img);
            }
        }
    }

    // ── PDF (Ghostscript) ────────────────────────────────────────────────────

    /**
     * Recomprime el PDF con Ghostscript. Devuelve null si no está instalado,
     * si falla, o si la salida no es un PDF válido.
     */
    private function comprimirPdf(string $rutaOrigen): ?string
    {
        $gs = $this->binarioGhostscript();
        if ($gs === false) {
            return null;
        }

        $destino = tempnam(sys_get_temp_dir(), 'brynex_pdf_').'.pdf';

        // El Threshold=1.0 es imprescindible: con el default (1.5) Ghostscript
        // solo remuestrea imágenes que superen la resolución objetivo por un 50%,
        // así que los escaneos que ya venían a ~130 dpi se salvaban del
        // remuestreo y el archivo terminaba más pesado que el original.
        $comando = sprintf(
            '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -dSAFER '
            .'-dDownsampleColorImages=true -dColorImageDownsampleType=/Bicubic '
            .'-dColorImageResolution=%d -dColorImageDownsampleThreshold=1.0 '
            .'-dDownsampleGrayImages=true -dGrayImageDownsampleType=/Bicubic '
            .'-dGrayImageResolution=%d -dGrayImageDownsampleThreshold=1.0 '
            .'-dAutoFilterColorImages=false -dColorImageFilter=/DCTEncode '
            .'-dAutoFilterGrayImages=false -dGrayImageFilter=/DCTEncode -dJPEGQ=%d '
            .'-sOutputFile=%s %s 2>&1',
            escapeshellarg($gs),
            self::DPI_PDF,
            self::DPI_PDF,
            self::CALIDAD_JPEG_PDF,
            escapeshellarg($destino),
            escapeshellarg($rutaOrigen)
        );

        try {
            $salidaCmd = [];
            $codigo = 0;
            @exec($comando, $salidaCmd, $codigo);

            if ($codigo !== 0 || ! is_file($destino)) {
                Log::warning('Ghostscript no pudo comprimir el PDF', [
                    'codigo' => $codigo,
                    'salida' => implode(' ', array_slice($salidaCmd, 0, 3)),
                ]);

                return null;
            }

            $resultado = @file_get_contents($destino);

            // Un PDF válido siempre empieza con la firma %PDF-. Si Ghostscript
            // abortó a medias puede dejar un archivo truncado con código 0.
            if ($resultado === false || ! str_starts_with($resultado, '%PDF-')) {
                return null;
            }

            return $resultado;
        } catch (\Throwable $e) {
            Log::warning('Error comprimiendo PDF', ['error' => $e->getMessage()]);

            return null;
        } finally {
            @unlink($destino);
        }
    }

    /**
     * Ruta del binario de Ghostscript, o false si no está disponible.
     *
     * Se resuelve una sola vez por request. Si exec() está deshabilitado (común
     * en hosting compartido) se reporta como no disponible y los PDF se guardan
     * sin comprimir.
     */
    private function binarioGhostscript(): string|false
    {
        if (self::$rutaGs !== null) {
            return self::$rutaGs;
        }

        $deshabilitadas = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $deshabilitadas, true) || ! function_exists('exec')) {
            return self::$rutaGs = false;
        }

        foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs'] as $candidato) {
            if (is_executable($candidato)) {
                return self::$rutaGs = $candidato;
            }
        }

        $salida = [];
        @exec('command -v gs 2>/dev/null', $salida);
        $encontrado = trim($salida[0] ?? '');

        return self::$rutaGs = ($encontrado !== '' && is_executable($encontrado)) ? $encontrado : false;
    }

    // ── AUXILIARES ───────────────────────────────────────────────────────────

    /** ¿El servidor puede recomprimir PDF? Útil para diagnóstico. */
    public function soportaCompresionPdf(): bool
    {
        return $this->binarioGhostscript() !== false;
    }

    private function humano(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 2).' MB'
            : round($bytes / 1024).' KB';
    }
}
