<?php

namespace App\Models;

use App\Models\BaseModel;

class Pension extends BaseModel
{
    protected $table = 'pensiones';
    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_asopagos',
    ];
}
