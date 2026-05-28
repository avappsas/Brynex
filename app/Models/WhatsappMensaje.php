<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMensaje extends BaseModel
{
    protected $table = 'whatsapp_mensajes';

    protected $fillable = [
        'conversacion_id',
        'aliado_id',
        'wa_message_id',
        'direccion',
        'tipo',
        'contenido',
        'media_url',
        'media_mime_type',
        'media_nombre',
        'media_wa_id',
        'plantilla_id',
        'plantilla_parametros',
        'estado',
        'estado_at',
        'usuario_id',
        'error_detalle',
    ];

    protected $casts = [
        'plantilla_parametros' => 'array',
        'estado_at'            => 'datetime',
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversacion::class, 'conversacion_id');
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(WhatsappPlantilla::class, 'plantilla_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function esEntrante(): bool
    {
        return $this->direccion === 'entrante';
    }

    public function esSaliente(): bool
    {
        return $this->direccion === 'saliente';
    }

    public function esMedia(): bool
    {
        return in_array($this->tipo, ['image', 'audio', 'document', 'video']);
    }

    public function tieneMedia(): bool
    {
        return $this->esMedia() && !empty($this->media_url);
    }

    /**
     * Ícono representativo del tipo de mensaje.
     */
    public function icono(): string
    {
        return match($this->tipo) {
            'image'    => '📷',
            'audio'    => '🎵',
            'document' => '📄',
            'video'    => '🎥',
            'template' => '📋',
            default    => '💬',
        };
    }

    /**
     * Ícono del estado de entrega para mensajes salientes.
     */
    public function iconoEstado(): string
    {
        return match($this->estado) {
            'enviado'    => '✓',
            'entregado'  => '✓✓',
            'leido'      => '🔵',
            'fallido'    => '❌',
            default      => '',
        };
    }

    /**
     * URL pública para acceder al media a través del controller.
     * El media se sirve desde el controlador para mantener el aislamiento.
     */
    public function urlMedia(): ?string
    {
        if (!$this->media_url) return null;
        return route('admin.whatsapp.chat.media', ['mensajeId' => $this->id]);
    }

    /**
     * Verifica si el archivo de media existe en disco.
     */
    public function mediaExiste(): bool
    {
        if (!$this->media_url) return false;
        return \Illuminate\Support\Facades\Storage::disk('local')->exists($this->media_url);
    }
}
