<?php

namespace App\Services\RedesSociales;

use App\Models\Publicacion;
use App\Models\PautaConfig;
use App\Models\RedSocialConfig;
use App\Models\WhatsappConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Pauta pagada (Meta Marketing API): anuncio "Click to WhatsApp" — un botón nativo "Enviar
 * mensaje" que abre WhatsApp directo con el mensaje precargado (con el código de referencia
 * de la pieza), en vez de un link largo en el texto. Es una creatividad NUEVA (no reutiliza
 * el post orgánico como sí hacía la versión anterior) porque este formato lo exige así.
 * Verificado contra la cuenta real de Brygar (act_763050131388073, moneda COP): los
 * presupuestos van en COP enteros, SIN multiplicar por 100 (confirmado leyendo
 * min_daily_budget=3319 de la cuenta real).
 *
 * Diseño de seguridad (no negociable, no lo decide la IA):
 * - crearBorrador() SIEMPRE crea todo en status=PAUSED → $0 de riesgo, se puede probar libre.
 * - activar() es la ÚNICA función que mueve dinero real (pasa el AdSet a ACTIVE) — antes de
 *   llamarla SIEMPRE se revalida el tope mensual del aliado, nunca se confía en una
 *   validación hecha antes en la sesión.
 * - Nada aquí sube presupuestos por su cuenta; sugerirPresupuesto() solo CALCULA un número,
 *   quien decide lanzarlo o activarlo es siempre una acción explícita del usuario.
 */
class MetaAdsService
{
    private const BASE_URL = 'https://graph.facebook.com/v23.0';

    /**
     * Crea Campaña + Conjunto de anuncios (destino WhatsApp) + Creatividad con botón nativo
     * "Enviar mensaje" + Anuncio, todo en PAUSED (cero gasto). No activa nada.
     * @return array{ok: bool, mensaje: string}
     */
    public static function crearBorrador(Publicacion $publicacion, PautaConfig $config, float $presupuestoDiarioCop): array
    {
        if (!$config->activo || !$config->ad_account_id) {
            return ['ok' => false, 'mensaje' => 'La pauta pagada no está configurada para este aliado.'];
        }
        if ($presupuestoDiarioCop > $config->disponibleEsteMes()) {
            return ['ok' => false, 'mensaje' => 'Ese presupuesto supera el tope mensual disponible ($' . number_format($config->disponibleEsteMes(), 0, ',', '.') . ' COP restantes este mes).'];
        }

        $fb = RedSocialConfig::paraAliado($publicacion->aliado_id, 'facebook');
        if (!$fb->credencialesCompletas()) {
            return ['ok' => false, 'mensaje' => 'Faltan credenciales de Facebook (ver Redes Sociales).'];
        }
        $waConfig = WhatsappConfig::where('aliado_id', $publicacion->aliado_id)->where('activo', true)->first();
        if (!$waConfig?->numero_telefono) {
            return ['ok' => false, 'mensaje' => 'No hay un número de WhatsApp del bot configurado para este aliado.'];
        }

        $token = $fb->access_token;
        $cuenta = 'act_' . ltrim($config->ad_account_id, 'act_');
        $pageId = $fb->identificador;
        $numeroWa = preg_replace('/\D/', '', $waConfig->numero_telefono);

        // 0. Subir la imagen al catálogo de anuncios de la cuenta (hace falta el image_hash)
        $subida = Http::asMultipart()->attach(
            'source', Storage::disk('public')->get($publicacion->imagen_path), basename($publicacion->imagen_path)
        )->post(self::BASE_URL . "/{$cuenta}/adimages", ['access_token' => $token]);
        if (!$subida->successful()) {
            return ['ok' => false, 'mensaje' => 'Imagen: ' . self::errorDeMeta($subida)];
        }
        $imagenes = $subida->json('images') ?? [];
        $imageHash = data_get(reset($imagenes) ?: [], 'hash');
        if (!$imageHash) {
            return ['ok' => false, 'mensaje' => 'Meta no devolvió el hash de la imagen subida.'];
        }

        // 1. Campaña
        $campana = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/campaigns", [
            'name'                             => "Pieza #{$publicacion->id} — {$publicacion->titulo}",
            'objective'                        => 'OUTCOME_ENGAGEMENT',
            'status'                           => 'PAUSED',
            'special_ad_categories'            => json_encode([]),
            'is_adset_budget_sharing_enabled'  => 'false',
            'access_token'                     => $token,
        ]);
        if (!$campana->successful()) {
            return ['ok' => false, 'mensaje' => 'Campaña: ' . self::errorDeMeta($campana)];
        }
        $campanaId = $campana->json('id');

        // 2. Conjunto de anuncios: destino WhatsApp — el clic abre un chat, no una página.
        $adset = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/adsets", [
            'name'               => "Pieza #{$publicacion->id} — conjunto",
            'campaign_id'        => $campanaId,
            'destination_type'   => 'WHATSAPP',
            'daily_budget'       => (int) round($presupuestoDiarioCop),
            'billing_event'      => 'IMPRESSIONS',
            'optimization_goal'  => 'CONVERSATIONS',
            'bid_strategy'       => 'LOWEST_COST_WITHOUT_CAP',
            'promoted_object'    => json_encode(['page_id' => $pageId, 'whatsapp_phone_number' => $numeroWa]),
            'targeting'          => json_encode(['geo_locations' => ['countries' => ['CO']], 'age_min' => 18, 'age_max' => 65]),
            'status'             => 'PAUSED',
            'access_token'       => $token,
        ]);
        if (!$adset->successful()) {
            self::borrar($campanaId, $token);
            return ['ok' => false, 'mensaje' => 'Conjunto de anuncios: ' . self::errorDeMeta($adset)];
        }
        $adsetId = $adset->json('id');

        // 3. Creatividad: botón "Enviar mensaje" + mensaje precargado con el código de
        // referencia — mismo texto que usa el link orgánico, para atribuir igual.
        $creativa = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/adcreatives", [
            'name'               => "Pieza #{$publicacion->id} — creatividad",
            'object_story_spec'  => json_encode([
                'page_id'   => $pageId,
                'link_data' => [
                    'message'      => $publicacion->copy ?: $publicacion->titulo,
                    'name'         => $publicacion->titulo,
                    'image_hash'   => $imageHash,
                    'link'         => 'https://api.whatsapp.com/send',
                    'call_to_action' => [
                        'type'  => 'WHATSAPP_MESSAGE',
                        'value' => ['app_destination' => 'WHATSAPP'],
                    ],
                    'page_welcome_message' => [
                        'type'                => 'VISUAL_EDITOR',
                        'version'             => 2,
                        'landing_screen_type' => 'welcome_message',
                        'media_type'          => 'text',
                        'text_format'         => [
                            'customer_action_type' => 'autofill_message',
                            'message' => [
                                'text'             => '¡Hola! 👋 Gracias por escribirnos.',
                                'autofill_message' => ['content' => $publicacion->mensajeWhatsappRastreado()],
                            ],
                        ],
                    ],
                ],
            ]),
            'access_token' => $token,
        ]);
        if (!$creativa->successful()) {
            self::borrar($campanaId, $token);
            return ['ok' => false, 'mensaje' => 'Creatividad: ' . self::errorDeMeta($creativa)];
        }
        $creativaId = $creativa->json('id');

        // 4. Anuncio
        $ad = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/ads", [
            'name'         => "Pieza #{$publicacion->id} — anuncio",
            'adset_id'     => $adsetId,
            'creative'     => json_encode(['creative_id' => $creativaId]),
            'status'       => 'PAUSED',
            'access_token' => $token,
        ]);
        if (!$ad->successful()) {
            self::borrar($campanaId, $token);
            return ['ok' => false, 'mensaje' => 'Anuncio: ' . self::errorDeMeta($ad)];
        }

        $publicacion->update([
            'pauta_estado'                 => 'borrador',
            'pauta_presupuesto_diario_cop' => $presupuestoDiarioCop,
            'meta_campana_id'              => $campanaId,
            'meta_adset_id'                => $adsetId,
            'meta_ad_id'                   => $ad->json('id'),
        ]);

        return ['ok' => true, 'mensaje' => 'Pauta creada en pausa (botón nativo de WhatsApp) — $0 gastado hasta que la actives.'];
    }

    /**
     * ÚNICA función que mueve dinero real: pasa el conjunto de anuncios a ACTIVE.
     * Revalida el tope mensual justo antes, sin confiar en validaciones previas.
     *
     * @param ?int $diasDuracion Si se pasa, además le pone `end_time` nativo de Meta al AdSet
     *   (Meta lo pausa solo, sin depender de nuestro cron `marketing:pauta-sync` ni de que
     *   alguien se acuerde de pausarlo a mano) — ideal para pruebas cortas con tope de días.
     */
    public static function activar(Publicacion $publicacion, ?int $diasDuracion = null): array
    {
        if (!$publicacion->meta_adset_id) {
            return ['ok' => false, 'mensaje' => 'Esta pieza no tiene una pauta creada todavía.'];
        }

        $config = PautaConfig::paraAliado($publicacion->aliado_id);
        if ((float) $publicacion->pauta_presupuesto_diario_cop > $config->disponibleEsteMes()) {
            return ['ok' => false, 'mensaje' => 'No se activó: superaría el tope mensual disponible ($' . number_format($config->disponibleEsteMes(), 0, ',', '.') . ' COP restantes).'];
        }

        $fb = RedSocialConfig::paraAliado($publicacion->aliado_id, 'facebook');
        $payload = [
            'status'       => 'ACTIVE',
            'access_token' => $fb->access_token,
        ];
        if ($diasDuracion) {
            $payload['end_time'] = now()->addDays($diasDuracion)->toIso8601String();
        }

        $resp = Http::asForm()->post(self::BASE_URL . "/{$publicacion->meta_adset_id}", $payload);

        if (!$resp->successful()) {
            return ['ok' => false, 'mensaje' => self::errorDeMeta($resp)];
        }

        $publicacion->update(['pauta_estado' => 'activa', 'pauta_activada_at' => $publicacion->pauta_activada_at ?: now()]);
        return ['ok' => true, 'mensaje' => 'Pauta activa — gastando presupuesto real desde ahora.'];
    }

    /** Pausa el gasto en cualquier momento (siempre seguro, no hay confirmación especial que pedir). */
    public static function pausar(Publicacion $publicacion): array
    {
        if (!$publicacion->meta_adset_id) {
            return ['ok' => false, 'mensaje' => 'Esta pieza no tiene una pauta creada.'];
        }

        $fb = RedSocialConfig::paraAliado($publicacion->aliado_id, 'facebook');
        $resp = Http::asForm()->post(self::BASE_URL . "/{$publicacion->meta_adset_id}", [
            'status'       => 'PAUSED',
            'access_token' => $fb->access_token,
        ]);

        if (!$resp->successful()) {
            return ['ok' => false, 'mensaje' => self::errorDeMeta($resp)];
        }

        $publicacion->update(['pauta_estado' => 'pausada']);
        return ['ok' => true, 'mensaje' => 'Pauta pausada.'];
    }

    /** Lee el gasto real acumulado de Meta y lo guarda — llamado por el comando diario de sincronización. */
    public static function sincronizarGasto(Publicacion $publicacion): void
    {
        if (!$publicacion->meta_adset_id) return;

        $fb = RedSocialConfig::paraAliado($publicacion->aliado_id, 'facebook');
        $resp = Http::get(self::BASE_URL . "/{$publicacion->meta_adset_id}/insights", [
            'fields'       => 'spend',
            'date_preset'  => 'maximum',
            'access_token' => $fb->access_token,
        ]);

        if ($resp->successful()) {
            $gasto = (float) data_get($resp->json(), 'data.0.spend', 0);
            $publicacion->update(['pauta_gasto_total_cop' => $gasto]);
        }
    }

    /**
     * Presupuesto diario sugerido: arranca en el default de prueba del aliado; si el tema de
     * esta pieza ya tiene historial de conversaciones de WhatsApp atribuidas con buen costo
     * por conversación, sugiere escalar — nunca decide activar, solo calcula el número.
     */
    public static function sugerirPresupuesto(Publicacion $publicacion, PautaConfig $config): float
    {
        $base = (float) $config->presupuesto_diario_default_cop;
        if (!$publicacion->tema) {
            return $base;
        }

        $piezasDelTema = Publicacion::where('aliado_id', $publicacion->aliado_id)
            ->where('tema', $publicacion->tema)
            ->where('pauta_gasto_total_cop', '>', 0)
            ->get();

        if ($piezasDelTema->isEmpty()) {
            return $base;
        }

        $gastoTotal = $piezasDelTema->sum('pauta_gasto_total_cop');
        $conversaciones = \App\Models\WhatsappConversacion::whereIn('origen_publicacion_id', $piezasDelTema->pluck('id'))->count();

        if ($conversaciones === 0) {
            return $base; // gastó y no trajo nada real todavía — no escalar a ciegas
        }

        $costoPorConversacion = $gastoTotal / $conversaciones;
        // Umbral simple: si el costo por conversación real es bueno (<15.000 COP), sugerir +40%.
        return $costoPorConversacion < 15000 ? round($base * 1.4) : $base;
    }

    private static function borrar(string $campanaId, string $token): void
    {
        // El cliente HTTP manda el body como JSON en DELETE, y Graph API no lo lee ahí —
        // el access_token tiene que ir en la query string.
        Http::delete(self::BASE_URL . "/{$campanaId}?access_token=" . urlencode($token));
    }

    private static function errorDeMeta(\Illuminate\Http\Client\Response $resp): string
    {
        $mensaje = $resp->json('error.error_user_msg') ?? $resp->json('error.message');
        return $mensaje ? "Meta respondió: {$mensaje}" : "Meta respondió con error HTTP {$resp->status()}.";
    }
}
