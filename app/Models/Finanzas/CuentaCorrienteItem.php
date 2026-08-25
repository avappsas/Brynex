<?php

namespace App\Models\Finanzas;

/**
 * App\Models\Finanzas\CuentaCorrienteItem
 *
 * Línea de desglose de un trabajo: "Cámara Hikvision 1080p", 4 x $180.000.
 * La suma de los ítems es el valor total del trabajo.
 *
 * @property int $id
 * @property int $prestamo_id
 * @property string $descripcion
 * @property float $cantidad
 * @property float $valor_unitario
 * @property float $costo_unitario
 * @property int $orden
 */
class CuentaCorrienteItem extends BaseFinanzasModel
{
    protected $table = 'finanzas_cc_trabajo_items';

    protected $fillable = [
        'prestamo_id',
        'descripcion',
        'cantidad',
        'valor_unitario',
        'costo_unitario',
        'orden',
    ];

    protected $casts = [
        'prestamo_id' => 'integer',
        'cantidad' => 'float',
        'valor_unitario' => 'float',
        'costo_unitario' => 'float',
        'orden' => 'integer',
    ];

    public function trabajo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function getSubtotalAttribute(): float
    {
        return round($this->cantidad * $this->valor_unitario, 2);
    }

    /**
     * Lo que costó esta línea: lo que salió del bolsillo, no lo que se cobra.
     */
    public function getCostoSubtotalAttribute(): float
    {
        return round($this->cantidad * $this->costo_unitario, 2);
    }

    public function getUtilidadAttribute(): float
    {
        return round($this->subtotal - $this->costo_subtotal, 2);
    }
}
