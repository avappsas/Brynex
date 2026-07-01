<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrosAdicionalEmpresa extends BaseModel
{
    protected $table = 'cobros_adicionales_empresa';

    protected $fillable = [
        'aliado_id',
        'empresa_id',
        'descripcion',
        'valor',
        'tipo',   // 'unica_vez' | 'recurrente'
        'activo',
    ];

    protected $casts = [
        'valor'  => 'float',
        'activo' => 'boolean',
    ];

    const TIPO_UNICA_VEZ  = 'unica_vez';
    const TIPO_RECURRENTE = 'recurrente';

    // ─── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    /**
     * Solo cobros activos de un aliado y empresa dados.
     */
    public function scopeActivos($query, int $alidoId, int $empresaId)
    {
        return $query->where('aliado_id', $alidoId)
                     ->where('empresa_id', $empresaId)
                     ->where('activo', true);
    }

    /**
     * Solo los de tipo recurrente activos (para mostrar en observación de factura).
     */
    public function scopeRecurrentesActivos($query, int $alidoId, int $empresaId)
    {
        return $query->activos($alidoId, $empresaId)
                     ->where('tipo', self::TIPO_RECURRENTE);
    }
}
