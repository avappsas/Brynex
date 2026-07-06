<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\FuenteIngreso
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string $tipo
 * @property string|null $descripcion
 * @property bool $activo
 * @property int $orden
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class FuenteIngreso extends BaseFinanzasModel
{
    protected $table = 'finanzas_fuentes_ingreso';

    protected $fillable = [
        'user_id',
        'nombre',
        'tipo', // fijo | proyecto | esporadico
        'descripcion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * Relación con el usuario (Base de datos principal)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con las entradas mensuales registradas
     */
    public function entradas()
    {
        return $this->hasMany(Entrada::class, 'fuente_id');
    }

    /**
     * Scope para fuentes activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
