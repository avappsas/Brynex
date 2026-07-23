<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\PaginaAliadoConfig;
use App\Models\PaginaLead;
use App\Models\Publicacion;
use App\Services\CotizacionPublicaService;
use App\Services\MetricaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Página web pública de un aliado (/aliado/{slug}, o su propio dominio si tiene uno mapeado —
 * ver rutas dinámicas en routes/web.php). Sin autenticación — el aliado se resuelve por slug
 * y la visibilidad depende de aliados.activo + pagina_aliado_config.activo. Todo el contenido
 * sale de la BD/configuración real del aliado; nada se hardcodea aquí.
 */
class PaginaAliadoController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $datos = Cache::remember(self::cacheKey($slug), 600, function () use ($slug) {
            $aliado = Aliado::where('slug', $slug)->where('activo', true)->first();
            if (!$aliado) {
                return null;
            }

            $config = PaginaAliadoConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
            if (!$config) {
                return null;
            }

            return $this->armarDatos($aliado, $config);
        });

        if (!$datos) {
            abort(404);
        }

        // Fuera del cache a propósito: si la visita se contara dentro del closure de arriba,
        // solo se registraría ~1 vez cada 10 min (la duración del cache) sin importar el
        // tráfico real — aquí sí corre en cada request real.
        MetricaService::registrar($datos['aliado']->id, MetricaService::VISITA);

        $datos['urlCanonica'] = $this->urlCanonica($datos['aliado'], $request);

        return view('publico.aliado.show', $datos);
    }

    /**
     * Vista previa vía URL firmada (usada desde el CMS admin): ignora el flag `activo` de la
     * página y nunca usa cache, para que el aliado vea siempre el estado recién guardado.
     * El aliado en sí debe seguir activo — no tiene sentido previsualizar un aliado inexistente.
     * No registra métricas (no es una visita real).
     */
    public function preview(Request $request, string $slug)
    {
        $aliado = Aliado::where('slug', $slug)->where('activo', true)->first();
        if (!$aliado) {
            abort(404);
        }

        $config = PaginaAliadoConfig::where('aliado_id', $aliado->id)->first()
            ?? new PaginaAliadoConfig(['aliado_id' => $aliado->id]);

        $datos = $this->armarDatos($aliado, $config);
        $datos['urlCanonica'] = $this->urlCanonica($aliado, $request);

        return response()
            ->view('publico.aliado.show', $datos)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Páginas legales estáticas (política de privacidad, términos, eliminación de datos) —
     * requeridas por Meta for Developers para publicar la app de Facebook/Instagram del aliado
     * (Configuración de la app > Información básica). Contenido real, con los datos de
     * contacto reales del aliado — nunca cacheadas (son documentos legales, siempre frescos).
     */
    public function politicaPrivacidad(Request $request, string $slug)
    {
        return $this->documentoLegal($request, $slug, 'privacidad');
    }

    public function terminosServicio(Request $request, string $slug)
    {
        return $this->documentoLegal($request, $slug, 'terminos');
    }

    public function eliminacionDatos(Request $request, string $slug)
    {
        return $this->documentoLegal($request, $slug, 'eliminacion-datos');
    }

    private function documentoLegal(Request $request, string $slug, string $tipo)
    {
        $aliado = Aliado::where('slug', $slug)->where('activo', true)->first();
        if (!$aliado) {
            abort(404);
        }

        return view('publico.aliado.legal', [
            'aliado'          => $aliado,
            'config'          => new PaginaAliadoConfig(['aliado_id' => $aliado->id]),
            'tipo'            => $tipo,
            'whatsapp'        => $aliado->whatsapp ?: $aliado->celular,
            'colorPrimario'   => $this->colorSeguro($aliado->color_primario),
            'textoSobreBrand' => $this->textoLegibleSobre($aliado->color_primario),
            'urlCanonica'     => $this->urlCanonica($aliado, $request),
        ]);
    }

    /**
     * Cotizador "Arma tu plan": recibe perfil + coberturas elegidas por el visitante y devuelve
     * el valor mensual calculado con la MISMA lógica que usa la IA (CotizacionPublicaService),
     * nunca en JS. Nunca expone el desglose interno por componente ni la comisión del asesor —
     * mismo criterio de privacidad que CotizarPlanPublicoTool.
     */
    public function cotizar(Request $request, string $slug)
    {
        $aliado = Aliado::where('slug', $slug)->where('activo', true)->first();
        if (!$aliado) {
            abort(404);
        }
        $config = PaginaAliadoConfig::where('aliado_id', $aliado->id)->where('activo', true)->first();
        if (!$config) {
            abort(404);
        }

        $validado = $request->validate([
            'independiente'   => 'required|boolean',
            'incluye_eps'     => 'required|boolean',
            'incluye_arl'     => 'required|boolean',
            'incluye_pension' => 'required|boolean',
            'incluye_caja'    => 'required|boolean',
            'nivel_arl'       => 'nullable|integer|min:1|max:5',
            'salario'         => 'nullable|numeric|min:0|max:999999999',
        ]);

        $componentes = [
            'incluye_eps'     => $validado['incluye_eps'],
            'incluye_arl'     => $validado['incluye_arl'],
            'incluye_pension' => $validado['incluye_pension'],
            'incluye_caja'    => $validado['incluye_caja'],
        ];

        if (!in_array(true, $componentes, true)) {
            return response()->json(['error' => 'Selecciona al menos una cobertura.'], 422);
        }

        $independiente = $validado['independiente'];
        $modalidad = CotizacionPublicaService::modalidadPorDefecto($independiente);
        if (!$modalidad) {
            return response()->json(['error' => 'No se pudo determinar la modalidad.'], 422);
        }

        [$plan, $coincidenciaExacta] = CotizacionPublicaService::resolverPlan($componentes, $independiente);
        if (!$plan) {
            return response()->json(['error' => 'No tenemos un plan disponible con esa combinación. Escríbenos por WhatsApp y te asesoramos.'], 422);
        }

        $salario = $validado['salario'] ?? \App\Models\ConfiguracionBrynex::salarioMinimo();

        $resultado = CotizacionPublicaService::cotizar($plan, $modalidad, $aliado->id, [
            'salario'   => $salario,
            'nivel_arl' => $validado['nivel_arl'] ?? 1,
        ]);

        $respuesta = [
            'plan_nombre'          => $plan->nombre,
            'coincidencia_exacta'  => $coincidenciaExacta,
            'componentes_incluidos' => [
                'eps'     => (bool) $plan->incluye_eps,
                'arl'     => (bool) $plan->incluye_arl,
                'pension' => (bool) $plan->incluye_pension,
                'caja'    => (bool) $plan->incluye_caja,
            ],
        ];

        if ($config->mostrar_precios) {
            $respuesta['precios_visibles']    = true;
            $respuesta['precios_modo']        = $config->precios_modo;
            $respuesta['valor_mensual_total'] = $resultado['total'];
            $respuesta['costo_afiliacion_sugerido'] = $resultado['costo_afiliacion_sugerido'];
            if (!empty($resultado['plan_pago_inicial'])) {
                $respuesta['plan_pago_inicial'] = $resultado['plan_pago_inicial'];
            }
            if ($independiente) {
                $respuesta['ahorro'] = null; // como independiente ya es la vía directa, no aplica comparación
            } else {
                $respuesta['ahorro'] = CotizacionPublicaService::costoDirectoIndependiente($salario);
            }
        } else {
            $respuesta['precios_visibles'] = false;
        }

        MetricaService::registrar($aliado->id, MetricaService::COTIZACION_COMPLETADA, ['plan' => $plan->nombre]);

        return response()->json($respuesta);
    }

    /**
     * Captura un lead desde la página pública (cotizador o formulario de contacto). Nunca pide
     * cédula ni datos sensibles — solo nombre y celular. Protegido con throttle + honeypot.
     */
    public function lead(Request $request, string $slug)
    {
        $aliado = Aliado::where('slug', $slug)->where('activo', true)->first();
        if (!$aliado) {
            abort(404);
        }

        // Honeypot: campo oculto que un humano nunca llena; si viene con contenido, es un bot.
        if ($request->filled('sitio_web')) {
            return response()->json(['ok' => true]);
        }

        $validado = $request->validate([
            'nombre'                  => 'required|string|max:150',
            'celular'                  => 'required|string|max:30|regex:/^[0-9+\s\-]{7,30}$/',
            'perfil'                  => 'nullable|in:dependiente,independiente',
            'incluye_eps'             => 'nullable|boolean',
            'incluye_arl'             => 'nullable|boolean',
            'incluye_pension'         => 'nullable|boolean',
            'incluye_caja'            => 'nullable|boolean',
            'ingreso_mensual'         => 'nullable|numeric|min:0|max:999999999',
            'valor_mensual_cotizado'  => 'nullable|numeric|min:0|max:999999999',
            'plan_interes'            => 'nullable|string|max:150',
            'origen'                  => 'nullable|in:cotizador,contacto',
            'consiento_datos'         => 'required|accepted',
        ]);

        PaginaLead::create([
            'aliado_id'              => $aliado->id,
            'nombre'                 => $validado['nombre'],
            'celular'                => $validado['celular'],
            'perfil'                 => $validado['perfil'] ?? null,
            'coberturas'             => [
                'eps'     => (bool) ($validado['incluye_eps'] ?? false),
                'arl'     => (bool) ($validado['incluye_arl'] ?? false),
                'pension' => (bool) ($validado['incluye_pension'] ?? false),
                'caja'    => (bool) ($validado['incluye_caja'] ?? false),
            ],
            'ingreso_mensual'        => $validado['ingreso_mensual'] ?? null,
            'valor_mensual_cotizado' => $validado['valor_mensual_cotizado'] ?? null,
            'plan_interes'           => $validado['plan_interes'] ?? null,
            'origen'                 => $validado['origen'] ?? 'cotizador',
            'ip_hash'                => hash('sha256', $request->ip() . config('app.key')),
        ]);

        MetricaService::registrar($aliado->id, MetricaService::LEAD_CAPTURADO, ['origen' => $validado['origen'] ?? 'cotizador']);

        return response()->json(['ok' => true]);
    }

    /** Beacon liviano para eventos que solo el navegador puede ver (hoy: clic en WhatsApp). */
    public function registrarMetrica(Request $request, string $slug)
    {
        $aliado = Aliado::where('slug', $slug)->where('activo', true)->first();
        if (!$aliado) {
            return response()->json(['ok' => false], 404);
        }

        // Whitelist estricta: solo eventos que de verdad solo puede reportar el cliente.
        // visita/cotizacion_completada/lead_capturado se registran del lado del servidor,
        // nunca confiando en lo que reporte el navegador.
        $validado = $request->validate(['tipo' => 'required|in:clic_whatsapp']);

        MetricaService::registrar($aliado->id, $validado['tipo']);

        return response()->json(['ok' => true]);
    }

    /** Sitemap de las páginas públicas de aliados activos — misma regla de visibilidad que show(). */
    public function sitemap()
    {
        $aliados = Aliado::where('activo', true)
            ->whereHas('paginaConfig', fn ($q) => $q->where('activo', true))
            ->get(['slug', 'dominio_propio']);

        $urls = $aliados->map(fn ($a) => $a->dominio_propio
            ? 'https://' . $a->dominio_propio . '/'
            : route('publico.aliado', $a->slug)
        );

        return response()
            ->view('publico.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * URL canónica de la página: si la visita llegó por el dominio propio del aliado (ej.
     * brygar.com), la canónica es la raíz de ESE dominio; si llegó por brynex.co/aliado/{slug},
     * la canónica es esa misma ruta. Evita contenido duplicado en buscadores entre ambas URLs.
     */
    private function urlCanonica(Aliado $aliado, Request $request): string
    {
        $host = strtolower($request->getHost());
        $dominioPropio = $aliado->dominio_propio ? strtolower(preg_replace('/^www\./', '', $aliado->dominio_propio)) : null;

        if ($dominioPropio && ($host === $dominioPropio || $host === 'www.' . $dominioPropio)) {
            return $request->getSchemeAndHttpHost() . '/';
        }

        return route('publico.aliado', $aliado->slug);
    }

    private function armarDatos(Aliado $aliado, PaginaAliadoConfig $config): array
    {
        return [
            'aliado'          => $aliado,
            'config'          => $config,
            'faqs'            => $aliado->paginaFaqs()->get(['id', 'pregunta', 'respuesta']),
            'planes'          => $this->planesDestacados($aliado, $config),
            'promos'          => $this->promosPublicadas($aliado),
            'colorPrimario'   => $this->colorSeguro($aliado->color_primario),
            'textoSobreBrand' => $this->textoLegibleSobre($aliado->color_primario),
            'whatsapp'        => $aliado->whatsapp ?: $aliado->celular,
        ];
    }

    private function planesDestacados(Aliado $aliado, PaginaAliadoConfig $config): \Illuminate\Support\Collection
    {
        return CotizacionPublicaService::planesDestacadosConPrecio($aliado->id, $config->mostrar_precios);
    }

    /** Últimas piezas publicadas con destino "web" (Fase 4 — generador de publicidad). */
    private function promosPublicadas(Aliado $aliado): \Illuminate\Support\Collection
    {
        return Publicacion::where('aliado_id', $aliado->id)
            ->where('estado', Publicacion::ESTADO_PUBLICADA)
            ->whereJsonContains('destinos', 'web')
            ->orderByDesc('publicada_at')
            ->limit(6)
            ->get(['id', 'titulo', 'copy', 'imagen_path', 'publicada_at']);
    }

    /** Valida que sea un HEX de 6 dígitos; si no, usa el azul por defecto de la marca BryNex. */
    private function colorSeguro(?string $hex): string
    {
        return ($hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) ? $hex : '#2563eb';
    }

    /** Elige texto blanco o azul oscuro según el brillo del color de marca (heurística YIQ). */
    private function textoLegibleSobre(?string $hex): string
    {
        $hex = $this->colorSeguro($hex);
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $brillo = ($r * 299 + $g * 587 + $b * 114) / 1000;
        return $brillo > 150 ? '#0a1628' : '#ffffff';
    }

    public static function cacheKey(string $slug): string
    {
        return "pagina_aliado_{$slug}";
    }

    public static function invalidarCache(string $slug): void
    {
        Cache::forget(self::cacheKey($slug));
    }
}
