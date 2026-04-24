<?php

namespace App\Models;

use App\Models\BaseModel;

class SaldoBanco extends BaseModel
{
    protected $table    = 'saldos_banco';
    protected $fillable = [
        'aliado_id', 'banco_cuenta_id', 'fecha', 'tipo',
        'descripcion', 'cuadre_id', 'gasto_id', 'factura_id',
        'usuario_id', 'valor', 'saldo_acumulado',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'valor'             => 'integer',
        'saldo_acumulado'   => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────
    public function bancoCuenta()
    {
        return $this->belongsTo(BancoCuenta::class, 'banco_cuenta_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cuadre()
    {
        return $this->belongsTo(Cuadre::class);
    }

    public function gasto()
    {
        return $this->belongsTo(Gasto::class);
    }

    // ── Helper estático ──────────────────────────────────────────────
    /** Saldo actual de una cuenta bancaria */
    public static function saldoActual(int $aliadoId, int $bancoCuentaId): int
    {
        return (int) static::where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $bancoCuentaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('saldo_acumulado') ?? 0;
    }

    /** Registra un movimiento y actualiza saldo_acumulado */
    public static function registrarMovimiento(array $data): static
    {
        $saldoAnterior = static::saldoActual($data['aliado_id'], $data['banco_cuenta_id']);

        $esSalida = in_array($data['tipo'], ['salida', 'transferencia_salida']);
        $nuevoSaldo = $esSalida
            ? $saldoAnterior - abs($data['valor'])
            : $saldoAnterior + abs($data['valor']);

        return static::create(array_merge($data, ['saldo_acumulado' => $nuevoSaldo]));
    }
}
