<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\Inversion
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string $tipo
 * @property float $monto_invertido_cop
 * @property float|null $cantidad_tokens
 * @property float|null $precio_compra_promedio
 * @property float|null $valor_actual_cop
 * @property bool $activo
 * @property string|null $observaciones
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Inversion extends BaseFinanzasModel
{
    protected $table = 'finanzas_inversiones';

    protected $fillable = [
        'user_id',
        'nombre',
        'tipo', // cripto | trading | otro
        'monto_invertido_cop',
        'cantidad_tokens',
        'precio_compra_promedio',
        'valor_actual_cop',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'monto_invertido_cop' => 'float',
        'cantidad_tokens' => 'float',
        'precio_compra_promedio' => 'float',
        'valor_actual_cop' => 'float',
        'activo' => 'boolean',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los movimientos de inversión
     */
    public function movimientos()
    {
        return $this->hasMany(InversionMovimiento::class, 'inversion_id');
    }

    /**
     * Scope para inversiones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Accessor para ganancia o pérdida neta
     */
    public function getGananciaPerdidaAttribute(): float
    {
        $valorActual = $this->valor_actual_cop ?? 0.00;
        return $valorActual - $this->monto_invertido_cop;
    }
}
