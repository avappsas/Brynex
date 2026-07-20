<?php

namespace App\Models\Finanzas;

/**
 * App\Models\Finanzas\CuentaTransferencia
 *
 * Movimiento de dinero entre dos cuentas del mismo usuario.
 * No cuenta como gasto ni como entrada: solo cambia dónde está la plata.
 */
class CuentaTransferencia extends BaseFinanzasModel
{
    protected $table = 'finanzas_cuenta_transferencias';

    protected $fillable = [
        'user_id',
        'cuenta_origen_id',
        'cuenta_destino_id',
        'fecha',
        'monto',
        'observacion',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'cuenta_origen_id' => 'integer',
        'cuenta_destino_id' => 'integer',
        'monto' => 'float',
    ];

    public function origen()
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_origen_id');
    }

    public function destino()
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_destino_id');
    }
}
