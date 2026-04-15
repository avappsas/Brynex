<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $table    = 'gastos';
    protected $fillable = [
        'aliado_id', 'usuario_id', 'cuadre_id',
        'fecha', 'tipo', 'descripcion', 'pagado_a', 'cc_pagado_a',
        'forma_pago', 'banco_origen_id', 'banco_destino_id',
        'valor', 'recibo_caja', 'lugar', 'observacion',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'valor'  => 'integer',
    ];

    // Etiquetas legibles para el frontend
    const TIPOS = [
        'papeleria'            => 'Papelería / útiles',
        'servicios'            => 'Pago servicios',
        'viaticos'             => 'Viáticos / transporte',
        'efectivo_banco'       => 'Efectivo → Banco',
        'banco_banco'          => 'Banco → Banco',
        'nomina'               => 'Pago nómina',
        'transferencia_banco'  => 'Pago desde banco',
        'otro_oficina'         => 'Otro gasto oficina',
        'otro_admin'           => 'Otro gasto admin',
    ];

    // Tipos que solo puede usar admin/superadmin
    const TIPOS_ADMIN = ['banco_banco', 'nomina', 'transferencia_banco', 'otro_admin'];

    // ── Relaciones ────────────────────────────────────────────────────
    public function cuadre()
    {
        return $this->belongsTo(Cuadre::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function bancoOrigen()
    {
        return $this->belongsTo(BancoCuenta::class, 'banco_origen_id');
    }

    public function bancoDestino()
    {
        return $this->belongsTo(BancoCuenta::class, 'banco_destino_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeEfectivo($q)
    {
        return $q->where('forma_pago', 'efectivo');
    }

    public function scopeEnFecha($q, $fecha)
    {
        return $q->whereDate('fecha', $fecha);
    }

    public function scopeDelAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    // ── Helpers ───────────────────────────────────────────────────────
    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function esDeEfectivo(): bool
    {
        return $this->forma_pago === 'efectivo' || $this->tipo === 'efectivo_banco';
    }
}
