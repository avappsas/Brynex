<?php

namespace App\Jobs;

use App\Services\AlertaOperativaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Manda la alerta fuera del request. La llamada a la API de Meta tarda cerca
 * de un segundo: hacerla en línea retrasaría el login de quien la dispara.
 *
 * En producción `QUEUE_CONNECTION=database` con workers activos; en local es
 * `sync` y se ejecuta al vuelo, que para desarrollo es lo cómodo.
 */
class EnviarAlertaOperativa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 30;

    public function __construct(
        public string $origen,
        public string $mensaje,
        public ?string $claveUnica = null,
        public int $minutosSilencio = 60,
    ) {}

    public function handle(AlertaOperativaService $alertas): void
    {
        if ($this->claveUnica) {
            $alertas->enviarUnaVez($this->claveUnica, $this->origen, $this->mensaje, $this->minutosSilencio);

            return;
        }

        $alertas->enviar($this->origen, $this->mensaje);
    }
}
