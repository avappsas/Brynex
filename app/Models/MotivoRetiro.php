<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoRetiro extends Model
{
    public $timestamps = false;
    protected $table = 'motivos_retiro';
    protected $fillable = ['nombre', 'es_reingreso', 'activo'];
    protected $casts = [
        'es_reingreso' => 'boolean',
        'activo'       => 'boolean',
    ];
}
