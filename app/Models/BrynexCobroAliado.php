<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrynexCobroAliado extends BaseModel
{
    protected $table = 'brynex_cobros_aliados';

    protected $fillable = [
        'aliado_id', 'mes', 'anio',
        'estado', 'total_cobrado', 'total_pagado',
        'fecha_cierre',
    ];

    protected $casts = [
        'mes'           => 'integer',
        'anio'          => 'integer',
        'total_cobrado' => 'decimal:2',
        'total_pagado'  => 'decimal:2',
        'fecha_cierre'  => 'datetime',
    ];

    // ── Estados ─────────────────────────────────────────────────────────────
    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_PARCIAL   = 'parcial';
    const ESTADO_PAGADO    = 'pagado';

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(BrynexCobroDetalle::class, 'cobro_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(BrynexPagoAliado::class, 'cobro_id')->orderBy('fecha_pago');
    }

    // ── Helpers de negocio ───────────────────────────────────────────────────

    /** Saldo pendiente por pagar */
    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->total_cobrado - (float) $this->total_pagado);
    }

    /** Porcentaje pagado */
    public function getPorcentajePagadoAttribute(): float
    {
        if ((float) $this->total_cobrado <= 0) return 100.0;
        return round(((float) $this->total_pagado / (float) $this->total_cobrado) * 100, 1);
    }

    /** Etiqueta de estado para la UI */
    public function getEtiquetaEstadoAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_PENDIENTE => '⏳ Pendiente',
            self::ESTADO_PARCIAL   => '💛 Parcial',
            self::ESTADO_PAGADO    => '✅ Pagado',
            default                => ucfirst($this->estado),
        };
    }

    /** Color para badges de UI */
    public function getColorEstadoAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_PENDIENTE => '#dc2626',
            self::ESTADO_PARCIAL   => '#d97706',
            self::ESTADO_PAGADO    => '#16a34a',
            default                => '#64748b',
        };
    }

    /**
     * Nombre legible del período (ej: "Mayo 2026")
     */
    public function getPeriodoAttribute(): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return ($meses[$this->mes] ?? $this->mes) . ' ' . $this->anio;
    }

    /**
     * Recalcula y actualiza total_pagado y estado basado en los pagos registrados.
     * Llamar después de cada nuevo pago.
     */
    public function recalcularEstado(): void
    {
        $totalPagado = (float) $this->pagos()->sum('valor');
        $totalCobrado = (float) $this->total_cobrado;

        $estado = self::ESTADO_PENDIENTE;
        if ($totalPagado >= $totalCobrado && $totalCobrado > 0) {
            $estado = self::ESTADO_PAGADO;
        } elseif ($totalPagado > 0) {
            $estado = self::ESTADO_PARCIAL;
        }

        $this->update([
            'total_pagado' => $totalPagado,
            'estado'       => $estado,
        ]);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', [self::ESTADO_PENDIENTE, self::ESTADO_PARCIAL]);
    }

    public function scopePeriodo($query, int $mes, int $anio)
    {
        return $query->where('mes', $mes)->where('anio', $anio);
    }

    public function scopeDelAliado($query, int $alidoId)
    {
        return $query->where('aliado_id', $alidoId);
    }
}
