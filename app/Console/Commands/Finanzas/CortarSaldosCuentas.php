<?php

namespace App\Console\Commands\Finanzas;

use App\Models\Finanzas\Cuenta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CortarSaldosCuentas extends Command
{
    /**
     * Pone las cuentas reales del dueño con el saldo que tienen hoy.
     *
     * Hasta ahora existía una sola cuenta "Banco" donde cayeron los 4.578 gastos
     * y las 559 entradas de toda la historia, así que su saldo era un acumulado
     * de seis años —$1.355 millones— imposible de cuadrar contra nada.
     *
     * El corte hace dos cosas:
     *  - Archiva las cuentas viejas: conservan sus movimientos (las gráficas de
     *    años pasados no cambian) pero dejan de mostrar ese saldo.
     *  - Crea las cuentas de verdad, ajustando `saldo_inicial` para que el saldo
     *    mostrado sea exactamente el real de hoy. En las que ya existen el ajuste
     *    descuenta los movimientos que ya tenían encima.
     *
     * De aquí en adelante cada movimiento dice de qué cuenta salió, y el saldo
     * se puede verificar contra la app del banco.
     *
     * Sin --apply solo simula. Ejecución: php artisan finanzas:cortar-saldos-cuentas --apply
     */
    protected $signature = 'finanzas:cortar-saldos-cuentas
                            {--user=2 : Id del dueño de las finanzas}
                            {--apply : Escribe los cambios; sin esta opción solo simula}';

    protected $description = 'Archiva las cuentas históricas y deja las cuentas reales con el saldo de hoy';

    /** Cuentas que dejan de mostrarse; sus movimientos se quedan donde están. */
    private const ARCHIVAR = [
        'Banco' => 'Banco (histórico hasta sep-2026)',
        'Efectivo' => 'Efectivo (histórico hasta sep-2026)',
    ];

    /**
     * Las cuentas reales y su saldo de hoy. En las cuentas donde también pasa
     * plata de Brygar, el saldo es solo la parte del dueño, así que no cuadra
     * contra la app del banco a propósito.
     */
    private const CUENTAS = [
        ['nombre' => 'Bancolombia', 'tipo' => 'banco', 'icono' => '🏦', 'saldo' => 66000000, 'nota' => 'Aproximado. Pasa plata de Brygar; aquí va solo lo propio.'],
        ['nombre' => 'BBVA', 'tipo' => 'banco', 'icono' => '🏦', 'saldo' => 33106000, 'nota' => 'Pasa plata de Brygar; aquí va solo lo propio.'],
        ['nombre' => 'BBVA — Mi Proyecto', 'tipo' => 'otro', 'icono' => '🐷', 'saldo' => 24161, 'nota' => null],
        ['nombre' => 'Nequi', 'tipo' => 'billetera', 'icono' => '📱', 'saldo' => 4809000, 'nota' => 'Puede tener plata de Brygar mezclada.'],
        ['nombre' => 'Daviplata', 'tipo' => 'billetera', 'icono' => '📱', 'saldo' => 18300, 'nota' => null],
        ['nombre' => 'Banco Falabella', 'tipo' => 'banco', 'icono' => '🏦', 'saldo' => 401241, 'nota' => null],
        ['nombre' => 'PayPal', 'tipo' => 'billetera', 'icono' => '💵', 'saldo' => 3250968, 'nota' => 'US$1.046 al cambio del 07-09-2026 ($3.108). Actualizar cuando se mueva.'],
        ['nombre' => 'Lulobank', 'tipo' => 'banco', 'icono' => '🏦', 'saldo' => 18644, 'nota' => null],
        ['nombre' => 'Lulo — Bolsillo Flex Ahorro 1', 'tipo' => 'otro', 'icono' => '🐷', 'saldo' => 1206680, 'nota' => 'Renta 7,5% E.A., unos $7.388 al mes.'],
        ['nombre' => 'Nubank', 'tipo' => 'banco', 'icono' => '🏦', 'saldo' => 11522, 'nota' => null],
        ['nombre' => 'Nu — Mi primera Cajita', 'tipo' => 'otro', 'icono' => '🐷', 'saldo' => 4922889, 'nota' => 'Renta 9,3% E.A. Lleva $822.890 de rendimientos.'],
        ['nombre' => 'Caja 1', 'tipo' => 'efectivo', 'icono' => '💵', 'saldo' => 400000000, 'nota' => 'Efectivo guardado.'],
        ['nombre' => 'Caja 2', 'tipo' => 'efectivo', 'icono' => '💵', 'saldo' => 60000000, 'nota' => 'Efectivo guardado.'],
    ];

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $aplicar = (bool) $this->option('apply');

        // Sin caché: el ajuste se calcula contra el saldo que hay ahora mismo.
        Cache::forget("finanzas_cuentas_{$userId}");
        $saldos = Cuenta::conSaldos($userId)->keyBy('nombre');

        $filas = [];

        foreach (self::ARCHIVAR as $nombre => $nombreNuevo) {
            $cuenta = Cuenta::where('user_id', $userId)->where('nombre', $nombre)->first();
            $filas[] = [
                $cuenta ? $nombreNuevo : $nombre,
                'archivar',
                $cuenta ? number_format($saldos[$nombre]->saldo_actual ?? 0) : '—',
                '—',
                $cuenta ? 'se archiva' : '⚠ no existe',
            ];
        }

        foreach (self::CUENTAS as $def) {
            $existente = Cuenta::where('user_id', $userId)->where('nombre', $def['nombre'])->first();
            $saldoHoy = $existente ? (float) ($saldos[$def['nombre']]->saldo_actual ?? 0) : 0.0;

            $filas[] = [
                $def['icono'].' '.$def['nombre'],
                $def['tipo'],
                number_format($saldoHoy),
                number_format($def['saldo']),
                $existente ? 'se ajusta' : 'se crea',
            ];
        }

        $this->table(['Cuenta', 'Tipo', 'Saldo actual', 'Saldo objetivo', 'Estado'], $filas);
        $this->line('Total de las cuentas nuevas: $'.number_format(collect(self::CUENTAS)->sum('saldo')).'.');

        if (! $aplicar) {
            $this->warn('Simulación: no se escribió nada. Repite con --apply.');

            return self::SUCCESS;
        }

        DB::connection('finanzas')->transaction(function () use ($userId, $saldos) {
            foreach (self::ARCHIVAR as $nombre => $nombreNuevo) {
                Cuenta::where('user_id', $userId)->where('nombre', $nombre)
                    ->update(['nombre' => $nombreNuevo, 'activo' => false]);
            }

            foreach (self::CUENTAS as $orden => $def) {
                $cuenta = Cuenta::firstOrNew([
                    'user_id' => $userId,
                    'nombre' => $def['nombre'],
                ]);

                // El saldo que se ve es `saldo_inicial` más los movimientos que ya
                // tenga la cuenta encima; el ajuste va sobre esa diferencia para
                // que quede clavado en el saldo real.
                $movimientos = $cuenta->exists
                    ? (float) ($saldos[$def['nombre']]->saldo_actual ?? 0) - (float) $cuenta->saldo_inicial
                    : 0.0;

                $cuenta->fill([
                    'tipo' => $def['tipo'],
                    'icono' => $def['icono'],
                    'saldo_inicial' => $def['saldo'] - $movimientos,
                    'activo' => true,
                    'orden' => $orden + 1,
                ])->save();
            }
        });

        Cache::forget("finanzas_cuentas_{$userId}");
        app(\App\Services\Finanzas\FinanzasAlertaService::class)->invalidarCacheUsuario($userId);

        $this->info('Listo. Cuentas archivadas: '.count(self::ARCHIVAR).'. Cuentas al día: '.count(self::CUENTAS).'.');

        return self::SUCCESS;
    }
}
