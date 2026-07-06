<?php

namespace App\Models\Finanzas;

/**
 * App\Models\Finanzas\InversionMovimiento
 *
 * @property int $id
 * @property int $inversion_id
 * @property string $tipo
 * @property string $fecha
 * @property float $monto_cop
 * @property float|null $cantidad_tokens
 * @property float|null $precio_token_cop
 * @property string|null $observacion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class InversionMovimiento extends BaseFinanzasModel
{
    protected $table = 'finanzas_inversion_movimientos';

    protected $fillable = [
        'inversion_id',
        'tipo', // compra | venta | ganancia | perdida
        'fecha',
        'monto_cop',
        'cantidad_tokens',
        'precio_token_cop',
        'observacion',
    ];

    protected $casts = [
        'inversion_id' => 'integer',
        'monto_cop' => 'float',
        'cantidad_tokens' => 'float',
        'precio_token_cop' => 'float',
    ];

    /**
     * Relación con la inversión
     */
    public function inversion()
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }
}
