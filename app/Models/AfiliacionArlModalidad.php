<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfiliacionArlModalidad extends BaseModel
{
    protected $table = 'afiliacion_arl_modalidad';

    protected $fillable = [
        'aliado_id', 'plan_id', 'tipo_modalidad_id', 'nivel_arl', 'costo_afiliacion',
    ];

    protected $casts = [
        'nivel_arl'        => 'integer',
        'costo_afiliacion' => 'decimal:2',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanContrato::class, 'plan_id');
    }

    public function tipoModalidad(): BelongsTo
    {
        return $this->belongsTo(TipoModalidad::class, 'tipo_modalidad_id');
    }
}
