<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaConocimiento extends BaseModel
{
    protected $table = 'ia_conocimiento';

    protected $fillable = [
        'aliado_id', 'titulo', 'contenido', 'categoria', 'fuente', 'estado',
        'vigente_desde', 'vigente_hasta', 'creado_por',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Conocimiento aprobado y vigente hoy, global o del aliado dado. */
    public function scopeVigente($query, ?int $alidoId = null)
    {
        $hoy = now()->toDateString();

        return $query->where('estado', 'aprobado')
            ->where(function ($q) use ($alidoId) {
                $q->whereNull('aliado_id');
                if ($alidoId) {
                    $q->orWhere('aliado_id', $alidoId);
                }
            })
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $hoy);
            })
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy);
            });
    }
}
