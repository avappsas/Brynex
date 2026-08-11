<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nivel de asesor: plantilla de comisiones agrupada por tamaño de cartera.
 *
 * El nivel se COPIA al asesor cuando se le asigna (ver Asesor::aplicarNivel) y desde ahí
 * las tarifas del asesor son independientes: editar el nivel no re-aplica sobre quienes ya
 * lo tienen. El rango de contratos solo sirve para sugerir el nivel en pantalla.
 */
class AsesorNivel extends BaseModel
{
    protected $table = 'asesor_niveles';

    protected $fillable = [
        'aliado_id', 'nombre', 'descripcion', 'orden',
        'contratos_min', 'contratos_max', 'admon_asesor', 'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'contratos_min' => 'integer',
        'contratos_max' => 'integer',
        'admon_asesor' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────
    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    public function tarifas(): HasMany
    {
        return $this->hasMany(AsesorNivelTarifa::class, 'asesor_nivel_id');
    }

    public function asesores(): HasMany
    {
        return $this->hasMany(Asesor::class, 'nivel_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────
    public function scopeDelAliado(Builder $q, int $alidoId): Builder
    {
        return $q->where('aliado_id', $alidoId);
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    /** ¿Un asesor con esta cantidad de contratos vigentes cae en este nivel? */
    public function cubreCantidad(int $contratosVigentes): bool
    {
        if ($contratosVigentes < (int) $this->contratos_min) {
            return false;
        }

        // contratos_max null = sin tope
        return $this->contratos_max === null || $contratosVigentes <= (int) $this->contratos_max;
    }

    /** "1 a 10 contratos" / "51 o más contratos" */
    public function rangoLabel(): string
    {
        if ($this->contratos_max === null) {
            return "{$this->contratos_min} o más contratos";
        }

        return "{$this->contratos_min} a {$this->contratos_max} contratos";
    }
}
