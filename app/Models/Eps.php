<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eps extends Model
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
