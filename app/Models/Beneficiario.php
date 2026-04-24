<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Beneficiario extends BaseModel
{
    use HasFactory;

    protected $table = 'beneficiarios';

    protected $fillable = [
        'aliado_id',
        'cc_cliente',
        'tipo_doc',
        'n_documento',
        'nombres',
        'fecha_expedicion',
        'fecha_nacimiento',
        'parentesco',
        'observacion',
        'fecha_ingreso',
    ];

    protected $casts = [
        'fecha_expedicion'  => 'date',
        'fecha_nacimiento'  => 'date',
        'fecha_ingreso'     => 'date',
        'cc_cliente'        => 'integer',
    ];

    // ── Relaciones ─────────────────────────────────────────
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cc_cliente', 'cedula');
    }

    public function aliado()
    {
        return $this->belongsTo(Aliado::class);
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeDeAliado($query, $alidoId)
    {
        return $query->where('aliado_id', $alidoId);
    }

    // ── Accesores ──────────────────────────────────────────
    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento
            ? $this->fecha_nacimiento->diffInYears(now())
            : null;
    }
}
