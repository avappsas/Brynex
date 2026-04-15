<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pension extends Model
{
    protected $table = 'pensiones';
    public $timestamps = false;

    protected $fillable = [
        'nit', 'codigo', 'razon_social',
        'direccion', 'telefono', 'ciudad', 'email',
        'nombre_asopagos',
    ];
}
