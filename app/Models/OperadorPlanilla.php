<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperadorPlanilla extends BaseModel
{
    protected $table    = 'operadores_planilla';
    protected $fillable = ['aliado_id', 'nombre', 'codigo', 'codigo_ni', 'activo', 'orden'];

    protected $casts = [
        'activo'    => 'boolean',
        'codigo_ni' => 'integer',
    ];

    /**
     * Scope: operadores disponibles para un aliado (globales + los del aliado),
     * respetando el override por-aliado de `aliado_operadores_planilla`
     * (Configuración → Operadores de planilla). Sin fila en el pivot = activo.
     */
    public function scopeParaAliado($query, int $aliadoId)
    {
        return $query
            ->where(function ($q) use ($aliadoId) {
                $q->whereNull('aliado_id')->orWhere('aliado_id', $aliadoId);
            })
            ->where('activo', true)
            ->whereNotIn('id', function ($sub) use ($aliadoId) {
                $sub->select('operador_id')
                    ->from('aliado_operadores_planilla')
                    ->where('aliado_id', $aliadoId)
                    ->where('activo', false);
            })
            ->orderBy('orden');
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }
}
