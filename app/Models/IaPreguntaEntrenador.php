<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaPreguntaEntrenador extends BaseModel
{
    protected $table = 'ia_preguntas_entrenador';

    protected $fillable = [
        'aliado_id', 'conversacion_id', 'pregunta', 'respuesta',
        'estado', 'respondido_por', 'respondido_at',
    ];

    protected $casts = [
        'respondido_at' => 'datetime',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(IaConversacion::class, 'conversacion_id');
    }

    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }
}
