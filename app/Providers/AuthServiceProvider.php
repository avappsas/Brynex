<?php

namespace App\Providers;

use App\Models\BrynexModuloAliado;
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
         * Tres reglas transversales, antes de mirar roles y permisos:
         *
         *  0. **Lo negado gana sobre todo.** `users.permisos_negados` le quita
         *     a un usuario concreto un permiso que su rol sí trae — incluido
         *     el superadmin. Es el único mecanismo que revoca en vez de
         *     otorgar. Se maneja con `permisos:negar`.
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

            // Regla 0: lo negado a este usuario no lo tiene, ni siendo
            // superadmin (ver `users.permisos_negados`).
            if ($user->tienePermisoNegado($ability)) {
                return false;
            }

            if (PermisoService::esSoloBrynex($ability) && ! $user->es_brynex) {
                return false;
            }

            if ($user->hasRole('superadmin') && ! PermisoService::esRestringido($ability)) {
                return true;
            }

            return null;
        });

        /**
         * Automatización de portales (ARL por API, portales de EPS, Conciliar EPS
         * y Buzón). BryNex la usa en cualquier aliado; los usuarios del aliado,
         * solo si BryNex le activó el módulo `automatizacion_portales` al aliado
         * activo. No es un permiso del catálogo, así que el Gate::before no la toca.
         */
        Gate::define('automatizar-portales', function (User $user) {
            if ($user->es_brynex) {
                return true;
            }

            return BrynexModuloAliado::aliadoTiene((int) session('aliado_id_activo'), 'automatizacion_portales');
        });

        /**
         * Solo la parte de ARL: afiliar, anular, renovar y certificado en ARL
         * Sura, y afiliar/anular/retirar en Colmena, por sus APIs. Un aliado
         * puede tener esto sin los portales de EPS, Conciliar EPS ni el Buzón
         * (módulo `arl_api`); quien tiene la automatización completa lo trae.
         */
        Gate::define('automatizar-arl', function (User $user) {
            if ($user->es_brynex) {
                return true;
            }

            $aliado = (int) session('aliado_id_activo');

            return BrynexModuloAliado::aliadoTiene($aliado, 'arl_api')
                || BrynexModuloAliado::aliadoTiene($aliado, 'automatizacion_portales');
        });
    }
}
