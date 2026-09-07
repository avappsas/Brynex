<?php

namespace App\Services\Banco;

use App\Models\BancoCuenta;
use App\Models\BancoMovimiento;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cruza las salidas del extracto contra los gastos registrados.
 *
 * Es el otro lado del cuadre. Las consignaciones explican la plata que entra;
 * los gastos —planillas de seguridad social, proveedores, traslados— explican
 * la que sale. Sin esto, más de la mitad de los movimientos de un extracto se
 * quedaban sin revisar: en la cuenta de Brygar eran 568 salidas.
 *
 * Usa el mismo motor que las entradas, así que hereda su criterio: donde no
 * puede decidir, no decide.
 *
 * Diferencia importante con las consignaciones: aquí no se «confirma» nada.
 * La tabla de gastos no tiene un estado de validación, así que lo único que
 * queda registrado es el vínculo — qué salida del banco corresponde a qué
 * gasto. Un gasto sin respaldo en el extracto es un gasto registrado que el
 * banco no reporta, y eso lo revisa una persona.
 */
class ConciliadorGastosService
{
    private EmparejadorMovimientos $emparejador;

    private int $diasTolerancia;

    public function __construct(
        ?int $diasTolerancia = null,
        private int $toleranciaValor = 0,
    ) {
        $this->diasTolerancia = $diasTolerancia ?? (int) config('banco.dias_tolerancia_gastos', 30);
        $this->emparejador = new EmparejadorMovimientos($this->diasTolerancia, $toleranciaValor);
    }

    /**
     * @return array{cuenta_id:int, desde:string, hasta:string, movimientos:int,
     *               gastos:int, cruces:array, por_regla:array,
     *               salidas_sin_identificar:array, gastos_sin_respaldo:array}
     */
    public function conciliar(
        BancoCuenta $cuenta,
        CarbonInterface $desde,
        CarbonInterface $hasta,
        bool $ejecutar = false,
    ): array {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        $movs = $this->emparejador->normalizar($this->salidasLibres($cuenta, $desde, $hasta));
        $gastos = $this->emparejador->normalizar($this->gastosLibres($cuenta, $desde, $hasta));

        $cruces = $this->emparejador->emparejar($movs, $gastos);

        if ($ejecutar) {
            $this->escribir($cuenta, $cruces);
        }

        $porRegla = [];
        foreach ($cruces as $c) {
            $porRegla[$c['regla']] = ($porRegla[$c['regla']] ?? 0) + 1;
        }

        return [
            'cuenta_id' => (int) $cuenta->id,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'movimientos' => count($movs),
            'gastos' => count($gastos),
            'cruces' => $cruces,
            'por_regla' => $porRegla,
            'salidas_sin_identificar' => $this->emparejador->idsLibresEnRango($movs, $desde, $hasta),
            'gastos_sin_respaldo' => $this->emparejador->idsLibres($gastos),
        ];
    }

    // ── Candidatos ───────────────────────────────────────────────────

    /** Salidas del banco que todavía no se amarran a un gasto. */
    private function salidasLibres(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta)
    {
        return DB::table('banco_movimientos as bm')
            ->leftJoin('banco_movimiento_gasto as p', 'p.banco_movimiento_id', '=', 'bm.id')
            ->where('bm.banco_cuenta_id', $cuenta->id)
            ->where('bm.tipo', BancoMovimiento::TIPO_DEBITO)
            ->where('bm.estado_conciliacion', BancoMovimiento::CONCILIACION_PENDIENTE)
            ->whereNull('p.id')
            ->whereBetween('bm.fecha', [
                $desde->copy()->subDays($this->diasTolerancia)->toDateString(),
                $hasta->copy()->addDays($this->diasTolerancia)->toDateString(),
            ])
            ->orderBy('bm.fecha')
            ->orderBy('bm.id')
            ->select('bm.id', 'bm.fecha', 'bm.valor', 'bm.referencia')
            ->get();
    }

    /**
     * Gastos pagados desde esta cuenta y todavía sin respaldo.
     *
     * El filtro es `banco_origen_id`, no la forma de pago: si el gasto dice
     * que salió de esta cuenta, tiene que estar en el extracto, sin importar
     * cómo lo hayan marcado. El número de recibo hace de referencia cuando
     * existe, aunque el extracto de Bancolombia venga sin comprobantes.
     */
    private function gastosLibres(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta)
    {
        return DB::table('gastos as g')
            ->leftJoin('banco_movimiento_gasto as p', 'p.gasto_id', '=', 'g.id')
            ->where('g.aliado_id', $cuenta->aliado_id)
            ->where('g.banco_origen_id', $cuenta->id)
            ->whereNull('p.id')
            ->whereBetween('g.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('g.fecha')
            ->orderBy('g.id')
            ->select('g.id', 'g.fecha', 'g.valor', 'g.recibo_caja as referencia')
            ->get();
    }

    // ── Escritura ────────────────────────────────────────────────────

    private function escribir(BancoCuenta $cuenta, array $cruces): void
    {
        if ($cruces === []) {
            return;
        }

        $ahora = now();
        $pivote = [];
        $todosMovs = [];

        foreach ($cruces as $c) {
            foreach ($c['movimientos'] as $movId) {
                $todosMovs[] = $movId;

                foreach ($c['contrapartes'] as $gastoId) {
                    $pivote[] = [
                        'aliado_id' => $cuenta->aliado_id,
                        'banco_movimiento_id' => $movId,
                        'gasto_id' => $gastoId,
                        'valor_aplicado' => count($c['contrapartes']) > 1
                            ? (float) ($c['valores_libro'][$gastoId] ?? 0)
                            : (float) ($c['valores_mov'][$movId] ?? 0),
                        'regla' => $c['regla'],
                        'dias_diferencia' => $c['dias'],
                        'usuario_id' => null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
        }

        DB::transaction(function () use ($pivote, $todosMovs, $ahora) {
            foreach (array_chunk($pivote, 100) as $tanda) {
                DB::table('banco_movimiento_gasto')->insert($tanda);
            }

            foreach (array_chunk($todosMovs, 400) as $tanda) {
                DB::table('banco_movimientos')->whereIn('id', $tanda)->update([
                    'estado_conciliacion' => BancoMovimiento::CONCILIACION_CONCILIADO,
                    'conciliado_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        });
    }
}
