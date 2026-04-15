<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArlTarifa extends Model
{
    protected $table = 'arl_tarifas';
    protected $fillable = ['aliado_id', 'nivel', 'porcentaje', 'descripcion'];
    protected $casts = ['porcentaje' => 'decimal:4'];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    /**
     * Obtiene el porcentaje de ARL para un nivel dado.
     * Primero busca tarifa del aliado; si no existe, usa la global (aliado_id null).
     */
    public static function porcentajePara(int $nivel, ?int $alidoId = null): float
    {
        if ($alidoId) {
            $tarifa = static::where('aliado_id', $alidoId)
                ->where('nivel', $nivel)
                ->value('porcentaje');
            if ($tarifa !== null) return (float) $tarifa;
        }

        // Global
        return (float) static::whereNull('aliado_id')
            ->where('nivel', $nivel)
            ->value('porcentaje') ?? 0.0;
    }
}
