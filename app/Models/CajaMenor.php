<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMenor extends Model
{
    protected $table    = 'caja_menor';
    protected $fillable = [
        'aliado_id', 'usuario_id', 'monto', 'fecha',
        'asignado_por', 'activo', 'observacion',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'monto'  => 'integer',
        'activo' => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function asignadoPor()
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeActiva($q)  { return $q->where('activo', true); }

    public function scopeDelAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    // ── Helper estático ──────────────────────────────────────────────
    /** Retorna el monto de caja menor activo para un usuario */
    public static function montoActivo(int $aliadoId, int $usuarioId): int
    {
        return (int) static::where('aliado_id', $aliadoId)
            ->where('usuario_id', $usuarioId)
            ->where('activo', true)
            ->orderByDesc('fecha')
            ->value('monto') ?? 0;
    }
}
