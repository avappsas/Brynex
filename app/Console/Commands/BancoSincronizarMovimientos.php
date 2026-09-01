<?php

namespace App\Console\Commands;

use App\Models\BancoCuenta;
use App\Services\Banco\BancoApiFactory;
use App\Services\Banco\SincronizadorMovimientosService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Baja el extracto del banco a `banco_movimientos`.
 *
 * Solo trae y guarda: no concilia ni mueve un peso del libro, así que se puede
 * volver a correr sobre el mismo rango cuantas veces se quiera — lo repetido
 * se descarta por huella.
 *
 * Ejemplos:
 *   php artisan banco:sincronizar-movimientos --aliado=1
 *   php artisan banco:sincronizar-movimientos --aliado=1 --desde=2026-08-01 --hasta=2026-08-31
 *   php artisan banco:sincronizar-movimientos --aliado=1 --cuenta=3 --saldo
 *
 * Mientras Bancolombia no active el producto, el adaptador por defecto es el
 * falso (config/banco.php): arma el extracto a partir de lo que ya está
 * registrado en la cuenta y le agrega el ruido de un extracto real.
 */
class BancoSincronizarMovimientos extends Command
{
    protected $signature = 'banco:sincronizar-movimientos
        {--aliado= : Aliado dueño de las cuentas (obligatorio)}
        {--cuenta= : Solo esta cuenta bancaria. Sin esto, todas las activas}
        {--desde= : Fecha inicial YYYY-MM-DD. Por defecto, los últimos días}
        {--hasta= : Fecha final YYYY-MM-DD. Por defecto, hoy}
        {--proveedor= : Fuerza un adaptador (fake, …). Por defecto el de config}
        {--saldo : Además compara el saldo del banco contra el de BryNex}';

    protected $description = 'Trae los movimientos del banco y los guarda sin tocar el libro de BryNex';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        if ($aliadoId <= 0) {
            $this->error('Falta --aliado. En consola no hay aliado en sesión, hay que decirlo.');

            return self::FAILURE;
        }

        try {
            $desde = $this->option('desde') ? Carbon::parse($this->option('desde')) : null;
            $hasta = $this->option('hasta') ? Carbon::parse($this->option('hasta')) : null;
        } catch (Throwable $e) {
            $this->error('Fechas inválidas. Use el formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        $cuentas = $this->cuentas($aliadoId);
        if ($cuentas->isEmpty()) {
            $this->warn('No hay cuentas bancarias activas para ese aliado.');

            return self::SUCCESS;
        }

        try {
            $api = BancoApiFactory::make($this->option('proveedor'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $servicio = new SincronizadorMovimientosService($api);

        $this->info("Adaptador: {$api->nombre()}");
        $this->newLine();

        $filas = [];
        $fallos = 0;

        foreach ($cuentas as $cuenta) {
            try {
                $r = $servicio->sincronizar($cuenta, $desde, $hasta);

                $filas[] = [
                    $cuenta->id,
                    mb_substr($cuenta->etiqueta, 0, 45),
                    "{$r['desde']} → {$r['hasta']}",
                    $r['traidos'],
                    $r['nuevos'],
                    $r['repetidos'],
                    $r['creditos'],
                    $r['debitos'],
                ];
            } catch (Throwable $e) {
                $fallos++;
                $this->error("Cuenta {$cuenta->id}: {$e->getMessage()}");
            }
        }

        if ($filas !== []) {
            $this->table(
                ['Cta', 'Cuenta', 'Rango', 'Traídos', 'Nuevos', 'Repetidos', 'Entradas', 'Salidas'],
                $filas
            );
        }

        if ($this->option('saldo')) {
            $this->compararSaldos($servicio, $cuentas);
        }

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function cuentas(int $aliadoId)
    {
        $query = BancoCuenta::query()
            ->where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->orderBy('banco');

        // Comparación laxa a propósito: sqlsrv devuelve los ids como string.
        if ($cuentaId = (int) $this->option('cuenta')) {
            $query->where('id', $cuentaId);
        }

        return $query->get();
    }

    private function compararSaldos(SincronizadorMovimientosService $servicio, $cuentas): void
    {
        $this->newLine();
        $this->info('Saldo del banco contra el saldo de BryNex:');

        $filas = [];

        foreach ($cuentas as $cuenta) {
            try {
                $c = $servicio->compararSaldo($cuenta);

                $filas[] = [
                    $cuenta->id,
                    mb_substr($cuenta->etiqueta, 0, 45),
                    number_format($c['banco'], 0, ',', '.'),
                    number_format($c['libro'], 0, ',', '.'),
                    number_format($c['diferencia'], 0, ',', '.'),
                ];
            } catch (Throwable $e) {
                $this->error("Cuenta {$cuenta->id}: {$e->getMessage()}");
            }
        }

        if ($filas !== []) {
            $this->table(['Cta', 'Cuenta', 'Banco', 'BryNex', 'Diferencia'], $filas);
            $this->line('  La diferencia es informativa: nada se ajusta solo.');
        }
    }
}
