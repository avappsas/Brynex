<?php

namespace App\Models;

use App\Models\BaseModel;

class Ciudad extends BaseModel
{
    protected $table = 'ciudades';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'departamento_id', 'nombre', 'ciudad_aportes', 'ciudad_asopagos'];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }
}
