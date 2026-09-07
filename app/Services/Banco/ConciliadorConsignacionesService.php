<?php

namespace App\Services\Banco;

use App\Models\BancoCuenta;
use App\Models\BancoMovimiento;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cruza las entradas del extracto contra las consignaciones registradas.
 *
 * Reemplaza el trabajo que se hacía a mano en el informe de validación: abrir
 * el extracto, buscar cada consignación y marcarla verificada. Lo que cruza se
 * confirma solo; lo que no, queda listado para que alguien lo mire — que es
 * justo lo que hoy se pierde entre 200 filas.
 *
 * Las reglas y el criterio de «ante la duda, no elijo» viven en
 * EmparejadorMovimientos, que es el mismo motor que usa el cruce de gastos.
 *
 * Lo que NO hace, a propósito: marcar `no_aparece`. Que una consignación no
 * esté en el extracto puede significar que no entró la plata, o que el rango
 * consultado no la alcanza. Eso lo decide una persona.
 */
class ConciliadorConsignacionesService
{
    /** Firma con el formato que ya entiende el informe de validación. */
    private const FIRMA = '[Soporte - Validado por: Conciliación con el extracto]';

    private EmparejadorMovimientos $emparejador;

    public function __construct(
        private int $diasTolerancia = 2,
        private int $toleranciaValor = 0,
    ) {
        $this->emparejador = new EmparejadorMovimientos($diasTolerancia, $toleranciaValor);
    }

    /**
     * @return array{cuenta_id:int, desde:string, hasta:string, movimientos:int,
     *               consignaciones:int, cruces:array, por_regla:array, ignorados:int,
     *               confirmadas:int, movimientos_sin_identificar:array,
     *               consignaciones_sin_respaldo:array}
     */
    public function conciliar(
        BancoCuenta $cuenta,
        CarbonInterface $desde,
        CarbonInterface $hasta,
        bool $ejecutar = false,
    ): array {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        $movs = $this->emparejador->normalizar($this->movimientosLibres($cuenta, $desde, $hasta));
        $cons = $this->emparejador->normalizar($this->consignacionesLibres($cuenta, $desde, $hasta));

        $cruces = $this->emparejador->emparejar($movs, $cons);
        $ignorados = $this->movimientosDelBanco($cuenta, $desde, $hasta);

        $confirmadas = 0;
        if ($ejecutar) {
            $confirmadas = $this->escribir($cuenta, $cruces, $ignorados);
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
            'consignaciones' => count($cons),
            'cruces' => $cruces,
            'por_regla' => $porRegla,
            'ignorados' => count($ignorados),
            'confirmadas' => $confirmadas,
            // Los que el banco puso (intereses, 4x1000) ya quedaron marcados
            // `ignorado`: contarlos aquí infla la lista de diferencias con
            // plata que no es de ningún cliente.
            'movimientos_sin_identificar' => array_values(
                array_diff($this->emparejador->idsLibres($movs), $ignorados)
            ),
            'consignaciones_sin_respaldo' => $this->emparejador->idsLibres($cons),
        ];
    }

    // ── Candidatos ───────────────────────────────────────────────────

    /**
     * Entradas del banco todavía sin cruzar.
     *
     * El rango se abre por ambos lados según la tolerancia, porque una
     * consignación del día 1 puede aparecer en el banco el 2.
     */
    private function movimientosLibres(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta)
    {
        return DB::table('banco_movimientos as bm')
            ->leftJoin('banco_movimiento_consignacion as p', 'p.banco_movimiento_id', '=', 'bm.id')
            ->where('bm.banco_cuenta_id', $cuenta->id)
            ->where('bm.tipo', BancoMovimiento::TIPO_CREDITO)
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

    /** Consignaciones del libro que todavía no tienen respaldo en el banco. */
    private function consignacionesLibres(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta)
    {
        return DB::table('consignaciones as cs')
            ->leftJoin('banco_movimiento_consignacion as p', 'p.consignacion_id', '=', 'cs.id')
            ->where('cs.aliado_id', $cuenta->aliado_id)
            ->where('cs.banco_cuenta_id', $cuenta->id)
            ->whereNull('cs.deleted_at')
            ->whereNull('p.id')
            ->whereBetween('cs.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('cs.fecha')
            ->orderBy('cs.id')
            ->select('cs.id', 'cs.fecha', 'cs.valor', 'cs.referencia', 'cs.confirmado')
            ->get();
    }

    // ── Movimientos que son del banco, no del libro ──────────────────

    /**
     * Débitos y créditos que BryNex nunca va a registrar: 4x1000, cuota de
     * manejo, intereses de la cuenta. Se marcan `ignorado` para que no queden
     * inflando la lista de diferencias.
     */
    private function movimientosDelBanco(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $debitos = (array) config('banco.costos_bancarios', []);
        $creditos = (array) config('banco.creditos_ignorados', []);

        if ($debitos === [] && $creditos === []) {
            return [];
        }

        return DB::table('banco_movimientos')
            ->where('banco_cuenta_id', $cuenta->id)
            ->where('estado_conciliacion', BancoMovimiento::CONCILIACION_PENDIENTE)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($q) use ($debitos, $creditos) {
                $q->where(fn ($qq) => $this->porPatrones($qq, BancoMovimiento::TIPO_DEBITO, $debitos))
                    ->orWhere(fn ($qq) => $this->porPatrones($qq, BancoMovimiento::TIPO_CREDITO, $creditos));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function porPatrones($query, string $tipo, array $patrones)
    {
        if ($patrones === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tipo', $tipo)->where(function ($q) use ($patrones) {
            foreach ($patrones as $p) {
                $q->orWhere('descripcion', 'like', '%'.$p.'%');
            }
        });
    }

    // ── Escritura ────────────────────────────────────────────────────

    /** @return int cuántas consignaciones quedaron confirmadas */
    private function escribir(BancoCuenta $cuenta, array $cruces, array $ignorados): int
    {
        $ahora = now();
        $pivote = [];
        $porConfirmar = [];
        $movConSuConsignacion = [];   // movimiento → consignación, solo en cruces 1:1
        $todosMovs = [];

        foreach ($cruces as $c) {
            $unico = count($c['movimientos']) === 1 && count($c['contrapartes']) === 1;

            foreach ($c['movimientos'] as $movId) {
                $todosMovs[] = $movId;

                foreach ($c['contrapartes'] as $consId) {
                    $pivote[] = [
                        'aliado_id' => $cuenta->aliado_id,
                        'banco_movimiento_id' => $movId,
                        'consignacion_id' => $consId,
                        // Un movimiento repartido entre varias consignaciones:
                        // cada una se lleva lo suyo. En el resto de los casos,
                        // la pieza aporta su propio valor.
                        'valor_aplicado' => count($c['contrapartes']) > 1
                            ? (float) ($c['valores_libro'][$consId] ?? 0)
                            : (float) ($c['valores_mov'][$movId] ?? 0),
                        'regla' => $c['regla'],
                        'dias_diferencia' => $c['dias'],
                        'usuario_id' => null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if ($unico) {
                    $movConSuConsignacion[$movId] = $c['contrapartes'][0];
                }
            }

            foreach ($c['contrapartes'] as $consId) {
                $porConfirmar[] = $consId;
            }
        }

        DB::transaction(function () use ($pivote, $movConSuConsignacion, $todosMovs, $ignorados, $ahora) {
            foreach (array_chunk($pivote, 100) as $tanda) {
                DB::table('banco_movimiento_consignacion')->insert($tanda);
            }

            foreach (array_chunk($todosMovs, 400) as $tanda) {
                DB::table('banco_movimientos')->whereIn('id', $tanda)->update([
                    'estado_conciliacion' => BancoMovimiento::CONCILIACION_CONCILIADO,
                    'conciliado_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }

            // El atajo `consignacion_id` solo aplica cuando hay una sola
            // consignación detrás; los repartidos lo dejan nulo.
            foreach ($movConSuConsignacion as $movId => $consId) {
                DB::table('banco_movimientos')->where('id', $movId)->update(['consignacion_id' => $consId]);
            }

            foreach (array_chunk($ignorados, 400) as $tanda) {
                DB::table('banco_movimientos')->whereIn('id', $tanda)->update([
                    'estado_conciliacion' => BancoMovimiento::CONCILIACION_IGNORADO,
                    'conciliado_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        });

        return $this->confirmarConsignaciones($porConfirmar, $ahora);
    }

    /**
     * Marca verificadas las consignaciones que el banco respalda.
     *
     * Se estampa la misma firma que usa el informe de validación a mano, para
     * que ese flujo la reconozca y la limpie si alguien devuelve la fila a
     * pendiente. `usuario_validador_id` queda nulo: no fue una persona.
     */
    private function confirmarConsignaciones(array $ids, CarbonInterface $ahora): int
    {
        if ($ids === []) {
            return 0;
        }

        $total = 0;

        foreach (array_chunk(array_unique($ids), 400) as $tanda) {
            $total += DB::table('consignaciones')
                ->whereIn('id', $tanda)
                ->where('confirmado', 0)
                ->update([
                    'confirmado' => 1,
                    'no_aparece' => 0,
                    'fecha_validacion' => $ahora,
                    'observacion' => DB::raw("LTRIM(ISNULL(observacion, '') + ' ".self::FIRMA."')"),
                    'updated_at' => $ahora,
                ]);
        }

        return $total;
    }
}
