<?php

namespace App\Models;

use App\Models\BaseModel;

class Departamento extends BaseModel
{
    protected $table = 'departamentos';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'nombre', 'dept_aportes', 'dept_asopagos'];

    public function ciudades()
    {
        return $this->hasMany(Ciudad::class, 'departamento_id');
    }
}
