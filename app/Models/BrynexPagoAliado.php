<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrynexPagoAliado extends BaseModel
{
    protected $table = 'brynex_pagos_aliados';

    protected $fillable = [
        'cobro_id', 'valor', 'fecha_pago',
        'banco', 'forma_pago', 'soporte_url',
        'observacion', 'usuario_id',
    ];

    protected $casts = [
        'valor'      => 'decimal:2',
        'fecha_pago' => 'date',
    ];

    const FORMAS_PAGO = [
        'transferencia' => '🏦 Transferencia Bancaria',
        'efectivo'      => '💵 Efectivo',
        'cheque'        => '📝 Cheque',
        'otro'          => '📋 Otro',
    ];

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(BrynexCobroAliado::class, 'cobro_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function getFormaPagoLabelAttribute(): string
    {
        return self::FORMAS_PAGO[$this->forma_pago] ?? ucfirst($this->forma_pago);
    }
}
