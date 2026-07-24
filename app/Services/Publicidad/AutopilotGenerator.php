<?php

namespace App\Services\Publicidad;

use App\Models\Aliado;
use App\Models\AutopilotConfig;
use App\Models\IaConfiguracionAliado;
use App\Models\Publicacion;
use App\Models\RedSocialConfig;
use App\Services\CotizacionPublicaService;
use App\Services\Ia\IaProviderFactory;

/**
 * Piloto automático de marketing ("community manager IA"): genera la pieza publicitaria
 * del día para un aliado. La IA de texto (misma clave del asistente) elige el tema —
 * viendo el historial reciente para NO repetirse — y escribe título, copy y el prompt
 * de imagen; Gemini genera la imagen. Según el modo, la pieza queda pendiente de
 * aprobación o se aprueba y publica sola por el flujo existente (PublicacionPublisher).
 */
class AutopilotGenerator
{
    /** Ángulos temáticos entre los que la IA rota. */
    private const CATALOGO_TEMAS = [
        'beneficios de la caja de compensación (subsidios, recreación, educación, vivienda)',
        'beneficios de estar afiliado a una EPS (salud para ti y tu familia)',
        'importancia de cotizar a pensión desde ya (futuro asegurado)',
        'protección de la ARL para trabajadores independientes',
        'promoción de un plan con su precio real',
        'dato educativo: ¿sabías que...? sobre seguridad social en Colombia',
        'recordatorio de la fecha límite de pago de la planilla',
        'ventaja de afiliarse con una agencia que hace todo el trámite por ti',
        'cobertura para toda la familia (beneficiarios en EPS y caja)',
        'testimonio/confianza: años de experiencia acompañando afiliados',
    ];

    /**
     * @return array{ok: bool, publicacion: ?Publicacion, error: ?string}
     */
    public static function generarPiezaDelDia(Aliado $aliado, AutopilotConfig $config): array
    {
        $iaConfig     = IaConfiguracionAliado::paraAliado($aliado->id);
        $credenciales = $iaConfig->credencialesEfectivas();

        if (empty($credenciales['api_key'])) {
            return ['ok' => false, 'publicacion' => null, 'error' => 'No hay clave de IA de texto configurada (ver Asistente Virtual).'];
        }
        if (!$iaConfig->tieneGemini()) {
            return ['ok' => false, 'publicacion' => null, 'error' => 'No hay clave de Gemini configurada para generar la imagen (ver Asistente Virtual).'];
        }

        $estilo = $config->estiloDelDia();

        $concepto = self::pedirConceptoALaIa($aliado, $credenciales, $estilo);
        if (!$concepto['ok']) {
            return ['ok' => false, 'publicacion' => null, 'error' => $concepto['error']];
        }

        $modelo = $estilo === \App\Models\AutopilotConfig::ESTILO_FOTORREALISTA
            ? GeminiImagenGenerator::MODELO_FOTORREALISTA
            : GeminiImagenGenerator::MODELO_ILUSTRACION;

        $imagen = GeminiImagenGenerator::generarVariantes($iaConfig->gemini_api_key, $concepto['prompt_imagen'], 1, $modelo);
        if (!$imagen['ok'] || empty($imagen['rutas'])) {
            return ['ok' => false, 'publicacion' => null, 'error' => 'Imagen: ' . ($imagen['error'] ?? 'Gemini no devolvió imagen.')];
        }

        LogoWatermarker::aplicar($imagen['rutas'][0], $aliado->logo);

        $destinos = array_merge(
            ['web'],
            RedSocialConfig::where('aliado_id', $aliado->id)->where('activo', true)->pluck('red')->all()
        );

        $esAuto = $config->modo === AutopilotConfig::MODO_AUTO;

        $publicacion = Publicacion::create([
            'aliado_id'   => $aliado->id,
            'titulo'      => $concepto['titulo'],
            'copy'        => $concepto['copy'],
            'imagen_path' => $imagen['rutas'][0],
            'origen'      => 'ia_auto',
            'tema'        => $concepto['tema'],
            'destinos'    => $destinos,
            'estado'      => $esAuto ? Publicacion::ESTADO_APROBADA : Publicacion::ESTADO_PENDIENTE,
            'creado_por'  => null,
        ]);

        if ($esAuto) {
            PublicacionPublisher::publicar($publicacion);
        }

        return ['ok' => true, 'publicacion' => $publicacion, 'error' => null];
    }

    /**
     * @return array{ok: bool, tema: ?string, titulo: ?string, copy: ?string, prompt_imagen: ?string, error: ?string}
     */
    private static function pedirConceptoALaIa(Aliado $aliado, array $credenciales, string $estilo): array
    {
        $planes = CotizacionPublicaService::planesDestacadosConPrecio($aliado->id, true);
        $listaPlanes = collect($planes)
            ->map(fn ($p) => "- {$p['nombre']}: primer mes (solo afiliación) \$" . number_format($p['costo_afiliacion'], 0, ',', '.')
                . ' COP · desde el mes siguiente $' . number_format($p['valor_mensual'], 0, ',', '.') . ' COP/mes')
            ->implode("\n") ?: '- (sin planes configurados: no menciones precios)';

        $historial = Publicacion::where('aliado_id', $aliado->id)
            ->whereNotIn('estado', [Publicacion::ESTADO_RECHAZADA])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['titulo', 'tema'])
            ->map(fn ($p) => '- ' . ($p->tema ? "[{$p->tema}] " : '') . $p->titulo)
            ->implode("\n") ?: '- (aún no hay publicaciones)';

        $catalogo = implode("\n", array_map(fn ($t) => "- {$t}", self::CATALOGO_TEMAS));
        $color    = $aliado->color_primario ?: '#2563eb';
        $fecha    = now('America/Bogota')->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');

        $instruccionEstilo = $estilo === \App\Models\AutopilotConfig::ESTILO_FOTORREALISTA
            ? "Pide una FOTOGRAFÍA PROFESIONAL FOTORREALISTA (no ilustración, no dibujo): personas colombianas reales de aspecto auténtico y diverso, luz natural suave, estilo editorial de alta gama tipo campaña de marca seria, cámara full-frame, poca profundidad de campo, composición limpia con espacio negativo para superponer texto después. NO pidas texto escrito dentro de la foto (el texto se agrega aparte)."
            : "Pide una ilustración digital plana moderna (flat design vector, NO fotografía), formato cuadrado 1:1, paleta basada en {$color} con blanco, personas colombianas diversas cuando aplique, un texto principal corto entre comillas para renderizar en la imagen (máx 6 palabras), estilo limpio corporativo con espacio en blanco.";

        $prompt = <<<PROMPT
Eres el community manager de {$aliado->nombre}, una agencia colombiana de afiliación a seguridad social (EPS, ARL, pensión, caja de compensación). Hoy es {$fecha}. Debes crear el concepto de la publicación del día para redes sociales.

CATÁLOGO DE ÁNGULOS (elige UNO, variando siempre respecto al historial):
{$catalogo}

PLANES REALES CON PRECIO VIGENTE (usa estos valores EXACTOS si el tema es una promo; NUNCA inventes precios ni descuentos que no estén aquí):
{$listaPlanes}
El "primer mes" es real: por el esquema de facturación, el mes de afiliación SOLO cobra el costo de afiliación (sin seguridad social ni administración); desde el mes siguiente se cobra el valor mensual completo. Puedes usar esto como gancho ("empieza con $X este mes") SIEMPRE que menciones también el valor mensual siguiente — nunca lo presentes como un descuento o promoción especial, es simplemente cómo funciona el pago.

HISTORIAL RECIENTE (NO repitas tema, mensaje ni enfoque visual de estas piezas):
{$historial}

Responde ÚNICAMENTE con un objeto JSON (sin bloque de código) con estas claves:
- "tema": etiqueta corta del ángulo elegido (máx 100 caracteres)
- "titulo": título interno de la pieza (máx 120 caracteres)
- "copy": texto del post para Facebook/Instagram, español colombiano, cercano y profesional, 2-4 líneas, máximo 2 emojis, con llamado a la acción de escribir por WhatsApp
- "prompt_imagen": prompt DETALLADO en español para generar la imagen con IA. {$instruccionEstilo} Sin marcas de agua. Varía la escena y composición respecto al historial.
PROMPT;

        try {
            $provider = IaProviderFactory::make($credenciales['proveedor']);
            $resp = $provider->chat(
                $credenciales['api_key'],
                $credenciales['modelo'],
                'Respondes ÚNICAMENTE con JSON válido, sin texto adicional ni bloques de código markdown.',
                [['role' => 'user', 'content' => $prompt]],
                []
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'tema' => null, 'titulo' => null, 'copy' => null, 'prompt_imagen' => null, 'error' => 'IA de texto: ' . $e->getMessage()];
        }

        $texto = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($resp['content'] ?? '')));
        $datos = json_decode($texto, true);

        if (!is_array($datos) || empty($datos['titulo']) || empty($datos['copy']) || empty($datos['prompt_imagen'])) {
            return ['ok' => false, 'tema' => null, 'titulo' => null, 'copy' => null, 'prompt_imagen' => null, 'error' => 'La IA no devolvió un concepto utilizable.'];
        }

        return [
            'ok'            => true,
            'tema'          => mb_substr((string) ($datos['tema'] ?? ''), 0, 100) ?: null,
            'titulo'        => mb_substr((string) $datos['titulo'], 0, 120),
            'copy'          => mb_substr((string) $datos['copy'], 0, 2000),
            'prompt_imagen' => mb_substr((string) $datos['prompt_imagen'], 0, 1000),
            'error'         => null,
        ];
    }
}
