<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ComisionAsesor extends Model
{
    protected $table = 'comisiones_asesores';

    protected $fillable = [
        'aliado_id',
        'asesor_id',
        'contrato_ref',
        'tipo',
        'periodo',
        'valor_base',
        'tipo_calculo',
        'valor_comision',
        'pagado',
        'fecha_pago',
        'observacion',
    ];

    protected $casts = [
        'pagado'         => 'boolean',
        'periodo'        => 'date',
        'fecha_pago'     => 'date',
        'valor_base'     => 'decimal:2',
        'valor_comision' => 'decimal:2',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────
    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class, 'asesor_id');
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    // ─── Factory: crear comisión de afiliación ────────────────────────
    public static function crearAfiliacion(
        Asesor $asesor,
        string $contratoRef,
        float  $valorContrato,
        ?Carbon $periodo = null
    ): self {
        $periodo ??= Carbon::now()->startOfMonth();
        $valorComision = $asesor->calcularComisionAfiliacion($valorContrato);

        return self::create([
            'aliado_id'      => $asesor->aliado_id,
            'asesor_id'      => $asesor->id,
            'contrato_ref'   => $contratoRef,
            'tipo'           => 'afiliacion',
            'periodo'        => $periodo,
            'valor_base'     => $valorContrato,
            'tipo_calculo'   => $asesor->comision_afil_tipo,
            'valor_comision' => $valorComision,
            'pagado'         => false,
        ]);
    }

    // ─── Factory: crear comisión de administración mensual ────────────
    public static function crearAdmon(
        Asesor $asesor,
        string $contratoRef,
        float  $valorAdmon,
        Carbon $periodo
    ): self {
        $valorComision = $asesor->calcularComisionAdmon($valorAdmon);

        return self::create([
            'aliado_id'      => $asesor->aliado_id,
            'asesor_id'      => $asesor->id,
            'contrato_ref'   => $contratoRef,
            'tipo'           => 'administracion',
            'periodo'        => $periodo->startOfMonth()->copy(),
            'valor_base'     => $valorAdmon,
            'tipo_calculo'   => $asesor->comision_admon_tipo,
            'valor_comision' => $valorComision,
            'pagado'         => false,
        ]);
    }

    // ─── Scope: por periodo (mes/año) ─────────────────────────────────
    public function scopeDelPeriodo($query, int $anio, int $mes)
    {
        $inicio = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $fin    = $inicio->copy()->endOfMonth();
        return $query->whereBetween('periodo', [$inicio, $fin]);
    }

    // ─── Helper: label del periodo ────────────────────────────────────
    public function periodoLabel(): string
    {
        return Carbon::parse($this->periodo)->locale('es')->isoFormat('MMMM [de] YYYY');
    }

    // ─── Helper: tipo con badge color ────────────────────────────────
    public function tipoLabel(): string
    {
        return $this->tipo === 'afiliacion' ? 'Afiliación' : 'Administración';
    }

    public function tipoColor(): string
    {
        return $this->tipo === 'afiliacion' ? '#8b5cf6' : '#0891b2';
    }
}
