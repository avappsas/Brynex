<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una celda de la matriz propia del asesor — la copia editable de la plantilla de su nivel.
 *
 * Manda sobre la del nivel. Si el asesor no tiene fila para una combinación, se cae a la
 * plantilla y, si tampoco, a su comisión plana de siempre (ver TarifaAsesorService).
 */
class AsesorTarifa extends BaseModel
{
    protected $table = 'asesor_tarifas';

    protected $fillable = [
        'aliado_id', 'asesor_id', 'plan_id', 'tipo_modalidad_id', 'nivel_arl', 'afil_asesor',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'tipo_modalidad_id' => 'integer',
        'nivel_arl' => 'integer',
        'afil_asesor' => 'decimal:2',
    ];

    public function getClaveAttribute(): string
    {
        return AsesorNivelTarifa::claveCelda($this->plan_id, $this->tipo_modalidad_id, $this->nivel_arl);
    }

    // ─── Relaciones ───────────────────────────────────────────────────
    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class, 'asesor_id');
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanContrato::class, 'plan_id');
    }

    public function tipoModalidad(): BelongsTo
    {
        return $this->belongsTo(TipoModalidad::class, 'tipo_modalidad_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────
    public function scopeDelAliado(Builder $q, int $alidoId): Builder
    {
        return $q->where('aliado_id', $alidoId);
    }
}
