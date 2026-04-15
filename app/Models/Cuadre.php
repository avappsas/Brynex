<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuadre extends Model
{
    protected $table    = 'cuadres';
    protected $fillable = [
        'aliado_id', 'usuario_id', 'fecha_inicio', 'fecha_fin',
        'estado', 'saldo_apertura', 'saldo_cierre', 'cerrado_por', 'observacion',
    ];

    protected $casts = [
        'fecha_inicio'   => 'date',
        'fecha_fin'      => 'date',
        'saldo_apertura' => 'integer',
        'saldo_cierre'   => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cerradoPor()
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function gastos()
    {
        return $this->hasMany(Gasto::class, 'cuadre_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeAbiertos($q)  { return $q->where('estado', 'abierto'); }
    public function scopeCerrados($q)  { return $q->where('estado', 'cerrado'); }

    public function scopeDelAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    // ── Helpers ───────────────────────────────────────────────────────
    public function estaAbierto(): bool { return $this->estado === 'abierto'; }

    /** Días que abarca el cuadre (para mostrar en la tabla por día) */
    public function diasDelPeriodo(): \Illuminate\Support\Collection
    {
        $inicio = $this->fecha_inicio->copy();
        $fin    = $this->fecha_fin ?? now()->toDate();
        $dias   = collect();
        while ($inicio->lte($fin)) {
            $dias->push($inicio->copy());
            $inicio->addDay();
        }
        return $dias;
    }
}
