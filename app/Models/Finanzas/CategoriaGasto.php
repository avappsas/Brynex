<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\CategoriaGasto
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string|null $icono
 * @property string|null $color
 * @property bool $es_recurrente
 * @property bool $activo
 * @property int $orden
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class CategoriaGasto extends BaseFinanzasModel
{
    protected $table = 'finanzas_categorias_gasto';

    protected $fillable = [
        'user_id',
        'nombre',
        'icono',
        'color',
        'es_recurrente',
        'activo',
        'orden',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'es_recurrente' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los gastos de la categoría
     */
    public function gastos()
    {
        return $this->hasMany(Gasto::class, 'categoria_id');
    }

    /**
     * Scope para categorías activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para categorías recurrentes
     */
    public function scopeRecurrentes($query)
    {
        return $query->where('es_recurrente', true);
    }
}
