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

    public const FORMATO_REEL   = 'reel';
    public const FORMATO_IMAGEN = 'imagen';

    public const NIVEL_LITE     = 'lite';
    public const NIVEL_STANDARD = 'standard';

    protected $fillable = [
        'aliado_id',
        'activo',
        'modo',
        'hora',
        'dias',
        'dias_flyer',
        'estilo_imagen',
        'formato',
        'video_nivel',
        'video_duracion',
    ];

    protected $casts = [
        'activo'         => 'boolean',
        'dias'           => 'array',
        'dias_flyer'     => 'array',
        'video_duracion' => 'integer',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    /** ¿Hoy (hora Colombia) es un día activo según la config? null = todos los días. */
    public function tocaHoy(): bool
    {
        // Un día de flyer siempre cuenta como día activo, aunque no esté en la lista normal:
        // si no, marcar un día promocional que no esté en `dias` no publicaría nada.
        if ($this->tocaFlyerHoy()) return true;
        if (empty($this->dias)) return true;
        return in_array(now('America/Bogota')->isoWeekday(), array_map('intval', $this->dias), true);
    }

    /**
     * ¿Hoy toca flyer promocional de un plan (foto propia + precio real + botón de WhatsApp)
     * en vez del post educativo? Vacío = nunca.
     */
    public function tocaFlyerHoy(): bool
    {
        if (empty($this->dias_flyer)) return false;
        return in_array(now('America/Bogota')->isoWeekday(), array_map('intval', $this->dias_flyer), true);
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

    /**
     * ¿El post educativo de hoy va como Reel? Los días de flyer promocional siempre son
     * pieza gráfica: ahí lo que importa es que se lean los precios, no el alcance.
     */
    public function tocaReelHoy(): bool
    {
        return !$this->tocaFlyerHoy() && ($this->formato ?? self::FORMATO_REEL) === self::FORMATO_REEL;
    }

    /** Modelo de Veo a usar, resuelto desde la config (ver costos en la migración). */
    public function modeloVideo(): string
    {
        return ($this->video_nivel ?? self::NIVEL_LITE) === self::NIVEL_STANDARD
            ? \App\Services\Publicidad\VeoVideoGenerator::MODELO_STANDARD
            : \App\Services\Publicidad\VeoVideoGenerator::MODELO_LITE;
    }

    public static function paraAliado(int $aliadoId): static
    {
        return static::firstOrCreate(
            ['aliado_id' => $aliadoId],
            [
                'activo'         => false,
                'modo'           => self::MODO_APROBAR,
                'hora'           => '09:00',
                'estilo_imagen'  => self::ESTILO_ILUSTRACION,
                'formato'        => self::FORMATO_REEL,
                'video_nivel'    => self::NIVEL_LITE,
                'video_duracion' => 8,
            ]
        );
    }
}
