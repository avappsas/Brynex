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
        'origen_campana',
        'estado',
        'asignado_a',
        'bot_activo',
        'pendiente_atencion',
        'pendiente_motivo',
        'ultimo_mensaje_at',
        'ventana_activa_hasta',
        'total_mensajes_no_leidos',
    ];

    protected $casts = [
        'ultimo_mensaje_at'         => 'datetime',
        'ventana_activa_hasta'      => 'datetime',
        'total_mensajes_no_leidos'  => 'integer',
        'bot_activo'                => 'boolean',
        'pendiente_atencion'        => 'boolean',
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

    /**
     * Conversaciones que el Asistente IA está atendiendo activamente ahora mismo:
     * el bot puede responder (bot_activo) Y ya participó al menos una vez. Así se
     * excluyen las conversaciones antiguas que nunca pasaron por la IA (bot_activo
     * es el valor por defecto para todas, no implica que la IA la haya atendido).
     */
    public function scopeAtendidasPorIa($query)
    {
        return $query->where('bot_activo', true)
            ->whereHas('mensajes', fn ($m) => $m->where('es_bot', true));
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
     * Solo ejecuta el UPDATE si realmente hay mensajes sin leer.
     */
    public function resetNoLeidos(): void
    {
        if ($this->total_mensajes_no_leidos === 0) return;
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
     * Asigna la conversación a un usuario. Al reclamarla, se resuelve cualquier
     * aviso de "pendiente por atender" que tuviera.
     */
    public function asignarA(int $userId): void
    {
        $this->update([
            'asignado_a'          => $userId,
            'estado'              => 'asignada',
            'pendiente_atencion'  => false,
            'pendiente_motivo'    => null,
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

    /** Silencia el Asistente IA en esta conversación, sin más efectos. */
    public function desactivarBot(): void
    {
        if ($this->bot_activo) {
            $this->update(['bot_activo' => false]);
        }
    }

    /**
     * Reactiva el Asistente IA en esta conversación. Limpia el aviso de pendiente,
     * ya que si el bot vuelve a responder no hay nada esperando a un humano.
     */
    public function activarBot(): void
    {
        $this->update([
            'bot_activo'          => true,
            'pendiente_atencion'  => false,
            'pendiente_motivo'    => null,
        ]);
    }

    /**
     * El Asistente IA transfiere la conversación a un humano (ej: el cliente lo pidió).
     * Queda sin asignar en el inbox General, marcada como pendiente por atender.
     */
    public function escalarAHumano(?string $motivo = null): void
    {
        $this->update([
            'bot_activo'          => false,
            'pendiente_atencion'  => true,
            'pendiente_motivo'    => $motivo,
        ]);
    }

    /**
     * Un usuario toma una conversación que estaba siendo atendida por la IA:
     * apaga el bot, se la asigna a sí mismo y resuelve el pendiente.
     */
    public function tomarConversacion(int $userId): void
    {
        $this->update([
            'bot_activo'          => false,
            'asignado_a'          => $userId,
            'estado'              => 'asignada',
            'pendiente_atencion'  => false,
            'pendiente_motivo'    => null,
        ]);
    }

    /**
     * Preview del último mensaje para mostrar en el inbox.
     * Optimizado para evitar consultas N+1 si la relación 'mensajes' ya fue precargada.
     */
    public function previewUltimoMensaje(): string
    {
        $ultimo = $this->relationLoaded('mensajes')
            ? $this->mensajes->first()
            : WhatsappMensaje::where('conversacion_id', $this->id)->latest()->first();

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
    /**
     * Nombre para mostrar: usa nombre_contacto si existe, sino busca al cliente real.
     */
    public function nombreMostrar(): string
    {
        // Si el nombre es 'Contacto de Prueba' o está vacío, intentar buscar el cliente real
        if (!$this->nombre_contacto || strtolower(trim($this->nombre_contacto)) === 'contacto de prueba') {
            $nombreReal = null;

            // 1. Intentar buscar a través del contrato asociado
            if ($this->contrato_id) {
                $contrato = $this->contrato;
                if ($contrato) {
                    $cliente = \App\Models\Cliente::where('cedula', $contrato->cedula)->first();
                    if ($cliente) {
                        $nombreReal = trim(($cliente->primer_nombre ?? '') . ' ' . ($cliente->primer_apellido ?? ''));
                    }
                }
            }

            // 2. Si no se encontró por contrato, buscar por número de celular en la BD
            if (empty($nombreReal)) {
                $phone10 = preg_replace('/^\+?57/', '', $this->wa_contact_id);
                if (strlen($phone10) >= 10) {
                    $cliente = \App\Models\Cliente::where('aliado_id', $this->aliado_id)
                        ->where(function ($q) use ($phone10) {
                            $q->where('celular', 'like', "%{$phone10}%")
                              ->orWhere('telefono', 'like', "%{$phone10}%");
                        })
                        ->first();
                    if ($cliente) {
                        $nombreReal = trim(($cliente->primer_nombre ?? '') . ' ' . ($cliente->primer_apellido ?? ''));
                    }
                }
            }

            // Si logramos resolver el nombre real, lo persistimos en BD para evitar futuras consultas
            if (!empty($nombreReal)) {
                $this->update(['nombre_contacto' => $nombreReal]);
                $this->nombre_contacto = $nombreReal; // Actualizar en memoria
            }
        }

        $nombre = $this->nombre_contacto ?: $this->wa_contact_id;
        if ($this->nombre_contacto) {
            return mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
        }
        return $nombre;
    }

    /**
     * URL de edición del cliente asociado a la conversación.
     * Busca primero por el contrato asociado y, si no lo tiene, realiza
     * una búsqueda por el número de celular del contacto.
     */
    public function getClienteUrlAttribute(): ?string
    {
        if ($this->contrato_id) {
            $contrato = $this->contrato;
            if ($contrato) {
                $cliente = \App\Models\Cliente::where('cedula', $contrato->cedula)->first();
                if ($cliente) {
                    return route('admin.clientes.edit', $cliente->id);
                }
            }
        }

        $phone = preg_replace('/^\+?57/', '', $this->wa_contact_id);
        if (strlen($phone) >= 10) {
            $cliente = \App\Models\Cliente::where('celular', 'like', "%{$phone}%")
                ->orWhere('telefono', 'like', "%{$phone}%")
                ->first();
            if ($cliente) {
                return route('admin.clientes.edit', $cliente->id);
            }
        }

        return null;
    }
}
