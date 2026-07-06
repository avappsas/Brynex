<?php

namespace App\Models\Finanzas;

use App\Models\User;

class AppLiderPago extends BaseFinanzasModel
{
    protected $table = 'finanzas_app_lideres_pagos';

    protected $fillable = [
        'user_id',
        'app_lider_aliado_id',
        'recibo_id',
        'anio',
        'mes',
        'monto',
        'estado',
        'saldo_pendiente',
        'observacion',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'app_lider_aliado_id' => 'integer',
        'recibo_id' => 'integer',
        'anio' => 'integer',
        'mes' => 'integer',
        'monto' => 'float',
        'estado' => 'string',
        'saldo_pendiente' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aliado()
    {
        return $this->belongsTo(AppLiderAliado::class, 'app_lider_aliado_id');
    }

    public function recibo()
    {
        return $this->belongsTo(AppLiderRecibo::class, 'recibo_id');
    }

    /**
     * Accesor para obtener la URL de descarga del soporte.
     */
    public function getSoporteUrlAttribute()
    {
        if ($this->recibo && $this->recibo->soporte_path) {
            return route('finanzas.app-lideres.descargar-soporte', $this->recibo->id);
        }
        return null;
    }
}
