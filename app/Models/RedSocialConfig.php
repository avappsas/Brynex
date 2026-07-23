<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class RedSocialConfig extends BaseModel
{
    protected $table = 'redes_configuracion_aliado';

    public const FACEBOOK  = 'facebook';
    public const INSTAGRAM = 'instagram';

    /** Redes soportadas hoy — agregar aquí + un Publicador nuevo en RedesFactory para extender. */
    public const REDES_DISPONIBLES = [self::FACEBOOK, self::INSTAGRAM];

    protected $fillable = [
        'aliado_id',
        'red',
        'identificador',
        'access_token',
        'nombre_cuenta',
        'extra',
        'activo',
        'verificado_en',
    ];

    protected $casts = [
        'extra'         => 'array',
        'activo'        => 'boolean',
        'verificado_en' => 'datetime',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    // ── Accesor/Mutador para token encriptado (mismo patrón que WhatsappConfig) ──────────

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function credencialesCompletas(): bool
    {
        return !empty($this->identificador) && !empty($this->access_token);
    }

    /** Obtiene (o crea vacía) la configuración de una red para un aliado. */
    public static function paraAliado(int $aliadoId, string $red): static
    {
        return static::firstOrCreate(
            ['aliado_id' => $aliadoId, 'red' => $red],
            ['activo' => false]
        );
    }
}
