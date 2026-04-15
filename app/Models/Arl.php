<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Arl extends Model
{
    protected $table = 'arls';
    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_arl', 'nombre_asopagos',
    ];
}
