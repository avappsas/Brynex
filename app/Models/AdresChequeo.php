<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdresChequeo extends BaseModel
{
    protected $table = 'adres_chequeos';

    public const ESTADO_PENDIENTE         = 'pendiente';
    public const ESTADO_ESPERANDO_CAPTCHA = 'esperando_captcha';
    public const ESTADO_CONSULTANDO       = 'consultando';
    public const ESTADO_LISTO             = 'listo';
    public const ESTADO_FALLIDO           = 'fallido';

    protected $fillable = [
        'aliado_id',
        'conversacion_id',
        'solicitado_por',
        'cedula',
        'tipo_documento',
        'autorizado_at',
        'autorizacion_texto',
        'estado',
        'sesion_id',
        'intentos',
        'captcha_enviado_at',
        'filas',
        'diagnostico',
        'total_filas',
        'completo',
        'pdf_path',
        'error',
    ];

    protected $casts = [
        'autorizado_at'      => 'datetime',
        'captcha_enviado_at' => 'datetime',
        'filas'              => 'array',
        'diagnostico'        => 'array',
        'intentos'           => 'integer',
        'total_filas'        => 'integer',
        'completo'           => 'boolean',
    ];

    // Espejo de los DEFAULT de la migración: create() no refresca el modelo con
    // lo que la BD aplicó (mismo motivo que en MarketingCampana).
    protected $attributes = [
        'estado'         => self::ESTADO_PENDIENTE,
        'tipo_documento' => 'Cedula de Ciudadania',
        'intentos'       => 0,
        'completo'       => false,
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversacion::class, 'conversacion_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeDelAliado($query, int $aliadoId)
    {
        return $query->where('aliado_id', $aliadoId);
    }

    public function scopeEsperandoCaptcha($query)
    {
        return $query->where('estado', self::ESTADO_ESPERANDO_CAPTCHA);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /** Sin esto no se consulta nada: es la autorización del titular. */
    public function estaAutorizado(): bool
    {
        return $this->autorizado_at !== null;
    }

    public function esperaCaptcha(): bool
    {
        return $this->estado === self::ESTADO_ESPERANDO_CAPTCHA && $this->sesion_id !== null;
    }
}
