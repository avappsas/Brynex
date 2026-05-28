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
        $plantillas = WhatsappPlantilla::delAliado($alidoId)
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

        $validated = $this->validarFormularioPlantilla($request);
        $validated['aliado_id'] = $alidoId;
        $validated['estado']    = 'pending';

        $plantilla = WhatsappPlantilla::create($validated);

        // Si el usuario eligió crear también en Meta
        if ($request->boolean('crear_en_meta')) {
            $config = WhatsappConfig::paraAliado($alidoId);
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
        $plantilla = WhatsappPlantilla::delAliado($alidoId)->findOrFail($id);

        return view('admin.whatsapp.plantillas.form', compact('plantilla'));
    }

    /**
     * Actualiza la plantilla.
     */
    public function update(Request $request, int $id)
    {
        $alidoId   = session('aliado_id_activo');
        $plantilla = WhatsappPlantilla::delAliado($alidoId)->findOrFail($id);

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
        $plantilla = WhatsappPlantilla::delAliado($alidoId)->findOrFail($id);
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

        $actualizadas = $this->apiService->sincronizarEstadoPlantillas($alidoId, $config);

        return redirect()
            ->route('admin.whatsapp.plantillas.index')
            ->with('ok', "Sincronización completada. {$actualizadas} plantilla(s) actualizadas.");
    }

    /**
     * API: lista plantillas aprobadas (para el selector en el modal de cobros).
     */
    public function apiListarAprobadas()
    {
        $alidoId = session('aliado_id_activo');

        $plantillas = WhatsappPlantilla::delAliado($alidoId)
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
