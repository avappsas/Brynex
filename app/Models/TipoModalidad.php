<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoModalidad extends BaseModel
{
    public $timestamps    = false;
    protected $table      = 'tipo_modalidad';
    protected $primaryKey = 'id';
    public $incrementing  = false;  // El ID NO es auto-incremental

    protected $fillable = [
        'id', 'tipo_modalidad', 'observacion', 'orden', 'modalidad', 'activo',
        'es_tiempo_parcial', 'dias_arl', 'dias_afp', 'dias_caja',
    ];

    protected $casts = [
        'activo'            => 'boolean',
        'es_tiempo_parcial' => 'boolean',
        'dias_arl'          => 'integer',
        'dias_afp'          => 'integer',
        'dias_caja'         => 'integer',
    ];

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

    /**
     * ¿Es modalidad de Tiempo Parcial?
     * Los planes TP tienen días fijos por entidad definidos en BD.
     */
    public function esTiempoParcial(): bool
    {
        return (bool) $this->es_tiempo_parcial;
    }

    /**
     * Retorna los días a cotizar por entidad para este plan de Tiempo Parcial.
     * Array: ['arl' => X, 'afp' => Y, 'caja' => Z]
     *
     * Regla de negocio:
     *   - ARL: siempre 30 días (cotización mensual completa, sin importar el plan)
     *   - AFP: días fijos del plan (7, 14 ó 21)
     *   - CAJA: días fijos del plan (7, 14 ó 21)
     *
     * Si no es TP, retorna 30 para todas (mes completo).
     */
    public function diasPorEntidad(): array
    {
        if ($this->esTiempoParcial()) {
            return [
                'arl'  => 30,                    // ARL siempre mensual completa
                'afp'  => $this->dias_afp  ?? 30,
                'caja' => $this->dias_caja ?? 30,
            ];
        }
        return ['arl' => 30, 'afp' => 30, 'caja' => 30];
    }
}
