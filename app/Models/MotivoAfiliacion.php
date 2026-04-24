<?php

namespace App\Models;

use App\Models\BaseModel;

class MotivoAfiliacion extends BaseModel
{
    public $timestamps = false;
    protected $table = 'motivos_afiliacion';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
