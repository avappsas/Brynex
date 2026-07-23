<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketingLista extends BaseModel
{
    protected $table = 'marketing_listas';

    protected $fillable = [
        'aliado_id',
        'nombre',
        'descripcion',
        'creado_por',
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function contactos(): BelongsToMany
    {
        return $this->belongsToMany(MarketingContacto::class, 'marketing_lista_contacto', 'lista_id', 'contacto_id')
            ->withTimestamps();
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeDelAliado($query, int $alidoId)
    {
        return $query->where('aliado_id', $alidoId);
    }
}
