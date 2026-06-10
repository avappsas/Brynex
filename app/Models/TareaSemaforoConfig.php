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

    protected static $_cache = [];

    /**
     * Obtiene la configuración del semáforo para un tipo de tarea.
     * Busca primero config específica del aliado, luego global (aliado_id = null).
     */
    public static function configParaTipo(string $tipo, ?int $alidoId = null): ?self
    {
        $key = "{$tipo}_" . ($alidoId ?? 'global');
        if (array_key_exists($key, static::$_cache)) {
            return static::$_cache[$key];
        }

        if ($alidoId) {
            $config = static::where('tipo_tarea', $tipo)->where('aliado_id', $alidoId)->first();
            if ($config) {
                return static::$_cache[$key] = $config;
            }
        }
        $config = static::where('tipo_tarea', $tipo)->whereNull('aliado_id')->first();
        return static::$_cache[$key] = $config;
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
