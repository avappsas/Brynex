<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\Patrimonio
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string $categoria
 * @property float $valor_compra
 * @property string $fecha_adquisicion
 * @property float|null $valor_actual
 * @property string|null $observaciones
 * @property bool $activo
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Patrimonio extends BaseFinanzasModel
{
    protected $table = 'finanzas_patrimonio';

    protected $fillable = [
        'user_id',
        'nombre',
        'categoria', // inmueble | vehiculo | electronico | joya | otro
        'valor_compra',
        'fecha_adquisicion',
        'valor_actual',
        'valor_actual_fecha',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'valor_compra' => 'float',
        'valor_actual' => 'float',
        'activo' => 'boolean',
    ];

    /**
     * Cuánto pierde (o gana) de valor al año cada tipo de bien, en tanto por uno.
     *
     * El inmueble va en cero a propósito: un apartamento no sigue una fórmula, y
     * un avalúo real vale más que cualquier tasa. Se actualiza a mano y ahí se
     * queda hasta el siguiente. Un número negativo valorizaría.
     */
    public const DEVALUACION_ANUAL = [
        'inmueble' => 0.00,
        'vehiculo' => 0.12,
        'electronico' => 0.30,
        'joya' => 0.00,
        'otro' => 0.10,
    ];

    /**
     * Lo que vale hoy: el último valor que se supo, devaluado por los días
     * transcurridos desde que se supo. Escribir un `valor_actual` nuevo reinicia
     * el conteo, así que un avalúo siempre manda sobre la fórmula.
     */
    public function getValorEstimadoAttribute(): float
    {
        $base = $this->valor_actual ?? $this->valor_compra;
        $desde = $this->valor_actual_fecha ?? $this->fecha_adquisicion;
        $tasa = self::DEVALUACION_ANUAL[$this->categoria] ?? 0.00;

        if (! $desde || $tasa == 0.0 || $base <= 0) {
            return round((float) $base, 2);
        }

        $anios = \Carbon\Carbon::parse($desde)->startOfDay()->diffInDays(now()->startOfDay(), false) / 365;

        if ($anios <= 0) {
            return round((float) $base, 2);
        }

        // Compuesto y por días: un bien de seis meses pierde media tasa, no la
        // tasa entera ni cero.
        return round((float) $base * ((1 - $tasa) ** $anios), 2);
    }

    /**
     * Diferencia contra lo que costó. Negativa cuando el bien se devaluó.
     */
    public function getDiferenciaValorAttribute(): float
    {
        return round($this->valor_estimado - (float) $this->valor_compra, 2);
    }

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los gastos de mantenimiento/impuestos del patrimonio
     */
    public function gastos()
    {
        return $this->hasMany(PatrimonioGasto::class, 'patrimonio_id');
    }

    /**
     * Scope para bienes activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Accessor para el total gastado en este bien (impuestos, seguros, mantenimiento, etc.)
     */
    public function getValorTotalGastosAttribute(): float
    {
        return (float) $this->gastos()->sum('monto');
    }
}
