<?php

namespace App\Models;

/**
 * Rastro de un intento de emisión de factura electrónica ante Dataico.
 *
 * Una fila por grupo `facturas.numero_factura`. El índice único
 * (aliado_id, numero_factura) es la garantía de que un reintento de job o dos
 * disparos simultáneos no emitan dos veces la misma factura ante la DIAN.
 */
class DataicoEnvio extends BaseModel
{
    protected $table = 'dataico_envios';

    protected $fillable = [
        'aliado_id', 'razon_social_id', 'numero_factura',
        'estado', 'base_admon',
        'cliente_identificacion', 'cliente_nombre', 'es_consumidor_final',
        'dataico_uuid', 'dataico_numero', 'cufe',
        'payload', 'respuesta', 'error_mensaje',
        'intentos', 'enviado_at',
    ];

    protected $casts = [
        'es_consumidor_final' => 'boolean',
        'enviado_at' => 'datetime',
        'base_admon' => 'decimal:2',
        'intentos' => 'integer',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIANDO = 'enviando';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDO = 'omitido';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_ENVIANDO => 'Enviando',
        self::ESTADO_ENVIADO => 'Enviada',
        self::ESTADO_ERROR => 'Con error',
        self::ESTADO_OMITIDO => 'Omitida',
    ];

    public function razonSocial()
    {
        return $this->belongsTo(RazonSocial::class, 'razon_social_id');
    }

    public function scopeAliado($q, int $aliadoId)
    {
        return $q->where('aliado_id', $aliadoId);
    }

    /** Envíos que vale la pena reintentar. */
    public function scopeReintentables($q, int $maxIntentos = 5)
    {
        return $q->where('estado', self::ESTADO_ERROR)
            ->where('intentos', '<', $maxIntentos);
    }

    public function fueEnviado(): bool
    {
        return $this->estado === self::ESTADO_ENVIADO;
    }
}
