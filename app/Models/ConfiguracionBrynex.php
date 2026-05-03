<?php

namespace App\Models;

use App\Models\BaseModel;

class ConfiguracionBrynex extends BaseModel
{
    protected $table = 'configuracion_brynex';
    protected $fillable = ['clave', 'valor', 'descripcion'];

    /** Cache en memoria para evitar N+1 queries cuando se llama desde bucles */
    private static array $cache = [];

    /**
     * Precarga TODAS las claves de configuración en 1 sola query.
     * Llamar al inicio de vistas/controllers que consulten múltiples claves en un bucle.
     */
    public static function precargar(): void
    {
        self::$cache = static::pluck('valor', 'clave')->toArray();
    }

    /** Limpia el cache en memoria (útil en tests o tras escribir con establecer()) */
    public static function limpiarCache(): void
    {
        self::$cache = [];
    }

    /** Obtiene un valor de configuración global por su clave (con cache en memoria) */
    public static function obtener(string $clave, mixed $default = null): mixed
    {
        if (!array_key_exists($clave, self::$cache)) {
            self::$cache[$clave] = static::where('clave', $clave)->value('valor');
        }
        return self::$cache[$clave] ?? $default;
    }

    /** Actualiza o crea una clave de configuración global (e invalida el cache) */
    public static function establecer(string $clave, mixed $valor): void
    {
        static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => (string) $valor]
        );
        // Invalidar cache para que la próxima lectura refleje el nuevo valor
        unset(self::$cache[$clave]);
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

    /**
     * ¿Está activa la regla de AFP obligatorio?
     *
     * Si true, los contratos de modalidad Dependiente E (id=0),
     * I Venc (id=10) e I Act (id=11) no pueden usar planes sin AFP,
     * a menos que el cliente esté exento (tipo_doc ≠ CC, mujer ≥50, hombre ≥55).
     */
    public static function reglaAfpObligatorio(): bool
    {
        return (bool)(int) static::obtener('regla_afp_obligatorio', 0);
    }

    /**
     * Tasa de mora PILA vigente (% efectivo anual).
     * Art. 635 ET: tasa usura consumo Superfinanciera menos 2 pp.
     * Configurable desde el panel BryNex sin necesidad de deploy.
     */
    public static function tasaMoraPila(): float
    {
        return (float) static::obtener('tasa_mora_pila', 26.17);
    }
}
