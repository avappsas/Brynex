<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PermisoService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        /**
         * Dos reglas transversales, antes de mirar roles y permisos:
         *
         *  1. **Los módulos `solo_brynex` exigen `es_brynex`.** El Hub BryNex,
         *     los cobros a aliados y el backup no son del aliado, son de la
         *     empresa dueña de la plataforma. Ni el superadmin de GiMave los ve.
         *
         *  2. **superadmin lo puede todo… menos lo restringido.** Un permiso
         *     marcado `restringido` (las contraseñas en claro, las credenciales
         *     de los operadores PILA, los tokens de Meta) no se hereda por rol:
         *     hay que otorgarlo a un usuario concreto. Así "algunos admin ven
         *     las claves de bancos" es la regla, no la excepción que se olvida.
         *     Devolver null deja seguir la evaluación normal, de modo que el
         *     superadmin que SÍ lo tenga otorgado a dedo pasa igual.
         */
        Gate::before(function (User $user, string $ability) {
            if (! array_key_exists($ability, PermisoService::meta())) {
                return null;   // no es un permiso del catálogo: flujo normal
            }

            if (PermisoService::esSoloBrynex($ability) && ! $user->es_brynex) {
                return false;
            }

            if ($user->hasRole('superadmin') && ! PermisoService::esRestringido($ability)) {
                return true;
            }

            return null;
        });
    }
}
