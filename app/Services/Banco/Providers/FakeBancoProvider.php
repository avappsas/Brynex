<?php

namespace App\Services\Banco\Providers;

use App\Models\BancoCuenta;
use App\Models\Consignacion;
use App\Models\Gasto;
use App\Services\Banco\BancoApiInterface;
use App\Services\Banco\MovimientoBanco;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Extracto bancario simulado, para trabajar sin el API del banco.
 *
 * No inventa un extracto de la nada: parte de lo que BryNex ya tiene
 * registrado en esa cuenta (consignaciones y gastos) y lo devuelve como si
 * viniera del banco. Así la conciliación se puede desarrollar y probar contra
 * datos reales del aliado, y lo que salga descuadrado es descuadre de verdad.
 *
 * Encima mete el ruido que trae cualquier extracto real, porque un fake que
 * cuadra al 100% no sirve para probar la conciliación:
 *
 *   - consignaciones que el banco NO reporta      → el caso `no_aparece`
 *   - consignaciones que el banco aplica un día después
 *   - consignaciones que llegan sin referencia    → hay que cruzar por valor
 *   - entradas que el banco reporta y nadie registró → bandeja sin identificar
 *   - el 4x1000 y la cuota de manejo               → débitos que se ignoran
 *
 * Todo el ruido es determinista (sale del id del registro, no de un random):
 * dos corridas seguidas del mismo rango devuelven exactamente lo mismo, que es
 * justo lo que necesita la deduplicación por huella para poder probarse.
 */
class FakeBancoProvider implements BancoApiInterface
{
    /** Consignaciones cuyo id cae en estos módulos reciben cada distorsión. */
    private const MOD_NO_REPORTADA = 17;

    private const MOD_UN_DIA_DESPUES = 13;

    private const MOD_SIN_REFERENCIA = 23;

    /** Gravamen a los movimientos financieros: 4 por mil. */
    private const TASA_GMF = 0.004;

    public function nombre(): string
    {
        return 'fake';
    }

    public function movimientos(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $movs = array_merge(
            $this->desdeConsignaciones($cuenta, $desde, $hasta),
            $this->desdeGastos($cuenta, $desde, $hasta),
            $this->entradasHuerfanas($cuenta, $desde, $hasta),
            $this->costosBancarios($cuenta, $desde, $hasta),
        );

        // El banco entrega el extracto en orden cronológico; el fake también,
        // porque de ese orden depende la secuencia que entra en la huella.
        usort($movs, function (array $a, array $b) {
            return [$a['fecha']->toDateString(), $a['orden']] <=> [$b['fecha']->toDateString(), $b['orden']];
        });

        return $this->armar($movs);
    }

    public function saldo(BancoCuenta $cuenta): array
    {
        // Suma de todo lo que este mismo fake reportaría desde el arranque del
        // módulo. No coincide con `saldos_banco` a propósito: el saldo real y
        // el del libro son dos cosas distintas, y compararlos es el punto.
        $hasta = Carbon::today();
        $movs = $this->movimientos($cuenta, $hasta->copy()->subYear(), $hasta);

        $saldo = 0.0;
        foreach ($movs as $m) {
            $saldo += $m->esCredito() ? $m->valor : -$m->valor;
        }

        return [
            'disponible' => round($saldo, 2),
            'total' => round($saldo, 2),
            'fecha' => $hasta,
        ];
    }

    // ── Fuentes del extracto simulado ────────────────────────────────

    /** Entradas: lo que el aliado registró como consignado a esta cuenta. */
    private function desdeConsignaciones(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        // Se pide un día más por el lado derecho porque las consignaciones
        // corridas un día quedarían fuera del rango si se corta exacto.
        $filas = Consignacion::query()
            ->where('aliado_id', $cuenta->aliado_id)
            ->where('banco_cuenta_id', $cuenta->id)
            ->whereBetween('fecha', [
                $desde->copy()->startOfDay(),
                $hasta->copy()->endOfDay(),
            ])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $movs = [];

        foreach ($filas as $c) {
            if ($c->id % self::MOD_NO_REPORTADA === 0) {
                continue;   // el banco nunca la reportó
            }

            $fecha = $c->fecha->copy();
            if ($c->id % self::MOD_UN_DIA_DESPUES === 0) {
                $fecha->addDay();
                if ($fecha->gt($hasta)) {
                    continue;   // se corrió fuera del rango consultado
                }
            }

            $sinReferencia = $c->id % self::MOD_SIN_REFERENCIA === 0;

            $movs[] = [
                'fecha' => $fecha,
                'orden' => (int) $c->id,
                'tipo' => 'credito',
                'valor' => (float) $c->valor,
                'descripcion' => $this->descripcionEntrada($c),
                'referencia' => $sinReferencia ? null : ($c->referencia ?: null),
                'canal' => $c->tipo === Consignacion::TIPO_TRASLADO_EFECTIVO ? 'CORRESPONSAL' : 'TRANSFERENCIA',
                'payload' => ['origen' => 'consignacion', 'consignacion_id' => (int) $c->id],
            ];
        }

        return $movs;
    }

    /** Salidas: los gastos que el aliado pagó desde esta cuenta. */
    private function desdeGastos(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $filas = Gasto::query()
            ->where('aliado_id', $cuenta->aliado_id)
            ->where('banco_origen_id', $cuenta->id)
            ->whereBetween('fecha', [
                $desde->copy()->startOfDay(),
                $hasta->copy()->endOfDay(),
            ])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $movs = [];

        foreach ($filas as $g) {
            $movs[] = [
                'fecha' => $g->fecha->copy(),
                'orden' => 100000 + (int) $g->id,
                'tipo' => 'debito',
                'valor' => (float) $g->valor,
                'descripcion' => $this->descripcionSalida($g),
                'referencia' => $g->recibo_caja ?: null,
                'canal' => 'SUCURSAL VIRTUAL',
                'payload' => ['origen' => 'gasto', 'gasto_id' => (int) $g->id],
            ];
        }

        return $movs;
    }

    /**
     * Entradas que el banco reporta y nadie registró en BryNex.
     *
     * Es el caso que hoy no existe en el sistema: plata que llegó y quedó
     * invisible hasta que alguien la digita. Una por semana del rango, con un
     * valor derivado de la fecha para que sea estable entre corridas.
     */
    private function entradasHuerfanas(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $movs = [];
        $cursor = $desde->copy()->startOfDay();

        while ($cursor->lte($hasta)) {
            if ($cursor->dayOfWeek === Carbon::WEDNESDAY) {
                $semilla = (int) $cursor->format('Ymd') + (int) $cuenta->id;

                $movs[] = [
                    'fecha' => $cursor->copy(),
                    'orden' => 900000,
                    'tipo' => 'credito',
                    'valor' => (float) (50000 + ($semilla % 45) * 10000),
                    'descripcion' => 'ABONO TRANSFERENCIA SUCURSAL VIRTUAL',
                    'referencia' => null,
                    'canal' => 'TRANSFERENCIA',
                    'contraparte_nombre' => 'PAGADOR NO IDENTIFICADO',
                    'contraparte_documento' => (string) (1000000000 + $semilla % 99999999),
                    'payload' => ['origen' => 'huerfana'],
                ];
            }

            $cursor->addDay();
        }

        return $movs;
    }

    /**
     * Cobros del banco: 4x1000 sobre los débitos del mes y cuota de manejo.
     *
     * Nunca van a cuadrar contra el libro porque BryNex no los registra: son
     * el ejemplo de movimiento que la conciliación debe marcar `ignorado` en
     * vez de dejar como diferencia.
     */
    private function costosBancarios(BancoCuenta $cuenta, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $debitos = (float) DB::table('gastos')
            ->where('aliado_id', $cuenta->aliado_id)
            ->where('banco_origen_id', $cuenta->id)
            ->whereBetween('fecha', [
                $desde->copy()->startOfDay()->toDateString(),
                $hasta->copy()->endOfDay()->toDateString(),
            ])
            ->sum('valor');

        $cierre = $hasta->copy()->startOfDay();
        $movs = [];

        if ($debitos > 0) {
            $movs[] = [
                'fecha' => $cierre->copy(),
                'orden' => 990001,
                'tipo' => 'debito',
                'valor' => round($debitos * self::TASA_GMF, 2),
                'descripcion' => 'GMF 4X1000',
                'referencia' => null,
                'canal' => 'BANCO',
                'payload' => ['origen' => 'gmf'],
            ];
        }

        $movs[] = [
            'fecha' => $cierre->copy(),
            'orden' => 990002,
            'tipo' => 'debito',
            'valor' => 19500.00,
            'descripcion' => 'CUOTA DE MANEJO',
            'referencia' => null,
            'canal' => 'BANCO',
            'payload' => ['origen' => 'cuota_manejo'],
        ];

        return $movs;
    }

    // ── Armado final ─────────────────────────────────────────────────

    /**
     * Convierte las filas crudas en DTOs, numerando la secuencia dentro de
     * cada día. Esa secuencia es la que salva la huella cuando dos movimientos
     * del mismo día tienen el mismo valor y la misma descripción.
     */
    private function armar(array $filas): array
    {
        $secuenciaPorDia = [];
        $salida = [];

        foreach ($filas as $f) {
            $dia = $f['fecha']->toDateString();
            $secuenciaPorDia[$dia] = ($secuenciaPorDia[$dia] ?? 0) + 1;

            $salida[] = new MovimientoBanco(
                fecha: $f['fecha'],
                tipo: $f['tipo'],
                valor: round((float) $f['valor'], 2),
                descripcion: $f['descripcion'] ?? null,
                referencia: $f['referencia'] ?? null,
                // El fake no da id externo a propósito: el API de extracto
                // tampoco lo garantiza, y así se ejercita la huella calculada.
                idExterno: null,
                fechaHora: null,
                saldoDespues: null,
                canal: $f['canal'] ?? null,
                contraparteNombre: $f['contraparte_nombre'] ?? null,
                contraparteDocumento: $f['contraparte_documento'] ?? null,
                secuencia: $secuenciaPorDia[$dia],
                payload: $f['payload'] ?? [],
            );
        }

        return $salida;
    }

    /** El extracto no repite "PAGO" cuando el gasto ya viene descrito así. */
    private function descripcionSalida(Gasto $g): string
    {
        $texto = trim((string) ($g->descripcion ?: $g->tipo));
        if (! preg_match('/^pago/i', $texto)) {
            $texto = 'PAGO '.$texto;
        }

        return mb_strtoupper(mb_substr($texto, 0, 60));
    }

    private function descripcionEntrada(Consignacion $c): string
    {
        return match ($c->tipo) {
            Consignacion::TIPO_TRASLADO_EFECTIVO => 'CONSIGNACION EFECTIVO CORRESPONSAL',
            Consignacion::TIPO_BANCO_RECIBIDO => 'TRANSFERENCIA RECIBIDA OTRO BANCO',
            Consignacion::TIPO_ANTICIPO => 'ABONO TRANSFERENCIA SUCURSAL VIRTUAL',
            default => 'ABONO TRANSFERENCIA',
        };
    }
}
