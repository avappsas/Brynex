<?php

namespace App\Console\Commands;

use App\Models\BancoCuenta;
use App\Services\Banco\ConciliadorConsignacionesService;
use App\Services\Banco\LectorExtractoBancolombia;
use App\Services\Banco\SincronizadorMovimientosService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Carga uno o varios extractos en Excel de la Sucursal Virtual.
 *
 * La pantalla sirve para el día a día —un archivo, un clic—; esto es para
 * cuando hay que meter meses viejos de una sentada, como al arrancar.
 *
 *   php artisan banco:cargar-extracto --cuenta=145 ~/Downloads/812*_2026.xlsx
 *   php artisan banco:cargar-extracto --cuenta=145 --sin-cruzar archivo.xlsx
 */
class BancoCargarExtracto extends Command
{
    protected $signature = 'banco:cargar-extracto
        {archivos* : Uno o más .xlsx descargados de la Sucursal Virtual}
        {--cuenta= : Cuenta bancaria de BryNex a la que corresponden (obligatorio)}
        {--sin-cruzar : Solo guarda los movimientos, sin correr la conciliación}';

    protected $description = 'Carga extractos de Bancolombia en Excel y los cruza contra las consignaciones';

    public function handle(): int
    {
        $cuenta = BancoCuenta::find((int) $this->option('cuenta'));
        if (! $cuenta) {
            $this->error('Falta --cuenta con el id de la cuenta bancaria.');

            return self::FAILURE;
        }

        $this->info("Cuenta: {$cuenta->etiqueta}");
        $this->newLine();

        $lector = new LectorExtractoBancolombia;
        $sinc = new SincronizadorMovimientosService;
        $conciliador = new ConciliadorConsignacionesService;

        $filas = [];
        $fallos = 0;

        foreach ($this->argument('archivos') as $ruta) {
            $nombre = basename($ruta);

            try {
                $ext = $lector->leer($ruta);
                $lector->verificarCuenta($ext, $cuenta);

                if ($ext['movimientos'] === []) {
                    $this->warn("$nombre: sin movimientos, se omite");

                    continue;
                }

                $r = $sinc->guardar($cuenta, $ext['movimientos'], 'extracto_xlsx');

                $cruces = 0;
                $confirmadas = 0;
                $sinIdent = 0;
                $sinResp = 0;

                if (! $this->option('sin-cruzar')) {
                    $c = $conciliador->conciliar(
                        $cuenta, Carbon::parse($r['desde']), Carbon::parse($r['hasta']), true
                    );
                    $cruces = count($c['cruces']);
                    $confirmadas = $c['confirmadas'];
                    $sinIdent = count($c['movimientos_sin_identificar']);
                    $sinResp = count($c['consignaciones_sin_respaldo']);
                }

                $filas[] = [
                    $nombre,
                    "{$r['desde']} → {$r['hasta']}",
                    $r['traidos'],
                    $r['nuevos'],
                    $cruces,
                    $confirmadas,
                    $sinIdent,
                    $sinResp,
                    $ext['descuadre'] === null ? 'ok' : 'DESCUADRE',
                ];
            } catch (Throwable $e) {
                $fallos++;
                $this->error("$nombre: {$e->getMessage()}");
            }
        }

        if ($filas !== []) {
            $this->table(
                ['Archivo', 'Rango', 'Leídos', 'Nuevos', 'Cruces', 'Confirm.', 'Sin ident.', 'Sin respaldo', 'Archivo'],
                $filas
            );
            $this->line('  «Sin ident.» es plata que entró al banco y no está en el libro.');
            $this->line('  «Sin respaldo» son consignaciones registradas que el extracto no reporta.');
        }

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
