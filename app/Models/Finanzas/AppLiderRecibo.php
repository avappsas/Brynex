<?php

namespace App\Models\Finanzas;

use App\Models\User;

class AppLiderRecibo extends BaseFinanzasModel
{
    protected $table = 'finanzas_app_lideres_recibos';

    protected $fillable = [
        'user_id',
        'monto_total',
        'fecha_pago',
        'soporte_path',
        'banco',
        'observacion',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'monto_total' => 'float',
        'fecha_pago' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pagos()
    {
        return $this->hasMany(AppLiderPago::class, 'recibo_id');
    }
}
