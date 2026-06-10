<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConfig;
use App\Models\WhatsappPlantilla;
use App\Services\WhatsappApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestión de plantillas de WhatsApp para el aliado.
 * Solo el admin del aliado puede crear/editar plantillas.
 */
class WhatsappPlantillaController extends Controller
{
    public function __construct(protected WhatsappApiService $apiService) {}

    /**
     * Lista las plantillas del aliado activo.
     */
    public function index()
    {
        $alidoId    = session('aliado_id_activo');
        $config     = WhatsappConfig::paraAliado($alidoId);
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantillas = WhatsappPlantilla::delAliado($effectiveAliadoId)
            ->orderByRaw("CASE estado WHEN 'approved' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('nombre_display')
            ->get();

        return view('admin.whatsapp.plantillas.index', compact('plantillas'));
    }

    /**
     * Formulario de nueva plantilla.
     */
    public function create()
    {
        return view('admin.whatsapp.plantillas.form', ['plantilla' => null]);
    }

    /**
     * Guarda la nueva plantilla y opcionalmente la crea en Meta.
     */
    public function store(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $config  = WhatsappConfig::paraAliado($alidoId);
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $validated = $this->validarFormularioPlantilla($request);
        $validated['aliado_id'] = $effectiveAliadoId;
        $validated['estado']    = 'pending';

        $plantilla = WhatsappPlantilla::create($validated);

        // Si el usuario eligió crear también en Meta
        if ($request->boolean('crear_en_meta')) {
            if ($config->credencialesCompletas()) {
                $resultado = $this->apiService->crearPlantillaEnMeta($plantilla, $config);
                if ($resultado['ok']) {
                    $plantilla->update([
                        'meta_template_id' => $resultado['meta_template_id'],
                        'estado'           => $resultado['estado'] ?? 'pending',
                        'creado_en_meta'   => true,
                    ]);
                    return redirect()
                        ->route('admin.whatsapp.plantillas.index')
                        ->with('ok', 'Plantilla creada y enviada a Meta para aprobación. Puede tardar 24-72h.');
                }
                return redirect()
                    ->route('admin.whatsapp.plantillas.index')
                    ->with('warning', 'Plantilla guardada en el sistema, pero hubo un error al enviar a Meta: ' . ($resultado['error'] ?? ''));
            }
        }

        return redirect()
            ->route('admin.whatsapp.plantillas.index')
            ->with('ok', 'Plantilla guardada. Recuerda crearla también en Meta Business Suite para poder usarla.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(int $id)
    {
        $alidoId   = session('aliado_id_activo');
        $config    = WhatsappConfig::paraAliado($alidoId);
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantilla = WhatsappPlantilla::delAliado($effectiveAliadoId)->findOrFail($id);

        return view('admin.whatsapp.plantillas.form', compact('plantilla'));
    }

    /**
     * Actualiza la plantilla.
     */
    public function update(Request $request, int $id)
    {
        $alidoId   = session('aliado_id_activo');
        $config    = WhatsappConfig::paraAliado($alidoId);
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantilla = WhatsappPlantilla::delAliado($effectiveAliadoId)->findOrFail($id);

        $validated = $this->validarFormularioPlantilla($request);
        $plantilla->update($validated);

        return redirect()
            ->route('admin.whatsapp.plantillas.index')
            ->with('ok', 'Plantilla actualizada correctamente.');
    }

    /**
     * Elimina la plantilla (soft delete).
     */
    public function destroy(int $id)
    {
        $alidoId   = session('aliado_id_activo');
        $config    = WhatsappConfig::paraAliado($alidoId);
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantilla = WhatsappPlantilla::delAliado($effectiveAliadoId)->findOrFail($id);
        $plantilla->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Sincroniza el estado de las plantillas del aliado con Meta.
     */
    public function sincronizar()
    {
        $alidoId = session('aliado_id_activo');
        $config  = WhatsappConfig::paraAliado($alidoId);

        if (!$config->credencialesCompletas()) {
            return redirect()
                ->route('admin.whatsapp.plantillas.index')
                ->with('warning', 'No hay credenciales de WhatsApp configuradas para este aliado.');
        }

        $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
        $brynexId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        $targetAliadoId = $config->usa_cuenta_brynex ? $brynexId : $alidoId;

        $actualizadas = $this->apiService->sincronizarEstadoPlantillas($targetAliadoId, $config);

        return redirect()
            ->route('admin.whatsapp.plantillas.index')
            ->with('ok', "Sincronización completada. {$actualizadas} plantilla(s) actualizadas.");
    }

    /**
     * Muestra la interfaz para seleccionar e importar plantillas desde Meta.
     */
    public function vistaImportar()
    {
        $alidoId = session('aliado_id_activo');
        $config  = WhatsappConfig::paraAliado($alidoId);

        if (!$config->credencialesCompletas()) {
            return redirect()
                ->route('admin.whatsapp.plantillas.index')
                ->with('warning', 'No hay credenciales de WhatsApp configuradas para este aliado.');
        }

        $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
        $brynexId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        $targetAliadoId = $config->usa_cuenta_brynex ? $brynexId : $alidoId;

        $templatesMetaRaw = $this->apiService->obtenerPlantillasDeMeta($config);
        $plantillasLocales = WhatsappPlantilla::delAliado($targetAliadoId)->pluck('nombre')->toArray();

        $plantillasDisponibles = [];
        foreach ($templatesMetaRaw as $tmpl) {
            $nombre = $tmpl['name'] ?? '';
            $yaExiste = in_array($nombre, $plantillasLocales);

            // Parsear componentes para vista previa
            $parsed = $this->parsearComponentesMeta($tmpl['components'] ?? []);

            $plantillasDisponibles[] = [
                'id'         => $tmpl['id'] ?? '',
                'nombre'     => $nombre,
                'categoria'  => $tmpl['category'] ?? 'UTILITY',
                'idioma'     => $tmpl['language'] ?? 'es_CO',
                'estado'     => strtolower($tmpl['status'] ?? 'approved'),
                'ya_existe'  => $yaExiste,
                'cuerpo'     => $parsed['cuerpo'],
                'footer'     => $parsed['footer'],
                'header_tipo'=> $parsed['headerTipo'],
                'botones'    => $parsed['botones'],
            ];
        }

        return view('admin.whatsapp.plantillas.importar', compact('plantillasDisponibles'));
    }

    /**
     * Procesa la importación de las plantillas seleccionadas desde Meta.
     */
    public function procesarImportar(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $config  = WhatsappConfig::paraAliado($alidoId);

        if (!$config->credencialesCompletas()) {
            return redirect()
                ->route('admin.whatsapp.plantillas.index')
                ->with('warning', 'No hay credenciales de WhatsApp configuradas para este aliado.');
        }

        $request->validate([
            'plantillas'   => 'required|array',
            'plantillas.*' => 'required|string',
        ]);

        $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
        $brynexId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        $targetAliadoId = $config->usa_cuenta_brynex ? $brynexId : $alidoId;

        $seleccionadas = $request->input('plantillas');
        $templatesMetaRaw = $this->apiService->obtenerPlantillasDeMeta($config);

        $importadas = 0;
        foreach ($templatesMetaRaw as $tmpl) {
            $nombre = $tmpl['name'] ?? '';
            if (in_array($nombre, $seleccionadas)) {
                $parsed = $this->parsearComponentesMeta($tmpl['components'] ?? []);

                $plantillaExistente = WhatsappPlantilla::withTrashed()
                    ->where('aliado_id', $targetAliadoId)
                    ->where('nombre', $nombre)
                    ->first();

                $datosPlantilla = [
                    'nombre_display'   => str_replace('_', ' ', ucfirst($nombre)),
                    'categoria'        => $tmpl['category'] ?? 'UTILITY',
                    'idioma'           => $tmpl['language'] ?? 'es_CO',
                    'estado'           => strtolower($tmpl['status'] ?? 'approved'),
                    'meta_template_id' => $tmpl['id'] ?? null,
                    'creado_en_meta'   => true,
                    'header_tipo'      => $parsed['headerTipo'],
                    'header_valor'     => $parsed['headerValor'],
                    'cuerpo'           => $parsed['cuerpo'],
                    'footer'           => $parsed['footer'],
                    'botones'          => $parsed['botones'],
                    'variables_mapa'   => [],
                ];

                if ($plantillaExistente) {
                    if ($plantillaExistente->trashed()) {
                        $plantillaExistente->restore();
                    }
                    $plantillaExistente->update($datosPlantilla);
                } else {
                    WhatsappPlantilla::create(array_merge([
                        'aliado_id' => $targetAliadoId,
                        'nombre'    => $nombre,
                    ], $datosPlantilla));
                }

                $importadas++;
            }
        }

        return redirect()
            ->route('admin.whatsapp.plantillas.index')
            ->with('ok', "Se han importado correctamente {$importadas} plantilla(s) de WhatsApp.");
    }

    /**
     * Parsea los componentes de Meta en campos relacionales locales.
     */
    private function parsearComponentesMeta(array $components): array
    {
        $headerTipo = null;
        $headerValor = null;
        $cuerpo = '';
        $footer = null;
        $botones = [];

        foreach ($components as $comp) {
            $type = $comp['type'] ?? '';
            if ($type === 'HEADER') {
                $headerTipo = $comp['format'] ?? null;
                $headerValor = $comp['text'] ?? null;
            } elseif ($type === 'BODY') {
                $cuerpo = $comp['text'] ?? '';
            } elseif ($type === 'FOOTER') {
                $footer = $comp['text'] ?? null;
            } elseif ($type === 'BUTTONS') {
                $btns = $comp['buttons'] ?? [];
                foreach ($btns as $btn) {
                    $botones[] = [
                        'tipo'     => $btn['type'] ?? '',
                        'texto'    => $btn['text'] ?? '',
                        'url'      => $btn['url'] ?? null,
                        'telefono' => $btn['phone_number'] ?? null,
                    ];
                }
            }
        }

        return compact('headerTipo', 'headerValor', 'cuerpo', 'footer', 'botones');
    }

    /**
     * API: lista plantillas aprobadas (para el selector en el modal de cobros).
     */
    public function apiListarAprobadas()
    {
        $alidoId = session('aliado_id_activo');
        $config  = WhatsappConfig::paraAliado($alidoId);
        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantillas = WhatsappPlantilla::delAliado($effectiveAliadoId)
            ->aprobadas()
            ->select('id', 'nombre', 'nombre_display', 'cuerpo', 'variables_mapa', 'botones')
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'nombre'         => $p->nombre,
                'nombre_display' => $p->nombre_display,
                'preview'        => mb_substr($p->cuerpo, 0, 100),
                'cant_variables' => $p->cantidadVariables(),
                'variables_mapa' => $p->variables_mapa,
            ]);

        return response()->json(['ok' => true, 'plantillas' => $plantillas]);
    }

    private function validarFormularioPlantilla(Request $request): array
    {
        return $request->validate([
            'nombre'        => ['required', 'string', 'max:150', 'regex:/^[a-z0-9_]+$/'],
            'nombre_display'=> 'required|string|max:200',
            'categoria'     => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'idioma'        => 'required|string|max:10',
            'header_tipo'   => 'nullable|in:TEXT,IMAGE,DOCUMENT,VIDEO',
            'header_valor'  => 'nullable|string|max:2000',
            'cuerpo'        => 'required|string|max:4096',
            'footer'        => 'nullable|string|max:60',
            'botones'       => 'nullable|array|max:3',
            'botones.*.tipo'=> 'required|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'botones.*.texto'      => 'required|string|max:25',
            'botones.*.url'        => 'nullable|url|max:2000',
            'botones.*.telefono'   => 'nullable|string|max:25',
            'variables_mapa'=> 'nullable|array',
        ]);
    }
}
