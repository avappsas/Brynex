<?php

namespace App\Models;

use App\Models\BaseModel;

class Eps extends BaseModel
{
    protected $table = 'eps';
    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'nombre', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_aportes', 'nombre_asopagos',
        'formulario_pdf', 'formulario_campos',
    ];

    protected $casts = [
        'formulario_campos' => 'array',
    ];
}
