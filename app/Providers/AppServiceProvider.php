<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\Paginator;

use App\Auth\BrynexUserProvider;
use App\Models\Beneficiario;
use App\Models\DocumentoCliente;
use App\Models\Cliente;
use App\Observers\BeneficiarioObserver;
use App\Observers\DocumentoObserver;
use App\Observers\ClienteObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cargar helpers globales (garantiza disponibilidad en producción
        // aunque no se haya corrido composer dump-autoload)
        require_once app_path('helpers.php');
    }

    public function boot(): void
    {
        // Provider personalizado: maneja sesiones antiguas que usan cédula como identifier
        Auth::provider('brynex', function ($app, array $config) {
            return new BrynexUserProvider(
                $app['hash'],
                $config['model']
            );
        });

        Beneficiario::observe(BeneficiarioObserver::class);
        DocumentoCliente::observe(DocumentoObserver::class);
        Cliente::observe(ClienteObserver::class);

        // Dispara la factura electrónica cuando entra plata a la cuenta de la
        // razón social emisora y la config está en modo 'factura'.
        \App\Models\Consignacion::observe(\App\Observers\ConsignacionObserver::class);

        // Paginación con vista personalizada (compatible con el diseño del sistema)
        Paginator::defaultView('vendor.pagination.custom');
        Paginator::defaultSimpleView('vendor.pagination.custom');

        // SQL Server: forzar SET options requeridos por columnas computadas / índices filtrados.
        // Usa evento lazy para no crashear en entornos locales sin driver sqlsrv.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Database\Events\ConnectionEstablished::class,
            function ($event) {
                if (str_contains((string)($event->connectionName ?? ''), 'sqlsrv')) {
                    try {
                        \Illuminate\Support\Facades\DB::connection($event->connectionName)->unprepared(
                            'SET ANSI_NULLS ON; SET ANSI_WARNINGS ON; SET ANSI_PADDING ON; SET CONCAT_NULL_YIELDS_NULL ON; SET QUOTED_IDENTIFIER ON;'
                        );
                    } catch (\Throwable $e) { /* silencioso en dev sin SQL Server */ }
                }
            }
        );
    }
}
