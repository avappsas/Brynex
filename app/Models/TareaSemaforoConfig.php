<?php

namespace App\Models;

use App\Models\BaseModel;

class TareaSemaforoConfig extends BaseModel
{
    public $timestamps = false;
    protected $table = 'tarea_semaforo_config';

    protected $fillable = [
        'aliado_id', 'tipo_tarea', 'dias_limite', 'dias_alerta_amarilla',
    ];

    /**
     * Obtiene la configuración del semáforo para un tipo de tarea.
     * Busca primero config específica del aliado, luego global (aliado_id = null).
     */
    public static function configParaTipo(string $tipo, ?int $alidoId = null): ?self
    {
        if ($alidoId) {
            $config = static::where('tipo_tarea', $tipo)->where('aliado_id', $alidoId)->first();
            if ($config) return $config;
        }
        return static::where('tipo_tarea', $tipo)->whereNull('aliado_id')->first();
    }

    /**
     * Calcula la fecha límite para una tarea nueva del tipo dado.
     */
    public static function fechaLimiteParaTipo(string $tipo, ?int $alidoId = null): ?\Carbon\Carbon
    {
        $config = static::configParaTipo($tipo, $alidoId);
        if (!$config) return null;
        return now()->addDays($config->dias_limite);
    }
}
