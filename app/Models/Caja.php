<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'cajas';
    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'nombre', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_asopagos',
    ];
}
