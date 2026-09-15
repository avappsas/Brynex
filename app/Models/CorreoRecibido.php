<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correo que llegó al buzón de afiliaciones y lo que el agente hizo con él.
 * Ver la migración `create_correos_recibidos`.
 */
class CorreoRecibido extends BaseModel
{
    protected $table = 'correos_recibidos';

    protected $fillable = [
        'aliado_id', 'buzon', 'message_id', 'uid', 'in_reply_to', 'referencias', 'de', 'de_nombre', 'asunto',
        'recibido_at', 'texto', 'adjuntos', 'clasificacion', 'entidad', 'correo_afiliacion_id', 'contrato_id',
        'radicado_id', 'estado', 'accion', 'revisado_por', 'revisado_at',
    ];

    protected $casts = [
        'adjuntos'    => 'array',
        'recibido_at' => 'datetime',
        'revisado_at' => 'datetime',
    ];

    public function correoAfiliacion(): BelongsTo
    {
        return $this->belongsTo(CorreoAfiliacion::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
