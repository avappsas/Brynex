<?php

namespace App\Jobs;

use App\Models\{
    WhatsappConfig, WhatsappConversacion,
    WhatsappEnvioMasivo, WhatsappEnvioMasivoDetalle,
    WhatsappMensaje, WhatsappPlantilla
};
use App\Services\WhatsappApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Job para procesar envíos masivos de WhatsApp en background.
 *
 * Procesa cada destinatario del lote, respetando los rate limits de Meta:
 *  - Máximo ~80 mensajes por segundo en tier 1
 *  - El job hace micro-sleeps entre envíos para no superar el límite
 *
 * El job se puede reintentar si falla parcialmente.
 */
class WhatsappEnvioMasivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 3600; // 1 hora máximo para lotes grandes

    public function __construct(
        protected int   $envioId,
        protected array $parametrosGlobales = []
    ) {}

    public function handle(WhatsappApiService $apiService): void
    {
        $envio = WhatsappEnvioMasivo::with(['plantilla', 'detalles'])->find($this->envioId);

        if (!$envio || $envio->estado === 'completado') return;

        // Marcar como procesando
        $envio->update(['estado' => 'procesando']);

        $config = WhatsappConfig::paraAliado($envio->aliado_id);

        if (!$config->credencialesCompletas()) {
            $envio->update(['estado' => 'fallido']);
            Log::error("WhatsApp masivo #{$this->envioId}: sin credenciales configuradas");
            return;
        }

        $plantilla = $envio->plantilla;
        $enviados  = 0;
        $fallidos  = 0;
        $omitidos  = 0;

        // Procesar solo los pendientes (permite reanudar si el job falla a mitad)
        $pendientes = $envio->detalles()->where('estado', 'pendiente')->get();

        foreach ($pendientes as $detalle) {
            try {
                // Construir parámetros para esta plantilla
                // Los parámetros globales se mezclan con los específicos del destinatario
                $params = $this->construirParametros($plantilla, $detalle, $this->parametrosGlobales);

                // Obtener o crear la conversación para este destinatario
                $conversacion = $this->obtenerOCrearConversacion(
                    $detalle->wa_numero,
                    $envio->aliado_id,
                    $detalle->nombre_destinatario
                );

                // Enviar el template
                $resultado = $apiService->enviarTemplate(
                    $detalle->wa_numero,
                    $plantilla,
                    $params,
                    $config
                );

                if ($resultado['ok']) {
                    // Guardar el mensaje en la conversación
                    WhatsappMensaje::create([
                        'conversacion_id'      => $conversacion->id,
                        'aliado_id'            => $envio->aliado_id,
                        'wa_message_id'        => $resultado['wa_message_id'],
                        'direccion'            => 'saliente',
                        'tipo'                 => 'template',
                        'plantilla_id'         => $plantilla->id,
                        'plantilla_parametros' => $params,
                        'estado'               => 'enviado',
                        'usuario_id'           => $envio->usuario_id,
                    ]);

                    $conversacion->update(['ultimo_mensaje_at' => now()]);

                    $detalle->update([
                        'estado'        => 'enviado',
                        'wa_message_id' => $resultado['wa_message_id'],
                    ]);

                    $enviados++;
                } else {
                    $detalle->update([
                        'estado' => 'fallido',
                        'error'  => $resultado['error'] ?? 'Error desconocido',
                    ]);
                    $fallidos++;
                }
            } catch (\Exception $e) {
                $detalle->update([
                    'estado' => 'fallido',
                    'error'  => $e->getMessage(),
                ]);
                $fallidos++;
                Log::error("WhatsApp masivo: error en destinatario {$detalle->wa_numero}", [
                    'error' => $e->getMessage(),
                ]);
            }

            // Rate limiting: ~12 envíos/segundo es seguro para tier 1
            // Para lotes grandes pausamos cada 10 mensajes
            if (($enviados + $fallidos) % 10 === 0) {
                usleep(800000); // 0.8 segundos cada 10 mensajes
            }
        }

        // Marcar el envío como completado
        $envio->update([
            'estado'          => 'completado',
            'total_enviados'  => $enviados,
            'total_fallidos'  => $fallidos,
            'total_omitidos'  => $omitidos,
        ]);

        Log::info("WhatsApp masivo #{$this->envioId} completado", [
            'enviados' => $enviados,
            'fallidos' => $fallidos,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        WhatsappEnvioMasivo::find($this->envioId)?->update(['estado' => 'fallido']);
        Log::error("WhatsApp masivo #{$this->envioId}: job fallido", ['error' => $e->getMessage()]);
    }

    private function construirParametros(
        WhatsappPlantilla $plantilla,
        WhatsappEnvioMasivoDetalle $detalle,
        array $parametrosGlobales
    ): array {
        // Si hay parámetros globales definidos por el usuario, usarlos directamente
        if (!empty($parametrosGlobales)) {
            return array_values($parametrosGlobales);
        }

        // Auto-generar parámetros desde el mapa de variables de la plantilla
        $mapa = $plantilla->variables_mapa ?? [];
        if (empty($mapa)) return [];

        $params = [];
        foreach ($mapa as $index => $campo) {
            $params[] = $this->resolverCampo($campo, $detalle);
        }

        return $params;
    }

    private function resolverCampo(string $campo, WhatsappEnvioMasivoDetalle $detalle): string
    {
        return match($campo) {
            'cliente.nombre'              => $detalle->nombre_destinatario,
            'factura.total'               => '',  // Se rellena con datos reales si se pasan en parametros
            'factura.fecha_vencimiento'   => '',
            default                       => '',
        };
    }

    private function obtenerOCrearConversacion(
        string $numero,
        int $alidoId,
        string $nombre
    ): WhatsappConversacion {
        return WhatsappConversacion::firstOrCreate(
            [
                'aliado_id'     => $alidoId,
                'wa_contact_id' => $numero,
                'estado'        => 'abierta',
            ],
            [
                'nombre_contacto'       => $nombre,
                'ultimo_mensaje_at'     => now(),
            ]
        );
    }
}
