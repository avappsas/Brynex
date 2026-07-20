<?php

namespace App\Console\Commands\Finanzas;

use App\Models\Finanzas\Cuenta;
use App\Services\Finanzas\FinanzasAlertaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Artisan command: finanzas:precalentar-cache {userId} {anio} {mes}
 *
 * Ejecuta todas las queries pesadas del dashboard de Finanzas en background,
 * poblando el caché de archivo. Después de este comando, el dashboard carga
 * en < 500ms desde caché.
 *
 * Uso:
 *   php artisan finanzas:precalentar-cache 1 2026 7
 */
class PrecalentarCacheFinanzas extends Command
{
    protected $signature = 'finanzas:precalentar-cache
                            {userId : ID del usuario}
                            {anio   : Año del dashboard}
                            {mes    : Mes del dashboard}';

    protected $description = 'Precalienta el caché del dashboard de Finanzas Personales (ejecutar tras limpiar caché)';

    protected FinanzasAlertaService $alertaService;

    public function __construct(FinanzasAlertaService $alertaService)
    {
        parent::__construct();
        $this->alertaService = $alertaService;
    }

    public function handle(): int
    {
        $userId = (int) $this->argument('userId');
        $anio   = (int) $this->argument('anio');
        $mes    = (int) $this->argument('mes');

        set_time_limit(300); // 5 minutos máximo

        $this->info("Precalentando caché Finanzas — Usuario {$userId} · {$anio}/{$mes}");

        $steps = [
            'Resumen mensual'       => fn() => $this->alertaService->getResumenMensual($userId, $anio, $mes),
            'Evolución anual'       => fn() => $this->alertaService->getEvolucionAnual($userId, $anio),
            'Consolidado global'    => fn() => $this->alertaService->getConsolidadoGlobal($userId),
            'Cuentas con saldos'    => fn() => Cuenta::conSaldos($userId),
        ];

        $total = microtime(true);

        foreach ($steps as $label => $fn) {
            $t = microtime(true);
            $this->output->write("  ➤ {$label} ... ");
            try {
                $fn();
                $ms = round((microtime(true) - $t) * 1000);
                $this->line("<info>✓</info> ({$ms}ms)");
            } catch (\Exception $e) {
                $this->line('<error>✗ ' . $e->getMessage() . '</error>');
            }
        }

        $totalMs = round((microtime(true) - $total) * 1000);
        $this->info("Caché precalentado en {$totalMs}ms. El dashboard ahora cargará en < 1s.");

        return self::SUCCESS;
    }
}
