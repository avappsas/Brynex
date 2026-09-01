<?php

namespace App\Models;

/**
 * Un movimiento del extracto bancario, tal como lo entregó el banco.
 *
 * Regla: estas filas no se editan. Lo único que cambia después de insertarlas
 * es el estado del cruce (`estado_conciliacion`, `consignacion_id`). Si el
 * banco corrige un movimiento, llega como movimiento nuevo con otra huella.
 */
class BancoMovimiento extends BaseModel
{
    protected $table = 'banco_movimientos';

    protected $fillable = [
        'aliado_id', 'banco_cuenta_id', 'proveedor',
        'id_externo', 'huella',
        'fecha', 'fecha_hora', 'tipo', 'valor', 'saldo_despues',
        'descripcion', 'referencia', 'canal',
        'contraparte_nombre', 'contraparte_documento',
        'estado_conciliacion', 'consignacion_id', 'conciliado_por', 'conciliado_at',
        'payload',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_hora' => 'datetime',
        'conciliado_at' => 'datetime',
        'valor' => 'decimal:2',
        'saldo_despues' => 'decimal:2',
    ];

    // ── Tipos de movimiento ──────────────────────────────────────────
    /** Entra plata a la cuenta */
    const TIPO_CREDITO = 'credito';

    /** Sale plata de la cuenta */
    const TIPO_DEBITO = 'debito';

    // ── Estados del cruce contra el libro de BryNex ──────────────────
    /** Todavía nadie lo cruzó */
    const CONCILIACION_PENDIENTE = 'pendiente';

    /** Amarrado a una consignación registrada */
    const CONCILIACION_CONCILIADO = 'conciliado';

    /** Movimiento que no le corresponde al libro (comisiones, 4x1000…) */
    const CONCILIACION_IGNORADO = 'ignorado';

    // ── Relaciones ───────────────────────────────────────────────────
    public function bancoCuenta()
    {
        return $this->belongsTo(BancoCuenta::class, 'banco_cuenta_id');
    }

    public function consignacion()
    {
        return $this->belongsTo(Consignacion::class, 'consignacion_id');
    }

    public function conciliador()
    {
        return $this->belongsTo(User::class, 'conciliado_por');
    }

    // ── Scopes ───────────────────────────────────────────────────────
    /**
     * Siempre por aliado: no hay scope global por `aliado_id` en BaseModel,
     * así que toda consulta de este modelo debe pasar por aquí.
     */
    public function scopeDelAliado($query, int $aliadoId)
    {
        return $query->where('aliado_id', $aliadoId);
    }

    public function scopeEntradas($query)
    {
        return $query->where('tipo', self::TIPO_CREDITO);
    }

    /** Entradas del banco que todavía no se amarran a nada del libro. */
    public function scopeSinIdentificar($query)
    {
        return $query->where('tipo', self::TIPO_CREDITO)
            ->where('estado_conciliacion', self::CONCILIACION_PENDIENTE);
    }
}
