<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que BryNex recuerda de una empresa en el portal de una EPS: el asesor que
 * la EPS le asignó, la última sesión y la huella de una clave rechazada.
 *
 * La clave en sí vive en el módulo de claves (`clave_accesos`); aquí solo se
 * guarda su huella cuando el portal la rechaza, para no volver a probarla hasta
 * que alguien la cambie. `usuario_portal_id` es un sustituto manual opcional.
 */
class EpsPortalEmpresa extends BaseModel
{
    protected $table = 'eps_portal_empresas';

    protected $fillable = [
        'entidad', 'nit', 'usuario_portal_id', 'codigo_asesor', 'nombre_asesor',
        'activo', 'clave_fallida_hash', 'ultimo_error', 'ultima_sesion_at',
    ];

    protected $casts = [
        'activo'           => 'boolean',
        'ultima_sesion_at' => 'datetime',
    ];

    public function usuarioPortal(): BelongsTo
    {
        return $this->belongsTo(EpsUsuarioPortal::class, 'usuario_portal_id');
    }

    public static function de(string $entidad, string $nit): self
    {
        return static::firstOrCreate(
            ['entidad' => $entidad, 'nit' => preg_replace('/\D/', '', $nit)],
            ['activo' => true]
        );
    }
}
