<?php

namespace App\Models\Finanzas;

/**
 * App\Models\Finanzas\ProyectoMovimiento
 *
 * @property int $id
 * @property int $proyecto_id
 * @property string $tipo
 * @property string $fecha
 * @property float $monto
 * @property string|null $observacion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ProyectoMovimiento extends BaseFinanzasModel
{
    protected $table = 'finanzas_proyecto_movimientos';

    protected $fillable = [
        'proyecto_id',
        'tipo', // entrada | salida
        'fecha',
        'monto',
        'observacion',
    ];

    protected $casts = [
        'proyecto_id' => 'integer',
        'monto' => 'float',
    ];

    /**
     * Relación con el proyecto
     */
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
