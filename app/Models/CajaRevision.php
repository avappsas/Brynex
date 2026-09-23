<?php

namespace App\Models;

/**
 * Una corrida de la revisión de subsidios de una caja, para un aliado y un día.
 *
 * Es el candado que evita repetir el barrido: el comando nocturno y el disparo
 * desde el portal preguntan `yaSeHizo()` antes de arrancar.
 */
class CajaRevision extends BaseModel
{
    protected $table = 'caja_revisiones';

    protected $fillable = [
        'aliado_id', 'entidad', 'fecha', 'estado', 'alcance',
        'revisados', 'bloqueados', 'tareas_nuevas', 'tareas_cerradas', 'mensaje',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public const ENTIDAD_COMFANDI = 'comfandi';

    public const ESTADO_CORRIENDO = 'corriendo';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ALCANCE_CANDIDATOS = 'candidatos';

    public const ALCANCE_COMPLETA = 'completa';

    /**
     * ¿Ya se revisó hoy? Una corrida con error no cuenta: se puede reintentar.
     *
     * Una que quedó en `corriendo` sí cuenta durante dos horas —es la que está
     * en curso— y después se da por colgada, para que un proceso muerto no deje
     * al aliado sin revisión el resto del día.
     */
    public static function yaSeHizo(int $aliadoId, string $entidad = self::ENTIDAD_COMFANDI): bool
    {
        $hoy = static::where('aliado_id', $aliadoId)->where('entidad', $entidad)
            ->whereDate('fecha', today())->latest('id')->first();

        if (! $hoy) {
            return false;
        }

        if ($hoy->estado === self::ESTADO_OK) {
            return true;
        }

        return $hoy->estado === self::ESTADO_CORRIENDO
            && $hoy->updated_at
            && $hoy->updated_at->gt(now()->subHours(2));
    }

    /**
     * Abre (o retoma) la corrida del día y la deja en `corriendo`.
     */
    public static function abrir(int $aliadoId, string $entidad, string $alcance): self
    {
        $revision = static::firstOrNew([
            'aliado_id' => $aliadoId,
            'entidad' => $entidad,
            'fecha' => today()->toDateString(),
        ]);

        $revision->fill([
            'estado' => self::ESTADO_CORRIENDO,
            'alcance' => $alcance,
            'mensaje' => null,
        ])->save();

        return $revision;
    }

    /** Cierra la corrida con sus totales. */
    public function terminar(array $totales, ?string $mensaje = null): void
    {
        $this->fill($totales + [
            'estado' => self::ESTADO_OK,
            'mensaje' => $mensaje,
        ])->save();
    }

    public function fallar(string $mensaje): void
    {
        $this->fill([
            'estado' => self::ESTADO_ERROR,
            'mensaje' => mb_substr($mensaje, 0, 500),
        ])->save();
    }
}
