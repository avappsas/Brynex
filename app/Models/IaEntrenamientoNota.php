<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaEntrenamientoNota extends BaseModel
{
    protected $table = 'ia_entrenamiento_notas';

    protected $fillable = [
        'aliado_id', 'creado_por', 'mensaje_cliente', 'respuesta_ia', 'correccion', 'contexto', 'estado',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
