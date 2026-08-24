<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un seguro que el aliado vende: "Plan exequial 2" a $30.000, "Seguro mascotas" a $15.000.
 *
 * Es el catálogo, no lo cobrado: el contrato copia el `valor` a su campo `seguro` al
 * guardarse, así que subirle el precio al catálogo no le cambia la cuota a quien ya
 * lo tiene — igual que con la administración.
 */
class AliadoSeguro extends BaseModel
{
    protected $table = 'aliado_seguros';

    protected $fillable = ['aliado_id', 'nombre', 'valor', 'descripcion', 'orden', 'activo'];

    protected $casts = [
        'valor' => 'decimal:2',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'seguro_id');
    }

    /** Scope: los que el aliado tiene activos, en su orden de presentación. */
    public function scopeActivos($q, ?int $aliadoId = null)
    {
        return $q->where('activo', true)
            ->when($aliadoId, fn ($qq) => $qq->where('aliado_id', $aliadoId))
            ->orderBy('orden')
            ->orderBy('nombre');
    }
}
