<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\Entrada
 *
 * @property int $id
 * @property int $user_id
 * @property int $fuente_id
 * @property int $anio
 * @property int $mes
 * @property float $monto
 * @property string|null $observacion
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Entrada extends BaseFinanzasModel
{
    protected $table = 'finanzas_entradas';

    protected $fillable = [
        'user_id',
        'fuente_id',
        'anio',
        'mes',
        'monto',
        'observacion',
        'cuenta_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'fuente_id' => 'integer',
        'anio' => 'integer',
        'mes' => 'integer',
        'monto' => 'float',
        'cuenta_id' => 'integer',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_id');
    }

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con la fuente de ingreso
     */
    public function fuente()
    {
        return $this->belongsTo(FuenteIngreso::class, 'fuente_id');
    }

    /**
     * Scope para filtrar por año y mes
     */
    public function scopeDelMes($query, $anio, $mes)
    {
        return $query->where('anio', $anio)->where('mes', $mes);
    }
}
