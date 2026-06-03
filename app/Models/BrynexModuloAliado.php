<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrynexModuloAliado extends BaseModel
{
    protected $table = 'brynex_modulos_aliado';

    protected $fillable = [
        'aliado_id', 'modulo_id', 'activo',
        'notas_negociacion', 'fecha_inicio', 'fecha_fin',
    ];

    protected $casts = [
        'activo'      => 'boolean',
        'fecha_inicio'=> 'date',
        'fecha_fin'   => 'date',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(BrynexModulo::class, 'modulo_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
