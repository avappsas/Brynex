<?php

namespace App\Services\Banco;

use App\Models\BancoCuenta;
use Carbon\Carbon;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee el extracto que Bancolombia entrega en Excel desde la Sucursal Virtual.
 *
 * Es la vía que quedó después de que el banco respondiera que sus APIs no
 * sirven para consultar movimientos (ver docs/plan-api-bancolombia.md). El
 * archivo sale igual que un extracto real, así que todo lo que sigue —la
 * deduplicación, el cruce, la bandeja— funciona sin cambios.
 *
 * Cómo viene el archivo:
 *
 *   fila  7-8   DESDE | HASTA | TIPO CUENTA | NRO CUENTA | SUCURSAL
 *   fila 11-12  SALDO ANTERIOR | TOTAL ABONOS | TOTAL CARGOS | SALDO ACTUAL
 *   fila 15+    FECHA | DESCRIPCIÓN | SUCURSAL | DCTO. | VALOR | SALDO
 *
 * Tres cosas que el formato obliga a manejar:
 *
 *   1. **La fecha no trae año** — viene como «1/07». El año sale del rango del
 *      encabezado, mirando a cuál de los dos meses pertenece; así un extracto
 *      que cruza diciembre y enero no manda todo al año equivocado.
 *   2. **Los encabezados se repiten** cada tantas filas, porque el archivo está
 *      paginado. Se descartan por no traer un número en la columna del valor.
 *   3. **La columna DCTO. viene vacía.** En el extracto de julio de Brygar no
 *      hay un solo comprobante en 359 movimientos, así que el cruce no puede
 *      apoyarse en la referencia: queda en fecha y valor.
 */
class LectorExtractoBancolombia
{
    /** Fila donde arranca la tabla de movimientos. */
    private const FILA_MOVIMIENTOS = 15;

    /**
     * @return array{
     *   movimientos: MovimientoBanco[],
     *   cuenta: string, desde: ?string, hasta: ?string,
     *   total_abonos: float, total_cargos: float,
     *   saldo_anterior: float, saldo_actual: float,
     *   descuadre: ?float
     * }
     */
    public function leer(string $rutaArchivo): array
    {
        $hoja = IOFactory::load($rutaArchivo)->getSheet(0);
        $filas = $hoja->toArray(null, true, false, false);

        $encabezado = $this->encabezado($filas);
        $movimientos = $this->movimientos($filas, $encabezado);

        $abonos = 0.0;
        $cargos = 0.0;
        foreach ($movimientos as $m) {
            $m->esCredito() ? $abonos += $m->valor : $cargos += $m->valor;
        }

        // El resumen del propio archivo dice cuánto debería sumar. Si no
        // cuadra, el Excel llegó recortado (pasa al copiar y pegar rangos) y
        // conviene avisar antes de guardar medio mes.
        $descuadre = null;
        if ($encabezado['total_abonos'] > 0) {
            $dif = round($abonos - $encabezado['total_abonos'], 2);
            $descuadre = abs($dif) > 1 ? $dif : null;
        }

        return array_merge($encabezado, [
            'movimientos' => $movimientos,
            'descuadre' => $descuadre,
        ]);
    }

    /** Valida que el archivo sea de la cuenta que se está cargando. */
    public function verificarCuenta(array $extracto, BancoCuenta $cuenta): void
    {
        $delArchivo = preg_replace('/\D/', '', (string) $extracto['cuenta']);
        $deBryNex = preg_replace('/\D/', '', (string) $cuenta->numero_cuenta);

        if ($delArchivo === '' || $deBryNex === '') {
            return;   // sin número de un lado no hay nada que comparar
        }

        if ($delArchivo !== $deBryNex) {
            throw new InvalidArgumentException(
                "El extracto es de la cuenta {$extracto['cuenta']} y usted eligió la {$cuenta->numero_cuenta}."
            );
        }
    }

    // ── Encabezado ───────────────────────────────────────────────────

    private function encabezado(array $filas): array
    {
        $datos = [
            'cuenta' => '',
            'desde' => null,
            'hasta' => null,
            'saldo_anterior' => 0.0,
            'total_abonos' => 0.0,
            'total_cargos' => 0.0,
            'saldo_actual' => 0.0,
        ];

        foreach ($filas as $i => $fila) {
            $primera = trim((string) ($fila[0] ?? ''));

            if ($primera === 'DESDE' && isset($filas[$i + 1])) {
                $v = $filas[$i + 1];
                $datos['desde'] = $this->fecha((string) ($v[0] ?? ''));
                $datos['hasta'] = $this->fecha((string) ($v[1] ?? ''));
                $datos['cuenta'] = trim((string) ($v[3] ?? ''));
            }

            if ($primera === 'SALDO ANTERIOR' && isset($filas[$i + 1])) {
                $v = $filas[$i + 1];
                $datos['saldo_anterior'] = $this->numero($v[0] ?? null);
                $datos['total_abonos'] = $this->numero($v[1] ?? null);
                $datos['total_cargos'] = $this->numero($v[2] ?? null);
                $datos['saldo_actual'] = $this->numero($v[3] ?? null);
            }
        }

        return $datos;
    }

    private function fecha(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', $texto))->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Movimientos ──────────────────────────────────────────────────

    /** @return MovimientoBanco[] */
    private function movimientos(array $filas, array $encabezado): array
    {
        $secuenciaPorDia = [];
        $salida = [];

        foreach (array_slice($filas, self::FILA_MOVIMIENTOS) as $fila) {
            $fechaCruda = trim((string) ($fila[0] ?? ''));
            $descripcion = trim((string) ($fila[1] ?? ''));
            $sucursal = trim((string) ($fila[2] ?? ''));
            $documento = trim((string) ($fila[3] ?? ''));
            $valorCrudo = $fila[4] ?? null;

            if ($fechaCruda === '' || $valorCrudo === null || $valorCrudo === '') {
                continue;
            }
            if (! $this->esNumero($valorCrudo)) {
                continue;   // encabezado repetido por paginación
            }

            $fecha = $this->fechaMovimiento($fechaCruda, $encabezado);
            if (! $fecha) {
                continue;
            }

            $valor = $this->numero($valorCrudo);
            if ($valor === 0.0) {
                continue;
            }

            $dia = $fecha->toDateString();
            $secuenciaPorDia[$dia] = ($secuenciaPorDia[$dia] ?? 0) + 1;

            $salida[] = new MovimientoBanco(
                fecha: $fecha,
                tipo: $valor > 0 ? 'credito' : 'debito',
                valor: abs($valor),
                descripcion: $descripcion ?: null,
                // El extracto no trae comprobante: la columna DCTO. viene
                // vacía. Se lee igual por si algún día la llenan.
                referencia: $documento ?: null,
                idExterno: null,
                fechaHora: null,
                saldoDespues: $this->numero($fila[5] ?? null) ?: null,
                canal: $sucursal ?: null,
                contraparteNombre: $this->pagador($descripcion),
                contraparteDocumento: null,
                secuencia: $secuenciaPorDia[$dia],
                payload: ['origen' => 'extracto_xlsx', 'fila' => $fechaCruda.' '.$descripcion],
            );
        }

        return $salida;
    }

    /**
     * El día viene como «1/07», sin año. Se decide con el rango del extracto:
     * si el mes coincide con el de la fecha final, es su año; si coincide con
     * el de la inicial, el de esa. Sin eso, un extracto de fin de año mandaría
     * los movimientos de enero doce meses atrás.
     */
    private function fechaMovimiento(string $texto, array $encabezado): ?Carbon
    {
        if (! preg_match('#^(\d{1,2})/(\d{1,2})$#', trim($texto), $m)) {
            return null;
        }

        [$dia, $mes] = [(int) $m[1], (int) $m[2]];

        foreach (['hasta', 'desde'] as $extremo) {
            if (! $encabezado[$extremo]) {
                continue;
            }
            $ref = Carbon::parse($encabezado[$extremo]);
            if ((int) $ref->month === $mes) {
                return Carbon::create($ref->year, $mes, $dia)->startOfDay();
            }
        }

        $anio = $encabezado['hasta'] ? Carbon::parse($encabezado['hasta'])->year : now()->year;

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }

    /**
     * Los pagos por llave Bre-B traen el nombre de quien pagó, recortado:
     * «PAGO LLAVE JUAN CARLO». Sirve para identificar al cliente cuando la
     * entrada no cruza sola.
     */
    private function pagador(string $descripcion): ?string
    {
        if (preg_match('/^PAGO LLAVE\s+(.+)$/i', trim($descripcion), $m)) {
            return trim($m[1]) ?: null;
        }

        return null;
    }

    // ── Números ──────────────────────────────────────────────────────

    private function esNumero($valor): bool
    {
        if (is_numeric($valor)) {
            return true;
        }

        return (bool) preg_match('/^-?[\d,]+\.?\d*$/', trim((string) $valor));
    }

    private function numero($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) str_replace([',', ' '], '', trim((string) $valor));
    }
}
