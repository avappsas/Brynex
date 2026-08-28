<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Le devuelve a los gastos migrados la cuenta de la que salió la plata.
 *
 * La base vieja sí la guarda —`Gastos.Banco`, el mismo id de cuenta que usa
 * `FACTURACION.Consignacion`— pero el paso 12 de la migración nunca leyó esa
 * columna: trajo el gasto con `banco_origen_id` en null y `forma_pago` en
 * efectivo. Por eso la ficha de la razón social mostraba entradas reales de
 * enero a abril contra cero de salidas, y el neto quedaba en blanco.
 *
 * A diferencia de las consignaciones, aquí no hay que desduplicar nada: el paso
 * 12 sí es re-entrante por `id_legacy`, así que cada gasto está una sola vez.
 *
 * Nunca pisa un gasto que ya tenga cuenta. Si alguien la corrigió a mano
 * después de la migración, esa corrección manda sobre el legacy.
 *
 * Va de a una cuenta con `--cuenta-legacy`. Correrlo para todas de una haría
 * aparecer plata en fichas que nadie pidió revisar, y el defecto es el mismo
 * para cada una: se corrige la que se está mirando.
 *
 * Solo sirve para el aliado dueño de `sqlsrv_legacy` — ver `config/legacy.php`
 * sobre por qué el id de cuenta 8 significa un banco distinto en la base de
 * cada aliado.
 *
 * Sin `--ejecutar` solo reporta.
 */
class GastosRestaurarCuenta extends Command
{
    protected $signature = 'gastos:restaurar-cuenta
        {--aliado= : Aliado cuyos gastos se corrigen}
        {--cuenta= : Cuenta de Brynex de la que salió la plata}
        {--cuenta-legacy= : Id de esa misma cuenta en la base vieja}
        {--desde= : Desde esta fecha (YYYY-MM-DD)}
        {--hasta= : Hasta esta fecha, sin incluirla (YYYY-MM-DD)}
        {--revertir : Le quita la cuenta a los gastos que este comando marcó}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Restaura la cuenta de origen de los gastos migrados del legacy';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $cuentaId = (int) $this->option('cuenta');
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');

        if (! $aliadoId || ! $cuentaId || ! $desde || ! $hasta) {
            $this->error('Faltan --aliado, --cuenta, --desde y --hasta.');

            return self::FAILURE;
        }

        if ($aliadoId !== (int) config('legacy.aliado_id')) {
            $this->error('La base legacy configurada no es la del aliado '.$aliadoId.'. Ver config/legacy.php.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');

        if ($this->option('revertir')) {
            return $this->revertir($aliadoId, $cuentaId, $desde, $hasta, $ejecutar);
        }

        $cuentaLegacy = (string) $this->option('cuenta-legacy');

        if ($cuentaLegacy === '') {
            $this->error('Falta --cuenta-legacy.');

            return self::FAILURE;
        }

        $legacy = DB::connection(config('legacy.conexion'))->select(
            'SELECT Id id, Tipo tipo, ISNULL(TRY_CAST(VALOR AS float), 0) valor
               FROM Gastos
              WHERE Banco = ? AND Fecha >= ? AND Fecha < ?',
            [$cuentaLegacy, $desde, $hasta]
        );

        $this->line('gastos en el legacy con esa cuenta: '.count($legacy)
                   .'  ('.number_format(array_sum(array_column($legacy, 'valor'))).')');

        if (! $legacy) {
            return self::SUCCESS;
        }

        $ids = array_map(fn ($g) => (int) $g->id, $legacy);
        $enBrynex = collect();

        foreach (array_chunk($ids, 400) as $ch) {
            $enBrynex = $enBrynex->concat(
                DB::table('gastos')->where('aliado_id', $aliadoId)->whereIn('id_legacy', $ch)
                    ->get(['id', 'id_legacy', 'tipo', 'valor', 'fecha', 'banco_origen_id'])
            );
        }

        $porCambiar = $enBrynex->filter(fn ($g) => $g->banco_origen_id === null);
        $yaTenian = $enBrynex->reject(fn ($g) => $g->banco_origen_id === null);

        $this->table(['concepto', 'cuántos', 'valor'], [
            ['encontrados en Brynex', $enBrynex->count(), number_format($enBrynex->sum('valor'))],
            ['no migrados', count($ids) - $enBrynex->count(), ''],
            ['por marcar', $porCambiar->count(), number_format($porCambiar->sum('valor'))],
            ['ya tenían cuenta', $yaTenian->count(), number_format($yaTenian->sum('valor'))],
        ]);

        if ($yaTenian->isNotEmpty()) {
            $otras = $yaTenian->groupBy('banco_origen_id')->map->count();
            $this->warn('  se respetan las que ya tenían cuenta: '.$otras->toJson());
        }

        foreach ($porCambiar->groupBy(fn ($g) => substr((string) $g->fecha, 0, 7))->sortKeys() as $mes => $g) {
            $this->line(sprintf('  %s  n=%4d  %16s', $mes, $g->count(), number_format($g->sum('valor'))));
        }

        if (! $ejecutar) {
            $this->warn('SIMULACIÓN — no se escribió nada. Agregue --ejecutar.');

            return self::SUCCESS;
        }

        $n = 0;

        foreach ($porCambiar->pluck('id')->chunk(300) as $ch) {
            $n += DB::table('gastos')->whereIn('id', $ch->all())
                ->update(['banco_origen_id' => $cuentaId]);
        }

        $this->info($n.' gastos marcados como salidos de la cuenta '.$cuentaId.'.');

        return self::SUCCESS;
    }

    /**
     * Deshace. Solo toca gastos migrados —los que tienen `id_legacy`—, así que
     * un gasto registrado a mano en esa cuenta se queda como está.
     */
    private function revertir(int $aliadoId, int $cuentaId, string $desde, string $hasta, bool $ejecutar): int
    {
        $q = DB::table('gastos')
            ->where('aliado_id', $aliadoId)
            ->where('banco_origen_id', $cuentaId)
            ->whereNotNull('id_legacy')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<', $hasta);

        $this->line('gastos a devolver: '.$q->count());

        if (! $ejecutar) {
            $this->warn('SIMULACIÓN — no se escribió nada. Agregue --ejecutar.');

            return self::SUCCESS;
        }

        $this->info($q->update(['banco_origen_id' => null]).' gastos quedaron sin cuenta de origen.');

        return self::SUCCESS;
    }
}
