<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappConversacion extends BaseModel
{
    use SoftDeletes;

    protected $table = 'whatsapp_conversaciones';

    protected $fillable = [
        'aliado_id',
        'wa_contact_id',
        'nombre_contacto',
        'contrato_id',
        'empresa_id',
        'estado',
        'asignado_a',
        'ultimo_mensaje_at',
        'ventana_activa_hasta',
        'total_mensajes_no_leidos',
    ];

    protected $casts = [
        'ultimo_mensaje_at'         => 'datetime',
        'ventana_activa_hasta'      => 'datetime',
        'total_mensajes_no_leidos'  => 'integer',
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(WhatsappMensaje::class, 'conversacion_id')->orderBy('created_at');
    }

    public function ultimoMensaje(): HasMany
    {
        return $this->hasMany(WhatsappMensaje::class, 'conversacion_id')->latest()->limit(1);
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeAbiertas($query)
    {
        return $query->where('estado', 'abierta');
    }

    public function scopeAsignadas($query)
    {
        return $query->where('estado', 'asignada');
    }

    public function scopeActivas($query)
    {
        return $query->whereIn('estado', ['abierta', 'asignada']);
    }

    public function scopeDelAliado($query, int $alidoId)
    {
        return $query->where('aliado_id', $alidoId);
    }

    // ── Helpers de negocio ───────────────────────────────────────────

    /**
     * Determina si la ventana de 24h de Meta está activa.
     * Dentro de la ventana: se puede enviar texto libre.
     * Fuera de la ventana: solo plantillas aprobadas.
     */
    public function ventanaActiva(): bool
    {
        if (!$this->ventana_activa_hasta) return false;
        return $this->ventana_activa_hasta->isFuture();
    }

    /**
     * Minutos restantes de la ventana de 24h.
     */
    public function minutosVentanaRestante(): int
    {
        if (!$this->ventanaActiva()) return 0;
        return (int) now()->diffInMinutes($this->ventana_activa_hasta);
    }

    /**
     * Incrementa el contador de mensajes no leídos.
     */
    public function incrementarNoLeidos(): void
    {
        $this->increment('total_mensajes_no_leidos');
        $this->ultimo_mensaje_at = now();
        $this->save();
    }

    /**
     * Resetea el contador de no leídos (cuando el agente abre el chat).
     */
    public function resetNoLeidos(): void
    {
        $this->update(['total_mensajes_no_leidos' => 0]);
    }

    /**
     * Actualiza la ventana de 24h al recibir un mensaje del cliente.
     */
    public function renovarVentana(): void
    {
        $this->update([
            'ventana_activa_hasta' => now()->addHours(24),
            'ultimo_mensaje_at'    => now(),
        ]);
    }

    /**
     * Asigna la conversación a un usuario.
     */
    public function asignarA(int $userId): void
    {
        $this->update([
            'asignado_a' => $userId,
            'estado'     => 'asignada',
        ]);
    }

    /**
     * Libera la asignación (vuelve al inbox general).
     */
    public function liberar(): void
    {
        $this->update([
            'asignado_a' => null,
            'estado'     => 'abierta',
        ]);
    }

    /**
     * Cierra la conversación.
     */
    public function cerrar(): void
    {
        $this->update(['estado' => 'cerrada']);
    }

    /**
     * Preview del último mensaje para mostrar en el inbox.
     */
    public function previewUltimoMensaje(): string
    {
        $ultimo = WhatsappMensaje::where('conversacion_id', $this->id)->latest()->first();
        if (!$ultimo) return '';

        return match($ultimo->tipo) {
            'text'     => mb_substr($ultimo->contenido ?? '', 0, 60),
            'image'    => '📷 Imagen',
            'audio'    => '🎵 Audio',
            'document' => '📄 Documento',
            'video'    => '🎥 Video',
            'template' => '📋 Plantilla',
            default    => $ultimo->tipo,
        };
    }

    /**
     * Nombre para mostrar: usa nombre_contacto si existe, sino el número.
     */
    public function nombreMostrar(): string
    {
        return $this->nombre_contacto ?: $this->wa_contact_id;
    }
}
