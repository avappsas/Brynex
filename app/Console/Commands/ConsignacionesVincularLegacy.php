<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Le devuelve la cuenta y la factura a UNA consignación por factura del legacy.
 *
 * De dónde viene el problema: la migración metió las consignaciones de BRYGAR
 * en una sola cuenta —la 137, «Bancolombia Brayan Garcia»— y sin `factura_id`.
 * El dato no se perdió, está en la propia fila: `referencia` guarda el id de
 * cuenta del legacy y `observacion` guarda «Migrada de factura legacy #107935».
 * Por eso la ficha de la razón social mostraba enero a abril en cero.
 *
 * Y está duplicado. La migración se corrió varias veces —27, 28 y 30 de abril y
 * 1 de mayo— y cada corrida volvió a insertar: de 1.261 facturas del legacy en
 * la cuenta 8 salieron 5.036 consignaciones, casi todas por cuadruplicado.
 * Poner la cuenta buena encima de las cuatro copias multiplicaría la plata por
 * cuatro: 1.221 millones donde el banco dice 305.
 *
 * Por eso este comando toca UNA copia por factura y deja las otras exactamente
 * como están. No borra nada. Las copias que sobran se quedan en la cuenta 137
 * sin factura, igual de invisibles que hoy, y quedan para limpiarlas aparte.
 *
 * Cuál es la buena la dice `Brygar_BD`, no el azar: en 35 facturas las copias
 * traen valores distintos entre sí, así que se prefiere la que cuadra con el
 * `Valor_Consignado` del legacy y solo si ninguna cuadra se toma la más
 * antigua.
 *
 * El rango se mide sobre el `Fecha_Pago` del legacy y no sobre la fecha de la
 * consignación: son la misma fecha salvo por unos pocos días de corrimiento, y
 * cortar por el lado de Brynex dejaba 716.300 pesos de abril por fuera.
 *
 * Solo sirve para el aliado dueño de `sqlsrv_legacy` — ver `config/legacy.php`
 * sobre por qué el id de cuenta 8 significa un banco distinto en la base de
 * cada aliado.
 *
 * Sin `--ejecutar` solo reporta. Con `--revertir` deshace: a cada fila que este
 * comando movió le devuelve la cuenta que todavía tienen sus copias hermanas.
 */
class ConsignacionesVincularLegacy extends Command
{
    protected $signature = 'consignaciones:vincular-legacy
        {--aliado= : Aliado cuyas consignaciones se vinculan}
        {--cuenta= : Cuenta de Brynex a la que pertenecen}
        {--cuenta-legacy= : Id de esa misma cuenta en la base vieja}
        {--desde= : Desde esta fecha de pago del legacy (YYYY-MM-DD)}
        {--hasta= : Hasta esta fecha, sin incluirla (YYYY-MM-DD)}
        {--revertir : Deshace lo que este comando dejó en esa cuenta}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Vincula a su cuenta y a su factura una consignación por factura migrada del legacy';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $cuentaId = (int) $this->option('cuenta');
        $cuentaLegacy = (string) $this->option('cuenta-legacy');

        if (! $aliadoId || ! $cuentaId) {
            $this->error('Faltan --aliado y --cuenta.');

            return self::FAILURE;
        }

        if ($aliadoId !== (int) config('legacy.aliado_id')) {
            $this->error('La base legacy configurada no es la del aliado '.$aliadoId.'. Ver config/legacy.php.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');

        if ($this->option('revertir')) {
            return $this->revertir($aliadoId, $cuentaId, $ejecutar);
        }

        if (! $cuentaLegacy || ! $this->option('desde') || ! $this->option('hasta')) {
            $this->error('Faltan --cuenta-legacy, --desde y --hasta.');

            return self::FAILURE;
        }

        $legacy = DB::connection(config('legacy.conexion'))->select(
            'SELECT Id_Factura id, ISNULL(TRY_CAST(Valor_Consignado AS float), 0) valor
               FROM FACTURACION
              WHERE Consignacion = ? AND Fecha_Pago >= ? AND Fecha_Pago < ?',
            [$cuentaLegacy, $this->option('desde'), $this->option('hasta')]
        );

        $this->line('facturas en el legacy: '.count($legacy));

        if (! $legacy) {
            return self::SUCCESS;
        }

        // Las facturas y las consignaciones se indexan de una: son miles y
        // preguntar de a una serían miles de viajes al SQL Server a 250 ms.
        $porLegacy = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->whereNotNull('id_legacy')
            ->pluck('id', 'id_legacy');

        $candidatas = DB::table('consignaciones')
            ->where('aliado_id', $aliadoId)
            ->where('referencia', $cuentaLegacy)
            ->where('observacion', 'like', 'Migrada de factura legacy%')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'observacion', 'valor', 'banco_cuenta_id', 'factura_id'])
            ->groupBy(fn ($c) => preg_match('/#(\d+)/', (string) $c->observacion, $m) ? (int) $m[1] : 0);

        $this->line('consignaciones migradas de esa cuenta: '.$candidatas->flatten()->count()
                   .' en '.$candidatas->count().' facturas');

        $escoger = [];
        $r = ['ok' => 0, 'ya' => 0, 'sin_consignacion' => 0, 'sin_factura' => 0, 'por_valor' => 0, 'por_antiguedad' => 0];

        foreach ($legacy as $f) {
            $grupo = $candidatas->get((int) $f->id);

            if (! $grupo) {
                $r['sin_consignacion']++;

                continue;
            }

            $facturaId = $porLegacy[(int) $f->id] ?? null;

            if (! $facturaId) {
                $r['sin_factura']++;

                continue;
            }

            // La que cuadra con el legacy manda; si ninguna cuadra, la primera
            // que se insertó.
            $elegida = $grupo->firstWhere('valor', '==', round((float) $f->valor));
            $elegida ? $r['por_valor']++ : $r['por_antiguedad']++;
            $elegida ??= $grupo->first();

            if ((int) $elegida->banco_cuenta_id === $cuentaId && (int) $elegida->factura_id === (int) $facturaId) {
                $r['ya']++;

                continue;
            }

            $escoger[] = ['id' => $elegida->id, 'factura_id' => $facturaId];
            $r['ok']++;
        }

        $this->table(['concepto', 'cuántas'], collect($r)->map(fn ($v, $k) => [$k, $v])->values());

        if (! $ejecutar) {
            $this->warn('SIMULACIÓN — no se escribió nada. Agregue --ejecutar.');

            return self::SUCCESS;
        }

        $barra = $this->output->createProgressBar(count($escoger));

        foreach (array_chunk($escoger, 200) as $lote) {
            DB::transaction(function () use ($lote, $cuentaId) {
                foreach ($lote as $fila) {
                    DB::table('consignaciones')->where('id', $fila['id'])
                        ->update(['banco_cuenta_id' => $cuentaId, 'factura_id' => $fila['factura_id']]);
                }
            });
            $barra->advance(count($lote));
        }

        $barra->finish();
        $this->newLine(2);
        $this->info(count($escoger).' consignaciones vinculadas a la cuenta '.$cuentaId.'.');

        return self::SUCCESS;
    }

    /**
     * Deshace: a cada fila movida le devuelve la cuenta que sus copias
     * hermanas —las que este comando no tocó— siguen teniendo.
     */
    private function revertir(int $aliadoId, int $cuentaId, bool $ejecutar): int
    {
        $movidas = DB::table('consignaciones')
            ->where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $cuentaId)
            ->where('observacion', 'like', 'Migrada de factura legacy%')
            ->whereNull('deleted_at')
            ->get(['id', 'observacion']);

        $this->line('filas movidas por este comando: '.$movidas->count());

        if ($movidas->isEmpty()) {
            return self::SUCCESS;
        }

        $hermanas = DB::table('consignaciones')
            ->where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', '!=', $cuentaId)
            ->where('observacion', 'like', 'Migrada de factura legacy%')
            ->whereNull('deleted_at')
            ->get(['observacion', 'banco_cuenta_id'])
            ->groupBy('observacion')
            ->map(fn ($g) => (int) $g->first()->banco_cuenta_id);

        $sinHermana = $movidas->filter(fn ($c) => ! $hermanas->has($c->observacion));

        if ($sinHermana->isNotEmpty()) {
            $this->warn($sinHermana->count().' filas no tienen copia hermana: se quedan como están.');
        }

        if (! $ejecutar) {
            $this->warn('SIMULACIÓN — no se escribió nada. Agregue --ejecutar.');

            return self::SUCCESS;
        }

        $n = 0;

        foreach ($movidas->groupBy(fn ($c) => $hermanas[$c->observacion] ?? 0) as $cuenta => $g) {
            if (! $cuenta) {
                continue;
            }

            foreach ($g->pluck('id')->chunk(300) as $ch) {
                $n += DB::table('consignaciones')->whereIn('id', $ch->all())
                    ->update(['banco_cuenta_id' => (int) $cuenta, 'factura_id' => null]);
            }
        }

        $this->info($n.' consignaciones devueltas a su cuenta anterior.');

        return self::SUCCESS;
    }
}
