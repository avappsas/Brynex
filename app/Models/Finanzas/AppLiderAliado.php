<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\AppLiderAliado
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property float $valor_mensual
 * @property bool $activo
 * @property string $fecha_inicio
 * @property string|null $fecha_fin
 * @property string|null $observaciones
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class AppLiderAliado extends BaseFinanzasModel
{
    protected $table = 'finanzas_app_lideres_aliados';

    protected $fillable = [
        'user_id',
        'nombre',
        'valor_mensual',
        'activo',
        'fecha_inicio',
        'fecha_fin',
        'observaciones',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'valor_mensual' => 'float',
        'activo' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope para aliados activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
