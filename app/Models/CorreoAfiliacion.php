<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correo de afiliación enviado al asesor de una EPS y lo que respondió.
 * Ver la migración `create_correos_afiliacion`.
 */
class CorreoAfiliacion extends BaseModel
{
    protected $table = 'correos_afiliacion';

    protected $fillable = [
        'aliado_id', 'contrato_id', 'radicado_id', 'entidad', 'motivo', 'buzon', 'para', 'cc',
        'asunto', 'cuerpo', 'adjuntos', 'message_id', 'estado', 'enviado_at', 'vence_at',
        'respondido_at', 'respuesta_de', 'respuesta_resumen', 'error', 'usuario_id', 'avisado_vencido_at',
    ];

    protected $casts = [
        'adjuntos'      => 'array',
        'enviado_at'    => 'datetime',
        'vence_at'      => 'datetime',
        'respondido_at' => 'datetime',
        'avisado_vencido_at' => 'datetime',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(Radicado::class);
    }
}
