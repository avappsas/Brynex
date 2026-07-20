<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\Proyecto
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $activo
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Proyecto extends BaseFinanzasModel
{
    protected $table = 'finanzas_proyectos';

    protected $fillable = [
        'user_id',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'user_id' => 'integer',
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
     * Relación con los movimientos del proyecto
     */
    public function movimientos()
    {
        return $this->hasMany(ProyectoMovimiento::class, 'proyecto_id');
    }

    /**
     * Scope para proyectos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Accessor para el balance acumulado (Entradas - Salidas)
     */
    public function getBalanceAttribute(): float
    {
        return $this->entradas_total - $this->salidas_total;
    }

    /**
     * Total histórico de entradas del proyecto
     */
    public function getEntradasTotalAttribute(): float
    {
        return (float) $this->movimientos()->where('tipo', 'entrada')->sum('monto');
    }

    /**
     * Total histórico de salidas del proyecto
     */
    public function getSalidasTotalAttribute(): float
    {
        return (float) $this->movimientos()->where('tipo', 'salida')->sum('monto');
    }
}
