<?php

namespace App\Providers;

use App\Listeners\RegistrarCorridaProgramada;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Cómo salió cada comando de la agenda. Va aquí y no en cada comando
        // porque son treinta: así ninguno se puede olvidar de anotarse.
        ScheduledTaskStarting::class => [
            [RegistrarCorridaProgramada::class, 'empezando'],
        ],
        ScheduledTaskFinished::class => [
            [RegistrarCorridaProgramada::class, 'termino'],
        ],
        ScheduledBackgroundTaskFinished::class => [
            [RegistrarCorridaProgramada::class, 'terminoEnSegundoPlano'],
        ],
        ScheduledTaskFailed::class => [
            [RegistrarCorridaProgramada::class, 'fallo'],
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
