<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\IaConfiguracionAliado;
use App\Models\Publicacion;
use App\Models\RedSocialConfig;
use App\Services\CotizacionPublicaService;
use App\Services\Publicidad\CopiaIaGenerator;
use App\Services\Publicidad\GeminiImagenGenerator;
use App\Services\Publicidad\PublicacionPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Generador de publicidad: piezas por plantilla (canvas, en el navegador del admin) o por IA
 * (copy con la clave de asistente del aliado; imagen opcional con Gemini). Flujo de aprobación
 * (aliado o superadmin BryNex, cualquiera con acceso al panel) y publicación a web + redes.
 */
class PublicidadController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:superadmin|admin']);
    }

    public function index(Request $request)
    {
        $aliado = $this->aliadoActivo();

        $query = Publicacion::where('aliado_id', $aliado->id)->orderByDesc('created_at');
        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }

        $publicaciones = $query->paginate(20)->withQueryString();
        $pendientes    = Publicacion::where('aliado_id', $aliado->id)->pendientes()->count();

        return view('admin.publicidad.index', compact('aliado', 'publicaciones', 'pendientes'));
    }

    public function create()
    {
        $aliado    = $this->aliadoActivo();
        $iaConfig  = IaConfiguracionAliado::paraAliado($aliado->id);
        $redesActivas = RedSocialConfig::where('aliado_id', $aliado->id)->where('activo', true)->pluck('red');
        $planes = CotizacionPublicaService::planesDestacadosConPrecio($aliado->id, true);

        return view('admin.publicidad.create', [
            'aliado'       => $aliado,
            'tieneIaTexto' => !empty($iaConfig->credencialesEfectivas()['api_key']),
            'tieneGemini'  => $iaConfig->tieneGemini(),
            'redesActivas' => $redesActivas,
            'planes'       => $planes,
        ]);
    }

    public function store(Request $request)
    {
        $aliado = $this->aliadoActivo();

        $validated = $request->validate([
            'titulo'          => 'required|string|max:150',
            'copy'            => 'nullable|string|max:2000',
            'imagen'          => 'required_without:imagen_path_generado|nullable|image|max:5120',
            'imagen_path_generado' => 'required_without:imagen|nullable|string|max:255',
            'origen'          => 'required|in:plantilla,ia,subida',
            'plantilla_usada' => 'nullable|string|max:60',
            'estilo_imagen'   => 'nullable|in:ilustracion,fotorrealista',
            'destinos'        => 'required|array|min:1',
            'destinos.*'      => 'in:' . implode(',', Publicacion::DESTINOS_DISPONIBLES),
            'programada_at'   => 'nullable|date|after_or_equal:now',
        ]);

        if ($request->hasFile('imagen')) {
            $rutaImagen = $request->file('imagen')->store('publicidad', 'public');
        } else {
            // Viene de una generación previa (IA) ya guardada en storage — solo confirmamos que exista.
            $rutaImagen = $validated['imagen_path_generado'];
            if (!Storage::disk('public')->exists($rutaImagen)) {
                return back()->withErrors(['imagen' => 'La imagen generada ya no está disponible, genera de nuevo.'])->withInput();
            }
        }

        Publicacion::create([
            'aliado_id'       => $aliado->id,
            'titulo'          => $validated['titulo'],
            'copy'            => $validated['copy'] ?? null,
            'imagen_path'     => $rutaImagen,
            'origen'          => $validated['origen'],
            'plantilla_usada' => $validated['plantilla_usada'] ?? null,
            'estilo_imagen'   => $validated['estilo_imagen'] ?? null,
            'destinos'        => $validated['destinos'],
            'programada_at'   => $validated['programada_at'] ?? null,
            'estado'          => Publicacion::ESTADO_PENDIENTE,
            'creado_por'      => Auth::id(),
        ]);

        return redirect()->route('admin.publicidad.index')->with('success', 'Pieza creada y enviada a aprobación.');
    }

    public function show(int $id)
    {
        $aliado = $this->aliadoActivo();
        $publicacion = Publicacion::where('aliado_id', $aliado->id)->with(['creador', 'aprobador', 'metricas'])->findOrFail($id);

        return view('admin.publicidad.show', compact('aliado', 'publicacion'));
    }

    public function aprobar(int $id)
    {
        $aliado = $this->aliadoActivo();
        $publicacion = Publicacion::where('aliado_id', $aliado->id)->findOrFail($id);

        if ($publicacion->estado !== Publicacion::ESTADO_PENDIENTE) {
            return back()->withErrors(['estado' => 'Solo se pueden aprobar piezas pendientes.']);
        }

        $publicacion->update([
            'estado'       => Publicacion::ESTADO_APROBADA,
            'aprobado_por' => Auth::id(),
        ]);

        if ($publicacion->listaParaPublicar()) {
            PublicacionPublisher::publicar($publicacion);
            return redirect()->route('admin.publicidad.show', $publicacion->id)->with('success', 'Pieza aprobada y publicada.');
        }

        return redirect()->route('admin.publicidad.show', $publicacion->id)
            ->with('success', 'Pieza aprobada — se publicará automáticamente el ' . $publicacion->programada_at->format('d/m/Y H:i') . '.');
    }

    public function rechazar(Request $request, int $id)
    {
        $aliado = $this->aliadoActivo();
        $publicacion = Publicacion::where('aliado_id', $aliado->id)->findOrFail($id);

        $validated = $request->validate(['motivo_rechazo' => 'required|string|max:500']);

        $publicacion->update([
            'estado'         => Publicacion::ESTADO_RECHAZADA,
            'aprobado_por'   => Auth::id(),
            'motivo_rechazo' => $validated['motivo_rechazo'],
        ]);

        return redirect()->route('admin.publicidad.index')->with('success', 'Pieza rechazada.');
    }

    public function reintentar(int $id, string $red)
    {
        $aliado = $this->aliadoActivo();
        $publicacion = Publicacion::where('aliado_id', $aliado->id)->findOrFail($id);

        abort_unless($publicacion->estado === Publicacion::ESTADO_PUBLICADA, 400);
        abort_unless(in_array($red, Publicacion::DESTINOS_DISPONIBLES, true), 404);

        $resultado = PublicacionPublisher::reintentar($publicacion, $red);

        return back()->with($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
    }

    public function destroy(int $id)
    {
        $aliado = $this->aliadoActivo();
        $publicacion = Publicacion::where('aliado_id', $aliado->id)->findOrFail($id);

        abort_unless(in_array($publicacion->estado, [Publicacion::ESTADO_BORRADOR, Publicacion::ESTADO_RECHAZADA], true), 400);

        Storage::disk('public')->delete($publicacion->imagen_path);
        $publicacion->delete();

        return redirect()->route('admin.publicidad.index')->with('success', 'Pieza eliminada.');
    }

    // ── Piloto automático (community manager IA) ──────────────────────────

    public function autopilot()
    {
        $aliado   = $this->aliadoActivo();
        $config   = \App\Models\AutopilotConfig::paraAliado($aliado->id);
        $iaConfig = IaConfiguracionAliado::paraAliado($aliado->id);

        $piezas = Publicacion::where('aliado_id', $aliado->id)
            ->where('origen', 'ia_auto')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        // Ranking de piezas publicadas por interacciones reales (para el card de rendimiento)
        $ranking = Publicacion::where('aliado_id', $aliado->id)
            ->publicadas()
            ->with('metricas')
            ->get()
            ->filter(fn ($p) => $p->metricas->isNotEmpty())
            ->map(fn ($p) => [
                'pieza'         => $p,
                'interacciones' => $p->metricas->sum(fn ($m) => $m->interacciones()),
                'alcance'       => $p->metricas->sum('alcance'),
            ])
            ->sortByDesc('interacciones')
            ->take(10)
            ->values();

        return view('admin.publicidad.autopilot', [
            'aliado'       => $aliado,
            'config'       => $config,
            'tieneIaTexto' => !empty($iaConfig->credencialesEfectivas()['api_key']),
            'tieneGemini'  => $iaConfig->tieneGemini(),
            'piezas'       => $piezas,
            'ranking'      => $ranking,
        ]);
    }

    public function autopilotUpdate(Request $request)
    {
        $aliado = $this->aliadoActivo();

        $validated = $request->validate([
            'activo'        => 'required|boolean',
            'modo'          => 'required|in:aprobar,auto',
            'hora'          => 'required|date_format:H:i',
            'dias'          => 'nullable|array',
            'dias.*'        => 'integer|between:1,7',
            'estilo_imagen' => 'required|in:ilustracion,fotorrealista,alternar',
        ]);

        $config = \App\Models\AutopilotConfig::paraAliado($aliado->id);
        $config->update([
            'activo'        => $validated['activo'],
            'modo'          => $validated['modo'],
            'hora'          => $validated['hora'],
            'dias'          => !empty($validated['dias']) && count($validated['dias']) < 7 ? array_values($validated['dias']) : null,
            'estilo_imagen' => $validated['estilo_imagen'],
        ]);

        return redirect()->route('admin.publicidad.autopilot')->with('success', 'Piloto automático actualizado.');
    }

    // ── Generación asistida (AJAX) ────────────────────────────────────────

    public function generarCopia(Request $request)
    {
        $aliado = $this->aliadoActivo();

        $validated = $request->validate([
            'tipo_pieza' => 'required|string|max:100',
            'contexto'   => 'required|string|max:500',
        ]);

        $resultado = CopiaIaGenerator::generarVariantes($aliado->id, $aliado->nombre, $validated['tipo_pieza'], $validated['contexto']);

        return response()->json($resultado);
    }

    public function generarImagen(Request $request)
    {
        $aliado = $this->aliadoActivo();
        $iaConfig = IaConfiguracionAliado::paraAliado($aliado->id);

        if (!$iaConfig->tieneGemini()) {
            return response()->json(['ok' => false, 'rutas' => [], 'error' => 'No hay una clave de Gemini configurada (ver Asistente Virtual).']);
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:1000',
            'estilo' => 'nullable|in:ilustracion,fotorrealista',
        ]);

        $modelo = $validated['estilo'] === 'fotorrealista'
            ? GeminiImagenGenerator::MODELO_FOTORREALISTA
            : GeminiImagenGenerator::MODELO_ILUSTRACION;

        $resultado = GeminiImagenGenerator::generarVariantes($iaConfig->gemini_api_key, $validated['prompt'], 2, $modelo);

        if ($resultado['ok']) {
            foreach ($resultado['rutas'] as $ruta) {
                \App\Services\Publicidad\LogoWatermarker::aplicar($ruta, $aliado->logo);
            }
            $resultado['urls'] = array_map(fn ($r) => asset('storage/' . $r) . '?v=' . time(), $resultado['rutas']);
        }

        return response()->json($resultado);
    }

    /** Sube a storage el PNG generado por canvas en el navegador (dataURL -> archivo) y devuelve su path. */
    public function subirCanvas(Request $request)
    {
        $this->aliadoActivo();

        $validated = $request->validate(['imagen' => 'required|image|max:5120']);
        $ruta = $request->file('imagen')->store('publicidad', 'public');

        return response()->json(['ok' => true, 'path' => $ruta, 'url' => asset('storage/' . $ruta)]);
    }

    private function aliadoActivo(): Aliado
    {
        $aliado = Aliado::find(session('aliado_id_activo'));
        abort_if(!$aliado, 400, 'No hay un aliado activo seleccionado.');
        return $aliado;
    }
}
