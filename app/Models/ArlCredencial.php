<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
        'aliado_id', 'usuario_portal_id', 'nit', 'poliza',
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

    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class);
    }

    /** El usuario del portal con el que se entra a esta empresa. */
    public function usuarioPortal(): BelongsTo
    {
        return $this->belongsTo(ArlUsuarioPortal::class, 'usuario_portal_id');
    }

    // ── Acceso al login ────────────────────────────────────────────
    //
    // Los tres datos del login salen del usuario del portal, que es donde
    // viven de verdad. Las columnas propias quedan como respaldo para filas
    // que todavía no se hayan vinculado.

    public function getTipoDocumentoAttribute($valor): string
    {
        return $this->usuarioPortal?->tipo_documento ?: ($valor ?: 'C');
    }

    public function getUsuarioAttribute($valor): string
    {
        return $this->usuarioPortal?->usuario ?: (string) $valor;
    }

    /**
     * OJO: definir este accessor desactiva el cast `encrypted` de la columna
     * —Laravel prefiere el mutator y entrega el valor crudo—, así que el
     * respaldo tiene que descifrar a mano lo que el cast haría solo.
     */
    public function getContrasenaAttribute($valor): ?string
    {
        if ($portal = $this->usuarioPortal) {
            return $portal->contrasena;
        }

        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException $e) {
            return null; // fila vieja ilegible: la clave vive en el usuario del portal
        }
    }

    /**
     * Registra qué usuario del portal entra a esta empresa.
     *
     * La contraseña se guarda contra el usuario, así que cambiarla desde
     * cualquier razón social la deja al día en todas las que ese mismo usuario
     * administra: es el punto del diseño.
     */
    public static function registrar(
        int $aliadoId,
        string $nit,
        string $tipoDocumento,
        string $usuario,
        string $contrasena,
    ): self {
        $portal = ArlUsuarioPortal::registrar($tipoDocumento, $usuario, $contrasena);

        $credencial = static::firstOrNew(['nit' => $nit]);

        $credencial->fill([
            'aliado_id'         => $aliadoId,
            'usuario_portal_id' => $portal->id,
            'activo'            => true,
            'ultimo_error'      => null,
        ])->save();

        return $credencial->setRelation('usuarioPortal', $portal);
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
