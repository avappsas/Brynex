<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IaConversacion extends BaseModel
{
    protected $table = 'ia_conversaciones';

    protected $fillable = [
        'aliado_id', 'canal', 'user_id', 'telefono', 'bot_activo', 'ultima_actividad',
    ];

    protected $casts = [
        'bot_activo'       => 'boolean',
        'ultima_actividad' => 'datetime',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(IaMensaje::class, 'conversacion_id')->orderBy('created_at');
    }

    /** Conversación activa (o nueva) para un usuario en el canal web. */
    public static function paraUsuarioWeb(int $alidoId, int $userId): static
    {
        $conversacion = static::where('aliado_id', $alidoId)
            ->where('canal', 'web')
            ->where('user_id', $userId)
            ->orderByDesc('ultima_actividad')
            ->first();

        if ($conversacion) {
            return $conversacion;
        }

        return static::create([
            'aliado_id'        => $alidoId,
            'canal'            => 'web',
            'user_id'          => $userId,
            'ultima_actividad' => now(),
        ]);
    }

    /** Conversación activa (o nueva) para un número de WhatsApp. */
    public static function paraTelefono(int $alidoId, string $telefono): static
    {
        $conversacion = static::where('aliado_id', $alidoId)
            ->where('canal', 'whatsapp')
            ->where('telefono', $telefono)
            ->first();

        if ($conversacion) {
            return $conversacion;
        }

        return static::create([
            'aliado_id'        => $alidoId,
            'canal'            => 'whatsapp',
            'telefono'         => $telefono,
            'ultima_actividad' => now(),
        ]);
    }
}
