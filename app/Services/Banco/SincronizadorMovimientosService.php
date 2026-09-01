<?php

namespace App\Services\Banco;

use App\Models\BancoCuenta;
use App\Models\BancoMovimiento;
use App\Models\SaldoBanco;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Trae el extracto de una cuenta y lo guarda en `banco_movimientos`.
 *
 * Es deliberadamente tonto: baja, deduplica, inserta. No concilia, no toca
 * `consignaciones` ni `saldos_banco`, no marca nada como pagado. Ese cruce va
 * en un servicio aparte, y separarlos es lo que permite volver a bajar un mes
 * entero sin miedo a que algo se mueva en el libro.
 *
 * La deduplicación es por huella (ver MovimientoBanco::huella) y se apoya en
 * el índice único de la tabla: si dos corridas simultáneas intentan meter la
 * misma fila, gana una y la otra se descarta sin romper la corrida.
 */
class SincronizadorMovimientosService
{
    private BancoApiInterface $api;

    public function __construct(?BancoApiInterface $api = null)
    {
        $this->api = $api ?? BancoApiFactory::make();
    }

    /**
     * Sincroniza una cuenta en un rango de fechas (ambas inclusive).
     *
     * @return array{cuenta_id:int, proveedor:string, desde:string, hasta:string,
     *               traidos:int, nuevos:int, repetidos:int, creditos:int, debitos:int}
     */
    public function sincronizar(BancoCuenta $cuenta, ?CarbonInterface $desde = null, ?CarbonInterface $hasta = null): array
    {
        $hasta ??= Carbon::today();
        $desde ??= $hasta->copy()->subDays((int) config('banco.dias_atras', 5));

        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        if ($desde->gt($hasta)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        }

        $maxDias = (int) config('banco.max_dias_rango', 90);
        if ($desde->diffInDays($hasta) > $maxDias) {
            throw new InvalidArgumentException(
                "El rango pedido supera el tope de $maxDias días. Pártelo en varias corridas."
            );
        }

        $movimientos = $this->api->movimientos($cuenta, $desde, $hasta);

        // Huellas ya guardadas del rango, en una sola consulta. Preguntar por
        // cada movimiento sería un viaje al SQL Server por fila.
        $existentes = BancoMovimiento::query()
            ->where('banco_cuenta_id', $cuenta->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->copy()->addDay()->toDateString()])
            ->pluck('huella')
            ->flip();

        $ahora = now();
        $filas = [];
        $vistas = [];
        $repetidos = 0;
        $creditos = 0;
        $debitos = 0;

        foreach ($movimientos as $mov) {
            $huella = $mov->huella();

            // `$vistas` cubre el duplicado dentro de la misma respuesta del
            // banco, que el índice único atraparía pero abortando la tanda.
            if (isset($existentes[$huella]) || isset($vistas[$huella])) {
                $repetidos++;

                continue;
            }
            $vistas[$huella] = true;

            $mov->esCredito() ? $creditos++ : $debitos++;

            $filas[] = [
                'aliado_id' => $cuenta->aliado_id,
                'banco_cuenta_id' => $cuenta->id,
                'proveedor' => $this->api->nombre(),
                'id_externo' => $mov->idExterno,
                'huella' => $huella,
                'fecha' => $mov->fecha->toDateString(),
                'fecha_hora' => $mov->fechaHora?->toDateTimeString(),
                'tipo' => $mov->tipo,
                'valor' => $mov->valor,
                'saldo_despues' => $mov->saldoDespues,
                'descripcion' => $mov->descripcion ? mb_substr($mov->descripcion, 0, 255) : null,
                'referencia' => $mov->referencia ? mb_substr($mov->referencia, 0, 100) : null,
                'canal' => $mov->canal ? mb_substr($mov->canal, 0, 60) : null,
                'contraparte_nombre' => $mov->contraparteNombre ? mb_substr($mov->contraparteNombre, 0, 150) : null,
                'contraparte_documento' => $mov->contraparteDocumento ? mb_substr($mov->contraparteDocumento, 0, 30) : null,
                'estado_conciliacion' => BancoMovimiento::CONCILIACION_PENDIENTE,
                'consignacion_id' => null,
                'conciliado_por' => null,
                'conciliado_at' => null,
                'payload' => $mov->payload ? json_encode($mov->payload, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        $nuevos = $this->insertar($filas);

        return [
            'cuenta_id' => (int) $cuenta->id,
            'proveedor' => $this->api->nombre(),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'traidos' => count($movimientos),
            'nuevos' => $nuevos,
            'repetidos' => $repetidos,
            'creditos' => $creditos,
            'debitos' => $debitos,
        ];
    }

    /**
     * Saldo del banco contra el saldo que BryNex cree tener.
     *
     * Solo informa; no ajusta nada. Un descuadre casi siempre significa que
     * falta registrar un movimiento, no que el libro esté mal calculado.
     *
     * @return array{banco:float, libro:int, diferencia:float, fecha:string}
     */
    public function compararSaldo(BancoCuenta $cuenta): array
    {
        $saldo = $this->api->saldo($cuenta);
        $libro = SaldoBanco::saldoActual((int) $cuenta->aliado_id, (int) $cuenta->id);

        return [
            'banco' => (float) $saldo['disponible'],
            'libro' => (int) $libro,
            'diferencia' => round((float) $saldo['disponible'] - $libro, 2),
            'fecha' => $saldo['fecha']->toDateString(),
        ];
    }

    /**
     * Inserta por tandas. Si una tanda choca con el índice único (otra corrida
     * metió la misma fila en el intermedio), se reintenta fila por fila para
     * no perder las que sí eran nuevas.
     */
    private function insertar(array $filas): int
    {
        if ($filas === []) {
            return 0;
        }

        $lote = max(1, (int) config('banco.lote_insert', 100));
        $total = 0;

        foreach (array_chunk($filas, $lote) as $tanda) {
            try {
                DB::table('banco_movimientos')->insert($tanda);
                $total += count($tanda);
            } catch (QueryException $e) {
                $total += $this->insertarUnaAUna($tanda);
            }
        }

        return $total;
    }

    private function insertarUnaAUna(array $tanda): int
    {
        $total = 0;

        foreach ($tanda as $fila) {
            try {
                DB::table('banco_movimientos')->insert($fila);
                $total++;
            } catch (QueryException $e) {
                // Duplicado por carrera: la fila ya está, no hay nada que hacer.
                Log::info('banco: movimiento omitido al insertar', [
                    'cuenta' => $fila['banco_cuenta_id'],
                    'huella' => $fila['huella'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $total;
    }
}
