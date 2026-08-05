<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission;

/**
 * Catálogo de módulos (funciones) del sistema.
 *
 * Un módulo agrupa los permisos de Spatie que empiezan por su `codigo`:
 * el módulo `facturacion` agrupa facturacion.ver, facturacion.anular, etc.
 *
 * OJO — no confundir con {@see BrynexModulo} (`brynex_modulos`), que es la
 * tabla de FACTURACIÓN de Brynex al aliado (cuánto le cobro a GiMave por
 * WhatsApp). Aquí se decide QUIÉN VE QUÉ; allá CUÁNTO SE COBRA. Se cruzan
 * por `modulo_brynex_codigo`.
 */
class Modulo extends BaseModel
{
    protected $table = 'modulos';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'grupo', 'icono', 'ruta_nombre',
        'restringido', 'solo_brynex', 'modulo_brynex_codigo', 'orden', 'activo',
    ];

    protected $casts = [
        'restringido' => 'boolean',
        'solo_brynex' => 'boolean',
        'activo' => 'boolean',
    ];

    public const GRUPOS = [
        'operacion' => 'Operación',
        'financiero' => 'Financiero',
        'reportes' => 'Reportes',
        'comunicacion' => 'Comunicación',
        'administracion' => 'Administración',
        'brynex' => 'BryNex Global',
    ];

    public function permisos(): HasMany
    {
        return $this->hasMany(Permission::class, 'modulo_id')->orderBy('orden');
    }

    /**
     * Solo los que tiene sentido marcar a mano en la pantalla de permisos:
     * los que el rol `usuario` NO trae, más los restringidos. Ver la migración
     * `add_asignable_to_permissions`.
     */
    public function permisosAsignables(): HasMany
    {
        return $this->permisos()->where('asignable', true);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    public function scopeDelGrupo($query, string $grupo)
    {
        return $query->where('grupo', $grupo);
    }

    public function grupoNombre(): string
    {
        return self::GRUPOS[$this->grupo] ?? $this->grupo;
    }
}
