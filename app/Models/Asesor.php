<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Asesor extends Model
{
    use SoftDeletes;

    protected $table = 'asesores';

    protected $fillable = [
        'aliado_id',
        'cedula',
        'nombre',
        'telefono',
        'celular',
        'correo',
        'direccion',
        'ciudad',
        'departamento',
        'cuenta_bancaria',
        'comision_afil_tipo',
        'comision_afil_valor',
        'comision_admon_tipo',
        'comision_admon_valor',
        'fecha_ingreso',
        'activo',
        'id_original_access',
    ];

    protected $casts = [
        'activo'                => 'boolean',
        'fecha_ingreso'         => 'date',
        'comision_afil_valor'   => 'decimal:2',
        'comision_admon_valor'  => 'decimal:2',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────
    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    public function comisiones(): HasMany
    {
        return $this->hasMany(ComisionAsesor::class, 'asesor_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────
    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function scopeDelAliado(Builder $q, int $alidoId): Builder
    {
        return $q->where('aliado_id', $alidoId);
    }

    // ─── Lógica de cálculo de comisiones ─────────────────────────────

    /**
     * Calcula la comisión de afiliación dado el valor del contrato.
     * Si tipo = 'fijo' → retorna el valor fijo del asesor.
     * Si tipo = 'porcentaje' → retorna el % del valor del contrato.
     */
    public function calcularComisionAfiliacion(float $valorContrato = 0): float
    {
        if ($this->comision_afil_tipo === 'porcentaje') {
            return round($valorContrato * ($this->comision_afil_valor / 100), 2);
        }
        return (float) $this->comision_afil_valor;
    }

    /**
     * Calcula la comisión de administración mensual dado el valor de la cuota.
     */
    public function calcularComisionAdmon(float $valorAdmon = 0): float
    {
        if ($this->comision_admon_tipo === 'porcentaje') {
            return round($valorAdmon * ($this->comision_admon_valor / 100), 2);
        }
        return (float) $this->comision_admon_valor;
    }

    // ─── Helpers de presentación ─────────────────────────────────────
    public function comisionAfiliacionLabel(): string
    {
        if ($this->comision_afil_tipo === 'porcentaje') {
            return "{$this->comision_afil_valor}%";
        }
        return '$' . number_format($this->comision_afil_valor, 0, ',', '.');
    }

    public function comisionAdmonLabel(): string
    {
        if ($this->comision_admon_tipo === 'porcentaje') {
            return "{$this->comision_admon_valor}%";
        }
        return '$' . number_format($this->comision_admon_valor, 0, ',', '.');
    }

    // ─── Totales pendientes de pago ───────────────────────────────────
    public function totalPendiente(): float
    {
        return (float) $this->comisiones()->where('pagado', false)->sum('valor_comision');
    }

    public function totalPagado(): float
    {
        return (float) $this->comisiones()->where('pagado', true)->sum('valor_comision');
    }
}
