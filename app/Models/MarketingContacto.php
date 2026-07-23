<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketingContacto extends BaseModel
{
    protected $table = 'marketing_contactos';

    protected $fillable = [
        'aliado_id',
        'celular',
        'cedula',
        'nombres',
        'departamento',
        'ciudad',
        'observacion',
        'veces_contactado',
        'ultima_campana_at',
        'respondio_alguna_vez',
    ];

    protected $casts = [
        'cedula'                => 'integer',
        'veces_contactado'      => 'integer',
        'ultima_campana_at'     => 'datetime',
        'respondio_alguna_vez'  => 'boolean',
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function listas(): BelongsToMany
    {
        return $this->belongsToMany(MarketingLista::class, 'marketing_lista_contacto', 'contacto_id', 'lista_id')
            ->withTimestamps();
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeDelAliado($query, int $alidoId)
    {
        return $query->where('aliado_id', $alidoId);
    }
}
