<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingBloqueado extends BaseModel
{
    protected $table = 'marketing_bloqueados';

    protected $fillable = [
        'aliado_id',
        'celular',
        'motivo',
        'origen',
        'bloqueado_por',
        'conversacion_id',
    ];

    // ── Relaciones ──────────────────────────────────────────────────

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function bloqueadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bloqueado_por');
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversacion::class, 'conversacion_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /** Consultada antes de cualquier carga de lista o lanzamiento de tanda. */
    public static function estaBloqueado(int $alidoId, string $celular): bool
    {
        return static::where('aliado_id', $alidoId)->where('celular', $celular)->exists();
    }

    /**
     * Agrega (o actualiza el motivo de) un número en la lista negra. Idempotente —
     * bloquear dos veces el mismo número no falla, solo actualiza el registro existente.
     */
    public static function bloquear(int $alidoId, string $celular, string $origen, ?string $motivo = null, ?int $bloqueadoPor = null, ?int $conversacionId = null): self
    {
        return static::updateOrCreate(
            ['aliado_id' => $alidoId, 'celular' => $celular],
            [
                'motivo'          => $motivo,
                'origen'          => $origen,
                'bloqueado_por'   => $bloqueadoPor,
                'conversacion_id' => $conversacionId,
            ]
        );
    }
}
