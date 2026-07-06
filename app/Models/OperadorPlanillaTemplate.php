<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperadorPlanillaTemplate extends BaseModel
{
    use SoftDeletes;

    protected $table = 'operador_planillas_templates';

    protected $fillable = [
        'operador_planilla_id',
        'nombre',
        'formulario_pdf',
        'formulario_campos',
    ];

    protected $casts = [
        'formulario_campos' => 'array',
    ];

    /**
     * Relación con el Operador de Planilla global.
     */
    public function operador()
    {
        return $this->belongsTo(OperadorPlanilla::class, 'operador_planilla_id');
    }
}
