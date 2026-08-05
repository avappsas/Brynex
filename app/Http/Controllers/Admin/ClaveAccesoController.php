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

    // ─── Vista global para buscar y filtrar todas las claves ──────────
    public function vistaGlobal(Request $request)
    {
        $aliadoId = session('aliado_id_activo') ?: auth()->user()->aliado_id;

        $query = ClaveAcceso::where('aliado_id', $aliadoId)
            ->with(['razonSocial', 'cliente', 'empresa']);

        // Filtro por Razón Social
        if ($request->filled('razon_social_id')) {
            $query->where('razon_social_id', $request->get('razon_social_id'));
        }

        // Filtro por Entidad
        if ($request->filled('entidad')) {
            $query->where('entidad', 'LIKE', '%' . $request->get('entidad') . '%');
        }

        // Filtro por Tipo
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->get('tipo'));
        }

        // Búsqueda general (usuario, correo, etc.)
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where(function($q) use ($buscar) {
                $q->where('usuario', 'LIKE', "%$buscar%")
                  ->orWhere('correo_entidad', 'LIKE', "%$buscar%")
                  ->orWhere('observacion', 'LIKE', "%$buscar%")
                  ->orWhere('entidad', 'LIKE', "%$buscar%");
            });
        }

        $claves = $query->orderBy('tipo')->orderBy('entidad')->get();

        // Obtener razones sociales para el filtro select
        $razones = \App\Models\RazonSocial::where('aliado_id', $aliadoId)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        $claves = $this->taparContrasenas($claves);

        return view('admin.claves.global', compact('claves', 'razones'));
    }

    /**
     * Quita la contraseña de la colección si el usuario no tiene el permiso
     * restringido `claves_acceso.ver_contrasena`.
     *
     * Se hace en el servidor y no ocultándola en la vista porque el listado la
     * mandaba entera al navegador (en base64, que no es cifrado) y los tres
     * endpoints JSON la devolvían en claro: bastaba abrir el inspector. Tapar
     * el <span> no habría tapado nada.
     */
    private function taparContrasenas($claves)
    {
        if (auth()->user()->can('claves_acceso.ver_contrasena')) {
            return $claves;
        }

        return $claves->each(function ($c) {
            // Se conserva si hay o no clave guardada (el usuario necesita saber
            // que existe para pedirla), pero no su contenido.
            $c->contrasena = $c->contrasena ? '__oculta__' : null;
        });
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

        return response()->json($this->taparContrasenas($claves));
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

        return response()->json($this->taparContrasenas($claves));
    }

    // ─── Listar claves de una empresa (por empresa_id directo) ────────
    public function indexEmpresa(int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');

        $claves = ClaveAcceso::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresaId)
            ->orderBy('tipo')
            ->orderBy('entidad')
            ->get();

        return response()->json($this->taparContrasenas($claves));
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
            'empresa_id'       => 'nullable|integer',
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
        foreach (['cedula', 'razon_social_id', 'empresa_id', 'usuario', 'contrasena', 'link_acceso', 'correo_entidad', 'observacion'] as $campo) {
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
