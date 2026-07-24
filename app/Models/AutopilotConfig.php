<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutopilotConfig extends BaseModel
{
    protected $table = 'autopilot_config';

    public const MODO_APROBAR = 'aprobar';
    public const MODO_AUTO    = 'auto';

    public const ESTILO_ILUSTRACION   = 'ilustracion';
    public const ESTILO_FOTORREALISTA = 'fotorrealista';
    public const ESTILO_ALTERNAR      = 'alternar';

    protected $fillable = [
        'aliado_id',
        'activo',
        'modo',
        'hora',
        'dias',
        'estilo_imagen',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'dias'   => 'array',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    /** ¿Hoy (hora Colombia) es un día activo según la config? null = todos los días. */
    public function tocaHoy(): bool
    {
        if (empty($this->dias)) return true;
        return in_array(now('America/Bogota')->isoWeekday(), array_map('intval', $this->dias), true);
    }

    /** ¿Ya pasó la hora configurada para generar la pieza de hoy? */
    public function horaLlego(): bool
    {
        return now('America/Bogota')->format('H:i') >= $this->hora;
    }

    /** Estilo de imagen para la pieza de hoy (resuelve "alternar" al azar). */
    public function estiloDelDia(): string
    {
        if ($this->estilo_imagen === self::ESTILO_ALTERNAR) {
            return random_int(0, 1) ? self::ESTILO_FOTORREALISTA : self::ESTILO_ILUSTRACION;
        }
        return $this->estilo_imagen ?: self::ESTILO_ILUSTRACION;
    }

    public static function paraAliado(int $aliadoId): static
    {
        return static::firstOrCreate(
            ['aliado_id' => $aliadoId],
            ['activo' => false, 'modo' => self::MODO_APROBAR, 'hora' => '09:00', 'estilo_imagen' => self::ESTILO_ILUSTRACION]
        );
    }
}
