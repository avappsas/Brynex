<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centro de trabajo de una razón social en ARL Sura.
 *
 * Es un caché de lo que responde el portal: no se digita, se trae con
 * `arl:sincronizar-centros`. Cada razón social nombró los suyos a su manera
 * (BRYGAR usa 000RIESGO1 / 000RIESGO3 / 0000000001), así que el único camino
 * confiable de `contratos.n_arl` al `cdSucursal` que exige el afiliar es esta
 * tabla.
 */
class ArlCentroTrabajo extends BaseModel
{
    protected $table = 'arl_centros_trabajo';

    protected $fillable = [
        'aliado_id', 'razon_social_id',
        'codigo_centro', 'nombre_centro', 'nivel_riesgo', 'tasa',
        'cd_actividad', 'municipio_sura', 'departamento', 'municipio',
        'direccion', 'telefono', 'activo', 'sincronizado_at',
    ];

    protected $casts = [
        'nivel_riesgo'    => 'integer',
        'tasa'            => 'decimal:3',
        'activo'          => 'boolean',
        'sincronizado_at' => 'datetime',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function razonSocial(): BelongsTo
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function scopeDelAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    /**
     * El centro que le corresponde a un contrato según su nivel de riesgo.
     * Devuelve null a propósito cuando no hay uno: preferimos que la afiliación
     * se detenga con un mensaje claro antes que mandar a Sura un centro que no
     * corresponde al riesgo real del trabajador.
     */
    public static function paraRiesgo(int $razonSocialId, int $nivelRiesgo): ?self
    {
        return static::where('razon_social_id', $razonSocialId)
            ->where('nivel_riesgo', $nivelRiesgo)
            ->where('activo', true)
            ->orderBy('codigo_centro')
            ->first();
    }
}
