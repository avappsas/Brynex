<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
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
