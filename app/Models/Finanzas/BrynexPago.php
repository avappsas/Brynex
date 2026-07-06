<?php

namespace App\Models\Finanzas;

use App\Models\User;
use App\Models\Aliado;

/**
 * App\Models\Finanzas\BrynexPago
 *
 * @property int $id
 * @property int $user_id
 * @property int $aliado_id
 * @property int $anio
 * @property int $mes
 * @property float $monto
 * @property string|null $observacion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class BrynexPago extends BaseFinanzasModel
{
    protected $table = 'finanzas_brynex_pagos';

    protected $fillable = [
        'user_id',
        'recibo_id',
        'aliado_id',
        'anio',
        'mes',
        'monto',
        'estado',
        'saldo_pendiente',
        'observacion',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'recibo_id' => 'integer',
        'aliado_id' => 'integer',
        'anio' => 'integer',
        'mes' => 'integer',
        'monto' => 'float',
        'estado' => 'string',
        'saldo_pendiente' => 'float',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el Aliado principal de la base de datos BryNex
     */
    public function aliado()
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    /**
     * Relación con el recibo de pago asociado
     */
    public function recibo()
    {
        return $this->belongsTo(BrynexRecibo::class, 'recibo_id');
    }
}
