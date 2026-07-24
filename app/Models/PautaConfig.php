<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PautaConfig extends BaseModel
{
    protected $table = 'pauta_config';

    protected $fillable = [
        'aliado_id',
        'activo',
        'ad_account_id',
        'limite_mensual_cop',
        'presupuesto_diario_default_cop',
    ];

    protected $casts = [
        'activo'                          => 'boolean',
        'limite_mensual_cop'              => 'decimal:2',
        'presupuesto_diario_default_cop'  => 'decimal:2',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
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
