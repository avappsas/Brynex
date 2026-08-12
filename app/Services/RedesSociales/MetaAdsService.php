<?php

namespace App\Services\RedesSociales;

use App\Models\Publicacion;
use App\Models\PautaConfig;
use App\Models\RedSocialConfig;
use App\Models\WhatsappConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    /** Días que una creatividad nueva queda a salvo de la rotación, para que junte datos. */
    private const DIAS_GRACIA = 4;

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
        if ($presupuestoDiarioCop > PautaConfig::TOPE_DIARIO_COP) {
            return ['ok' => false, 'mensaje' => 'El tope diario es de $' . number_format(PautaConfig::TOPE_DIARIO_COP, 0, ',', '.') . ' COP.'];
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

        // 0. Media al catálogo de la cuenta. Si la pieza es un Reel, el anuncio tiene que ser
        // el VIDEO: antes se pautaba el póster, o sea un cuadro fijo justo del formato que
        // más alcance da.
        $media = self::subirMedia($publicacion, $cuenta, $token);
        if (!$media['ok']) {
            return ['ok' => false, 'mensaje' => $media['mensaje']];
        }
        $imageHash = $media['image_hash'];
        $videoId   = $media['video_id'];

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
            'targeting'          => json_encode(self::segmentacion($config, $token)),
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
            'object_story_spec'  => json_encode(
                ['page_id' => $pageId] + self::historia($publicacion, $imageHash, $videoId)
            ),
            'access_token'       => $token,
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
     * Sube la imagen (y el video, si la pieza es un Reel) al catálogo de la cuenta.
     *
     * En una pieza de video la imagen es el póster: sirve de miniatura, no de anuncio.
     *
     * @return array{ok: bool, image_hash: ?string, video_id: ?string, mensaje: string}
     */
    private static function subirMedia(Publicacion $publicacion, string $cuenta, string $token): array
    {
        if (!$publicacion->imagen_path || !Storage::disk('public')->exists($publicacion->imagen_path)) {
            return ['ok' => false, 'image_hash' => null, 'video_id' => null, 'mensaje' => 'La pieza no tiene imagen (ni póster, si es video) para el anuncio.'];
        }

        $subida = Http::asMultipart()->attach(
            'source', Storage::disk('public')->get($publicacion->imagen_path), basename($publicacion->imagen_path)
        )->post(self::BASE_URL . "/{$cuenta}/adimages", ['access_token' => $token]);
        if (!$subida->successful()) {
            return ['ok' => false, 'image_hash' => null, 'video_id' => null, 'mensaje' => 'Imagen: ' . self::errorDeMeta($subida)];
        }
        $imagenes  = $subida->json('images') ?? [];
        $imageHash = data_get(reset($imagenes) ?: [], 'hash');
        if (!$imageHash) {
            return ['ok' => false, 'image_hash' => null, 'video_id' => null, 'mensaje' => 'Meta no devolvió el hash de la imagen subida.'];
        }

        $videoId = null;
        if (($publicacion->tipo_pieza ?? null) === 'video' && $publicacion->video_path) {
            $sube = self::subirVideo($publicacion, $cuenta, $token);
            if (!$sube['ok']) {
                return ['ok' => false, 'image_hash' => null, 'video_id' => null, 'mensaje' => $sube['mensaje']];
            }
            $videoId = $sube['video_id'];
        }

        return ['ok' => true, 'image_hash' => $imageHash, 'video_id' => $videoId, 'mensaje' => 'Media lista.'];
    }

    /**
     * `object_story_spec` de la creatividad: botón nativo de WhatsApp + mensaje precargado con
     * el código de referencia de la pieza, que es lo que permite atribuir la conversación.
     *
     * `video_data` y `link_data` son excluyentes: Meta rechaza el creativo si van los dos.
     */
    private static function historia(Publicacion $publicacion, string $imageHash, ?string $videoId): array
    {
        $bienvenida = [
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
        ];
        $llamado = [
            'type'  => 'WHATSAPP_MESSAGE',
            'value' => ['app_destination' => 'WHATSAPP'],
        ];

        if ($videoId) {
            return ['video_data' => [
                'video_id'             => $videoId,
                'message'              => $publicacion->copy ?: $publicacion->titulo,
                'title'                => $publicacion->titulo,
                'image_hash'           => $imageHash,
                'call_to_action'       => $llamado,
                'page_welcome_message' => $bienvenida,
            ]];
        }

        return ['link_data' => [
            'message'              => $publicacion->copy ?: $publicacion->titulo,
            'name'                 => $publicacion->titulo,
            'image_hash'           => $imageHash,
            'link'                 => 'https://api.whatsapp.com/send',
            'call_to_action'       => $llamado,
            'page_welcome_message' => $bienvenida,
        ]];
    }

    /**
     * Sube el video de la pieza al catálogo de la cuenta y espera a que Meta lo procese.
     *
     * El creativo no se puede crear con un video a medio procesar: Meta acepta la subida al
     * instante pero devuelve error al referenciarlo hasta que el estado es `ready`. Un Reel de
     * 16 segundos tarda entre 10 y 40 segundos.
     *
     * @return array{ok: bool, video_id: ?string, mensaje: string}
     */
    private static function subirVideo(Publicacion $publicacion, string $cuenta, string $token): array
    {
        if (!Storage::disk('public')->exists($publicacion->video_path)) {
            return ['ok' => false, 'video_id' => null, 'mensaje' => 'No se encuentra el archivo de video de la pieza.'];
        }

        $subida = Http::timeout(180)->asMultipart()->attach(
            'source',
            Storage::disk('public')->get($publicacion->video_path),
            basename($publicacion->video_path)
        )->post(self::BASE_URL . "/{$cuenta}/advideos", ['access_token' => $token]);

        if (!$subida->successful()) {
            return ['ok' => false, 'video_id' => null, 'mensaje' => 'Video: ' . self::errorDeMeta($subida)];
        }
        $videoId = $subida->json('id');
        if (!$videoId) {
            return ['ok' => false, 'video_id' => null, 'mensaje' => 'Meta no devolvió el id del video subido.'];
        }

        // Hasta 2 minutos de espera. Si Meta se demora más, es mejor fallar y reintentar que
        // dejar una campaña a medio armar apuntando a un video que no existe todavía.
        for ($intento = 0; $intento < 24; $intento++) {
            sleep(5);
            $estado = Http::get(self::BASE_URL . "/{$videoId}", [
                'fields'       => 'status',
                'access_token' => $token,
            ]);
            $fase = $estado->json('status.video_status');
            if ($fase === 'ready') {
                return ['ok' => true, 'video_id' => $videoId, 'mensaje' => 'Video listo.'];
            }
            if ($fase === 'error') {
                return ['ok' => false, 'video_id' => null, 'mensaje' => 'Meta no pudo procesar el video de la pieza.'];
            }
        }

        return ['ok' => false, 'video_id' => null, 'mensaje' => 'Meta sigue procesando el video (más de 2 minutos). Reintenta en un momento.'];
    }

    /**
     * Segmentación del conjunto de anuncios.
     *
     * Antes estaba quemada en toda Colombia de 18 a 65: con 5.000 COP/día eso reparte el
     * alcance entre 50 millones de personas. Ahora sale de `pauta_config`, y las ciudades se
     * traducen a las claves internas de Meta —que no se pueden escribir a mano— la primera
     * vez y quedan cacheadas.
     *
     * Sin ciudades configuradas se mantiene el país entero: es el comportamiento anterior y
     * nunca deja un conjunto sin geografía, que Meta rechazaría.
     */
    private static function segmentacion(PautaConfig $config, string $token): array
    {
        $base = [
            'age_min' => $config->edad_min ?: 25,
            'age_max' => $config->edad_max ?: 55,
            // Meta exige declararlo explícitamente o rechaza la creación del conjunto.
            // En 0 respeta la segmentación tal cual: con presupuesto chico y una etapa de
            // aprendizaje, que Meta amplíe el público por su cuenta haría imposible saber
            // qué funcionó. Se puede subir a 1 cuando ya haya un ganador claro.
            'targeting_automation' => ['advantage_audience' => 0],
        ];

        $ciudades = $config->ciudades ?: [];
        if (empty($ciudades)) {
            return $base + ['geo_locations' => ['countries' => ['CO']]];
        }

        $claves = $config->ciudades_claves ?: [];
        $faltan = array_diff($ciudades, array_keys($claves));

        foreach ($faltan as $ciudad) {
            $r = Http::get(self::BASE_URL . '/search', [
                'type'           => 'adgeolocation',
                'location_types' => json_encode(['city']),
                'q'              => $ciudad,
                'country_code'   => 'CO',
                'limit'          => 10,
                'access_token'   => $token,
            ]);

            // Meta ignora el filtro de tipo cuando no encuentra la ciudad y devuelve BARRIOS:
            // "Palmira" trae "Ciudadela Palmira" de primero. Quedarse con data.0 significaba
            // pautarle a un barrio creyendo que era el municipio. Se exige tipo `city` y que
            // el nombre coincida de verdad.
            $clave = null;
            foreach ((array) $r->json('data') as $d) {
                if (($d['type'] ?? null) !== 'city') {
                    continue;
                }
                if (self::mismoNombre($d['name'] ?? '', $ciudad)) {
                    $clave = $d['key'] ?? null;
                    break;
                }
            }

            if ($clave) {
                $claves[$ciudad] = $clave;
            } else {
                Log::warning("Pauta: Meta no tiene la ciudad '{$ciudad}' como municipio; se excluye de la segmentación.");
            }
        }

        if ($claves !== ($config->ciudades_claves ?: [])) {
            $config->update(['ciudades_claves' => $claves]);
        }

        // Si ninguna ciudad se pudo resolver, país entero antes que un conjunto inválido.
        $resueltas = array_values(array_intersect_key($claves, array_flip($ciudades)));
        if (empty($resueltas)) {
            return $base + ['geo_locations' => ['countries' => ['CO']]];
        }

        return $base + [
            'geo_locations' => [
                'cities' => array_map(fn ($k) => ['key' => $k, 'radius' => 25, 'distance_unit' => 'kilometer'], $resueltas),
            ],
        ];
    }

    /** Compara nombres de ciudad ignorando tildes y mayúsculas: "Jamundi" debe casar con "Jamundí". */
    private static function mismoNombre(string $a, string $b): bool
    {
        $normalizar = fn (string $s) => mb_strtolower(trim(strtr(
            $s,
            ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N']
        )));

        return $normalizar($a) === $normalizar($b);
    }

    /**
     * Crea (una sola vez) el conjunto permanente del aliado y devuelve su id.
     *
     * Todo se crea en PAUSED: encender el gasto sigue siendo un acto explícito. De ahí en
     * adelante las piezas entran como anuncios dentro de este mismo conjunto, que ya viene
     * con historial — que es justamente lo que evita reiniciar el aprendizaje de Meta.
     *
     * Si el conjunto ya existe, sincroniza el presupuesto diario por si cambió el semanal.
     *
     * @return array{ok: bool, adset_id: ?string, mensaje: string}
     */
    public static function asegurarConjuntoPermanente(PautaConfig $config, int $aliadoId): array
    {
        if (!$config->activo || !$config->ad_account_id) {
            return ['ok' => false, 'adset_id' => null, 'mensaje' => 'La pauta pagada no está configurada para este aliado.'];
        }

        $fb = RedSocialConfig::paraAliado($aliadoId, 'facebook');
        if (!$fb->credencialesCompletas()) {
            return ['ok' => false, 'adset_id' => null, 'mensaje' => 'Faltan credenciales de Facebook (ver Redes Sociales).'];
        }
        $waConfig = WhatsappConfig::where('aliado_id', $aliadoId)->where('activo', true)->first();
        if (!$waConfig?->numero_telefono) {
            return ['ok' => false, 'adset_id' => null, 'mensaje' => 'No hay un número de WhatsApp del bot configurado.'];
        }

        $token    = $fb->access_token;
        $cuenta   = 'act_' . ltrim($config->ad_account_id, 'act_');
        $diario   = (int) round($config->presupuestoDiarioCop());
        $numeroWa = preg_replace('/\D/', '', $waConfig->numero_telefono);

        // Ya existe: solo alinear el presupuesto. Cambiarlo reinicia parcialmente el
        // aprendizaje, así que se toca únicamente cuando de verdad difiere.
        if ($config->meta_adset_permanente_id) {
            $actual = Http::get(self::BASE_URL . "/{$config->meta_adset_permanente_id}", [
                'fields'       => 'daily_budget,status',
                'access_token' => $token,
            ]);
            if ($actual->successful() && (int) $actual->json('daily_budget') !== $diario) {
                Http::asForm()->post(self::BASE_URL . "/{$config->meta_adset_permanente_id}", [
                    'daily_budget' => $diario,
                    'access_token' => $token,
                ]);
            }
            return ['ok' => true, 'adset_id' => $config->meta_adset_permanente_id, 'mensaje' => 'Conjunto permanente ya existía.'];
        }

        $campana = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/campaigns", [
            'name'                            => 'BRYGAR — conjunto permanente (WhatsApp)',
            'objective'                       => 'OUTCOME_ENGAGEMENT',
            'status'                          => 'PAUSED',
            'special_ad_categories'           => json_encode([]),
            'is_adset_budget_sharing_enabled' => 'false',
            'access_token'                    => $token,
        ]);
        if (!$campana->successful()) {
            return ['ok' => false, 'adset_id' => null, 'mensaje' => 'Campaña: ' . self::errorDeMeta($campana)];
        }
        $campanaId = $campana->json('id');

        $adset = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/adsets", [
            'name'              => 'Permanente — Cali y Valle',
            'campaign_id'       => $campanaId,
            'destination_type'  => 'WHATSAPP',
            'daily_budget'      => $diario,
            'billing_event'     => 'IMPRESSIONS',
            'optimization_goal' => 'CONVERSATIONS',
            'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
            'promoted_object'   => json_encode(['page_id' => $fb->identificador, 'whatsapp_phone_number' => $numeroWa]),
            'targeting'         => json_encode(self::segmentacion($config, $token)),
            'status'            => 'PAUSED',
            'access_token'      => $token,
        ]);
        if (!$adset->successful()) {
            self::borrar($campanaId, $token);
            return ['ok' => false, 'adset_id' => null, 'mensaje' => 'Conjunto: ' . self::errorDeMeta($adset)];
        }

        $config->update([
            'meta_campana_permanente_id' => $campanaId,
            'meta_adset_permanente_id'   => $adset->json('id'),
        ]);

        return ['ok' => true, 'adset_id' => $adset->json('id'), 'mensaje' => 'Conjunto permanente creado en pausa — $0 gastado hasta que lo actives.'];
    }

    /**
     * Mete una pieza como anuncio nuevo dentro del conjunto permanente.
     *
     * El anuncio entra ACTIVO si el conjunto ya lo está: el retador tiene que competir de
     * verdad contra las creatividades que ya viven ahí. El gasto no sube por esto — el
     * presupuesto es del conjunto y se reparte entre sus anuncios.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public static function agregarPieza(Publicacion $publicacion): array
    {
        $config = PautaConfig::paraAliado($publicacion->aliado_id);

        $conjunto = self::asegurarConjuntoPermanente($config, $publicacion->aliado_id);
        if (!$conjunto['ok']) {
            return ['ok' => false, 'mensaje' => $conjunto['mensaje']];
        }
        if ($publicacion->meta_ad_id) {
            return ['ok' => false, 'mensaje' => 'Esta pieza ya está en el conjunto permanente.'];
        }

        // El piloto genera una pieza DIARIA, pero el presupuesto semanal es uno solo. Si
        // entraran las siete, 50.000 se partirían en siete y ninguna juntaría datos para
        // saber si sirve. Solo pasan las primeras del cupo semanal; el resto se queda en
        // orgánico, que no cuesta nada.
        $cupo = max(1, (int) ($config->piezas_semana_max ?: 3));
        $estaSemana = Publicacion::where('aliado_id', $publicacion->aliado_id)
            ->where('meta_adset_id', $conjunto['adset_id'])
            ->where('updated_at', '>=', now()->subDays(7))
            ->whereNotNull('meta_ad_id')
            ->count();
        if ($estaSemana >= $cupo) {
            return ['ok' => false, 'mensaje' => "Cupo semanal lleno: ya hay {$estaSemana} pieza(s) pautada(s) de {$cupo}. Esta se queda solo en orgánico."];
        }

        $fb     = RedSocialConfig::paraAliado($publicacion->aliado_id, 'facebook');
        $token  = $fb->access_token;
        $cuenta = 'act_' . ltrim($config->ad_account_id, 'act_');

        $media = self::subirMedia($publicacion, $cuenta, $token);
        if (!$media['ok']) {
            return ['ok' => false, 'mensaje' => $media['mensaje']];
        }

        $creativa = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/adcreatives", [
            'name'              => "Pieza #{$publicacion->id} — creatividad",
            'object_story_spec' => json_encode(
                ['page_id' => $fb->identificador] + self::historia($publicacion, $media['image_hash'], $media['video_id'])
            ),
            'access_token'      => $token,
        ]);
        if (!$creativa->successful()) {
            return ['ok' => false, 'mensaje' => 'Creatividad: ' . self::errorDeMeta($creativa)];
        }

        // El anuncio sigue el estado del conjunto: si la pauta está encendida, el retador
        // entra compitiendo; si está en pausa, entra en pausa.
        $estadoConjunto = Http::get(self::BASE_URL . "/{$conjunto['adset_id']}", [
            'fields'       => 'status',
            'access_token' => $token,
        ])->json('status');

        $ad = Http::asForm()->post(self::BASE_URL . "/{$cuenta}/ads", [
            'name'         => "Pieza #{$publicacion->id} — anuncio",
            'adset_id'     => $conjunto['adset_id'],
            'creative'     => json_encode(['creative_id' => $creativa->json('id')]),
            'status'       => $estadoConjunto === 'ACTIVE' ? 'ACTIVE' : 'PAUSED',
            'access_token' => $token,
        ]);
        if (!$ad->successful()) {
            return ['ok' => false, 'mensaje' => 'Anuncio: ' . self::errorDeMeta($ad)];
        }

        $publicacion->update([
            'pauta_estado'                 => $estadoConjunto === 'ACTIVE' ? 'activa' : 'borrador',
            'pauta_presupuesto_diario_cop' => $config->presupuestoDiarioCop(),
            'meta_campana_id'              => $config->meta_campana_permanente_id,
            'meta_adset_id'                => $conjunto['adset_id'],
            'meta_ad_id'                   => $ad->json('id'),
            'pauta_activada_at'            => $estadoConjunto === 'ACTIVE' ? ($publicacion->pauta_activada_at ?: now()) : $publicacion->pauta_activada_at,
        ]);

        return ['ok' => true, 'mensaje' => "Pieza #{$publicacion->id} agregada al conjunto permanente."];
    }

    /**
     * Deja activas solo las mejores creatividades del conjunto y pausa el resto.
     *
     * Se ordena por conversaciones de WhatsApp atribuidas de verdad, no por likes: un like no
     * paga una afiliación. Las piezas con menos de $DIAS_GRACIA días quedan protegidas —
     * juzgar un anuncio sin datos es tirar una moneda, no medir.
     *
     * @return array{ok: bool, mensaje: string, pausadas: int}
     */
    public static function rotarCreatividades(PautaConfig $config, int $aliadoId): array
    {
        if (!$config->meta_adset_permanente_id) {
            return ['ok' => false, 'mensaje' => 'Todavía no hay conjunto permanente.', 'pausadas' => 0];
        }

        $maximo = max(1, (int) ($config->creatividades_max ?: 3));
        $activas = Publicacion::where('aliado_id', $aliadoId)
            ->where('meta_adset_id', $config->meta_adset_permanente_id)
            ->where('pauta_estado', 'activa')
            ->whereNotNull('meta_ad_id')
            ->get();

        if ($activas->count() <= $maximo) {
            return ['ok' => true, 'mensaje' => "Nada que rotar ({$activas->count()} de {$maximo}).", 'pausadas' => 0];
        }

        $conversaciones = \App\Models\WhatsappConversacion::whereIn('origen_publicacion_id', $activas->pluck('id'))
            ->selectRaw('origen_publicacion_id, COUNT(*) as total')
            ->groupBy('origen_publicacion_id')
            ->pluck('total', 'origen_publicacion_id');

        $ordenadas = $activas->sortByDesc(function ($p) use ($conversaciones) {
            $protegida = $p->pauta_activada_at && $p->pauta_activada_at->gt(now()->subDays(self::DIAS_GRACIA));

            // Las protegidas van primero para que nunca caigan en la zona de pausa; entre
            // iguales manda la fecha, así el retador más nuevo desplaza al más viejo.
            return [$protegida ? 1 : 0, (int) ($conversaciones[$p->id] ?? 0), $p->pauta_activada_at?->timestamp ?? 0];
        })->values();

        $fb = RedSocialConfig::paraAliado($aliadoId, 'facebook');
        $pausadas = 0;
        foreach ($ordenadas->slice($maximo) as $pieza) {
            $r = Http::asForm()->post(self::BASE_URL . "/{$pieza->meta_ad_id}", [
                'status'       => 'PAUSED',
                'access_token' => $fb->access_token,
            ]);
            if ($r->successful()) {
                $pieza->update(['pauta_estado' => 'pausada']);
                $pausadas++;
            }
        }

        return ['ok' => true, 'mensaje' => "Rotación: {$pausadas} creatividad(es) pausada(s), se dejan {$maximo}.", 'pausadas' => $pausadas];
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
        $sugerido = $costoPorConversacion < 15000 ? round($base * 1.4) : $base;

        return min($sugerido, PautaConfig::TOPE_DIARIO_COP);
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
