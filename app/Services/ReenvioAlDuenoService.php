<?php

namespace App\Services;

use App\Models\Aliado;
use App\Models\WhatsappConfig;
use App\Models\WhatsappConversacion;
use App\Models\WhatsappPlantilla;
use Illuminate\Support\Facades\Log;

/**
 * Le reenvía al WhatsApp personal del dueño lo que llega a ciertos contactos.
 *
 * Existe porque hay conversaciones que no se atienden desde el inbox: los deudores de sus
 * préstamos personales y —desde sep-2026— los asesores que responden a las piezas de
 * reclutamiento, que él cierra directamente. Marcar la conversación como pendiente no basta:
 * eso solo se ve entrando al panel, y quien contesta un anuncio a las nueve de la noche no
 * espera a mañana.
 *
 * Fuera de la ventana de 24 horas Meta no deja mandar texto libre, así que se cae a la
 * plantilla de notificación. Si falla algo, se traga la excepción a propósito: esto corre
 * dentro de la recepción del mensaje del cliente, y ese camino no puede romperse por un aviso.
 */
class ReenvioAlDuenoService
{
    public function __construct(private WhatsappApiService $whatsappApi) {}

    /**
     * @param  string  $waFrom  Número de quien escribió.
     * @param  string  $titulo  Encabezado corto: de qué es el aviso.
     */
    public function enviar(string $waFrom, ?string $nombreContacto, string $texto, string $titulo): bool
    {
        try {
            $numeroDueno = config('finanzas.whatsapp_personal_dueno');
            if (! $numeroDueno) {
                return false;
            }

            // Si el que escribió es él mismo, no hay nada que reenviarle: se mandaría un aviso
            // a sí mismo y, peor, renovaría su propia ventana con un mensaje que no leyó nadie.
            if (preg_replace('/\D/', '', $waFrom) === preg_replace('/\D/', '', $numeroDueno)) {
                return false;
            }

            // El reenvío sale SIEMPRE por la línea de Brygar: es la que tiene la plantilla de
            // notificación aprobada y la ventana abierta con el dueño.
            $aliado = Aliado::where('nombre', 'like', '%brygar%')->first();
            $config = $aliado ? WhatsappConfig::paraAliado($aliado->id) : null;
            if (! $config) {
                return false;
            }

            $nombre = $nombreContacto ?: $waFrom;
            $cuerpo = "🔔 *{$titulo}*\n{$nombre} ({$waFrom})\n\"{$texto}\"";

            $conversacionDueno = WhatsappConversacion::where('aliado_id', $config->aliado_id)
                ->where('wa_contact_id', $numeroDueno)
                ->first();

            if ($conversacionDueno && $conversacionDueno->ventanaActiva()) {
                // Dentro de la ventana el texto libre no gasta plantilla.
                $this->whatsappApi->enviarTexto($numeroDueno, $cuerpo, $config);

                return true;
            }

            $plantilla = WhatsappPlantilla::delAliado($config->aliado_id)
                ->aprobadas()
                ->where(function ($q) {
                    $q->where('nombre', 'notificacion_brynex')
                        ->orWhere('nombre', 'notificaciones_brynex')
                        ->orWhere('nombre', 'notificar_brynex')
                        ->orWhere('nombre', 'like', '%notificacion%brynex%')
                        ->orWhere('nombre', 'like', '%brynex%notificacion%');
                })
                ->first();

            if ($plantilla) {
                $this->whatsappApi->enviarTemplate(
                    $numeroDueno,
                    $plantilla,
                    ["{$titulo} — {$nombre}", mb_substr($texto, 0, 1000)],
                    $config
                );

                return true;
            }

            $this->whatsappApi->enviarTexto($numeroDueno, $cuerpo, $config);

            return true;
        } catch (\Throwable $e) {
            Log::error('No se pudo reenviar al dueño: '.$e->getMessage());

            return false;
        }
    }
}
