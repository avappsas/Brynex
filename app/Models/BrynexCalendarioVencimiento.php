<?php

namespace App\Models;

/**
 * Cuándo vence cada obligación. La DIAN vence por el último dígito del NIT y
 * publica un calendario nuevo cada año, así que esto es parametría anual.
 *
 * `ultimo_digito` en null significa "misma fecha para todos", como la
 * renovación de la matrícula mercantil.
 */
class BrynexCalendarioVencimiento extends BaseModel
{
    protected $table = 'brynex_calendario_vencimientos';

    protected $fillable = [
        'anio', 'obligacion_codigo', 'periodo', 'ultimo_digito', 'fecha_vencimiento',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
    ];

    /**
     * Fecha de vencimiento, o null si ese año no tiene calendario cargado.
     * Los años viejos se generan sin fecha a propósito: inventar un
     * vencimiento es peor que dejarlo vacío.
     */
    public static function buscar(int $anio, string $codigo, int $periodo, int $ultimoDigito): ?\Carbon\Carbon
    {
        $fila = static::where('anio', $anio)
            ->where('obligacion_codigo', $codigo)
            ->where('periodo', $periodo)
            ->where(function ($q) use ($ultimoDigito) {
                $q->where('ultimo_digito', $ultimoDigito)
                    ->orWhereNull('ultimo_digito');
            })
            ->orderByRaw('CASE WHEN ultimo_digito IS NULL THEN 1 ELSE 0 END')
            ->first();

        return $fila?->fecha_vencimiento;
    }
}
