<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seguimiento del pipeline asíncrono de video (Veo + overlay FFmpeg), previo a convertirse
 * en una Publicacion normal. Avanza vía el comando `videos:procesar` (cron cada minuto) —
 * ver ProcesarVideosIa.
 */
class PublicidadVideoIa extends BaseModel
{
    protected $table = 'publicidad_videos_ia';

    public const ESTADO_GENERANDO = 'generando';
    public const ESTADO_LISTA     = 'lista';
    public const ESTADO_ERROR     = 'error';

    protected $fillable = [
        'aliado_id',
        'prompt_video',
        'frases_texto',
        'modelo',
        'estado',
        'operation_name',
        'video_path',
        'imagen_poster_path',
        'error_mensaje',
        'creado_por',
    ];

    protected $casts = [
        'frases_texto' => 'array',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_GENERANDO,
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }
}
