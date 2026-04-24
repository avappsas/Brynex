<?php

namespace App\Models;

use App\Models\BaseModel;

class MotivoRetiro extends BaseModel
{
    public $timestamps = false;
    protected $table = 'motivos_retiro';
    protected $fillable = ['nombre', 'es_reingreso', 'activo'];
    protected $casts = [
        'es_reingreso' => 'boolean',
        'activo'       => 'boolean',
    ];
}
