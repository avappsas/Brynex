<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un usuario del portal de ARL Sura, con su contraseña.
 *
 * La contraseña es de la persona, no de la empresa: con la misma cédula se
 * entra al portal y desde ahí se administran varias razones sociales. Guardarla
 * aquí —una sola vez— es lo que hace que cambiarla desde cualquier empresa la
 * cambie para todas: no hay copias que puedan quedar viejas.
 */
class ArlUsuarioPortal extends BaseModel
{
    protected $table = 'arl_usuarios_portal';

    protected $fillable = [
        'tipo_documento', 'usuario', 'contrasena',
        'activo', 'ultima_sesion_at', 'ultimo_error',
    ];

    /** La contraseña no sale nunca en un JSON ni en una vista. */
    protected $hidden = ['contrasena'];

    protected $casts = [
        'contrasena'       => 'encrypted',
        'activo'           => 'boolean',
        'ultima_sesion_at' => 'datetime',
    ];

    /** Las empresas que este usuario administra en el portal. */
    public function credenciales(): HasMany
    {
        return $this->hasMany(ArlCredencial::class, 'usuario_portal_id');
    }

    /**
     * Guarda la contraseña de ese usuario, venga de donde venga.
     *
     * Es el único punto por donde debería entrar una clave nueva: al ir contra
     * el usuario y no contra la empresa, actualizarla desde una razón social la
     * deja al día en todas las demás.
     */
    public static function registrar(string $tipoDocumento, string $usuario, string $contrasena): self
    {
        $portal = static::firstOrNew([
            'tipo_documento' => $tipoDocumento,
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
