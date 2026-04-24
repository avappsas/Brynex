<?php

namespace App\Models;

use App\Models\BaseModel;

class Caja extends BaseModel
{
    protected $table = 'cajas';
    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'nombre', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_asopagos',
    ];
}
