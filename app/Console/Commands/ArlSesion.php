<?php

namespace App\Console\Commands;

use App\Models\ArlCredencial;
use App\Models\RazonSocial;
use App\Services\ArlSura\ArlSuraApiService;
use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Registra las credenciales del portal de ARL Sura y prueba que abran sesión.
 *
 * La contraseña se pide de forma oculta y se guarda cifrada: nunca viaja como
 * argumento, para que no quede en el historial del shell ni en `ps`.
 */
class ArlSesion extends Command
{
    protected $signature = 'arl:sesion
                            {--aliado=1 : Aliado dueño de la credencial}
                            {--registrar : Pide y guarda usuario y contraseña}
                            {--probar : Abre sesión y verifica que responda}
                            {--poliza= : Póliza con la que probar}';

    protected $description = 'Gestiona la sesión automática con el portal de ARL Sura';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');

        if ($this->option('registrar') && ! $this->registrar($aliadoId)) {
            return self::FAILURE;
        }

        $credencial = ArlCredencial::activaDe($aliadoId);

        if (! $credencial) {
            $this->error("El aliado {$aliadoId} no tiene credenciales. Corre: php artisan arl:sesion --aliado={$aliadoId} --registrar");
            return self::FAILURE;
        }

        $this->line("Credencial: {$credencial->tipo_documento} {$credencial->usuario}".
            ($credencial->ultima_sesion_at ? '  ·  última sesión '.$credencial->ultima_sesion_at->diffForHumans() : '  ·  sin usar aún'));

        if ($credencial->ultimo_error) {
            $this->warn('  Último error: '.$credencial->ultimo_error);
        }

        return $this->option('probar') ? $this->probar($aliadoId) : self::SUCCESS;
    }

    private function registrar(int $aliadoId): bool
    {
        $tipo    = $this->choice('Tipo de identificación con la que entras al portal', ['C' => 'Cédula', 'N' => 'NIT', 'E' => 'Cédula de extranjería'], 'C');
        $usuario = trim((string) $this->ask('Número de identificación'));
        $clave   = (string) $this->secret('Contraseña del portal (no se muestra)');

        if ($usuario === '' || $clave === '') {
            $this->error('Usuario y contraseña son obligatorios.');
            return false;
        }

        ArlCredencial::updateOrCreate(
            ['aliado_id' => $aliadoId],
            ['tipo_documento' => $tipo, 'usuario' => $usuario, 'contrasena' => $clave, 'activo' => true, 'ultimo_error' => null]
        );

        $this->info('Credencial guardada (la contraseña queda cifrada).');

        return true;
    }

    private function probar(int $aliadoId): int
    {
        $poliza = (string) ($this->option('poliza')
            ?: RazonSocial::where('aliado_id', $aliadoId)->whereNotNull('arl_poliza')->value('arl_poliza'));

        if ($poliza === '') {
            $this->error('No hay ninguna póliza registrada para este aliado. Pásala con --poliza.');
            return self::FAILURE;
        }

        $this->line("Abriendo sesión para la póliza {$poliza}...");

        try {
            ArlSuraSesionService::renovar($aliadoId, $poliza);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $api = new ArlSuraApiService($aliadoId, $poliza);

        if (! $api->sesionViva()) {
            $this->error('Se abrió sesión pero el API no responde con ella.');
            return self::FAILURE;
        }

        $this->info('Sesión abierta y verificada. Ya puedes correr las sincronizaciones y afiliar.');

        return self::SUCCESS;
    }
}
