<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catálogo de cargos de una razón social, cada uno con su nivel de riesgo.
 *
 * Los cargos dependen de la actividad económica de la razón social, por eso
 * cuelgan de ella. Elegir el cargo en el contrato resuelve de paso el nivel de
 * riesgo y, con él, el centro de trabajo en [[ArlCentroTrabajo]].
 */
class RazonSocialCargo extends BaseModel
{
    protected $table = 'razon_social_cargos';

    protected $fillable = [
        'aliado_id', 'razon_social_id', 'cargo', 'codigo_ocupacion',
        'nivel_riesgo', 'por_defecto', 'activo',
    ];

    protected $casts = [
        'nivel_riesgo' => 'integer',
        'por_defecto'  => 'boolean',
        'activo'       => 'boolean',
    ];

    public function razonSocial(): BelongsTo
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function scopeDelAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true)->orderBy('nivel_riesgo')->orderBy('cargo');
    }

    /**
     * Cargos visibles para una razón social: los suyos y los del catálogo común
     * (`razon_social_id` en NULL). Los propios van primero.
     */
    public function scopeVisiblesPara($q, ?int $razonSocialId)
    {
        return $q->where(function ($w) use ($razonSocialId) {
            $w->whereNull('razon_social_id');
            if ($razonSocialId) {
                $w->orWhere('razon_social_id', $razonSocialId);
            }
        });
    }

    /** El cargo sugerido para un nivel de riesgo, cuando el contrato no trae uno. */
    public static function porDefecto(int $razonSocialId, int $nivelRiesgo): ?self
    {
        return static::visiblesPara($razonSocialId)
            ->where('nivel_riesgo', $nivelRiesgo)
            ->where('activo', true)
            // Un cargo propio de la razón social manda sobre el genérico.
            ->orderByRaw('CASE WHEN razon_social_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('por_defecto')
            ->first();
    }
}
