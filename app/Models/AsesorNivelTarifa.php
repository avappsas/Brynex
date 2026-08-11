<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una celda de la plantilla del nivel: lo que gana de afiliación un asesor de ese nivel
 * en (plan, modalidad, nivel de riesgo ARL).
 *
 * Solo guarda la parte del asesor. El precio público, el retiro y los "otros" se heredan de
 * afiliacion_arl_modalidad, y lo del aliado es el resto — ver TarifaAsesorService::desglose().
 */
class AsesorNivelTarifa extends BaseModel
{
    protected $table = 'asesor_nivel_tarifas';

    protected $fillable = [
        'asesor_nivel_id', 'plan_id', 'tipo_modalidad_id', 'nivel_arl', 'afil_asesor',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'tipo_modalidad_id' => 'integer',
        'nivel_arl' => 'integer',
        'afil_asesor' => 'decimal:2',
    ];

    /** Llave de la celda, para indexar colecciones sin repetir el formato por ahí. */
    public static function claveCelda(int $planId, int $modalidadId, int $nivelArl): string
    {
        return "{$planId}_{$modalidadId}_{$nivelArl}";
    }

    public function getClaveAttribute(): string
    {
        return self::claveCelda($this->plan_id, $this->tipo_modalidad_id, $this->nivel_arl);
    }

    // ─── Relaciones ───────────────────────────────────────────────────
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(AsesorNivel::class, 'asesor_nivel_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanContrato::class, 'plan_id');
    }

    public function tipoModalidad(): BelongsTo
    {
        return $this->belongsTo(TipoModalidad::class, 'tipo_modalidad_id');
    }
}
