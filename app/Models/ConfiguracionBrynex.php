<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionBrynex extends Model
{
    protected $table = 'configuracion_brynex';
    protected $fillable = ['clave', 'valor', 'descripcion'];

    /** Obtiene un valor de configuración global por su clave */
    public static function obtener(string $clave, mixed $default = null): mixed
    {
        $registro = static::where('clave', $clave)->first();
        return $registro ? $registro->valor : $default;
    }

    /** Actualiza o crea una clave de configuración global */
    public static function establecer(string $clave, mixed $valor): void
    {
        static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => (string) $valor]
        );
    }

    // Helpers rápidos para los porcentajes usados en el cotizador
    public static function salarioMinimo(): float
    {
        return (float) static::obtener('salario_minimo', 1423500);
    }

    public static function pctSaludDependiente(): float
    {
        return (float) static::obtener('pct_salud_dependiente', 4.00);
    }

    public static function pctPensionDependiente(): float
    {
        return (float) static::obtener('pct_pension_dependiente', 16.00);
    }

    public static function pctCajaDependiente(): float
    {
        return (float) static::obtener('pct_caja_dependiente', 4.00);
    }

    public static function pctSaludIndependiente(): float
    {
        return (float) static::obtener('pct_salud_independiente', 12.50);
    }

    public static function pctPensionIndependiente(): float
    {
        return (float) static::obtener('pct_pension_independiente', 16.00);
    }

    public static function pctCajaIndependienteAlto(): float
    {
        return (float) static::obtener('pct_caja_independiente_alto', 2.00);
    }

    public static function pctCajaIndependienteBajo(): float
    {
        return (float) static::obtener('pct_caja_independiente_bajo', 0.60);
    }

    public static function pctIbcIndependienteSugerido(): float
    {
        return (float) static::obtener('pct_ibc_independiente_sugerido', 40.00);
    }

    public static function porcentajeIva(): float
    {
        return (float) static::obtener('porcentaje_iva', 19.00);
    }
}
