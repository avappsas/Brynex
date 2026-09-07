<?php

namespace App\Console\Commands;

use App\Models\BancoCuenta;
use App\Models\Bitacora;
use App\Services\Banco\ConciliadorConsignacionesService;
use App\Services\Banco\ConciliadorGastosService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Cruza el extracto contra las consignaciones y confirma lo que el banco respalda.
 *
 * Por defecto solo reporta. Sin `--ejecutar` no escribe una sola fila, y esa es
 * la forma de usarlo la primera vez sobre un mes viejo: se mira qué habría
 * hecho antes de dejarlo tocar el libro.
 *
 * Ejemplos:
 *   php artisan banco:conciliar --aliado=1 --desde=2026-08-01 --hasta=2026-08-31
 *   php artisan banco:conciliar --aliado=1 --ejecutar
 *   php artisan banco:conciliar --aliado=1 --cuenta=208 --detalle --ejecutar
 */
class BancoConciliar extends Command
{
    protected $signature = 'banco:conciliar
        {--aliado= : Aliado dueño de las cuentas (obligatorio)}
        {--cuenta= : Solo esta cuenta bancaria. Sin esto, todas las activas}
        {--desde= : Fecha inicial YYYY-MM-DD. Por defecto, los últimos días}
        {--hasta= : Fecha final YYYY-MM-DD. Por defecto, hoy}
        {--dias= : Días de tolerancia entre la fecha del banco y la del libro}
        {--detalle : Lista cruce por cruce y lo que quedó sin explicar}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Cruza los movimientos del banco contra las consignaciones y confirma las que cuadran';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        if ($aliadoId <= 0) {
            $this->error('Falta --aliado. En consola no hay aliado en sesión, hay que decirlo.');

            return self::FAILURE;
        }

        try {
            $hasta = $this->option('hasta') ? Carbon::parse($this->option('hasta')) : Carbon::today();
            $desde = $this->option('desde')
                ? Carbon::parse($this->option('desde'))
                : $hasta->copy()->subDays((int) config('banco.dias_atras', 5));
        } catch (Throwable $e) {
            $this->error('Fechas inválidas. Use el formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $dias = $this->option('dias') !== null
            ? (int) $this->option('dias')
            : (int) config('banco.dias_tolerancia', 2);

        $cuentas = $this->cuentas($aliadoId);
        if ($cuentas->isEmpty()) {
            $this->warn('No hay cuentas bancarias activas para ese aliado.');

            return self::SUCCESS;
        }

        if (! $ejecutar) {
            $this->warn('Modo reporte: no se escribe nada. Agregue --ejecutar para aplicar.');
        }
        $this->newLine();

        $servicio = new ConciliadorConsignacionesService($dias);
        $servicioGastos = new ConciliadorGastosService($dias);
        $filas = [];
        $fallos = 0;

        foreach ($cuentas as $cuenta) {
            try {
                $r = $servicio->conciliar($cuenta, $desde, $hasta, $ejecutar);
                $g = $servicioGastos->conciliar($cuenta, $desde, $hasta, $ejecutar);

                $filas[] = [
                    $cuenta->id,
                    mb_substr($cuenta->etiqueta, 0, 38),
                    $r['movimientos'],
                    $r['consignaciones'],
                    count($r['cruces']),
                    $r['confirmadas'],
                    count($r['movimientos_sin_identificar']),
                    count($r['consignaciones_sin_respaldo']),
                    count($g['cruces']),
                    count($g['salidas_sin_identificar']),
                    $r['ignorados'],
                ];

                if ($this->option('detalle')) {
                    $this->detalle($cuenta, $r);
                }

                if ($ejecutar && $r['cruces'] !== []) {
                    Bitacora::registrar(
                        'conciliar',
                        'BancoMovimiento',
                        (int) $cuenta->id,
                        "Conciliación con el extracto: {$r['confirmadas']} consignaciones confirmadas",
                        [
                            'cuenta' => (int) $cuenta->id,
                            'rango' => [$r['desde'], $r['hasta']],
                            'por_regla' => $r['por_regla'],
                            'sin_identificar' => count($r['movimientos_sin_identificar']),
                        ],
                        (int) $cuenta->aliado_id
                    );
                }
            } catch (Throwable $e) {
                $fallos++;
                $this->error("Cuenta {$cuenta->id}: {$e->getMessage()}");
            }
        }

        if ($filas !== []) {
            $this->table(
                ['Cta', 'Cuenta', 'Entradas', 'Consig', 'Cruces', 'Confirm.', 'Sin ident.', 'Sin respaldo', 'Sal. cruz.', 'Sal. s/ident.', 'Ignorados'],
                $filas
            );
            $this->line('  «Sin respaldo» son consignaciones que el banco no reporta. NO se marcan');
            $this->line('  como `no aparece`: eso lo decide una persona, porque también puede ser');
            $this->line('  que el rango consultado no las alcance.');
        }

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function cuentas(int $aliadoId)
    {
        $query = BancoCuenta::query()
            ->where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->orderBy('banco');

        if ($cuentaId = (int) $this->option('cuenta')) {
            $query->where('id', $cuentaId);
        }

        return $query->get();
    }

    private function detalle(BancoCuenta $cuenta, array $r): void
    {
        $this->newLine();
        $this->info("Cuenta {$cuenta->id} — {$cuenta->etiqueta}");

        if ($r['cruces'] === []) {
            $this->line('  sin cruces');
        }

        foreach ($r['cruces'] as $c) {
            $movs = implode('+', $c['movimientos']);
            $cons = implode('+', $c['consignaciones']);
            $dias = $c['dias'] > 0 ? " ({$c['dias']}d)" : '';
            $this->line(sprintf(
                '  %-14s mov %-14s → consig %-14s %12s%s',
                $c['regla'],
                $movs,
                $cons,
                number_format($c['valor'], 0, ',', '.'),
                $dias
            ));
        }

        if ($r['movimientos_sin_identificar'] !== []) {
            $this->newLine();
            $this->warn('  Entradas del banco que nadie registró: '.implode(', ', array_slice($r['movimientos_sin_identificar'], 0, 30)));
        }

        if ($r['consignaciones_sin_respaldo'] !== []) {
            $this->warn('  Consignaciones que el banco no reporta: '.implode(', ', array_slice($r['consignaciones_sin_respaldo'], 0, 30)));
        }
    }
}
