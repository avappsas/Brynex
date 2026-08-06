<?php

namespace App\Services;

use App\Models\WhatsappConfig;
use App\Models\WhatsappPlantilla;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Avisos operativos a BryNex por WhatsApp: backups caídos, accesos raros,
 * usuarios creados por un aliado.
 *
 * Sale por la cuenta de Brygar con la plantilla aprobada `notificar_brynex`
 * (dos variables: origen y mensaje). Se usa plantilla y no texto libre porque
 * el texto libre solo se puede enviar dentro de la ventana de 24h desde el
 * último mensaje del destinatario — y una alerta tiene que salir siempre.
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
        try {
            $config = WhatsappConfig::paraAliado(self::ALIADO_ID);

            if (! $config->credencialesCompletas()) {
                Log::error('AlertaOperativa: credenciales de WhatsApp incompletas', ['aliado_id' => self::ALIADO_ID]);

                return false;
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
                $this->numeroDestino(),
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
     * Meta rechaza variables con saltos de línea, tabulaciones o cadenas de
     * espacios. Se aplanan a " · " para que el mensaje siga siendo legible.
     */
    private function sanear(string $texto): string
    {
        $texto = preg_replace('/[\r\n]+/', ' · ', trim($texto));
        $texto = preg_replace('/[\t]+/', ' ', $texto);
        $texto = preg_replace('/ {2,}/', ' ', $texto);

        return Str::limit($texto, self::MAX_PARAM, '…');
    }
}
