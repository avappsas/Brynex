<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventario de los registros trampa sembrados en cada aliado.
 * Ver la migración `create_canarios_table` para el porqué.
 */
class Canario extends BaseModel
{
    protected $table = 'canarios';

    public const UPDATED_AT = null;

    protected $fillable = [
        'aliado_id',
        'tipo',
        'referencia_id',
        'cedula',
        'nombre',
        'notas',
        'activo',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'activo' => 'boolean',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
