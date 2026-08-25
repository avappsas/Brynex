<?php

namespace App\Models\Finanzas;

use App\Models\User;

/**
 * App\Models\Finanzas\CuentaCorrienteCliente
 *
 * Cliente recurrente al que se le hacen trabajos a crédito (ej. "Oficina Arroyave").
 * Su saldo no se guarda: es la suma de los saldos de sus trabajos pendientes.
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string|null $cedula
 * @property string|null $telefono
 * @property float $tasa_interes_mensual
 * @property int $dias_mora_alerta
 * @property bool $alertas_activas
 * @property string|null $notas
 * @property bool $activo
 */
class CuentaCorrienteCliente extends BaseFinanzasModel
{
    protected $table = 'finanzas_cc_clientes';

    protected $fillable = [
        'user_id',
        'nombre',
        'cedula',
        'telefono',
        'tasa_interes_mensual',
        'dias_mora_alerta',
        'alertas_activas',
        'notas',
        'activo',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tasa_interes_mensual' => 'float',
        'dias_mora_alerta' => 'integer',
        'alertas_activas' => 'boolean',
        'activo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Trabajos del cliente. Cada trabajo es un `Prestamo` de cuenta corriente,
     * para reusar la liquidación de intereses, los movimientos y los abonos.
     */
    public function trabajos()
    {
        return $this->hasMany(Prestamo::class, 'cc_cliente_id')->where('es_cuenta_corriente', true);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Trabajos que todavía deben plata.
     */
    public function trabajosPendientes()
    {
        return $this->trabajos()->whereIn('estado', ['activo', 'mora']);
    }
}
