<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un acceso al panel: quién entró, desde dónde y con qué equipo.
 *
 * Solo tiene `created_at` — un acceso es un hecho puntual, no se edita nunca.
 */
class AccesoUsuario extends BaseModel
{
    protected $table = 'accesos_usuario';

    public const UPDATED_AT = null;

    protected $fillable = [
        'aliado_id',
        'user_id',
        'ip',
        'dispositivo_id',
        'huella',
        'user_agent',
        'anomalias',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'aliado_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    /** @return string[] */
    public function anomaliasLista(): array
    {
        return $this->anomalias ? explode(',', $this->anomalias) : [];
    }

    public function tieneAnomalias(): bool
    {
        return ! empty($this->anomalias);
    }

    /**
     * Texto legible de cada anomalía, para las alertas y la pantalla.
     */
    public static function etiqueta(string $anomalia): string
    {
        return [
            'primer_acceso' => 'primer acceso de la cuenta',
            'dispositivo_nuevo' => 'equipo nunca visto para esta cuenta',
            'huella_nueva' => 'navegador distinto al habitual',
            'ip_nueva' => 'IP nueva',
            'red_nueva' => 'red distinta a las habituales',
            'ip_rotativa' => 'muchas IPs distintas en 24h (posible VPN o proxy)',
            'dispositivo_multicuenta' => 'el mismo equipo ya entró con otra cuenta',
        ][$anomalia] ?? $anomalia;
    }
}
