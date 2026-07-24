<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicacionMetrica extends BaseModel
{
    protected $table = 'publicacion_metricas';

    protected $fillable = [
        'publicacion_id',
        'red',
        'alcance',
        'me_gusta',
        'comentarios',
        'compartidos',
        'medido_at',
    ];

    protected $casts = [
        'alcance'     => 'integer',
        'me_gusta'    => 'integer',
        'comentarios' => 'integer',
        'compartidos' => 'integer',
        'medido_at'   => 'datetime',
    ];

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class);
    }

    public function interacciones(): int
    {
        return $this->me_gusta + $this->comentarios + $this->compartidos;
    }
}
