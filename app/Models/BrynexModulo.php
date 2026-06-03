<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class BrynexModulo extends BaseModel
{
    protected $table = 'brynex_modulos';

    public $incrementing = true;
    protected $keyType   = 'int';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo', 'orden'];

    protected $casts = ['activo' => 'boolean'];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function tramos(): HasMany
    {
        return $this->hasMany(BrynexTramoTarifa::class, 'modulo_id');
    }

    public function modulosAliado(): HasMany
    {
        return $this->hasMany(BrynexModuloAliado::class, 'modulo_id');
    }

    public function cobrosDetalle(): HasMany
    {
        return $this->hasMany(BrynexCobroDetalle::class, 'modulo_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }
}
