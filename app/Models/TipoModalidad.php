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
        'id', 'tipo_modalidad', 'observacion', 'descripcion', 'orden', 'modalidad', 'activo',
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

    /** IDs que corresponden a modalidades independientes (I Venc=10, UPC=13, En el Exterior=14) */
    const IDS_INDEPENDIENTE = [10, 13, 14];

    /** UPC: afiliar a alguien fuera del núcleo familiar — no depende del salario */
    const ID_UPC = 13;

    /** IDs que requieren el campo "Modo ARL" */
    const IDS_MODO_ARL = [10, -1];

    /** IDs en que la ARL es libre (no bloqueada a la razon social) */
    const IDS_ARL_LIBRE = [10, -1, 8];

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

    /** Fracción del SMMLV según los días AFP del plan de Tiempo Parcial */
    const FACTOR_SALARIO_POR_DIAS = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];

    /**
     * Fracción del salario mínimo que corresponde a esta modalidad.
     * Tiempo completo → 1.0; Tiempo Parcial → 0.25 / 0.50 / 0.75 según días AFP.
     */
    public function factorSalario(): float
    {
        if (! $this->esTiempoParcial()) {
            return 1.0;
        }
        return self::FACTOR_SALARIO_POR_DIAS[$this->dias_afp] ?? 1.0;
    }

    /**
     * Salario mínimo legal que puede tener un contrato en esta modalidad.
     * UPC (13) no depende del salario: no tiene piso.
     */
    public function salarioMinimoPermitido(): float
    {
        // (int) obligatorio: el id no es IDENTITY y llega como string
        if ((int) $this->id === self::ID_UPC) {
            return 0.0;
        }
        return round(ConfiguracionBrynex::salarioMinimo() * $this->factorSalario());
    }
}
