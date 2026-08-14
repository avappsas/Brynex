<?php

namespace App\Models\Finanzas;

/**
 * App\Models\Finanzas\PrestamoMovimiento
 *
 * @property int $id
 * @property int $prestamo_id
 * @property string $tipo
 * @property string $fecha
 * @property float $monto
 * @property float $saldo_antes
 * @property float $saldo_despues
 * @property int|null $dias_periodo
 * @property string|null $observacion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class PrestamoMovimiento extends BaseFinanzasModel
{
    protected $table = 'finanzas_prestamo_movimientos';

    protected $fillable = [
        'prestamo_id',
        'tipo', // desembolso | interes_mensual | interes_proporcional | capitalizacion | abono_interes | abono_capital | pago_total
        'fecha',
        'monto',
        'saldo_antes',
        'saldo_despues',
        'dias_periodo',
        'observacion',
        'soporte_path',
        'cuenta_id',
    ];

    protected $casts = [
        'prestamo_id' => 'integer',
        'monto' => 'float',
        'saldo_antes' => 'float',
        'saldo_despues' => 'float',
        'dias_periodo' => 'integer',
        'cuenta_id' => 'integer',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_id');
    }

    /**
     * Relación con el préstamo
     */
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }
}
