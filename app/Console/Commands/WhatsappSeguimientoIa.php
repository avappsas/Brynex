<?php

namespace App\Console\Commands;

use App\Events\WhatsappConversacionActualizada;
use App\Events\WhatsappMensajeNuevo;
use App\Models\{IaConfiguracionAliado, WhatsappConfig, WhatsappConversacion, WhatsappMensaje};
use App\Services\Ia\SeguimientoEvaluador;
use App\Services\WhatsappApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Seguimiento comercial automático: si el Asistente IA atendió a alguien y quedó una
 * afiliación en el aire, le manda un único mensaje para no dejar enfriar la venta.
 *
 * NO se le escribe a todo el que se quede callado. Antes de enviar nada, SeguimientoEvaluador
 * revisa de qué se trató la conversación: un cliente activo que pidió su planilla y dio las
 * gracias no tiene ninguna afiliación pendiente, y un "¿quieres avanzar con la afiliación?"
 * horas después le queda fuera de lugar.
 *
 * Solo una vez por espera — cuando el cliente escribe de nuevo,
 * WhatsappConversacion::renovarVentana() limpia el flag y vuelve a habilitarse.
 *
 * Ejecución manual: php artisan whatsapp:seguimiento-ia [--dry-run]
 */
class WhatsappSeguimientoIa extends Command
{
    protected $signature = 'whatsapp:seguimiento-ia {--dry-run : Muestra qué enviaría sin escribir ni enviar nada}';

    protected $description = 'Escribe a los prospectos con una afiliación pendiente que llevan horas sin responder';

    /** Espera mínima sin respuesta del cliente antes de hacer seguimiento. */
    private const HORAS_ESPERA = 3;

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
        $omitidos = 0;

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

            // ¿De verdad quedó algo pendiente? Un cliente que ya resolvió su trámite no
            // debe recibir un mensaje de venta.
            $decision = SeguimientoEvaluador::evaluar($conversacion, $iaConfig);

            if (!$decision['seguir']) {
                $this->line("Omitido    -> #{$conversacion->id}: {$decision['motivo']}");
                // Se marca igual para no volver a evaluarlo (y volver a pagar la consulta)
                // en cada corrida. Si el cliente escribe, renovarVentana() lo reabre.
                if (!$dryRun) {
                    $conversacion->update(['seguimiento_enviado_at' => now()]);
                }
                $omitidos++;
                continue;
            }

            $textoFirmado = "🤖 *{$iaConfig->nombreBot()}:*\n" . $decision['mensaje'];

            $this->line("Seguimiento -> #{$conversacion->id} ({$conversacion->wa_contact_id}): {$decision['motivo']}");

            if ($dryRun) {
                $this->line("             \"{$decision['mensaje']}\"");
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

        $this->info(($dryRun ? '[dry-run] ' : '') . "Seguimientos enviados: {$enviados} · omitidos: {$omitidos}");

        return self::SUCCESS;
    }
}
