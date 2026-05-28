<?php

namespace App\Jobs;

use App\Models\{WhatsappConfig, WhatsappMensaje};
use App\Services\WhatsappApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Job para descargar media recibida del cliente desde los servidores de Meta.
 *
 * Meta almacena los archivos media por 30 días. Este job los descarga
 * y guarda en storage local para tenerlos disponibles permanentemente.
 *
 * Se ejecuta en background para no bloquear el procesamiento del webhook.
 */
class WhatsappDescargarMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 120;

    // Esperar 10s, 30s, 60s... entre reintentos (backoff exponencial)
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(
        protected int $mensajeId,
        protected int $alidoId
    ) {}

    public function handle(WhatsappApiService $apiService): void
    {
        $mensaje = WhatsappMensaje::find($this->mensajeId);

        if (!$mensaje || empty($mensaje->media_wa_id)) {
            return;
        }

        // Si ya fue descargado, no repetir
        if (!empty($mensaje->media_url) && $mensaje->mediaExiste()) {
            return;
        }

        $config = WhatsappConfig::paraAliado($this->alidoId);

        if (!$config->credencialesCompletas()) {
            Log::warning("WhatsApp media: sin credenciales para aliado {$this->alidoId}");
            return;
        }

        $path = $apiService->descargarMedia($mensaje->media_wa_id, $config);

        if ($path) {
            $mensaje->update(['media_url' => $path]);
            Log::info("WhatsApp media descargado: {$path}", ['mensaje_id' => $this->mensajeId]);
        } else {
            Log::error("WhatsApp media: falló descarga", [
                'mensaje_id'  => $this->mensajeId,
                'wa_media_id' => $mensaje->media_wa_id,
            ]);
            $this->release(30); // Reintentar en 30 segundos
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("WhatsApp media: job fallido definitivamente", [
            'mensaje_id' => $this->mensajeId,
            'error'      => $e->getMessage(),
        ]);

        // Marcar el mensaje con un error para que el agente sepa que no se pudo descargar
        WhatsappMensaje::find($this->mensajeId)?->update([
            'error_detalle' => 'No se pudo descargar el archivo media: ' . $e->getMessage(),
        ]);
    }
}
