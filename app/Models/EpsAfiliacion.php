<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada trámite hecho en el portal de una EPS, con lo enviado y lo recibido.
 * Los portales no tienen ambiente de pruebas: esto es lo que permite
 * reconstruir qué pasó el día que un trámite salga mal.
 */
class EpsAfiliacion extends BaseModel
{
    protected $table = 'eps_afiliaciones';

    protected $fillable = [
        'aliado_id', 'contrato_id', 'radicado_id', 'entidad', 'operacion', 'estado',
        'numero_radicado', 'payload', 'respuesta', 'mensaje_error', 'ruta_pdf', 'usuario_id',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
