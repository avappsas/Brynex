<?php

namespace App\Services\Banco;

use App\Models\BancoCuenta;
use App\Models\BancoMovimiento;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cruza el extracto del banco contra las consignaciones registradas en BryNex.
 *
 * Reemplaza el trabajo que hoy se hace a mano en el informe de validación:
 * abrir el extracto, buscar cada consignación y marcarla verificada o
 * `no_aparece`. Lo que cruza se confirma solo; lo que no, queda listado para
 * que alguien lo mire — que es justo lo que hoy se pierde entre 200 filas.
 *
 * El cruce va en pasadas, de la regla más confiable a la más floja, y cada
 * pasada consume lo que emparejó:
 *
 *   1. `referencia`     — mismo valor y mismo número de comprobante.
 *   2. `fecha_valor`    — misma fecha y mismo valor exacto.
 *   3. `valor_cercano`  — mismo valor, con la fecha corrida unos días (el banco
 *                         aplica al día siguiente más seguido de lo que uno cree).
 *   4. `partido`        — varias transferencias que suman una consignación.
 *                         Bre-B tope por transacción: los pagos grandes llegan
 *                         partidos en dos o tres.
 *   5. `agrupado`       — una transferencia que cubre varias consignaciones,
 *                         el cliente que paga tres facturas de un solo golpe.
 *
 * Nada de esto adivina: si una pasada deja dos candidatos igual de válidos y no
 * puede decidir, los deja libres para la siguiente y termina reportándolos.
 * Marcar mal una consignación como pagada es peor que no marcarla.
 *
 * Lo que NO hace, a propósito: marcar `no_aparece`. Que una consignación no
 * esté en el extracto puede significar que no entró la plata, o que el rango
 * consultado no la alcanza. Eso lo decide una persona.
 */
class ConciliadorConsignacionesService
{
    /** Firma con el formato que ya entiende el informe de validación. */
    private const FIRMA = '[Soporte - Validado por: Conciliación con el extracto]';

    /**
     * Tope de candidatos por ventana para intentar sumas.
     *
     * La búsqueda es por índice de valores, no por fuerza bruta, así que 120
     * candidatos son ~7.000 lookups: nada. El tope existe solo para que una
     * cuenta con miles de movimientos en tres días no se lleve la corrida.
     */
    private const MAX_COMBINATORIA = 120;

    public function __construct(
        private int $diasTolerancia = 2,
        private int $toleranciaValor = 0,
    ) {}

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

        $movs = $this->movimientosLibres($cuenta, $desde, $hasta);
        $cons = $this->consignacionesLibres($cuenta, $desde, $hasta);

        $cruces = array_merge(
            $this->porReferencia($movs, $cons),
            $this->porFechaValor($movs, $cons),
            $this->porValorCercano($movs, $cons),
            $this->porPagoPartido($movs, $cons),
            $this->porPagoAgrupado($movs, $cons),
        );

        $ignorados = $this->costosBancarios($cuenta, $desde, $hasta);

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
            'movimientos_sin_identificar' => $this->idsLibres($movs),
            'consignaciones_sin_respaldo' => $this->idsLibres($cons),
        ];
    }

    // ── Candidatos ───────────────────────────────────────────────────

    /**
     * Entradas del banco todavía sin cruzar.
     *
     * Solo créditos: los débitos son gastos y salen por otro lado. El rango se
     * abre por ambos lados según la tolerancia, porque una consignación del día
     * 1 puede aparecer en el banco el 2.
     */
    private function movimientosLibres(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $filas = DB::table('banco_movimientos as bm')
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

        return $this->normalizar($filas);
    }

    /** Consignaciones del libro que todavía no tienen respaldo en el banco. */
    private function consignacionesLibres(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $filas = DB::table('consignaciones as cs')
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

        return $this->normalizar($filas);
    }

    private function normalizar($filas): array
    {
        $salida = [];

        foreach ($filas as $f) {
            $salida[] = [
                'id' => (int) $f->id,
                'fecha' => Carbon::parse($f->fecha)->startOfDay(),
                'valor' => (int) round((float) $f->valor),
                'referencia' => $this->refNormalizada($f->referencia ?? null),
                'confirmado' => (bool) ($f->confirmado ?? false),
                'usado' => false,
            ];
        }

        return $salida;
    }

    /**
     * Deja la referencia comparable: sin espacios ni signos, sin ceros a la
     * izquierda. Las de menos de 4 caracteres se descartan — un "12" coincide
     * con cualquier cosa y ahí es donde nacen los cruces falsos.
     */
    private function refNormalizada(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }

        $limpia = ltrim(preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($ref)), '0');

        return mb_strlen($limpia) >= 4 ? $limpia : null;
    }

    // ── Pasadas ──────────────────────────────────────────────────────

    /** 1. Mismo comprobante y mismo valor. La más confiable. */
    private function porReferencia(array &$movs, array &$cons): array
    {
        $cruces = [];

        foreach ($movs as $i => $mov) {
            if ($mov['usado'] || $mov['referencia'] === null) {
                continue;
            }

            foreach ($cons as $j => $con) {
                if ($con['usado'] || $con['referencia'] === null) {
                    continue;
                }
                if ($con['referencia'] !== $mov['referencia']) {
                    continue;
                }
                if (! $this->mismoValor($mov['valor'], $con['valor'])) {
                    continue;
                }

                $dias = $mov['fecha']->diffInDays($con['fecha'], false);
                if (abs($dias) > $this->diasTolerancia) {
                    continue;
                }

                $movs[$i]['usado'] = true;
                $cons[$j]['usado'] = true;
                $cruces[] = $this->cruce([$mov], [$con], 'referencia', abs($dias));
                break;
            }
        }

        return $cruces;
    }

    /** 2. Misma fecha y mismo valor. Con varios iguales, se emparejan en orden. */
    private function porFechaValor(array &$movs, array &$cons): array
    {
        return $this->emparejarUnoAUno($movs, $cons, 0, 'fecha_valor');
    }

    /** 3. Mismo valor con la fecha corrida dentro de la tolerancia. */
    private function porValorCercano(array &$movs, array &$cons): array
    {
        return $this->emparejarUnoAUno($movs, $cons, $this->diasTolerancia, 'valor_cercano');
    }

    private function emparejarUnoAUno(array &$movs, array &$cons, int $tolerancia, string $regla): array
    {
        $cruces = [];

        foreach ($movs as $i => $mov) {
            if ($mov['usado']) {
                continue;
            }

            $mejor = null;
            $mejorDias = PHP_INT_MAX;

            foreach ($cons as $j => $con) {
                if ($con['usado'] || ! $this->mismoValor($mov['valor'], $con['valor'])) {
                    continue;
                }

                $dias = abs($mov['fecha']->diffInDays($con['fecha'], false));
                if ($dias > $tolerancia) {
                    continue;
                }

                // La más cercana en fecha; a igual distancia, la más antigua,
                // que es el orden en que el banco las aplica.
                if ($dias < $mejorDias) {
                    $mejor = $j;
                    $mejorDias = $dias;
                }
            }

            if ($mejor !== null) {
                $movs[$i]['usado'] = true;
                $cons[$mejor]['usado'] = true;
                $cruces[] = $this->cruce([$mov], [$cons[$mejor]], $regla, $mejorDias);
            }
        }

        return $cruces;
    }

    /**
     * 4. Varias transferencias que suman una consignación.
     *
     * Es el caso que trae Bre-B: el tope por transacción obliga a partir los
     * pagos grandes. Se prueban combinaciones de hasta tres movimientos.
     */
    private function porPagoPartido(array &$movs, array &$cons): array
    {
        $cruces = [];

        foreach ($cons as $j => $con) {
            if ($con['usado']) {
                continue;
            }

            $candidatos = $this->cercanos($movs, $con['fecha']);
            if ($candidatos === [] || count($candidatos) > self::MAX_COMBINATORIA) {
                continue;
            }

            $combo = $this->buscarSuma($movs, $candidatos, $con['valor']);
            if ($combo === null) {
                continue;
            }

            $piezas = [];
            $dias = 0;
            foreach ($combo as $i) {
                $movs[$i]['usado'] = true;
                $piezas[] = $movs[$i];
                $dias = max($dias, abs($movs[$i]['fecha']->diffInDays($con['fecha'], false)));
            }

            $cons[$j]['usado'] = true;
            $cruces[] = $this->cruce($piezas, [$con], 'partido', $dias);
        }

        return $cruces;
    }

    /** 5. Una transferencia que cubre varias consignaciones del mismo cliente. */
    private function porPagoAgrupado(array &$movs, array &$cons): array
    {
        $cruces = [];

        foreach ($movs as $i => $mov) {
            if ($mov['usado']) {
                continue;
            }

            $candidatos = $this->cercanos($cons, $mov['fecha']);
            if ($candidatos === [] || count($candidatos) > self::MAX_COMBINATORIA) {
                continue;
            }

            $combo = $this->buscarSuma($cons, $candidatos, $mov['valor']);
            if ($combo === null) {
                continue;
            }

            $piezas = [];
            $dias = 0;
            foreach ($combo as $j) {
                $cons[$j]['usado'] = true;
                $piezas[] = $cons[$j];
                $dias = max($dias, abs($cons[$j]['fecha']->diffInDays($mov['fecha'], false)));
            }

            $movs[$i]['usado'] = true;
            $cruces[] = $this->cruce([$mov], $piezas, 'agrupado', $dias);
        }

        return $cruces;
    }

    /** Índices libres de $lista cuya fecha cae dentro de la tolerancia. */
    private function cercanos(array $lista, CarbonInterface $fecha): array
    {
        $indices = [];

        foreach ($lista as $k => $item) {
            if ($item['usado']) {
                continue;
            }
            if (abs($item['fecha']->diffInDays($fecha, false)) <= $this->diasTolerancia) {
                $indices[] = $k;
            }
        }

        return $indices;
    }

    /**
     * Busca 2 o 3 elementos que sumen $objetivo.
     *
     * Va por índice de valores en vez de probar todas las combinaciones: para
     * cada elemento pregunta si existe el complemento que falta. La primera
     * versión recorría los candidatos de a tres y por eso tenía que limitarse
     * a 25 — con 200 consignaciones al mes, la regla no llegaba a correr nunca.
     *
     * Devuelve null si hay más de una combinación posible: cuando el sistema no
     * puede distinguir cuál es la buena, no elige — el cruce equivocado deja la
     * factura de otro cliente marcada como pagada.
     *
     * La tolerancia de valor no aplica aquí: sumar con holgura multiplica las
     * coincidencias por azar, que es justo lo que no se quiere en las sumas.
     */
    private function buscarSuma(array $lista, array $indices, int $objetivo): ?array
    {
        $n = count($indices);
        if ($n < 2) {
            return null;
        }

        $valor = [];
        $porValor = [];
        foreach ($indices as $pos => $idx) {
            $v = $lista[$idx]['valor'];
            $valor[$pos] = $v;
            $porValor[$v][] = $pos;
        }

        $encontradas = [];

        $anotar = function (array $posiciones) use (&$encontradas, $indices) {
            sort($posiciones);
            if (count(array_unique($posiciones)) !== count($posiciones)) {
                return;
            }
            $encontradas[implode('-', $posiciones)] = array_map(
                fn ($p) => $indices[$p],
                $posiciones
            );
        };

        // Pares: para cada elemento, ¿existe lo que falta?
        for ($a = 0; $a < $n; $a++) {
            $falta = $objetivo - $valor[$a];
            foreach ($porValor[$falta] ?? [] as $b) {
                if ($b > $a) {
                    $anotar([$a, $b]);
                    if (count($encontradas) > 1) {
                        return null;
                    }
                }
            }
        }

        // Tercias: mismo truco sobre cada par.
        for ($a = 0; $a < $n; $a++) {
            for ($b = $a + 1; $b < $n; $b++) {
                $falta = $objetivo - $valor[$a] - $valor[$b];
                if ($falta <= 0) {
                    continue;
                }
                foreach ($porValor[$falta] ?? [] as $c) {
                    if ($c > $b) {
                        $anotar([$a, $b, $c]);
                        if (count($encontradas) > 1) {
                            return null;
                        }
                    }
                }
            }
        }

        return $encontradas === [] ? null : array_values($encontradas)[0];
    }

    private function mismoValor(int $a, int $b): bool
    {
        return abs($a - $b) <= $this->toleranciaValor;
    }

    private function cruce(array $movs, array $cons, string $regla, int $dias): array
    {
        return [
            'movimientos' => array_column($movs, 'id'),
            'consignaciones' => array_column($cons, 'id'),
            'valor' => array_sum(array_column($movs, 'valor')),
            'regla' => $regla,
            'dias' => $dias,
            'ya_confirmadas' => count(array_filter($cons, fn ($c) => $c['confirmado'])),
            // Los valores viajan con el cruce para que la escritura no tenga
            // que volver a la base por cada pieza: con ~250 ms por consulta,
            // un mes de cruces se iría en round-trips.
            'valores_mov' => array_column($movs, 'valor', 'id'),
            'valores_con' => array_column($cons, 'valor', 'id'),
        ];
    }

    private function idsLibres(array $lista): array
    {
        return array_values(array_column(array_filter($lista, fn ($x) => ! $x['usado']), 'id'));
    }

    // ── Costos del banco ─────────────────────────────────────────────

    /**
     * Débitos que el libro nunca va a tener porque BryNex no los registra:
     * 4x1000, cuota de manejo y demás cobros del banco. Se marcan `ignorado`
     * para que no queden inflando la lista de diferencias.
     */
    private function costosBancarios(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $patrones = (array) config('banco.costos_bancarios', []);
        if ($patrones === []) {
            return [];
        }

        $query = DB::table('banco_movimientos')
            ->where('banco_cuenta_id', $cuenta->id)
            ->where('tipo', BancoMovimiento::TIPO_DEBITO)
            ->where('estado_conciliacion', BancoMovimiento::CONCILIACION_PENDIENTE)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($q) use ($patrones) {
                foreach ($patrones as $p) {
                    $q->orWhere('descripcion', 'like', '%'.$p.'%');
                }
            });

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ── Escritura ────────────────────────────────────────────────────

    /** @return int cuántas consignaciones quedaron confirmadas */
    private function escribir(BancoCuenta $cuenta, array $cruces, array $ignorados): int
    {
        $ahora = now();
        $pivote = [];
        $porConfirmar = [];
        $movConSuConsignacion = [];   // movimiento → consignación, solo en cruces 1:1

        foreach ($cruces as $c) {
            $unico = count($c['movimientos']) === 1 && count($c['consignaciones']) === 1;

            foreach ($c['movimientos'] as $movId) {
                foreach ($c['consignaciones'] as $consId) {
                    $pivote[] = [
                        'aliado_id' => $cuenta->aliado_id,
                        'banco_movimiento_id' => $movId,
                        'consignacion_id' => $consId,
                        // En 1:1 y en agrupado el movimiento aporta su valor
                        // completo a esa consignación; en partido, cada pieza
                        // aporta lo suyo. En ambos casos es el valor del cruce
                        // repartido, no el total.
                        'valor_aplicado' => $this->valorAplicado($c, $movId, $consId),
                        'regla' => $c['regla'],
                        'dias_diferencia' => $c['dias'],
                        'usuario_id' => null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if ($unico) {
                    $movConSuConsignacion[$movId] = $c['consignaciones'][0];
                }
            }

            foreach ($c['consignaciones'] as $consId) {
                $porConfirmar[] = $consId;
            }
        }

        DB::transaction(function () use ($pivote, $movConSuConsignacion, $cruces, $ignorados, $ahora) {
            foreach (array_chunk($pivote, 100) as $tanda) {
                DB::table('banco_movimiento_consignacion')->insert($tanda);
            }

            // Movimientos conciliados. Los de cruce 1:1 además guardan el
            // atajo `consignacion_id`; los repartidos lo dejan nulo porque no
            // tienen una sola consignación que apuntar.
            $todosMovs = [];
            foreach ($cruces as $c) {
                foreach ($c['movimientos'] as $m) {
                    $todosMovs[] = $m;
                }
            }

            foreach (array_chunk($todosMovs, 400) as $tanda) {
                DB::table('banco_movimientos')->whereIn('id', $tanda)->update([
                    'estado_conciliacion' => BancoMovimiento::CONCILIACION_CONCILIADO,
                    'conciliado_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }

            foreach ($movConSuConsignacion as $movId => $consId) {
                DB::table('banco_movimientos')->where('id', $movId)
                    ->update(['consignacion_id' => $consId]);
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

    private function valorAplicado(array $cruce, int $movId, int $consId): float
    {
        // Un movimiento repartido entre varias consignaciones: cada una se
        // lleva lo suyo. En el resto de los casos, lo que aporta la pieza es su
        // propio valor.
        if (count($cruce['consignaciones']) > 1) {
            return (float) ($cruce['valores_con'][$consId] ?? 0);
        }

        return (float) ($cruce['valores_mov'][$movId] ?? 0);
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
