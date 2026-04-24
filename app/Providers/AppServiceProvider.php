<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
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
    }
}
