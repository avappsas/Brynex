<?php

namespace App\Services\RedesSociales;

use App\Models\PautaConfig;
use App\Models\RedSocialConfig;
use App\Services\Publicidad\SegmentosAudiencia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sube segmentos de clientes a Meta como Custom Audience, para poder pautarles anuncios
 * (Click-to-WhatsApp) en vez de escribirles por WhatsApp sin autorización.
 *
 * Los teléfonos NUNCA salen en claro: Meta exige —y aquí se cumple— que se envíen hasheados
 * con SHA-256 sobre el valor normalizado. Meta compara hashes contra los de sus usuarios; si
 * un número no coincide con ninguna cuenta, simplemente no hace match y el dato no le sirve
 * de nada. Por eso el proceso es mucho más liviano, en términos de datos personales, que
 * mandarle un mensaje a esa misma persona.
 *
 * Aun así es una transmisión de datos a un tercero con finalidad publicitaria: debe estar
 * cubierta por el aviso de privacidad del aliado.
 */
class MetaAudienciaService
{
    private const BASE_URL = 'https://graph.facebook.com/v23.0';

    /** Meta acepta hasta 10.000 registros por llamada; se deja margen. */
    private const TAMANO_LOTE = 5000;

    /**
     * Crea (o reutiliza) la audiencia del segmento y le carga los teléfonos actuales.
     *
     * @return array{ok: bool, mensaje: string, audiencia_id: ?string, subidos: int}
     */
    public static function sincronizar(int $aliadoId, string $segmento): array
    {
        $def = SegmentosAudiencia::SEGMENTOS[$segmento] ?? null;
        if (!$def) {
            return ['ok' => false, 'mensaje' => "Segmento desconocido: {$segmento}.", 'audiencia_id' => null, 'subidos' => 0];
        }

        $config = PautaConfig::paraAliado($aliadoId);
        if (!$config->ad_account_id) {
            return ['ok' => false, 'mensaje' => 'Falta el ID de la cuenta publicitaria (ver Pauta).', 'audiencia_id' => null, 'subidos' => 0];
        }

        $fb = RedSocialConfig::paraAliado($aliadoId, 'facebook');
        if (!$fb->credencialesCompletas()) {
            return ['ok' => false, 'mensaje' => 'Faltan credenciales de Facebook (ver Redes Sociales).', 'audiencia_id' => null, 'subidos' => 0];
        }

        $telefonos = SegmentosAudiencia::telefonos($segmento, $aliadoId);
        if (!$telefonos) {
            return ['ok' => false, 'mensaje' => 'El segmento no tiene teléfonos para subir.', 'audiencia_id' => null, 'subidos' => 0];
        }

        $token  = $fb->access_token;
        $cuenta = 'act_' . ltrim($config->ad_account_id, 'act_');

        $audienciaId = ($config->audiencias ?? [])[$segmento] ?? null;

        if (!$audienciaId) {
            $creacion = self::crearAudiencia($cuenta, $token, $def['nombre'], $def['descripcion']);
            if (!$creacion['ok']) {
                return ['ok' => false, 'mensaje' => $creacion['mensaje'], 'audiencia_id' => null, 'subidos' => 0];
            }
            $audienciaId = $creacion['id'];

            $config->update([
                'audiencias' => array_merge($config->audiencias ?? [], [$segmento => $audienciaId]),
            ]);
        }

        $subidos = 0;
        foreach (array_chunk($telefonos, self::TAMANO_LOTE) as $lote) {
            $resultado = self::subirLote($audienciaId, $token, $lote);
            if (!$resultado['ok']) {
                return [
                    'ok'           => false,
                    'mensaje'      => "Se subieron {$subidos} y falló el siguiente lote: {$resultado['mensaje']}",
                    'audiencia_id' => $audienciaId,
                    'subidos'      => $subidos,
                ];
            }
            $subidos += count($lote);
        }

        $config->update([
            'audiencias_sync_at' => now(),
        ]);

        return [
            'ok'           => true,
            'mensaje'      => "Audiencia «{$def['nombre']}» actualizada con {$subidos} contactos. Meta tarda unos minutos en hacer el match.",
            'audiencia_id' => $audienciaId,
            'subidos'      => $subidos,
        ];
    }

    /** @return array{ok: bool, id: ?string, mensaje: string} */
    private static function crearAudiencia(string $cuenta, string $token, string $nombre, string $descripcion): array
    {
        $resp = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/customaudiences", [
            'name'         => 'BryNex — ' . $nombre,
            'description'  => $descripcion,
            'subtype'      => 'CUSTOM',
            // Declara que los datos los entregó el propio cliente al negocio, no un tercero.
            // Meta lo exige para las audiencias de archivo de clientes.
            'customer_file_source' => 'USER_PROVIDED_ONLY',
            'access_token' => $token,
        ]);

        if (!$resp->successful() || !$resp->json('id')) {
            return ['ok' => false, 'id' => null, 'mensaje' => self::errorDeMeta($resp)];
        }

        return ['ok' => true, 'id' => (string) $resp->json('id'), 'mensaje' => 'Audiencia creada.'];
    }

    /**
     * Carga un lote de teléfonos hasheados.
     *
     * @param array<string> $telefonos Ya normalizados (57XXXXXXXXXX).
     * @return array{ok: bool, mensaje: string}
     */
    private static function subirLote(string $audienciaId, string $token, array $telefonos): array
    {
        $data = array_map(fn ($t) => [hash('sha256', $t)], $telefonos);

        $resp = Http::asForm()->post(self::BASE_URL . "/{$audienciaId}/users", [
            'payload'      => json_encode(['schema' => ['PHONE'], 'data' => $data]),
            'access_token' => $token,
        ]);

        if (!$resp->successful()) {
            Log::warning('Meta Custom Audience: fallo al subir lote', [
                'audiencia_id' => $audienciaId,
                'error'        => $resp->body(),
            ]);

            return ['ok' => false, 'mensaje' => self::errorDeMeta($resp)];
        }

        return ['ok' => true, 'mensaje' => 'Lote subido.'];
    }

    private static function errorDeMeta(\Illuminate\Http\Client\Response $resp): string
    {
        return $resp->json('error.error_user_msg')
            ?? $resp->json('error.message')
            ?? ('HTTP ' . $resp->status());
    }
}
