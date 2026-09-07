<?php

namespace App\Services\Banco;

use Carbon\CarbonInterface;

/**
 * El motor de cruce: empareja movimientos del banco con registros del libro.
 *
 * No sabe si lo del libro son consignaciones o gastos, y por eso sirve para
 * los dos lados del cuadre. Las reglas van de la más confiable a la más floja,
 * y cada pasada consume lo que emparejó:
 *
 *   1. `referencia`     — mismo valor y mismo comprobante.
 *   2. `fecha_valor`    — misma fecha y mismo valor exacto.
 *   3. `valor_cercano`  — mismo valor con la fecha corrida unos días.
 *   4. `partido`        — varios movimientos que suman un registro del libro.
 *   5. `agrupado`       — un movimiento que cubre varios registros.
 *
 * Donde no puede decidir, no decide: si dos combinaciones distintas cuadran
 * igual de bien, las deja libres y las reporta. Marcar mal una factura como
 * pagada es peor que dejarla pendiente.
 */
class EmparejadorMovimientos
{
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
     * Empareja las dos listas. Ambas se modifican: lo emparejado queda marcado
     * como usado, y lo que sobra es lo que hay que revisar a mano.
     *
     * @param  array  $movs  filas del banco: id, fecha, valor, referencia, usado
     * @param  array  $libro  filas de BryNex, con la misma forma
     * @return array<int, array{movimientos:int[], contrapartes:int[], valor:int, regla:string, dias:int, valores_mov:array, valores_libro:array}>
     */
    public function emparejar(array &$movs, array &$libro): array
    {
        return array_merge(
            $this->porReferencia($movs, $libro),
            $this->emparejarUnoAUno($movs, $libro, 0, 'fecha_valor'),
            $this->emparejarUnoAUno($movs, $libro, $this->diasTolerancia, 'valor_cercano'),
            $this->porPagoPartido($movs, $libro),
            $this->porPagoAgrupado($movs, $libro),
        );
    }

    /**
     * Deja la lista lista para emparejar: fecha como Carbon, valor en pesos
     * enteros y referencia comparable.
     *
     * @param  iterable  $filas  objetos con id, fecha, valor y (opcional) referencia
     */
    public function normalizar(iterable $filas): array
    {
        $salida = [];

        foreach ($filas as $f) {
            $salida[] = [
                'id' => (int) $f->id,
                'fecha' => \Carbon\Carbon::parse($f->fecha)->startOfDay(),
                'valor' => (int) round((float) $f->valor),
                'referencia' => $this->refNormalizada($f->referencia ?? null),
                'confirmado' => (bool) ($f->confirmado ?? false),
                'usado' => false,
            ];
        }

        return $salida;
    }

    /** Ids de lo que quedó sin emparejar. */
    public function idsLibres(array $lista): array
    {
        return array_values(array_column(array_filter($lista, fn ($x) => ! $x['usado']), 'id'));
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

    private function porReferencia(array &$movs, array &$libro): array
    {
        $cruces = [];

        foreach ($movs as $i => $mov) {
            if ($mov['usado'] || $mov['referencia'] === null) {
                continue;
            }

            foreach ($libro as $j => $reg) {
                if ($reg['usado'] || $reg['referencia'] === null) {
                    continue;
                }
                if ($reg['referencia'] !== $mov['referencia'] || ! $this->mismoValor($mov['valor'], $reg['valor'])) {
                    continue;
                }

                $dias = $mov['fecha']->diffInDays($reg['fecha'], false);
                if (abs($dias) > $this->diasTolerancia) {
                    continue;
                }

                $movs[$i]['usado'] = true;
                $libro[$j]['usado'] = true;
                $cruces[] = $this->cruce([$mov], [$reg], 'referencia', abs($dias));
                break;
            }
        }

        return $cruces;
    }

    private function emparejarUnoAUno(array &$movs, array &$libro, int $tolerancia, string $regla): array
    {
        $cruces = [];

        foreach ($movs as $i => $mov) {
            if ($mov['usado']) {
                continue;
            }

            $mejor = null;
            $mejorDias = PHP_INT_MAX;

            foreach ($libro as $j => $reg) {
                if ($reg['usado'] || ! $this->mismoValor($mov['valor'], $reg['valor'])) {
                    continue;
                }

                $dias = abs($mov['fecha']->diffInDays($reg['fecha'], false));
                if ($dias > $tolerancia) {
                    continue;
                }

                // El más cercano en fecha; a igual distancia, el más antiguo,
                // que es el orden en que el banco los aplica.
                if ($dias < $mejorDias) {
                    $mejor = $j;
                    $mejorDias = $dias;
                }
            }

            if ($mejor !== null) {
                $movs[$i]['usado'] = true;
                $libro[$mejor]['usado'] = true;
                $cruces[] = $this->cruce([$mov], [$libro[$mejor]], $regla, $mejorDias);
            }
        }

        return $cruces;
    }

    /** Varios movimientos que suman un registro del libro. */
    private function porPagoPartido(array &$movs, array &$libro): array
    {
        $cruces = [];

        foreach ($libro as $j => $reg) {
            if ($reg['usado']) {
                continue;
            }

            $candidatos = $this->cercanos($movs, $reg['fecha']);
            if ($candidatos === [] || count($candidatos) > self::MAX_COMBINATORIA) {
                continue;
            }

            $combo = $this->buscarSuma($movs, $candidatos, $reg['valor']);
            if ($combo === null) {
                continue;
            }

            $piezas = [];
            $dias = 0;
            foreach ($combo as $i) {
                $movs[$i]['usado'] = true;
                $piezas[] = $movs[$i];
                $dias = max($dias, abs($movs[$i]['fecha']->diffInDays($reg['fecha'], false)));
            }

            $libro[$j]['usado'] = true;
            $cruces[] = $this->cruce($piezas, [$reg], 'partido', $dias);
        }

        return $cruces;
    }

    /** Un movimiento que cubre varios registros del libro. */
    private function porPagoAgrupado(array &$movs, array &$libro): array
    {
        $cruces = [];

        foreach ($movs as $i => $mov) {
            if ($mov['usado']) {
                continue;
            }

            $candidatos = $this->cercanos($libro, $mov['fecha']);
            if ($candidatos === [] || count($candidatos) > self::MAX_COMBINATORIA) {
                continue;
            }

            $combo = $this->buscarSuma($libro, $candidatos, $mov['valor']);
            if ($combo === null) {
                continue;
            }

            $piezas = [];
            $dias = 0;
            foreach ($combo as $j) {
                $libro[$j]['usado'] = true;
                $piezas[] = $libro[$j];
                $dias = max($dias, abs($libro[$j]['fecha']->diffInDays($mov['fecha'], false)));
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
     * Busca 2 o 3 elementos que sumen $objetivo, por índice de valores: para
     * cada elemento pregunta si existe el complemento que falta.
     *
     * Devuelve null si hay más de una combinación posible. La tolerancia de
     * valor no aplica aquí: sumar con holgura multiplica las coincidencias por
     * azar, que es justo lo que no se quiere en las sumas.
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
            $encontradas[implode('-', $posiciones)] = array_map(fn ($p) => $indices[$p], $posiciones);
        };

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

    private function cruce(array $movs, array $registros, string $regla, int $dias): array
    {
        return [
            'movimientos' => array_column($movs, 'id'),
            'contrapartes' => array_column($registros, 'id'),
            'valor' => array_sum(array_column($movs, 'valor')),
            'regla' => $regla,
            'dias' => $dias,
            'ya_confirmadas' => count(array_filter($registros, fn ($c) => $c['confirmado'])),
            // Los valores viajan con el cruce para que la escritura no tenga
            // que volver a la base por cada pieza: con ~250 ms por consulta,
            // un mes de cruces se iría en round-trips.
            'valores_mov' => array_column($movs, 'valor', 'id'),
            'valores_libro' => array_column($registros, 'valor', 'id'),
        ];
    }
}
