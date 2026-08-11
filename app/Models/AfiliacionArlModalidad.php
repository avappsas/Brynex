<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfiliacionArlModalidad extends BaseModel
{
    protected $table = 'afiliacion_arl_modalidad';

    protected $fillable = [
        'aliado_id', 'plan_id', 'tipo_modalidad_id', 'nivel_arl',
        'costo_afiliacion', 'administracion', 'retiro', 'otros',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'tipo_modalidad_id' => 'integer',
        'nivel_arl' => 'integer',
        'costo_afiliacion' => 'decimal:2',
        'administracion' => 'decimal:2',
        'retiro' => 'decimal:2',
        'otros' => 'decimal:2',
    ];

    /**
     * Los cuatro valores son nullable y su null significa "usa el respaldo", no "vale 0":
     * costo_afiliacion y administracion caen a configuracion_aliado (plan → global), retiro
     * cae al porcentaje dist_retiro_pct y otros cae a 0. Resolverlos siempre por
     * TarifaAsesorService, nunca leyendo la columna directo.
     */
    public function estaVacia(): bool
    {
        return $this->costo_afiliacion === null
            && $this->administracion === null
            && $this->retiro === null
            && $this->otros === null;
    }

    /** Llave de la celda — misma convención que la matriz de asesores. */
    public function getClaveAttribute(): string
    {
        return AsesorNivelTarifa::claveCelda($this->plan_id, $this->tipo_modalidad_id, $this->nivel_arl);
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
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
