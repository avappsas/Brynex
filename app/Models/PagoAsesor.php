<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoAsesor extends BaseModel
{
    protected $table = 'pagos_asesores';

    protected $fillable = [
        'aliado_id',
        'asesor_id',
        'valor',
        'fecha',
        'tipo',           // 'efectivo' | 'banco'
        'banco_cuenta_id',
        'periodo_mes',
        'periodo_anio',
        'observacion',
        'usuario_id',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'valor'  => 'integer',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────
    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class, 'asesor_id');
    }

    public function bancoCuenta(): BelongsTo
    {
        return $this->belongsTo(BancoCuenta::class, 'banco_cuenta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────
    public function scopeDelAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    public function scopePeriodo($q, int $mes, int $anio)
    {
        return $q->where('periodo_mes', $mes)->where('periodo_anio', $anio);
    }

    // ─── Total pagado a un asesor (todos los períodos desde mayo 2025) ─
    public static function totalPagadoAsesor(int $aliadoId, int $asesorId): int
    {
        return (int) static::where('aliado_id', $aliadoId)
            ->where('asesor_id', $asesorId)
            ->sum('valor');
    }
}
