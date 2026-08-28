<?php

namespace App\Console\Commands;

use App\Models\BancoCuenta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ NO CORRER TODAVÍA. Ver «Antes de usar esto» al final del bloque.
 *
 * Devuelve a las consignaciones migradas la cuenta y la factura que tenían.
 *
 * La migración desde el legacy metió las 38.218 consignaciones de BRYGAR en
 * una sola cuenta —la 137, «Bancolombia Brayan Garcia»— y sin `factura_id`.
 * Durante meses pareció que el dato se había perdido y que solo existía en
 * `Brygar_BD`.
 *
 * No se perdió: está en la propia fila.
 *   `referencia`  guarda el id de cuenta del legacy (`FACTURACION.Consignacion`)
 *   `observacion` guarda «Migrada de factura legacy #107935», el `Id_Factura`
 *
 * Verificado contra el legacy sobre 200 filas al azar: coinciden las 200.
 *
 * Las cuentas se emparejan por número, no por id: los ids del legacy y los de
 * `banco_cuentas` coinciden hoy por casualidad del orden de creación, y
 * confiar en eso es frágil.
 *
 * Solo sirve para el aliado 2. `Brygar_BD` es la base de BRYGAR y nada más;
 * cada aliado tenía la suya, y el id 8 significaba una cuenta distinta en cada
 * una. Correr esto sobre otro aliado le asignaría cuentas ajenas.
 *
 * Sin `--ejecutar` solo reporta.
 *
 * ── Antes de usar esto ──────────────────────────────────────────────────
 *
 * Las consignaciones migradas están DUPLICADAS y hay que limpiarlas primero.
 * La migración se corrió al menos siete veces —27, 28 y 30 de abril; 1 y 8 de
 * mayo; 30 de junio; 1 de julio— y cada corrida volvió a insertar: de 17.566
 * facturas del legacy salieron 38.218 consignaciones. 16.023 facturas quedaron
 * con 2 copias y 1.543 con 4.
 *
 * El total no cuadra por más del doble:
 *   legacy `Valor_Consignado` .... $2.822.677.261
 *   brynex consignaciones ........ $6.400.915.818
 *
 * Los extractos bancarios de la cuenta 812-000169-10 confirman cuál es la
 * buena: de octubre-2025 a julio-2026 el banco registra entre $30M y $86M
 * mensuales, coherentes con el legacy y no con Brynex.
 *
 * Poner la cuenta correcta encima de filas duplicadas solo consolida el error:
 * la cuenta BRYGAR pasaría a mostrar $1.515M donde el banco dice $449M, y cada
 * factura quedaría con 2 o 4 consignaciones colgando, aparentando estar pagada
 * varias veces.
 *
 * El orden correcto es: dejar una sola consignación por factura del legacy
 * —eligiendo la que coincide con `Brygar_BD`, porque en 1.916 casos las copias
 * difieren— y solo entonces asignar la cuenta.
 */
class ConsignacionesRestaurarCuenta extends Command
{
    protected $signature = 'consignaciones:restaurar-cuenta
        {--aliado= : Aliado cuyas consignaciones se corrigen}
        {--desde= : Solo desde esta fecha (YYYY-MM-DD)}
        {--cuenta= : Solo las consignaciones que van a esta cuenta de Brynex}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Restaura la cuenta bancaria y la factura de las consignaciones migradas del legacy';

    /**
     * Cuentas del legacy: número y banco.
     *
     * El banco hace falta porque el número no basta: Daviplata y NEQUI son la
     * misma línea, 3117762689, y emparejar solo por número mandaba las 2.914
     * consignaciones de NEQUI a Daviplata.
     */
    private const CUENTAS_LEGACY = [
        '1' => ['752-779531-45', 'bancolombia'],
        '2' => ['571-322-395', 'bbva'],
        '3' => ['227-032-968', 'bbva'],
        '4' => ['3117762689', 'daviplata'],
        '5' => ['3117762689', 'nequi'],
        '6' => ['80800006688', 'bancolombia'],
        '7' => ['808-000063-86', 'bancolombia'],
        '8' => ['812-000169-10', 'bancolombia'],
    ];

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');

        if (! $aliadoId) {
            $this->error('Falta --aliado.');

            return self::FAILURE;
        }

        $mapa = $this->mapaDeCuentas($aliadoId);

        // Acotar a una sola cuenta es lo prudente cuando solo importa una: se
        // corrigen 6.272 filas en vez de 21.812, y las demás quedan como
        // estaban por si alguien quiere revisarlas antes.
        $soloCuenta = (int) $this->option('cuenta');

        if ($soloCuenta) {
            $mapa = array_filter($mapa, fn ($id) => $id === $soloCuenta);

            if (empty($mapa)) {
                $this->error("Ninguna cuenta del legacy corresponde a la cuenta {$soloCuenta}.");

                return self::FAILURE;
            }
        }

        if (empty($mapa)) {
            $this->error('No se pudo emparejar ninguna cuenta del legacy con las de este aliado.');

            return self::FAILURE;
        }

        $this->line('Cuentas emparejadas:');
        foreach ($mapa as $legacy => $id) {
            $c = BancoCuenta::find($id);
            $this->line("  legacy {$legacy} → {$id}  {$c->banco} — {$c->nombre} ({$c->numero_cuenta})");
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $this->newLine();
        $this->info('Analizando'.($ejecutar ? '' : '  (SIMULACIÓN — no escribe nada)'));

        // Las facturas se indexan una sola vez: son decenas de miles y
        // consultarlas por fila serían decenas de miles de viajes al SQL Server
        // a ~250 ms cada uno.
        $porLegacy = DB::table('facturas')
            ->where('aliado_id', $aliadoId)
            ->whereNotNull('id_legacy')
            ->pluck('id', 'id_legacy');

        $this->line('  facturas con id_legacy: '.$porLegacy->count());

        $resumen = ['cuenta' => 0, 'factura' => 0, 'sin_mapa' => 0, 'sin_factura' => 0];
        $porCuenta = [];

        DB::table('consignaciones')
            ->where('aliado_id', $aliadoId)
            ->where('observacion', 'like', 'Migrada de factura legacy%')
            ->when($this->option('desde'), fn ($q) => $q->whereDate('fecha', '>=', $this->option('desde')))
            ->select('id', 'referencia', 'observacion', 'banco_cuenta_id', 'factura_id')
            ->orderBy('id')
            ->chunk(500, function ($filas) use ($mapa, $porLegacy, $ejecutar, $soloCuenta, &$resumen, &$porCuenta) {
                $cambios = [];

                foreach ($filas as $c) {
                    $ref = trim((string) $c->referencia);

                    // Con --cuenta, las filas de otras cuentas se dejan
                    // completamente en paz: ni la cuenta ni la factura. Acotar
                    // a medias sería peor que no acotar.
                    if ($soloCuenta && ! isset($mapa[$ref])) {
                        continue;
                    }

                    $set = [];

                    // '0' es efectivo y '' es sin dato: ninguno tiene cuenta.
                    if (isset($mapa[$ref]) && (int) $c->banco_cuenta_id !== $mapa[$ref]) {
                        $set['banco_cuenta_id'] = $mapa[$ref];
                        $resumen['cuenta']++;
                        $porCuenta[$mapa[$ref]] = ($porCuenta[$mapa[$ref]] ?? 0) + 1;
                    } elseif (! isset($mapa[$ref])) {
                        $resumen['sin_mapa']++;
                    }

                    if ($c->factura_id === null && preg_match('/#(\d+)/', (string) $c->observacion, $m)) {
                        $facturaId = $porLegacy[(int) $m[1]] ?? null;

                        if ($facturaId) {
                            $set['factura_id'] = $facturaId;
                            $resumen['factura']++;
                        } else {
                            $resumen['sin_factura']++;
                        }
                    }

                    if ($set) {
                        $cambios[$c->id] = $set;
                    }
                }

                if ($ejecutar && $cambios) {
                    DB::transaction(function () use ($cambios) {
                        foreach ($cambios as $id => $set) {
                            DB::table('consignaciones')->where('id', $id)->update($set);
                        }
                    });
                }
            });

        $this->newLine();
        $this->line('  cuenta corregida ....... '.number_format($resumen['cuenta']));
        $this->line('  factura enlazada ....... '.number_format($resumen['factura']));
        $this->line('  sin cuenta que asignar . '.number_format($resumen['sin_mapa']).'   (efectivo o referencia vacía)');
        $this->line('  factura legacy no ubicada '.number_format($resumen['sin_factura']));

        if ($porCuenta) {
            $this->newLine();
            $this->line('Reparto por cuenta:');
            arsort($porCuenta);
            foreach ($porCuenta as $id => $n) {
                $c = BancoCuenta::find($id);
                $this->line('  '.str_pad($c->nombre.' ('.$c->numero_cuenta.')', 46).number_format($n));
            }
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Nada se escribió. Repite con --ejecutar para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * Empareja las cuentas del legacy con las de Brynex por número Y banco.
     *
     * Los números se comparan sin guiones ni espacios, porque el legacy y
     * Brynex los escriben distinto. El banco desempata las que comparten
     * número, como Daviplata y NEQUI.
     */
    private function mapaDeCuentas(int $aliadoId): array
    {
        $cuentas = BancoCuenta::where('aliado_id', $aliadoId)->get();
        $mapa = [];

        foreach (self::CUENTAS_LEGACY as $legacy => [$numero, $banco]) {
            $buscado = preg_replace('/\D+/', '', $numero);

            $hit = $cuentas->first(
                fn ($c) => preg_replace('/\D+/', '', (string) $c->numero_cuenta) === $buscado
                    && str_contains(mb_strtolower((string) $c->banco), $banco)
            );

            if ($hit) {
                $mapa[$legacy] = (int) $hit->id;
            } else {
                $this->warn("  cuenta legacy {$legacy} ({$banco} {$numero}) no existe en este aliado; sus consignaciones se dejan como están.");
            }
        }

        return $mapa;
    }
}
