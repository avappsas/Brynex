<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperadorCredencial extends BaseModel
{
    use SoftDeletes;

    protected $table = 'operadores_credenciales';

    protected $fillable = [
        'aliado_id',
        'razon_social_id',
        'operador_planilla_id',
        'usuario',
        'clave_secreta',
        'config'
    ];

    protected $casts = [
        'config' => 'array',
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
