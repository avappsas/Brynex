<?php

namespace App\Models;

/**
 * Una corrida de la revisión de subsidios de una caja, para una empresa y un día.
 *
 * Es el candado que evita repetir el barrido: el comando nocturno y el disparo
 * desde el portal preguntan `yaSeHizo()` antes de arrancar.
 *
 * La unidad es la **empresa**, no el aliado: la clave del portal vive en la
 * razón social y lo que se consulta es la empresa entera, así que una corrida
 * vale para todos los aliados que la compartan. `aliado_id` queda solo como
 * dato de quién la disparó.
 */
class CajaRevision extends BaseModel
{
    protected $table = 'caja_revisiones';

    protected $fillable = [
        'aliado_id', 'entidad', 'nit', 'fecha', 'estado', 'alcance',
        'revisados', 'bloqueados', 'tareas_nuevas', 'tareas_cerradas', 'mensaje',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public const ENTIDAD_COMFANDI = 'comfandi';

    public const ENTIDAD_COMFENALCO = 'comfenalco';

    public const ESTADO_CORRIENDO = 'corriendo';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ALCANCE_CANDIDATOS = 'candidatos';

    public const ALCANCE_COMPLETA = 'completa';

    /**
     * ¿Ya se revisó hoy esta empresa? Una corrida con error no cuenta: se puede
     * reintentar.
     *
     * Una que quedó en `corriendo` sí cuenta durante dos horas —es la que está
     * en curso— y después se da por colgada, para que un proceso muerto no deje
     * a la empresa sin revisión el resto del día.
     */
    public static function yaSeHizo(string $nit, string $entidad = self::ENTIDAD_COMFANDI): bool
    {
        $hoy = static::where('nit', static::nit($nit))->where('entidad', $entidad)
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
     * Abre (o retoma) la corrida del día de esa empresa y la deja en `corriendo`.
     */
    public static function abrir(string $nit, string $entidad, string $alcance, ?int $aliadoId = null): self
    {
        $revision = static::firstOrNew([
            'entidad' => $entidad,
            'nit' => static::nit($nit),
            'fecha' => today()->toDateString(),
        ]);

        $revision->fill([
            'aliado_id' => $aliadoId ?? $revision->aliado_id,
            'estado' => self::ESTADO_CORRIENDO,
            'alcance' => $alcance,
            'mensaje' => null,
        ])->save();

        return $revision;
    }

    /** Solo dígitos: el portal da el NIT con el de verificación pegado. */
    public static function nit(string $nit): string
    {
        return preg_replace('/\D/', '', $nit);
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
