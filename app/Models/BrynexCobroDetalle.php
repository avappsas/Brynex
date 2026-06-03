<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrynexCobroDetalle extends BaseModel
{
    protected $table = 'brynex_cobros_detalle';

    protected $fillable = [
        'cobro_id', 'modulo_id', 'descripcion',
        'cant_unidades', 'tarifa_unidad',
        'tarifa_minima_aplicada', 'subtotal',
    ];

    protected $casts = [
        'cant_unidades'          => 'integer',
        'tarifa_unidad'          => 'decimal:2',
        'tarifa_minima_aplicada' => 'decimal:2',
        'subtotal'               => 'decimal:2',
    ];

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(BrynexCobroAliado::class, 'cobro_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(BrynexModulo::class, 'modulo_id');
    }
}
