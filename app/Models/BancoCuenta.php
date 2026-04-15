<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BancoCuenta extends Model
{
    protected $table    = 'banco_cuentas';
    protected $fillable = [
        'aliado_id','nombre','nit','banco',
        'tipo_cuenta','numero_cuenta','activo','cobro','observacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'cobro'  => 'boolean',
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
}
