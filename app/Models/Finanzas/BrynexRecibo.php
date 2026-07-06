<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\BrynexRecibo
 *
 * @property int $id
 * @property int $user_id
 * @property float $monto_total
 * @property \Carbon\Carbon $fecha_pago
 * @property string|null $soporte_path
 * @property string|null $banco
 * @property string|null $observacion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class BrynexRecibo extends BaseFinanzasModel
{
    protected $table = 'finanzas_brynex_recibos';

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
        return $this->hasMany(BrynexPago::class, 'recibo_id');
    }
}
