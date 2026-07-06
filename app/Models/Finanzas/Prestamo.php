<?php

namespace App\Models\Finanzas;

use App\Models\User;
use Carbon\Carbon;

/**
 * App\Models\Finanzas\Prestamo
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre_deudor
 * @property string|null $cedula_deudor
 * @property string|null $telefono_deudor
 * @property float $monto_original
 * @property float $tasa_interes_mensual
 * @property string $fecha_desembolso
 * @property string|null $ultimo_corte
 * @property float $saldo_actual
 * @property string $estado
 * @property int $dias_mora_alerta
 * @property bool $alertas_activas
 * @property string|null $soporte_path
 * @property string|null $descripcion
 * @property string|null $observaciones
 * @property bool $es_cuenta_corriente
 * @property string|null $cuenta_corriente_grupo
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Prestamo extends BaseFinanzasModel
{
    protected $table = 'finanzas_prestamos';

    protected $fillable = [
        'user_id',
        'nombre_deudor',
        'cedula_deudor',
        'telefono_deudor',
        'monto_original',
        'tasa_interes_mensual',
        'fecha_desembolso',
        'ultimo_corte',
        'saldo_actual',
        'estado', // activo | pagado | mora | castigado
        'dias_mora_alerta',
        'alertas_activas',
        'soporte_path',
        'descripcion',
        'observaciones',
        'es_cuenta_corriente',
        'cuenta_corriente_grupo',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'monto_original' => 'float',
        'tasa_interes_mensual' => 'float',
        'saldo_actual' => 'float',
        'dias_mora_alerta' => 'integer',
        'alertas_activas' => 'boolean',
        'es_cuenta_corriente' => 'boolean',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los movimientos del préstamo
     */
    public function movimientos()
    {
        return $this->hasMany(PrestamoMovimiento::class, 'prestamo_id');
    }

    /**
     * Scope para préstamos activos
     */
    public function scopeActivos($query)
    {
        return $query->whereIn('estado', ['activo', 'mora']);
    }

    /**
     * Scope para préstamos en mora
     */
    public function scopeEnMora($query)
    {
        return $query->where('estado', 'mora');
    }

    /**
     * Scope para préstamos de tipo cuenta corriente
     */
    public function scopeCuentaCorriente($query)
    {
        return $query->where('es_cuenta_corriente', true);
    }

    /**
     * Accessor para obtener los días de mora a partir del último corte o la fecha de desembolso
     */
    public function getDiasMoraAttribute(): int
    {
        if ($this->estado === 'pagado') {
            return 0;
        }

        $referencia = $this->ultimo_corte ? Carbon::parse($this->ultimo_corte) : Carbon::parse($this->fecha_desembolso);
        $dias = $referencia->diffInDays(Carbon::now(), false);

        return $dias > 0 ? (int) $dias : 0;
    }

    /**
     * Accessor para obtener el total de intereses que se han acumulado y no pagado
     */
    public function getInteresesAcumuladosAttribute(): float
    {
        return max(0.00, $this->saldo_actual - $this->monto_original);
    }
}
