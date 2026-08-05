<?php

namespace App\Models;

use App\Models\BaseModel;

class BancoCuenta extends BaseModel
{
    protected $table    = 'banco_cuentas';
    protected $fillable = [
        'aliado_id','nombre','nit','banco',
        'tipo_cuenta','numero_cuenta','activo','cobro','facturacion','incapacidad',
        'observacion', 'llave',
    ];

    protected $casts = [
        'activo'      => 'boolean',
        'cobro'       => 'boolean',
        'facturacion' => 'boolean',
        'incapacidad' => 'boolean',
    ];

    public function getEtiquetaAttribute(): string
    {
        return "{$this->banco} — {$this->nombre} | {$this->tipo_cuenta} {$this->numero_cuenta}";
    }

    public static function activas(int $aliadoId)
    {
        return static::where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->orderBy('banco')
            ->get();
    }

    /** Cuentas marcadas para aparecer en la Cuenta de Cobro */
    public static function paraCobro(int $aliadoId)
    {
        return static::where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->where('cobro', true)
            ->orderBy('banco')
            ->get();
    }

    /**
     * Cuentas que pueden escogerse al facturar y al registrar movimientos
     * internos (gastos, comisiones, préstamos, cuadre diario, planos).
     */
    public static function paraFacturacion(int $aliadoId)
    {
        return static::where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->where('facturacion', true)
            ->orderBy('banco')
            ->get();
    }

    /**
     * Cuentas marcadas como destino de entradas de incapacidades.
     * Es una marca informativa: el selector de Incapacidades sigue
     * resolviéndose por el NIT de la razón social, no por esta bandera.
     */
    public static function paraIncapacidad(int $aliadoId)
    {
        return static::where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->where('incapacidad', true)
            ->orderBy('banco')
            ->get();
    }
}
