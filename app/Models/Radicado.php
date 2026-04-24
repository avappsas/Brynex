<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Radicado extends BaseModel
{
    protected $table = 'radicados';
    protected $fillable = [
        'contrato_id', 'aliado_id', 'tipo',
        'numero_radicado', 'estado',
        'canal_envio', 'enviado_al_cliente',
        'canal_envio_cliente', 'fecha_envio_cliente',
        'fecha_inicio_tramite', 'fecha_confirmacion',
        'user_id', 'observacion', 'ruta_pdf',
    ];
    protected $casts = [
        'enviado_al_cliente'   => 'boolean',
        'fecha_envio_cliente'  => 'datetime',
        'fecha_inicio_tramite' => 'datetime',
        'fecha_confirmacion'   => 'datetime',
    ];

    // ── Constantes de estado ──
    const ESTADO_PENDIENTE  = 'pendiente';
    const ESTADO_TRAMITE    = 'tramite';
    const ESTADO_TRASLADO   = 'traslado';
    const ESTADO_ERROR      = 'error';
    const ESTADO_OK         = 'ok';

    // Estados activos (requieren seguimiento)
    public static function estadosActivos(): array
    {
        return [self::ESTADO_PENDIENTE, self::ESTADO_TRAMITE, self::ESTADO_TRASLADO, self::ESTADO_ERROR];
    }

    public static function todosEstados(): array
    {
        return [
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_TRAMITE   => 'Trámite',
            self::ESTADO_TRASLADO  => 'Traslado',
            self::ESTADO_ERROR     => 'Error',
            self::ESTADO_OK        => 'OK',
        ];
    }

    // ── Constantes de tipo ──
    const TIPO_EPS     = 'eps';
    const TIPO_ARL     = 'arl';
    const TIPO_CAJA    = 'caja';
    const TIPO_PENSION = 'pension';

    // ── Constantes de canal ──
    const CANAL_WEB        = 'web';
    const CANAL_CORREO     = 'correo';
    const CANAL_ASESOR     = 'asesor';
    const CANAL_PRESENCIAL = 'presencial';
    const CANAL_OTRO       = 'otro';

    const CANAL_WHATSAPP   = 'whatsapp';
    const CANAL_FISICA      = 'fisica';

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RadicadoMovimiento::class)->orderByDesc('id');
    }

    // ── Helpers de estado ──
    public function esPendiente(): bool  { return $this->estado === self::ESTADO_PENDIENTE; }
    public function esTramite(): bool    { return $this->estado === self::ESTADO_TRAMITE; }
    public function esTraslado(): bool   { return $this->estado === self::ESTADO_TRASLADO; }
    public function esError(): bool      { return $this->estado === self::ESTADO_ERROR; }
    public function esOk(): bool         { return $this->estado === self::ESTADO_OK; }
    public function esFinalizado(): bool { return $this->estado === self::ESTADO_OK; }

    /**
     * Calcula los días transcurridos desde el inicio del estado actual.
     * Si hay movimientos, toma la fecha del último; si no, usa created_at.
     */
    public function diasEnEstado(): int
    {
        $ultimoMov = $this->movimientos()->reorder()->orderByDesc('id')->first();
        $desde = $ultimoMov ? $ultimoMov->created_at : $this->created_at;
        return max(0, (int) now()->diffInDays($desde));
    }

    /** ¿Alerta por llevar demasiado tiempo en trámite? (>5 días) */
    public function tieneAlertaDias(): bool
    {
        return $this->estado === self::ESTADO_TRAMITE && $this->diasEnEstado() > 5;
    }

    /** Etiqueta legible del tipo de radicado */
    public function tipoLabel(): string
    {
        return match($this->tipo) {
            'eps'     => 'EPS',
            'arl'     => 'ARL',
            'caja'    => 'Caja Compensación',
            'pension' => 'Pensión (AFP)',
            default   => strtoupper($this->tipo),
        };
    }

    /** Color badge para la UI según estado */
    public function estadoColor(): string
    {
        return match($this->estado) {
            'pendiente' => 'warning',
            'tramite'   => 'info',
            'traslado'  => 'orange',
            'error'     => 'danger',
            'ok'        => 'success',
            default     => 'secondary',
        };
    }

    /** Icono emoji por estado */
    public function estadoIcono(): string
    {
        return match($this->estado) {
            'pendiente' => '⏳',
            'tramite'   => '🔵',
            'traslado'  => '🔄',
            'error'     => '❌',
            'ok'        => '✅',
            default     => '❓',
        };
    }
}
