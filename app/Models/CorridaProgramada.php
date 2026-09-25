<?php

namespace App\Models;

/**
 * Una corrida de la agenda: qué comando, cuándo y cómo salió.
 *
 * La escribe el oyente RegistrarCorridaProgramada desde los eventos del
 * planificador de Laravel, sin que cada comando tenga que hacer nada.
 */
class CorridaProgramada extends BaseModel
{
    protected $table = 'corridas_programadas';

    protected $fillable = [
        'nombre', 'comando', 'inicio', 'fin', 'segundos',
        'exit_code', 'exitosa', 'motivo', 'salida',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fin' => 'datetime',
        'segundos' => 'integer',
        'exit_code' => 'integer',
        'exitosa' => 'boolean',
    ];

    /** Las corridas que empezaron en esa ventana, de la más vieja a la más nueva. */
    public static function entre($desde, $hasta)
    {
        return static::whereBetween('inicio', [$desde, $hasta])->orderBy('inicio')->get();
    }

    /** '5m 12s', o '—' si nunca terminó. */
    public function duracion(): string
    {
        if ($this->segundos === null) {
            return '—';
        }

        return $this->segundos >= 60
            ? intdiv($this->segundos, 60).'m '.($this->segundos % 60).'s'
            : $this->segundos.'s';
    }
}
