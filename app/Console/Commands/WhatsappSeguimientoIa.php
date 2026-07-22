<?php

namespace App\Console\Commands;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Models\{IaConfiguracionAliado, WhatsappConfig, WhatsappConversacion, WhatsappMensaje};
use App\Services\WhatsappApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Seguimiento comercial automático: si el Asistente IA respondió (ej. una cotización)
 * y el cliente no volvió a escribir en un tiempo, le manda un único mensaje de
 * seguimiento para no dejar la venta enfriarse. Solo una vez por espera — cuando el
 * cliente escribe de nuevo, WhatsappConversacion::renovarVentana() limpia el flag y
 * vuelve a habilitarse para la próxima vez que se quede callado.
 *
 * Ejecución manual: php artisan whatsapp:seguimiento-ia [--dry-run]
 */
class WhatsappSeguimientoIa extends Command
{
    protected $signature = 'whatsapp:seguimiento-ia {--dry-run : Muestra qué enviaría sin escribir ni enviar nada}';

    protected $description = 'Envía un mensaje de seguimiento a clientes que la IA atendió y no respondieron en 2 horas';

    /** Espera mínima sin respuesta del cliente antes de hacer seguimiento. */
    private const HORAS_ESPERA = 2;

    private const MENSAJES = [
        '¡Hola {nombre}! 👋 ¿Pudiste revisar la información que te compartí? Cualquier duda que tengas, con gusto te ayudo. 😊',
        'Hola {nombre}, quedo pendiente por si quieres avanzar con la afiliación o conocer otra opción más económica. ¡Aquí estoy para ayudarte! 🙌',
    ];

    public function handle(WhatsappApiService $whatsappApi): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limite = now()->subHours(self::HORAS_ESPERA);

        $candidatas = WhatsappConversacion::activas()
            ->where('bot_activo', true)
            ->whereNull('seguimiento_enviado_at')
            ->where('ventana_activa_hasta', '>', now())
            ->whereHas('mensajes', function ($q) use ($limite) {
                $q->where('direccion', 'saliente')
                  ->where('es_bot', true)
                  ->where('created_at', '<=', $limite);
            })
            ->get();

        $enviados = 0;

        foreach ($candidatas as $conversacion) {
            $ultimoMensaje = WhatsappMensaje::where('conversacion_id', $conversacion->id)
                ->orderByDesc('created_at')
                ->first();

            // Si el último mensaje real ya no es del bot (el cliente sí respondió, o un
            // humano tomó la conversación después), no corresponde hacer seguimiento.
            if (!$ultimoMensaje || $ultimoMensaje->direccion !== 'saliente' || !$ultimoMensaje->es_bot) {
                continue;
            }
            if ($ultimoMensaje->created_at->gt($limite)) {
                continue;
            }

            $iaConfig = IaConfiguracionAliado::where('aliado_id', $conversacion->aliado_id)->first();
            if (!$iaConfig || !$iaConfig->activo_whatsapp) {
                continue;
            }

            $config = WhatsappConfig::paraAliado($conversacion->aliado_id);
            if (!$config->credencialesCompletas()) {
                continue;
            }

            $nombreCliente = $conversacion->nombreMostrar();
            $textoBase = self::MENSAJES[array_rand(self::MENSAJES)];
            $texto = str_replace('{nombre}', $nombreCliente, $textoBase);
            $textoFirmado = "🤖 *{$iaConfig->nombreBot()}:*\n" . $texto;

            $this->line("Seguimiento -> conversación #{$conversacion->id} ({$conversacion->wa_contact_id})");

            if ($dryRun) {
                $enviados++;
                continue;
            }

            $envio = $whatsappApi->enviarTexto($conversacion->wa_contact_id, $textoFirmado, $config);
            if (!$envio['ok']) {
                Log::warning('Seguimiento IA WhatsApp: fallo al enviar', [
                    'error' => $envio['error'] ?? null,
                    'conversacion_id' => $conversacion->id,
                ]);
                continue;
            }

            $mensajeBot = WhatsappMensaje::create([
                'conversacion_id' => $conversacion->id,
                'aliado_id'       => $conversacion->aliado_id,
                'wa_message_id'   => $envio['wa_message_id'],
                'direccion'       => 'saliente',
                'tipo'            => 'text',
                'contenido'       => $textoFirmado,
                'estado'          => 'enviado',
                'es_bot'          => true,
            ]);

            $conversacion->update([
                'ultimo_mensaje_at'      => now(),
                'seguimiento_enviado_at' => now(),
            ]);

            broadcast(new WhatsappMensajeNuevo($mensajeBot, $conversacion));
            broadcast(new WhatsappConversacionActualizada($conversacion));

            $enviados++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Seguimientos procesados: {$enviados}");

        return self::SUCCESS;
    }
}
