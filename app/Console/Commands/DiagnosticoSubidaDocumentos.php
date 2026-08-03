<?php

namespace App\Console\Commands;

use App\Services\CompresorDocumentoService;
use Illuminate\Console\Command;

/**
 * Diagnóstico de la subida de documentos en el servidor donde se ejecute.
 *
 * Existe porque los dos motivos por los que falla una subida — los límites de
 * PHP y la ausencia de Ghostscript — solo se pueden ver desde el propio
 * servidor, y el error que llega al navegador ("413", "419") no distingue cuál
 * de los dos es. Correr esto por SSH en brynex.co responde ambas preguntas.
 */
class DiagnosticoSubidaDocumentos extends Command
{
    protected $signature = 'documentos:diagnostico';

    protected $description = 'Muestra los límites de subida de PHP y si el servidor puede comprimir imágenes y PDF';

    /**
     * Mínimo que debe permitir PHP para que la validación funcione.
     *
     * Los controladores validan max:10240 (10 MB), así que PHP tiene que aceptar
     * algo más que eso: si cortara justo en 10 MB, un archivo de 10.1 MB moriría
     * en Apache con un 413 en HTML en vez de llegar a Laravel y recibir el
     * mensaje "El archivo no puede superar 10 MB".
     */
    private const MIN_UPLOAD_MB = 11;

    public function handle(CompresorDocumentoService $compresor): int
    {
        // Los límites que importan son los de la web, NO los del CLI. Correr
        // este comando lee el php.ini del CLI (/etc/php/8.3/cli/php.ini), que en
        // brynex.co está en 2M mientras la web permite 20M — reportar ese valor
        // daba una falsa alarma. Acá se reconstruye lo que ve Apache.
        [$upload, $post, $origen] = $this->limitesWeb();

        $this->info('── Límites efectivos de la web ────────────────');
        $this->line(sprintf('  upload_max_filesize : %s MB', $upload));
        $this->line(sprintf('  post_max_size       : %s MB', $post));
        $this->line(sprintf('  origen              : %s', $origen));
        $this->line(sprintf('  (el CLI usa otros: %s / %s desde %s)',
            ini_get('upload_max_filesize'), ini_get('post_max_size'),
            php_ini_loaded_file() ?: 'ninguno'));
        $this->newLine();

        $ok = true;

        if ($upload < self::MIN_UPLOAD_MB) {
            $ok = false;
            $this->error("✗ upload_max_filesize ({$upload} MB) está por debajo de los "
                .self::MIN_UPLOAD_MB.' MB que acepta la validación.');
            $this->line('  → PHP descarta el archivo antes de llegar al controlador y el');
            $this->line('    navegador recibe una página HTML (error "Unexpected token <").');
            $this->line('  → Con mod_php se sube con php_value en public/.htaccess;');
            $this->line('    con PHP-FPM, en el php.ini del pool (.user.ini NO aplica a mod_php).');
        } else {
            $this->line("✓ upload_max_filesize suficiente ({$upload} MB).");
        }

        if ($post <= $upload) {
            $ok = false;
            $this->error("✗ post_max_size ({$post} MB) debe ser mayor que upload_max_filesize ({$upload} MB).");
            $this->line('  → Al superarse post_max_size PHP vacía $_POST, se pierde el token');
            $this->line('    CSRF y Laravel responde la página HTML de "419 Page Expired".');
        } else {
            $this->line("✓ post_max_size mayor que upload_max_filesize ({$post} MB).");
        }

        $this->newLine();
        $this->info('── Compresión ─────────────────────────────────');

        if (extension_loaded('gd')) {
            $this->line('✓ GD disponible → las imágenes (jpg/png/webp) se reescalan a 2200 px y se reencodan como JPEG.');
        } else {
            $ok = false;
            $this->error('✗ GD no está instalado → las imágenes se guardan sin comprimir.');
            $this->line('  → Instalar la extensión gd de PHP.');
        }

        if ($compresor->soportaCompresionPdf()) {
            $this->line('✓ Ghostscript disponible → los PDF escaneados se remuestrean a 110 dpi.');
        } else {
            $this->warn('⚠ Ghostscript no está disponible → los PDF se guardan tal cual.');
            $this->line('  → La subida funciona igual, solo se gasta más disco.');
            $this->line('  → Para activarlo:  sudo apt-get install -y ghostscript');
        }

        $this->newLine();
        $this->line($ok
            ? '<info>Sin problemas bloqueantes para subir documentos.</info>'
            : '<error>Hay que corregir los puntos marcados con ✗.</error>');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Límites que aplica el servidor web, no el CLI.
     *
     * Se arma en dos pasos, igual que los resuelve PHP: primero el php.ini del
     * SAPI web, después los php_value de public/.htaccess, que lo pisan.
     *
     * Devuelve [upload_mb, post_mb, descripción del origen].
     */
    private function limitesWeb(): array
    {
        $upload = $this->aMb(ini_get('upload_max_filesize'));
        $post = $this->aMb(ini_get('post_max_size'));
        $origen = 'php.ini del CLI (no se pudo leer el del servidor web)';

        // php.ini del SAPI web. Se prueban apache2 y fpm para la versión actual.
        $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        foreach (["/etc/php/{$version}/apache2/php.ini", "/etc/php/{$version}/fpm/php.ini"] as $ini) {
            if (! is_readable($ini)) {
                continue;
            }
            $contenido = file_get_contents($ini);
            if (preg_match('/^\s*upload_max_filesize\s*=\s*(\S+)/mi', $contenido, $m)) {
                $upload = $this->aMb($m[1]);
            }
            if (preg_match('/^\s*post_max_size\s*=\s*(\S+)/mi', $contenido, $m)) {
                $post = $this->aMb($m[1]);
            }
            $origen = $ini;
            break;
        }

        // Los php_value del .htaccess pisan al php.ini (solo bajo mod_php).
        $htaccess = public_path('.htaccess');
        if (is_readable($htaccess)) {
            $contenido = file_get_contents($htaccess);
            $pisado = false;
            if (preg_match('/^\s*php_value\s+upload_max_filesize\s+(\S+)/mi', $contenido, $m)) {
                $upload = $this->aMb($m[1]);
                $pisado = true;
            }
            if (preg_match('/^\s*php_value\s+post_max_size\s+(\S+)/mi', $contenido, $m)) {
                $post = $this->aMb($m[1]);
                $pisado = true;
            }
            if ($pisado) {
                $origen .= ' + php_value de public/.htaccess';
            }
        }

        return [$upload, $post, $origen];
    }

    /** Convierte "20M" / "1G" / "204800" al número de MB. */
    private function aMb(string $valor): float
    {
        $valor = trim($valor);
        $unidad = strtolower(substr($valor, -1));
        $numero = (float) $valor;

        return match ($unidad) {
            'g' => $numero * 1024,
            'm' => $numero,
            'k' => $numero / 1024,
            default => $numero / 1048576,
        };
    }
}
