<?php

namespace App\Models;

use App\Services\PermisoService;
use App\Traits\HasSqlServerDates;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles {
        hasPermissionTo as private hasPermissionToSpatie;
    }
    // Sanctum solo para los usuarios de servicio que consumen routes/api.php
    // (hoy, el de Cuenta_facil). Los usuarios del panel siguen entrando por
    // sesión: el trait no cambia nada para ellos mientras no se les emita un
    // token.
    use HasApiTokens;
    use HasSqlServerDates;
    use Notifiable, SoftDeletes;

    // Sin conexión fija: usa el 'default' de config/database.php (sqlsrv en
    // producción vía DB_CONNECTION en .env). Fijarla a mano rompía los tests
    // — el override a sqlite de phpunit.xml no podía aplicarse a este modelo,
    // que se carga en cada request de auth. Ver docs/auditoria-calidad.md.

    protected $fillable = [
        'aliado_id',
        'nombre',
        'email',
        'password',
        'cedula',
        'telefono',
        'es_brynex',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'es_brynex' => 'boolean',
        'activo' => 'boolean',
        // Fuera de $fillable a propósito: el formulario de usuarios no debe
        // poder tocarla. Se maneja con `permisos:negar`.
        'permisos_negados' => 'array',
    ];

    // Aliado principal del usuario
    public function aliado(): BelongsTo
    {
        return $this->belongsTo(Aliado::class, 'aliado_id');
    }

    // Aliados extra a los que tiene acceso (pivot - solo usuarios BryNex)
    public function aliados(): BelongsToMany
    {
        return $this->belongsToMany(Aliado::class, 'aliado_user', 'user_id', 'aliado_id')
            ->withPivot('rol', 'activo')
            ->withTimestamps();
    }

    // Verifica si el usuario puede acceder a un aliado dado
    public function puedeAccederAliado(int $alidoId): bool
    {
        // El aliado propio siempre es accesible.
        // El cast a int no es cosmético: sqlsrv devuelve `aliado_id` como
        // string ("1"), así que con `===` esta rama nunca se cumplía y el
        // método devolvía false hasta para el aliado propio.
        if ((int) $this->aliado_id === $alidoId) {
            return true;
        }
        // Superadmin BryNex → accede a CUALQUIER aliado activo
        if ($this->es_brynex && $this->hasRole('superadmin')) {
            return Aliado::where('id', $alidoId)->where('activo', true)->exists();
        }
        // BryNex regular → solo los aliados asignados en el pivot aliado_user
        if ($this->es_brynex) {
            return $this->aliados()
                ->where('aliados.id', $alidoId)
                ->where('aliados.activo', true)
                ->wherePivot('activo', true)
                ->exists();
        }

        return false;
    }

    // Obtiene el aliado activo en sesión (el principal o el seleccionado por BryNex)
    public function alidoActivo(): Aliado
    {
        $alidoIdSesion = session('aliado_id_activo');
        if ($this->es_brynex && $alidoIdSesion) {
            return Aliado::find($alidoIdSesion) ?? $this->aliado;
        }

        return $this->aliado;
    }

    /**
     * Los permisos de módulos marcados `solo_brynex` exigen `es_brynex`, sin
     * importar de dónde vengan.
     *
     * El chequeo tiene que estar AQUÍ y no solo en el `Gate::before` de
     * AuthServiceProvider: Spatie registra su propio `before`
     * (PermissionRegistrar::registerPermissions) y lo hace primero, así que
     * cuando el permiso llega por un rol Spatie contesta `true` y el nuestro
     * ni siquiera corre. Este método es justo el que Spatie consulta, de modo
     * que ya no depende del orden en que arranquen los service providers.
     *
     * Se vio con `formularios_pdf.editar`: al ser el primer permiso
     * `solo_brynex` asignado por rol, cuatro admin de aliados quedaron
     * editando el mapeo de formularios de toda la plataforma.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $nombre = is_string($permission) ? $permission : ($permission->name ?? null);

        if ($nombre !== null && ! $this->es_brynex && PermisoService::esSoloBrynex($nombre)) {
            return false;
        }

        // Mismo motivo: si el permiso le llega por rol o a dedo, Spatie
        // contestaría `true` antes de que el Gate::before vea la negación.
        if ($nombre !== null && $this->tienePermisoNegado($nombre)) {
            return false;
        }

        return $this->hasPermissionToSpatie($permission, $guardName);
    }

    /**
     * Permiso que se le quitó a este usuario aunque su rol lo traiga — el caso
     * de un superadmin de BryNex que no debe ver el estado financiero de los
     * aliados. Gana sobre todo: rol, superadmin y permiso otorgado a mano.
     */
    public function tienePermisoNegado(string $permiso): bool
    {
        return in_array($permiso, $this->permisos_negados ?? [], true);
    }

    /*
     * Una negación no sirve si quien la tiene puede darle la vuelta: un
     * superadmin reparte roles, otorga permisos y cambia contraseñas. Sin
     * estas dos reglas, a quien le negaron el estado financiero podía
     * cambiarle la clave al dueño y entrar como él, subir su otra cuenta a
     * `contable` o marcarle el permiso a dedo a otro usuario.
     */

    /**
     * ¿$este tiene algo de lo que a $quien le negaron? Entonces $quien no
     * puede tocar su ficha ni sus permisos: sería llegar por la puerta de
     * atrás a lo que se le quitó.
     */
    public function tieneLoNegadoA(User $quien): bool
    {
        if ($this->id === $quien->id) {
            return false;
        }

        foreach ($quien->permisos_negados ?? [] as $permiso) {
            if ($this->can($permiso)) {
                return true;
            }
        }

        return false;
    }

    /** ¿Repartir este rol entregaría algo que a este usuario le negaron? */
    public function rolDaLoNegado(string $rol): bool
    {
        $negados = $this->permisos_negados ?? [];
        if (! $negados) {
            return false;
        }

        // superadmin no lleva permisos en la tabla: lo tiene todo menos lo
        // restringido, por el Gate::before.
        if ($rol === 'superadmin') {
            foreach ($negados as $permiso) {
                if (! PermisoService::esRestringido($permiso)) {
                    return true;
                }
            }

            return false;
        }

        $delRol = \Spatie\Permission\Models\Role::findByName($rol, 'web')->permissions->pluck('name')->all();

        return (bool) array_intersect($negados, $delRol);
    }
}
