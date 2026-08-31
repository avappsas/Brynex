<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credencial del portal de ARL Sura de un aliado.
 *
 * Con ella `ArlSuraSesionService` abre sesión sola cuando la anterior caduca,
 * para que nadie tenga que copiar cookies del navegador.
 */
class ArlCredencial extends BaseModel
{
    protected $table = 'arl_credenciales';

    protected $fillable = [
        'aliado_id', 'nit', 'poliza', 'tipo_documento', 'usuario', 'contrasena',
        'activo', 'ultima_sesion_at', 'ultimo_error',
    ];

    /** La contraseña no sale nunca en un JSON ni en una vista. */
    protected $hidden = ['contrasena'];

    protected $casts = [
        'contrasena'       => 'encrypted',
        'activo'           => 'boolean',
        'ultima_sesion_at' => 'datetime',
    ];

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    /** La credencial general del aliado (la que no es de una empresa concreta). */
    public static function activaDe(int $aliadoId): ?self
    {
        return static::where('aliado_id', $aliadoId)->whereNull('nit')->where('activo', true)->first();
    }

    /**
     * La credencial de una empresa, por NIT.
     *
     * Va por NIT y no por razón social porque la misma empresa está registrada
     * en varios aliados: cargarla una vez sirve para todos, y cambiarla la
     * cambia en todas partes.
     */
    public static function deEmpresa(?string $nit): ?self
    {
        $nit = preg_replace('/\D/', '', (string) $nit);

        return $nit ? static::where('nit', $nit)->where('activo', true)->first() : null;
    }
}
