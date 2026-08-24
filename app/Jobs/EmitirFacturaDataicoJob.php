<?php

namespace App\Jobs;

use App\Models\DataicoConfiguracion;
use App\Services\Dataico\EmisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Emite ante Dataico un grupo de factura de Brynex.
 *
 * Se dispara cuando entra una consignación a la cuenta de la razón social
 * emisora y la configuración está en modo `factura`. El job NO confía en quien
 * lo despacha: vuelve a correr la selección completa, así que si la factura no
 * califica (fecha anterior al corte, ya emitida, admón en cero) no hace nada.
 *
 * `$tries = 1` a propósito. Los reintentos de red ya los hace el cliente HTTP,
 * y un reintento de cola sobre una factura rechazada por datos solo repetiría
 * el rechazo. Lo que quedó en `error` se reintenta desde el cierre diario o a
 * mano, con el dato ya corregido.
 */
class EmitirFacturaDataicoJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $aliadoId,
        public int $numeroFactura,
    ) {}

    /**
     * Una sola emisión en vuelo por factura. Es la primera de dos defensas
     * contra la doble emisión; la segunda, y la que de verdad manda, es el
     * reclamo atómico de `dataico_envios` en EmisionService — el lock de la
     * cola vive en la caché de archivo, que es por servidor.
     */
    public int $uniqueFor = 900;

    public function uniqueId(): string
    {
        return "dataico:{$this->aliadoId}:{$this->numeroFactura}";
    }

    public function handle(EmisionService $emision): void
    {
        $cfg = DataicoConfiguracion::activaDe($this->aliadoId);

        if (! $cfg || ! $cfg->estaCompleta() || $cfg->modo !== DataicoConfiguracion::MODO_FACTURA) {
            return;
        }

        $r = $emision->emitirNumeroFactura($cfg, $this->numeroFactura);

        if ($r !== null && $r['resultado'] === 'errores') {
            Log::warning('[dataico] emisión por factura falló', [
                'aliado_id' => $this->aliadoId,
                'numero_factura' => $this->numeroFactura,
                'mensaje' => $r['mensaje'],
            ]);
        }
    }
}
