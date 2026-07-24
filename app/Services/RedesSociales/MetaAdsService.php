<?php

namespace App\Services\RedesSociales;

use App\Models\Publicacion;
use App\Models\PautaConfig;
use App\Models\RedSocialConfig;
use Illuminate\Support\Facades\Http;

/**
 * Pauta pagada (Meta Marketing API) sobre una pieza ya publicada orgánicamente en Facebook —
 * la "impulsa" con presupuesto real usando el post existente (object_story_id), sin crear
 * una pieza nueva. Verificado contra la cuenta real de Brygar (act_763050131388073, moneda
 * COP): los presupuestos van en COP enteros, SIN multiplicar por 100 (confirmado leyendo
 * min_daily_budget=3319 de la cuenta real — no es una currency de subunidad ×100 aquí).
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
     * Crea Campaña + Conjunto de anuncios + Anuncio, todo en PAUSED (cero gasto), impulsando
     * el post real de Facebook de la pieza. No activa nada.
     * @return array{ok: bool, mensaje: string}
     */
    public static function crearBorrador(Publicacion $publicacion, PautaConfig $config, float $presupuestoDiarioCop): array
    {
        $idPost = $publicacion->idPostFacebook();
        if (!$idPost) {
            return ['ok' => false, 'mensaje' => 'Esta pieza no tiene un post de Facebook publicado — no se puede impulsar.'];
        }
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
        $token = $fb->access_token;
        $cuenta = 'act_' . ltrim($config->ad_account_id, 'act_');

        // 1. Campaña
        $campana = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/campaigns", [
            'name'                             => "Pieza #{$publicacion->id} — {$publicacion->titulo}",
            'objective'                        => 'OUTCOME_ENGAGEMENT',
            'status'                           => 'PAUSED',
            'special_ad_categories'            => json_encode([]),
            // El presupuesto vive en el conjunto de anuncios (no en la campaña), así que no
            // se comparte presupuesto entre conjuntos.
            'is_adset_budget_sharing_enabled'  => 'false',
            'access_token'                     => $token,
        ]);
        if (!$campana->successful()) {
            return ['ok' => false, 'mensaje' => 'Campaña: ' . self::errorDeMeta($campana)];
        }
        $campanaId = $campana->json('id');

        // 2. Conjunto de anuncios (el presupuesto y la segmentación viven aquí)
        $adset = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/adsets", [
            'name'               => "Pieza #{$publicacion->id} — conjunto",
            'campaign_id'        => $campanaId,
            'daily_budget'       => (int) round($presupuestoDiarioCop),
            'billing_event'      => 'IMPRESSIONS',
            'optimization_goal'  => 'POST_ENGAGEMENT',
            'bid_strategy'       => 'LOWEST_COST_WITHOUT_CAP',
            'targeting'          => json_encode(['geo_locations' => ['countries' => ['CO']], 'age_min' => 18, 'age_max' => 65]),
            'status'             => 'PAUSED',
            'access_token'       => $token,
        ]);
        if (!$adset->successful()) {
            self::borrar($campanaId, $token);
            return ['ok' => false, 'mensaje' => 'Conjunto de anuncios: ' . self::errorDeMeta($adset)];
        }
        $adsetId = $adset->json('id');

        // 3. Anuncio: usa el post orgánico ya publicado como creatividad (lo "impulsa")
        $ad = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/ads", [
            'name'         => "Pieza #{$publicacion->id} — anuncio",
            'adset_id'     => $adsetId,
            'creative'     => json_encode(['object_story_id' => $idPost]),
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

        return ['ok' => true, 'mensaje' => 'Pauta creada en pausa — $0 gastado hasta que la actives.'];
    }

    /**
     * ÚNICA función que mueve dinero real: pasa el conjunto de anuncios a ACTIVE.
     * Revalida el tope mensual justo antes, sin confiar en validaciones previas.
     */
    public static function activar(Publicacion $publicacion): array
    {
        if (!$publicacion->meta_adset_id) {
            return ['ok' => false, 'mensaje' => 'Esta pieza no tiene una pauta creada todavía.'];
        }

        $config = PautaConfig::paraAliado($publicacion->aliado_id);
        if ((float) $publicacion->pauta_presupuesto_diario_cop > $config->disponibleEsteMes()) {
            return ['ok' => false, 'mensaje' => 'No se activó: superaría el tope mensual disponible ($' . number_format($config->disponibleEsteMes(), 0, ',', '.') . ' COP restantes).'];
        }

        $fb = RedSocialConfig::paraAliado($publicacion->aliado_id, 'facebook');
        $resp = Http::asForm()->post(self::BASE_URL . "/{$publicacion->meta_adset_id}", [
            'status'       => 'ACTIVE',
            'access_token' => $fb->access_token,
        ]);

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
