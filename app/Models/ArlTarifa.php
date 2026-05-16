<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArlTarifa extends BaseModel
{
    protected $table = 'arl_tarifas';
    protected $fillable = ['aliado_id', 'nivel', 'porcentaje', 'descripcion'];
    protected $casts = ['porcentaje' => 'decimal:4'];

    /** Cache en memoria: evita N queries cuando se llama en bucles por contrato */
    private static array $cache = [];

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
        $key = "$alidoId:$nivel";
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $tarifa = null;
        if ($alidoId) {
            $tarifa = static::where('aliado_id', $alidoId)
                ->where('nivel', $nivel)
                ->value('porcentaje');
        }

        if ($tarifa === null) {
            // Global
            $tarifa = static::whereNull('aliado_id')
                ->where('nivel', $nivel)
                ->value('porcentaje') ?? 0.0;
        }

        return self::$cache[$key] = (float) $tarifa;
    }

    /** Limpia el cache (útil en tests) */
    public static function limpiarCache(): void
    {
        self::$cache = [];
    }
}
