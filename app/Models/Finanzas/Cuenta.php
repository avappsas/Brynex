<?php

namespace App\Models\Finanzas;

use Illuminate\Support\Facades\DB;

/**
 * App\Models\Finanzas\Cuenta
 *
 * Cuenta / bolsillo donde vive el dinero (Banco, Efectivo, Nequi...).
 * El saldo se calcula a partir de todos los movimientos asociados:
 *  (+) entradas mensuales, ingresos esporádicos, abonos de préstamos,
 *      entradas de proyectos y transferencias recibidas.
 *  (-) gastos, desembolsos de préstamos e inversiones (vía finanzas_gastos),
 *      salidas de proyectos y transferencias enviadas.
 */
class Cuenta extends BaseFinanzasModel
{
    protected $table = 'finanzas_cuentas';

    protected $fillable = [
        'user_id',
        'nombre',
        'tipo', // banco | efectivo | billetera | otro
        'icono',
        'color',
        'saldo_inicial',
        'activo',
        'orden',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'saldo_inicial' => 'float',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Calcula el saldo actual de todas las cuentas de un usuario en pocas consultas.
     * Devuelve la colección de cuentas activas con el atributo dinámico `saldo_actual`.
     */
    public static function conSaldos(int $userId)
    {
        $cuentas = static::where('user_id', $userId)
            ->activas()
            ->orderBy('orden')
            ->get();

        if ($cuentas->isEmpty()) {
            return $cuentas;
        }

        $conn = DB::connection('finanzas');

        // Salidas: gastos, préstamos otorgados e inversiones (todas viven en finanzas_gastos)
        $salidas = $conn->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->whereIn('tipo_movimiento', ['gasto', 'prestamo', 'inversion'])
            ->whereNotNull('cuenta_id')
            ->groupBy('cuenta_id')
            ->selectRaw('cuenta_id, SUM(monto) as total')
            ->pluck('total', 'cuenta_id');

        // Entradas esporádicas registradas en finanzas_gastos
        $esporadicos = $conn->table('finanzas_gastos')
            ->where('user_id', $userId)
            ->where('tipo_movimiento', 'ingreso_esporadico')
            ->whereNotNull('cuenta_id')
            ->groupBy('cuenta_id')
            ->selectRaw('cuenta_id, SUM(monto) as total')
            ->pluck('total', 'cuenta_id');

        // Entradas mensuales manuales
        $entradas = $conn->table('finanzas_entradas')
            ->where('user_id', $userId)
            ->whereNotNull('cuenta_id')
            ->groupBy('cuenta_id')
            ->selectRaw('cuenta_id, SUM(monto) as total')
            ->pluck('total', 'cuenta_id');

        // Abonos recibidos de préstamos (dinero que vuelve)
        $abonos = $conn->table('finanzas_prestamo_movimientos')
            ->join('finanzas_prestamos', 'finanzas_prestamo_movimientos.prestamo_id', '=', 'finanzas_prestamos.id')
            ->where('finanzas_prestamos.user_id', $userId)
            ->whereIn('finanzas_prestamo_movimientos.tipo', ['abono_capital', 'abono_interes', 'pago_total'])
            ->whereNotNull('finanzas_prestamo_movimientos.cuenta_id')
            ->groupBy('finanzas_prestamo_movimientos.cuenta_id')
            ->selectRaw('finanzas_prestamo_movimientos.cuenta_id, SUM(finanzas_prestamo_movimientos.monto) as total')
            ->pluck('total', 'cuenta_id');

        // Movimientos de proyectos (entrada suma, salida resta)
        $proyectos = $conn->table('finanzas_proyecto_movimientos')
            ->join('finanzas_proyectos', 'finanzas_proyecto_movimientos.proyecto_id', '=', 'finanzas_proyectos.id')
            ->where('finanzas_proyectos.user_id', $userId)
            ->whereNotNull('finanzas_proyecto_movimientos.cuenta_id')
            ->groupBy('finanzas_proyecto_movimientos.cuenta_id')
            ->selectRaw("finanzas_proyecto_movimientos.cuenta_id, SUM(CASE WHEN finanzas_proyecto_movimientos.tipo = 'entrada' THEN finanzas_proyecto_movimientos.monto ELSE -finanzas_proyecto_movimientos.monto END) as total")
            ->pluck('total', 'cuenta_id');

        // Transferencias entre cuentas
        $transferenciasSalida = $conn->table('finanzas_cuenta_transferencias')
            ->where('user_id', $userId)
            ->groupBy('cuenta_origen_id')
            ->selectRaw('cuenta_origen_id, SUM(monto) as total')
            ->pluck('total', 'cuenta_origen_id');

        $transferenciasEntrada = $conn->table('finanzas_cuenta_transferencias')
            ->where('user_id', $userId)
            ->groupBy('cuenta_destino_id')
            ->selectRaw('cuenta_destino_id, SUM(monto) as total')
            ->pluck('total', 'cuenta_destino_id');

        foreach ($cuentas as $cuenta) {
            $id = $cuenta->id;
            $cuenta->saldo_actual = (float) $cuenta->saldo_inicial
                + (float) ($entradas[$id] ?? 0)
                + (float) ($esporadicos[$id] ?? 0)
                + (float) ($abonos[$id] ?? 0)
                + (float) ($proyectos[$id] ?? 0)
                + (float) ($transferenciasEntrada[$id] ?? 0)
                - (float) ($salidas[$id] ?? 0)
                - (float) ($transferenciasSalida[$id] ?? 0);
        }

        return $cuentas;
    }
}
