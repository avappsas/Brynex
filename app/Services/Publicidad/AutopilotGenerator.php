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
        // Los días marcados como promocionales llevan flyer de un plan (foto propia, precio
        // real y botón de WhatsApp) en vez del post educativo del día.
        if ($config->tocaFlyerHoy()) {
            return FlyerPlanGenerator::generar($aliado, $config);
        }

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

        $logoParaReferencia = $aliado->logo_marca_claro ?: $aliado->logo;
        $rutaLogo = $logoParaReferencia ? \Illuminate\Support\Facades\Storage::disk('public')->path($logoParaReferencia) : null;
        $promptImagen = $concepto['prompt_imagen']
            . ' Compórtate como un director creativo experto en publicidad para redes sociales (Meta Ads): compón la pieza para llamar la atención, verse profesional y generar clics — no como una simple foto o ilustración de escena sin texto. '
            . 'Decide tú, en español, todo el contenido: un titular principal corto y muy impactante (máximo 5 palabras, que genere curiosidad o resuelva un problema), un subtítulo que explique el beneficio principal, entre 3 y 5 beneficios clave (cada uno con un ícono relacionado + texto corto), una frase de urgencia (ej. cupos limitados, solo hoy, inscripciones abiertas) y un llamado a la acción claro. '
            . 'Usa tipografía bold, alto contraste, degradados modernos, sombras y buena jerarquía visual — estilo campaña premium, no plano. '
            . 'Si aporta, agrega elementos de conversión como fecha, cupo limitado, descuento o garantía — pero NUNCA generes un código QR (los que dibuja un modelo de imagen no funcionan, no escanean de verdad), y NUNCA inventes datos concretos que no te haya dado (teléfonos, precios, fechas exactas, nombres de personas) — mantén esas menciones genéricas salvo los precios reales ya incluidos arriba. '
            . 'Cuida la ortografía y las tildes del español. '
            . 'Deja la esquina inferior derecha completamente libre de texto, íconos o elementos gráficos — ahí se agrega el logo de la marca por separado, y no debe quedar tapado ni encimado con nada.';
        if ($rutaLogo) {
            // La imagen adjunta (aparte del prompt) es el logo real — se le pide inspirarse
            // en su paleta, NUNCA reproducirlo literal (los modelos de imagen distorsionan
            // logos/texto reales); el logo nítido de verdad lo agrega LogoWatermarker después.
            $promptImagen .= ' Te adjunto el logo de la marca como referencia de color: usa tonos inspirados en su paleta para la escena. NO intentes dibujar ni reproducir el logo dentro de la imagen — eso se agrega después por separado.';
        }
        $imagen = GeminiImagenGenerator::generarVariantes($iaConfig->gemini_api_key, $promptImagen, 1, $modelo, $rutaLogo);
        if (!$imagen['ok'] || empty($imagen['rutas'])) {
            return ['ok' => false, 'publicacion' => null, 'error' => 'Imagen: ' . ($imagen['error'] ?? 'Gemini no devolvió imagen.')];
        }

        LogoWatermarker::aplicar($imagen['rutas'][0], $aliado->logo_marca_claro, $aliado->logo_marca_recorte);

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
            'estilo_imagen' => $estilo,
            'costo_estimado_usd' => GeminiImagenGenerator::costoEstimadoUsd($modelo),
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
     * Resumen de rendimiento real por tema (interacciones en redes + leads llegados en las
     * 48h siguientes a cada pieza) para que la IA aprenda qué contenido atrae más. Devuelve
     * cadena vacía si aún no hay piezas medidas — el prompt no menciona rendimiento inexistente.
     */
    public static function resumenRendimiento(int $aliadoId): string
    {
        $piezas = Publicacion::where('aliado_id', $aliadoId)
            ->publicadas()
            ->whereNotNull('tema')
            ->with('metricas')
            ->get()
            ->filter(fn ($p) => $p->metricas->isNotEmpty());

        if ($piezas->isEmpty()) {
            return '';
        }

        $porTema = $piezas->groupBy('tema')->map(function ($grupo) use ($aliadoId) {
            $interacciones = $grupo->sum(fn ($p) => $p->metricas->sum(fn ($m) => $m->interacciones()));
            $alcance       = $grupo->sum(fn ($p) => $p->metricas->sum('alcance'));
            // Leads del formulario web en las 48h siguientes — correlación, no certeza.
            $leadsWeb = $grupo->sum(function ($p) use ($aliadoId) {
                if (!$p->publicada_at) return 0;
                return \App\Models\PaginaLead::where('aliado_id', $aliadoId)
                    ->whereBetween('created_at', [$p->publicada_at, $p->publicada_at->copy()->addHours(48)])
                    ->count();
            });
            // Conversaciones de WhatsApp con atribución REAL (el cliente mandó el link con
            // el código de la pieza) — señal mucho más fuerte que la correlación por ventana.
            $conversacionesWa = \App\Models\WhatsappConversacion::whereIn('origen_publicacion_id', $grupo->pluck('id'))->count();
            $estilos = $grupo->pluck('estilo_imagen')->filter()->unique()->implode('/');

            return [
                'piezas'            => $grupo->count(),
                'interacciones'     => $interacciones,
                'alcance'           => $alcance,
                'leads_web'         => $leadsWeb,
                'conversaciones_wa' => $conversacionesWa,
                'estilos'           => $estilos ?: 'n/d',
            ];
        })->sortByDesc(fn ($d) => $d['conversaciones_wa'] * 3 + $d['interacciones']);

        $lineas = $porTema->map(fn ($datos, $tema) =>
            "- [{$tema}] ({$datos['estilos']}): {$datos['interacciones']} interacciones, alcance {$datos['alcance']}, "
            . "{$datos['conversaciones_wa']} conversaciones de WhatsApp atribuidas (real), {$datos['leads_web']} leads web en 48h ({$datos['piezas']} pieza(s))"
        )->implode("\n");

        return "\nRENDIMIENTO REAL DE PIEZAS ANTERIORES (ordenado de mejor a peor — prioriza los ángulos y estilos que más interacciones y leads atraen, pero sigue variando el contenido):\n{$lineas}";
    }

    /**
     * @return array{ok: bool, tema: ?string, titulo: ?string, copy: ?string, prompt_imagen: ?string, error: ?string}
     */
    private static function pedirConceptoALaIa(Aliado $aliado, array $credenciales, string $estilo): array
    {
        $planes = CotizacionPublicaService::planesDestacadosConPrecio($aliado->id, true);
        $listaPlanes = collect($planes)
            ->map(function ($p) {
                $servicios = collect([
                    'incluye_eps'     => 'EPS',
                    'incluye_arl'     => 'ARL',
                    'incluye_pension' => 'Pensión',
                    'incluye_caja'    => 'Caja de compensación',
                ])->filter(fn ($nombre, $clave) => !empty($p['componentes'][$clave]))->values()->implode(', ');

                $linea = "- {$p['nombre']} (cubre: {$servicios}): primer mes (solo afiliación) \$" . number_format($p['costo_afiliacion'], 0, ',', '.')
                    . ' COP · desde el mes siguiente $' . number_format($p['valor_mensual'], 0, ',', '.') . ' COP/mes';
                if ($p['en_promocion']) {
                    $linea .= ' · 🏷️ EN PROMOCIÓN (precio normal de afiliación $' . number_format($p['costo_afiliacion_normal'], 0, ',', '.')
                        . ' COP, válida hasta ' . \Carbon\Carbon::parse($p['promocion_vence'])->translatedFormat('d \d\e F') . ')';
                }
                return $linea;
            })
            ->implode("\n") ?: '- (sin planes configurados: no menciones precios)';

        $hayPromocion = collect($planes)->contains('en_promocion', true);
        $temaPromocion = $hayPromocion
            ? ['- promoción real vigente por tiempo limitado en uno de los planes (usa el precio EN PROMOCIÓN, menciona la fecha de vencimiento)']
            : [];

        $historial = Publicacion::where('aliado_id', $aliado->id)
            ->whereNotIn('estado', [Publicacion::ESTADO_RECHAZADA])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['titulo', 'tema'])
            ->map(fn ($p) => '- ' . ($p->tema ? "[{$p->tema}] " : '') . $p->titulo)
            ->implode("\n") ?: '- (aún no hay publicaciones)';

        $rendimiento = self::resumenRendimiento($aliado->id);

        $catalogo = implode("\n", array_map(fn ($t) => "- {$t}", array_merge(self::CATALOGO_TEMAS, $temaPromocion)));
        $color    = $aliado->color_primario ?: '#2563eb';
        $fecha    = now('America/Bogota')->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');

        $instruccionEstilo = $estilo === \App\Models\AutopilotConfig::ESTILO_FOTORREALISTA
            ? "Pide una FOTOGRAFÍA PROFESIONAL FOTORREALISTA (no ilustración, no dibujo), formato VERTICAL 4:5 (para el feed de Instagram/Facebook en celular, NUNCA panorámica): personas colombianas reales de aspecto auténtico y diverso, PLANO CERCANO (medio cuerpo o retrato, no de cuerpo entero ni de lejos) para que se sientan las expresiones, mirando a cámara o en un momento genuino de conexión (una sonrisa real, una mano en el hombro, un apretón de manos, una conversación cercana entre asesor y cliente) — nada de poses rígidas tipo banco de imágenes. Luz cálida (dorada/natural, no fría ni clínica), profundidad de campo baja, look editorial cercano y humano, transmitiendo confianza y calidez, no corporativo distante."
            : "Pide una ilustración digital plana moderna (flat design vector, NO fotografía), formato VERTICAL 4:5 (para el feed de Instagram/Facebook en celular), paleta CÁLIDA basada en {$color} combinada con tonos piel/beige/dorado suaves (no solo azul corporativo frío), personas colombianas diversas con expresiones genuinas y cercanas (sonrisas reales, contacto visual, gestos de cercanía como un abrazo o una mano en el hombro) en plano cercano — evita figuras rígidas o distantes tipo clipart genérico, estilo limpio pero cálido, con espacio en blanco.";

        $prompt = <<<PROMPT
Eres el community manager de {$aliado->nombre}, una agencia colombiana de afiliación a seguridad social (EPS, ARL, pensión, caja de compensación). Hoy es {$fecha}. Debes crear el concepto de la publicación del día para redes sociales.

CATÁLOGO DE ÁNGULOS (elige UNO, variando siempre respecto al historial):
{$catalogo}

PLANES REALES CON PRECIO VIGENTE (usa estos valores EXACTOS si el tema es una promo; NUNCA inventes precios ni descuentos que no estén aquí):
{$listaPlanes}
El "primer mes" es real: por el esquema de facturación, el mes de afiliación SOLO cobra el costo de afiliación (sin seguridad social ni administración); desde el mes siguiente se cobra el valor mensual completo. Puedes usar esto como gancho ("empieza con \$X este mes") SIEMPRE que menciones también el valor mensual siguiente — nunca lo presentes como un descuento o promoción especial, es simplemente cómo funciona el pago.
Cada plan indica entre paréntesis qué cubre ("cubre: ..."). Si mencionas un precio, menciona ÚNICAMENTE los servicios que ese plan cubre — NUNCA digas que un plan incluye EPS, ARL, Pensión o Caja si no aparece en su "cubre:", aunque el ángulo elegido hable de seguridad social en general.

HISTORIAL RECIENTE (NO repitas tema, mensaje ni enfoque visual de estas piezas):
{$historial}
{$rendimiento}

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
