<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un usuario del portal de empleadores de una EPS, con su contraseña.
 *
 * Igual que en ARL Sura, la clave es de la persona y no de la empresa: con el
 * mismo usuario se administran varias razones sociales, así que se guarda una
 * sola vez y cambiarla la cambia para todas.
 */
class EpsUsuarioPortal extends BaseModel
{
    protected $table = 'eps_usuarios_portal';

    protected $fillable = [
        'entidad', 'tipo_documento', 'usuario', 'contrasena',
        'activo', 'ultima_sesion_at', 'ultimo_error',
    ];

    /** La contraseña no sale nunca en un JSON ni en una vista. */
    protected $hidden = ['contrasena'];

    protected $casts = [
        'contrasena'       => 'encrypted',
        'activo'           => 'boolean',
        'ultima_sesion_at' => 'datetime',
    ];

    public function empresas(): HasMany
    {
        return $this->hasMany(EpsPortalEmpresa::class, 'usuario_portal_id');
    }

    /** Único punto por donde entra una clave nueva. */
    public static function registrar(string $entidad, string $tipoDocumento, string $usuario, string $contrasena): self
    {
        $portal = static::firstOrNew([
            'entidad'        => $entidad,
            'tipo_documento' => strtoupper($tipoDocumento),
            'usuario'        => trim($usuario),
        ]);

        $portal->fill([
            'contrasena'   => $contrasena,
            'activo'       => true,
            'ultimo_error' => null, // clave nueva: el rechazo anterior ya no cuenta
        ])->save();

        return $portal;
    }
}
