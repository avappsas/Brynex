<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaMensaje extends BaseModel
{
    protected $table = 'ia_mensajes';

    protected $fillable = [
        'conversacion_id', 'rol', 'contenido', 'tool_name', 'tool_data',
        'tokens_entrada', 'tokens_salida',
    ];

    protected $casts = [
        'tool_data' => 'array',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(IaConversacion::class, 'conversacion_id');
    }
}
