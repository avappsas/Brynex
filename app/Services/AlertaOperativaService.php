<?php

namespace App\Services;

use App\Models\WhatsappConfig;
use App\Models\WhatsappConversacion;
use App\Models\WhatsappPlantilla;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Avisos operativos a BryNex por WhatsApp: backups caídos, accesos raros,
 * usuarios creados por un aliado, despliegues.
 *
 * Sale por la cuenta de Brygar. Si el destinatario tiene abierta la ventana de
 * 24h (escribió o tocó «Mantener activo» hace menos de un día), va como texto
 * libre, que no gasta envíos de plantilla. Si no, va con la plantilla aprobada
 * `notificar_brynex` (dos variables: origen y mensaje), que sale aunque la
 * ventana esté cerrada — una alerta tiene que salir siempre. Es la misma regla
 * de ReenvioAlDuenoService.
 *
 * Esta clase la comparten el comando `whatsapp:alerta-backup` y las alertas de
 * seguridad, para que el número, la plantilla y el saneado vivan en un solo sitio.
 */
class AlertaOperativaService
{
    private const ALIADO_ID = 2;                 // Brygar

    private const NOMBRE_PLANTILLA = 'notificar_brynex';

    /** Tope de Meta por variable: 1024. Se recorta muy por debajo. */
    private const MAX_PARAM = 600;

    /** El texto libre admite 4096; una alerta se lee en la notificación. */
    private const MAX_TEXTO = 1500;

    public function __construct(private WhatsappApiService $whatsappApi) {}

    public function numeroDestino(): string
    {
        return (string) config('services.whatsapp.alertas_numero', '3117762689');
    }

    /**
     * Envía la alerta. Devuelve false y deja rastro en el log si no pudo:
     * un aviso que no sale nunca debe tumbar la operación que lo disparó.
     */
    public function enviar(string $origen, string $mensaje): bool
    {
        return $this->enviarA($this->numeroDestino(), $origen, $mensaje);
    }

    /**
     * Igual que enviar(), pero a un número concreto en vez del de guardia.
     *
     * Lo usa el módulo de razones sociales para avisarle los vencimientos al
     * contador asignado a cada una, que no es quien recibe las alertas de
     * infraestructura.
     */
    public function enviarA(string $numero, string $origen, string $mensaje): bool
    {
        try {
            $config = WhatsappConfig::paraAliado(self::ALIADO_ID);

            if (! $config->credencialesCompletas()) {
                Log::error('AlertaOperativa: credenciales de WhatsApp incompletas', ['aliado_id' => self::ALIADO_ID]);

                return false;
            }

            if ($this->ventanaAbierta($numero)) {
                $envio = $this->whatsappApi->enviarTexto(
                    $numero,
                    '🔔 *'.$this->sanear($origen)."*\n".Str::limit(trim($mensaje), self::MAX_TEXTO, '…'),
                    $config
                );

                if ($envio['ok'] ?? false) {
                    return true;
                }

                // La ventana pudo cerrarse entre la consulta y el envío: se cae a la plantilla.
                Log::warning('AlertaOperativa: el texto libre no salió, se intenta con plantilla', [
                    'origen' => $origen,
                    'error' => $envio['error'] ?? null,
                ]);
            }

            $plantilla = WhatsappPlantilla::delAliado(self::ALIADO_ID)
                ->aprobadas()
                ->where('nombre', self::NOMBRE_PLANTILLA)
                ->first();

            if (! $plantilla) {
                Log::error('AlertaOperativa: plantilla no encontrada', ['plantilla' => self::NOMBRE_PLANTILLA]);

                return false;
            }

            $envio = $this->whatsappApi->enviarTemplate(
                $numero,
                $plantilla,
                [$this->sanear($origen), $this->sanear($mensaje)],
                $config
            );

            if (! ($envio['ok'] ?? false)) {
                Log::error('AlertaOperativa: falló el envío', [
                    'origen' => $origen,
                    'error' => $envio['error'] ?? null,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('AlertaOperativa: excepción al enviar', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Igual que enviar(), pero se calla si ya avisó de lo mismo hace poco.
     *
     * Sin esto, un equipo nuevo que reintenta el login manda una alerta por
     * intento: a la tercera se dejan de leer, y una alerta que no se lee es
     * peor que ninguna porque da sensación de cobertura.
     */
    public function enviarUnaVez(string $clave, string $origen, string $mensaje, int $minutos = 60): bool
    {
        $cacheKey = 'alerta_operativa:'.md5($clave);

        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addMinutes($minutos));

        return $this->enviar($origen, $mensaje);
    }

    /**
     * Si el destinatario le escribió a la línea de Brygar en las últimas 24h.
     *
     * Se mira en dos sitios. La conversación guarda el número con el 57 adelante,
     * igual que llega de Meta. Y los mensajes que se desvían a GARVIS no llegan a
     * la conversación: por cada uno, GARVIS deja la clave `whatsapp_ventana:<número>`
     * en caché con 24 horas de vida.
     */
    private function ventanaAbierta(string $numero): bool
    {
        $numero = WhatsappApiService::normalizarNumero($numero);

        if (Cache::has('whatsapp_ventana:'.$numero)) {
            return true;
        }

        $conversacion = WhatsappConversacion::where('aliado_id', self::ALIADO_ID)
            ->where('wa_contact_id', $numero)
            ->first();

        return $conversacion !== null && $conversacion->ventanaActiva();
    }

    /**
     * Meta rechaza variables con saltos de línea, tabulaciones o cadenas de
     * espacios. Se aplanan a " · " para que el mensaje siga siendo legible.
     */
    private function sanear(string $texto): string
    {
        // La regla de Meta la aplica la plantilla; aquí solo se acorta más, que
        // una alerta se lee en la notificación del teléfono.
        return Str::limit(WhatsappPlantilla::sanearParametro($texto), self::MAX_PARAM, '…');
    }
}
