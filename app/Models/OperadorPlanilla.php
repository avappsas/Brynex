<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperadorPlanilla extends Model
{
    protected $table    = 'operadores_planilla';
    protected $fillable = ['aliado_id', 'nombre', 'codigo', 'activo', 'orden'];
}
