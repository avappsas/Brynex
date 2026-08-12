<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PautaConfig extends BaseModel
{
    protected $table = 'pauta_config';

    protected $fillable = [
        'aliado_id',
        'activo',
        'ad_account_id',
        'access_token_ads',
        'audiencias',
        'audiencias_sync_at',
        'limite_mensual_cop',
        'presupuesto_diario_default_cop',
        'ciudades',
        'ciudades_claves',
        'edad_min',
        'edad_max',
        'presupuesto_semanal_cop',
        'meta_campana_permanente_id',
        'meta_adset_permanente_id',
        'creatividades_max',
        'piezas_semana_max',
    ];

    protected $casts = [
        'activo'                          => 'boolean',
        'limite_mensual_cop'              => 'decimal:2',
        'presupuesto_diario_default_cop'  => 'decimal:2',
        'audiencias'                      => 'array',
        'audiencias_sync_at'              => 'datetime',
        'ciudades'                        => 'array',
        'ciudades_claves'                 => 'array',
        'edad_min'                        => 'integer',
        'edad_max'                        => 'integer',
        'presupuesto_semanal_cop'         => 'decimal:2',
        'creatividades_max'               => 'integer',
        'piezas_semana_max'               => 'integer',
    ];

    /** Techo duro de gasto diario. Ni el ajuste automático ni el panel pueden pasarse de aquí. */
    public const TOPE_DIARIO_COP = 15000.0;

    /**
     * Presupuesto diario del conjunto permanente, derivado del semanal.
     *
     * Meta cobra por día, no por semana: el semanal es como el dueño piensa el gasto, y esta
     * es su traducción. Se recorta al tope diario para que subir el semanal por descuido no
     * pueda desbordar el límite de seguridad.
     */
    public function presupuestoDiarioCop(): float
    {
        $semanal = (float) ($this->presupuesto_semanal_cop ?: 0);
        if ($semanal <= 0) {
            return min((float) $this->presupuesto_diario_default_cop, self::TOPE_DIARIO_COP);
        }

        return min(round($semanal / 7), self::TOPE_DIARIO_COP);
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    // ── Token de pauta, cifrado (mismo patrón que RedSocialConfig) ───────────────────────

    public function setAccessTokenAdsAttribute(?string $value): void
    {
        $this->attributes['access_token_ads'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAdsAttribute(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function paraAliado(int $aliadoId): static
    {
        return static::firstOrCreate(['aliado_id' => $aliadoId], ['activo' => false]);
    }

    /** Cuánto se ha comprometido/gastado este mes calendario en piezas de este aliado (borrador+activa+pausada+finalizada). */
    public function gastadoEsteMes(): float
    {
        return (float) Publicacion::where('aliado_id', $this->aliado_id)
            ->whereNotNull('pauta_estado')
            ->where('pauta_activada_at', '>=', now()->startOfMonth())
            ->sum('pauta_gasto_total_cop');
    }

    /** Presupuesto diario disponible sin pasarse del tope mensual, dado lo ya gastado. */
    public function disponibleEsteMes(): float
    {
        if ($this->limite_mensual_cop === null) {
            return 0;
        }
        return max(0, (float) $this->limite_mensual_cop - $this->gastadoEsteMes());
    }
}
