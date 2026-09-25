<?php

namespace App\Models;

/**
 * Nota crédito electrónica que anula una FE emitida por Dataico.
 *
 * Una fila por FE anulada (índice único en `dataico_envio_id`). Ver
 * `App\Services\Dataico\NotaCreditoService`.
 */
class DataicoNotaCredito extends BaseModel
{
    protected $table = 'dataico_notas_credito';

    protected $fillable = [
        'aliado_id', 'dataico_envio_id', 'numero_factura', 'factura_dataico_numero',
        'estado', 'razon', 'motivo', 'valor',
        'numero', 'dataico_uuid', 'cude',
        'payload', 'respuesta', 'error_mensaje',
        'usuario_id', 'enviado_at',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'enviado_at' => 'datetime',
    ];

    public const ESTADO_ENVIANDO = 'enviando';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_ERROR = 'error';

    public function envio()
    {
        return $this->belongsTo(DataicoEnvio::class, 'dataico_envio_id');
    }

    public function fueEnviada(): bool
    {
        return $this->estado === self::ESTADO_ENVIADO;
    }
}
