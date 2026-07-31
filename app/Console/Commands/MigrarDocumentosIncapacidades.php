<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mueve los documentos de incapacidades del disco público al privado.
 *
 * Hasta la corrección de seguridad C-4, las historias clínicas, epicrisis y
 * copias de cédula se guardaban en storage/app/public/incapacidades/, servido
 * por el enlace public/storage → accesibles por URL directa, sin sesión y con
 * la cédula en la ruta. El código ya escribe en el disco privado; este comando
 * migra lo que quedó del lado público.
 *
 * Las rutas guardadas en radicados.ruta_pdf son relativas al disco, así que no
 * hace falta tocar la base de datos: el mismo valor sirve para ambos discos.
 *
 * Ejecutar EN EL SERVIDOR (los archivos viven allí, no en local):
 *   php artisan incapacidades:migrar-documentos --dry-run
 *   php artisan incapacidades:migrar-documentos
 */
class MigrarDocumentosIncapacidades extends Command
{
    protected $signature   = 'incapacidades:migrar-documentos
                                {--dry-run : Solo muestra qué se movería}';
    protected $description = 'Mueve los documentos de incapacidades del disco público al privado (seguridad C-4)';

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $publico = Storage::disk('public');
        $privado = Storage::disk('local');

        $this->info($dryRun ? '🔍 Modo DRY-RUN' : '⚙️  Migrando documentos de incapacidades...');

        if (! $publico->exists('incapacidades')) {
            $this->info('✅ No hay nada en el disco público. Migración ya hecha o no aplica.');
            return self::SUCCESS;
        }

        $archivos = $publico->allFiles('incapacidades');
        $this->line('Archivos encontrados en el disco público: ' . count($archivos));

        if (empty($archivos)) {
            $this->info('✅ Nada que mover.');
            return self::SUCCESS;
        }

        $movidos = 0;
        $saltados = 0;
        $errores = 0;

        foreach ($archivos as $ruta) {
            if ($privado->exists($ruta)) {
                $this->warn("  [SKIP] Ya existe en el disco privado: {$ruta}");
                $saltados++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] {$ruta}");
                $movidos++;
                continue;
            }

            try {
                $privado->put($ruta, $publico->get($ruta));

                // Solo se borra el original si la copia quedó bien escrita.
                if ($privado->exists($ruta)) {
                    $publico->delete($ruta);
                    $movidos++;
                } else {
                    $this->error("  [ERROR] No se pudo escribir: {$ruta}");
                    $errores++;
                }
            } catch (\Throwable $e) {
                $this->error("  [ERROR] {$ruta} — {$e->getMessage()}");
                $errores++;
            }
        }

        $this->newLine();
        $this->info("Movidos: {$movidos} | Ya estaban: {$saltados} | Errores: {$errores}");

        if (! $dryRun && $errores === 0) {
            $this->info('✅ Listo. Verifica que los documentos se vean en el módulo de incapacidades.');
            $this->line('   Los directorios vacíos de storage/app/public/incapacidades se pueden borrar a mano.');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
