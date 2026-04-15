<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoModalidad extends Model
{
    public $timestamps    = false;
    protected $table      = 'tipo_modalidad';
    protected $primaryKey = 'id';
    public $incrementing  = false;  // El ID NO es auto-incremental

    protected $fillable = ['id', 'tipo_modalidad', 'observacion', 'orden', 'modalidad', 'activo'];
    protected $casts    = ['activo' => 'boolean'];

    /** Scope: activos, ordenados, sin el registro "Todos" (-100) */
    public function scopeActivos($q)
    {
        return $q->where('activo', true)
                 ->where('id', '!=', -100)
                 ->orderBy('orden');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'tipo_modalidad_id');
    }

    /** Nombre para mostrar en la UI: usa observacion si existe, si no tipo_modalidad */
    public function getNombreAttribute(): string
    {
        return $this->observacion ?: $this->tipo_modalidad;
    }

    /** IDs que corresponden a modalidades independientes */
    const IDS_INDEPENDIENTE = [10, 11];

    /** IDs que requieren el campo "Modo ARL" */
    const IDS_MODO_ARL = [10, 11, -1];

    /** IDs en que la ARL es libre (no bloqueada a la razon social) */
    const IDS_ARL_LIBRE = [10, 11, -1, 8];

    public function esIndependiente(): bool
    {
        return in_array($this->id, self::IDS_INDEPENDIENTE);
    }
}
