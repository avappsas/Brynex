<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaveAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaveAccesoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    // ─── Listar claves de un cliente (por cédula) ─────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $cedula   = $request->get('cedula');

        if (!$cedula) {
            return response()->json(['error' => 'Cédula requerida'], 422);
        }

        $claves = ClaveAcceso::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->orderBy('tipo')
            ->orderBy('entidad')
            ->get();

        return response()->json($claves);
    }

    // ─── Listar claves de una razón social ────────────────────────────
    public function indexRazonSocial(int $razonSocialId)
    {
        $aliadoId = session('aliado_id_activo');

        $claves = ClaveAcceso::where('aliado_id', $aliadoId)
            ->where('razon_social_id', $razonSocialId)
            ->orderBy('tipo')
            ->orderBy('entidad')
            ->get();

        return response()->json($claves);
    }

    // ─── Crear nueva clave ────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['aliado_id'] = session('aliado_id_activo');

        // Limpiar nulos
        $data = $this->limpiarNulos($data);

        $clave = ClaveAcceso::create($data);

        return response()->json([
            'success' => true,
            'clave'   => $clave,
            'message' => 'Clave registrada correctamente.',
        ]);
    }

    // ─── Actualizar clave ─────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');
        $clave    = ClaveAcceso::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        $data = $this->validar($request, $id);
        $data = $this->limpiarNulos($data);

        $clave->update($data);

        return response()->json([
            'success' => true,
            'clave'   => $clave->fresh(),
            'message' => 'Clave actualizada correctamente.',
        ]);
    }

    // ─── Eliminar (desactivar) ────────────────────────────────────────
    public function destroy(int $id)
    {
        $aliadoId = session('aliado_id_activo');
        $clave    = ClaveAcceso::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        $clave->delete();

        return response()->json([
            'success' => true,
            'message' => 'Clave eliminada correctamente.',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'cedula'           => 'nullable|integer',
            'razon_social_id'  => 'nullable|integer',
            'tipo'             => 'required|string|max:80',
            'entidad'          => 'required|string|max:150',
            'usuario'          => 'nullable|string|max:150',
            'contrasena'       => 'nullable|string|max:200',
            'link_acceso'      => 'nullable|string|max:350',
            'correo_entidad'   => 'nullable|string|max:150',
            'observacion'      => 'nullable|string|max:300',
            'activo'           => 'nullable|boolean',
        ], [
            'tipo.required'    => 'El tipo es obligatorio.',
            'entidad.required' => 'La entidad es obligatoria.',
        ]);
    }

    private function limpiarNulos(array $data): array
    {
        foreach (['cedula', 'razon_social_id', 'usuario', 'contrasena', 'link_acceso', 'correo_entidad', 'observacion'] as $campo) {
            if (isset($data[$campo]) && $data[$campo] === '') {
                $data[$campo] = null;
            }
        }
        // activo por defecto true si no se envía
        if (!isset($data['activo'])) {
            $data['activo'] = true;
        }
        return $data;
    }
}
