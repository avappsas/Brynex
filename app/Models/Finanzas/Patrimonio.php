<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\Patrimonio
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string $categoria
 * @property float $valor_compra
 * @property string $fecha_adquisicion
 * @property float|null $valor_actual
 * @property string|null $observaciones
 * @property bool $activo
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Patrimonio extends BaseFinanzasModel
{
    protected $table = 'finanzas_patrimonio';

    protected $fillable = [
        'user_id',
        'nombre',
        'categoria', // inmueble | vehiculo | electronico | joya | otro
        'valor_compra',
        'fecha_adquisicion',
        'valor_actual',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'valor_compra' => 'float',
        'valor_actual' => 'float',
        'activo' => 'boolean',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los gastos de mantenimiento/impuestos del patrimonio
     */
    public function gastos()
    {
        return $this->hasMany(PatrimonioGasto::class, 'patrimonio_id');
    }

    /**
     * Scope para bienes activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Accessor para el total gastado en este bien (impuestos, seguros, mantenimiento, etc.)
     */
    public function getValorTotalGastosAttribute(): float
    {
        return (float) $this->gastos()->sum('monto');
    }
}
