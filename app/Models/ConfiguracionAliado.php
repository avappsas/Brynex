<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionAliado extends BaseModel
{
    protected $table = 'configuracion_aliado';
    protected $fillable = [
        'aliado_id', 'plan_id',
        'administracion', 'costo_afiliacion', 'admon_asesor',
        'seguro_valor', 'encargado_default_id', 'activo',
        'dist_admon_pct', 'dist_retiro_pct',
    ];
    protected $casts = [
        'administracion'   => 'decimal:2',
        'costo_afiliacion' => 'decimal:2',
        'admon_asesor'     => 'decimal:2',
        'seguro_valor'     => 'decimal:2',
        'dist_admon_pct'   => 'decimal:2',
        'dist_retiro_pct'  => 'decimal:2',
        'activo'           => 'boolean',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanContrato::class, 'plan_id');
    }

    public function encargadoDefault(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encargado_default_id');
    }

    /**
     * Obtiene la configuración del aliado para un plan dado.
     * Si no existe configuración específica para el plan, devuelve la genérica (plan_id null).
     */
    public static function paraAliado(int $alidoId, ?int $planId = null): ?static
    {
        // Primero buscar específica por plan
        if ($planId) {
            $cfg = static::where('aliado_id', $alidoId)
                ->where('plan_id', $planId)
                ->where('activo', true)
                ->first();
            if ($cfg) return $cfg;
        }

        // Luego la genérica sin plan
        return static::where('aliado_id', $alidoId)
            ->whereNull('plan_id')
            ->where('activo', true)
            ->first();
    }

    /**
     * Calcula la distribución del costo de afiliación.
     * Retorna [admon, asesor, retiro, utilidad] en pesos.
     */
    public function calcularDistribucion(int $totalAfil, ?Asesor $asesor = null): array
    {
        $admon  = (int) round($totalAfil * (float)($this->dist_admon_pct  ?? 0) / 100);
        $retiro = (int) round($totalAfil * (float)($this->dist_retiro_pct ?? 0) / 100);

        // Comisión del asesor
        $asesorVal = 0;
        if ($asesor) {
            if ($asesor->comision_afil_tipo === 'porcentaje') {
                $asesorVal = (int) round($totalAfil * (float)$asesor->comision_afil_valor / 100);
            } else {
                $asesorVal = (int)$asesor->comision_afil_valor;
            }
        }

        $utilidad = max(0, $totalAfil - $admon - $asesorVal - $retiro);

        return [
            'admon'    => $admon,
            'asesor'   => $asesorVal,
            'retiro'   => $retiro,
            'utilidad' => $utilidad,
        ];
    }
}
