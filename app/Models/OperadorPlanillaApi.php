<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperadorPlanillaApi extends BaseModel
{
    use SoftDeletes;

    protected $table = 'operador_planillas_api';

    protected $fillable = [
        'aliado_id',
        'razon_social_id',
        'plano_id',
        'operador_planilla_id',
        'anio',
        'mes',
        'n_plano',
        'api_planilla_id',
        'numero_planilla',
        'valor_total',
        'url_pago',
        'estado',
        'mensaje_error',
        'response_log'
    ];

    protected $casts = [
        'response_log' => 'array',
        'valor_total' => 'decimal:2',
    ];

    public function razonSocial()
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function operadorPlanilla()
    {
        return $this->belongsTo(OperadorPlanilla::class, 'operador_planilla_id');
    }
}
