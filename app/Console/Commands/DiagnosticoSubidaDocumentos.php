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

    /** Mínimo que necesita la validación del controlador (max:15360 = 15 MB). */
    private const MIN_UPLOAD_MB = 15;

    public function handle(CompresorDocumentoService $compresor): int
    {
        $upload = $this->aMb(ini_get('upload_max_filesize'));
        $post = $this->aMb(ini_get('post_max_size'));

        $this->info('── Límites de PHP ─────────────────────────────');
        $this->line(sprintf('  upload_max_filesize : %s MB', $upload));
        $this->line(sprintf('  post_max_size       : %s MB', $post));
        $this->line(sprintf('  memory_limit        : %s', ini_get('memory_limit')));
        $this->line(sprintf('  max_execution_time  : %s s', ini_get('max_execution_time')));
        $this->line(sprintf('  php.ini cargado     : %s', php_ini_loaded_file() ?: 'ninguno'));
        $this->newLine();

        $ok = true;

        if ($upload < self::MIN_UPLOAD_MB) {
            $ok = false;
            $this->error("✗ upload_max_filesize ({$upload} MB) está por debajo de los "
                .self::MIN_UPLOAD_MB.' MB que acepta la validación.');
            $this->line('  → PHP descarta el archivo antes de llegar al controlador y el');
            $this->line('    navegador recibe una página HTML (error "Unexpected token <").');
            $this->line('  → Subir los valores en php.ini, o verificar que el servidor lea');
            $this->line('    public/.user.ini (solo funciona con PHP-FPM/CGI, no con mod_php).');
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
            $this->line('✓ Ghostscript disponible → los PDF escaneados se recomprimen a 150 dpi.');
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
