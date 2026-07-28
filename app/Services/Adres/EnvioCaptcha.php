<?php

namespace App\Services\Adres;

use App\Models\AdresChequeo;
use App\Models\WhatsappConfig;
use App\Services\WhatsappApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Manda el captcha al cliente por WhatsApp para que lo resuelva.
 *
 * Lo usan tanto la tool (primer envío) como el job (reintento cuando el cliente
 * se equivoca), así que vive aparte para no duplicar el manejo del archivo.
 *
 * La imagen se borra apenas Meta la recibe: es un archivo de un solo uso y no
 * tiene por qué quedarse acumulando en el disco.
 */
class EnvioCaptcha
{
    public function __construct(
        protected WhatsappApiService $whatsapp = new WhatsappApiService(),
    ) {
    }

    public function enviar(AdresChequeo $chequeo, string $pngCompuesto, string $encabezado): bool
    {
        $conversacion = $chequeo->conversacion;
        if (!$conversacion) {
            Log::warning('ADRES: chequeo sin conversación, no hay a quién mandarle el captcha', [
                'chequeo_id' => $chequeo->id,
            ]);
            return false;
        }

        $config = WhatsappConfig::paraAliado($chequeo->aliado_id);
        if (!$config->credencialesCompletas()) {
            Log::warning('ADRES: aliado sin credenciales de WhatsApp', ['aliado_id' => $chequeo->aliado_id]);
            return false;
        }

        $ruta = "adres/captchas/{$chequeo->id}.png";
        Storage::disk('local')->put($ruta, $pngCompuesto);

        try {
            $r = $this->whatsapp->enviarMedia(
                $conversacion->wa_contact_id,
                'image',
                $ruta,
                'image/png',
                'codigo.png',
                $config,
                $encabezado
            );

            if (!($r['ok'] ?? false)) {
                Log::warning('ADRES: no se pudo enviar el captcha', [
                    'chequeo_id' => $chequeo->id,
                    'error'      => $r['error'] ?? null,
                ]);
            }

            return (bool) ($r['ok'] ?? false);
        } finally {
            Storage::disk('local')->delete($ruta);
        }
    }

    /** Texto del primer envío. La instrucción va aquí y no dibujada en la imagen. */
    public static function encabezadoInicial(): string
    {
        return "🔐 Último paso.\n\n"
            . "ADRES pide un código de seguridad para dejarme consultar. "
            . "Escríbeme los números que ves en la imagen y con eso reviso tu historial.";
    }

    public static function encabezadoReintento(int $intentosRestantes): string
    {
        return "Ese código no era. Pasa seguido, se leen horrible 🙈\n\n"
            . "Te mando uno nuevo — este es distinto al anterior. "
            . "Te " . ($intentosRestantes === 1 ? 'queda 1 intento' : "quedan {$intentosRestantes} intentos") . '.';
    }
}
