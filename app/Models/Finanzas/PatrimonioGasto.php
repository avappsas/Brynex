<?php

namespace App\Models\Finanzas;

/**
 * App\Models\Finanzas\PatrimonioGasto
 *
 * @property int $id
 * @property int $patrimonio_id
 * @property string $concepto
 * @property float $monto
 * @property string $fecha
 * @property string|null $observacion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class PatrimonioGasto extends BaseFinanzasModel
{
    protected $table = 'finanzas_patrimonio_gastos';

    protected $fillable = [
        'patrimonio_id',
        'concepto',
        'monto',
        'fecha',
        'observacion',
    ];

    protected $casts = [
        'patrimonio_id' => 'integer',
        'monto' => 'float',
    ];

    /**
     * Relación con el patrimonio
     */
    public function patrimonio()
    {
        return $this->belongsTo(Patrimonio::class, 'patrimonio_id');
    }
}
