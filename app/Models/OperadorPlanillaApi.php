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
        'tipos_modalidad',
        'api_planilla_id',
        'numero_planilla',
        'valor_total',
        'url_pago',
        'estado',
        'mensaje_error',
        'response_log',
        // Lo que el operador reporta de la planilla, traído por
        // `planillas:sincronizar-totales`. Sin declararlos aquí el `update()`
        // los descarta sin decir nada.
        'nombre_aportante',
        'numero_afiliados',
        'periodo_cotizacion',
        'periodo_servicio',
        'fecha_limite',
        'total_administradoras',
        'totales_at'
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
