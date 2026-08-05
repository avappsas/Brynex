<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function __construct()
    {
        // El control fino vive en las rutas (permiso:usuarios.ver /
        // usuarios.gestionar / usuarios.permisos). Aquí solo se exige sesión,
        // para no tener dos fuentes de verdad que se contradigan.
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $alidoActivoId = session('aliado_id_activo');
        $esBrynex      = auth()->user()->es_brynex;

        $buscar       = $request->input('q');
        $filtroRol    = $request->input('rol');
        $filtroEstado = $request->input('estado');

        // Obtener los roles para el filtro
        $roles = Role::orderBy('name')->pluck('name');

        $usuarios = User::with(['aliado', 'roles'])
            ->when(!auth()->user()->hasRole('superadmin') || !$esBrynex, fn($q) =>
                $q->where('aliado_id', $alidoActivoId)
            )
            ->when(auth()->user()->hasRole('superadmin') && $esBrynex, fn($q) =>
                $q->where('aliado_id', $alidoActivoId)
            )
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('cedula', 'like', "%{$buscar}%");
                });
            })
            ->when($filtroRol, function ($q) use ($filtroRol) {
                $q->whereHas('roles', fn($sub) => $sub->where('name', $filtroRol));
            })
            ->when($filtroEstado, function ($q) use ($filtroEstado) {
                if ($filtroEstado === 'activo') {
                    $q->where('activo', 1);
                } elseif ($filtroEstado === 'pausado') {
                    $q->where('activo', 0);
                } elseif ($filtroEstado === 'inactivo') {
                    $q->onlyTrashed();
                }
            }, function ($q) {
                $q->withTrashed();
            })
            ->orderByRaw('CASE WHEN deleted_at IS NULL AND activo = 1 THEN 0 WHEN deleted_at IS NULL AND activo = 0 THEN 1 ELSE 2 END')
            ->orderBy('nombre')
            ->get();

        return view('admin.usuarios.index', compact('usuarios', 'roles', 'buscar', 'filtroRol', 'filtroEstado'));
    }

    public function create()
    {
        $aliados = Aliado::activos()->orderBy('nombre')->get();
        $roles   = Role::orderBy('name')->get()->pluck('name', 'name');
        return view('admin.usuarios.form', [
            'usuario' => new User(),
            'aliados' => $aliados,
            'roles'   => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        $esBrynexAdmin = $authUser->es_brynex; // puede elegir aliado
        $esSuperBrynex = $authUser->hasRole('superadmin') && $authUser->es_brynex; // puede marcar es_brynex

        // Si no es BryNex, forzar aliado_id desde la sesión (ignorar el del request)
        if (!$esBrynexAdmin) {
            $request->merge(['aliado_id' => session('aliado_id_activo')]);
        }

        $data = $request->validate([
            'nombre'    => 'required|string|max:150',
            'cedula'    => ['required','string','max:20', Rule::unique('users','cedula')->whereNull('deleted_at')],
            'email'     => ['nullable','email','max:150', Rule::unique('users','email')->whereNull('deleted_at')],
            'telefono'  => 'nullable|string|max:30',
            'aliado_id' => 'required|integer|exists:aliados,id',
            'rol'       => 'required|string|exists:roles,name',
            'es_brynex' => 'boolean',
            'activo'    => 'boolean',
            'password'  => 'required|string|min:8|confirmed',
        ], [
            'cedula.unique' => 'Ya existe un usuario activo con esa cédula.',
            'email.unique'  => 'Ya existe un usuario activo con ese correo.',
        ]);

        $usuario = User::create([
            'nombre'    => $data['nombre'],
            'cedula'    => $data['cedula'],
            'email'     => $data['email'] ?? $data['cedula'] . '@brynex.local',
            'telefono'  => $data['telefono'] ?? null,
            'aliado_id' => $data['aliado_id'],
            // es_brynex solo lo puede asignar superadmin BryNex
            'es_brynex' => $esSuperBrynex ? $request->boolean('es_brynex') : false,
            'activo'    => $request->boolean('activo', true),
            'password'  => Hash::make($data['password']),
        ]);

        $usuario->assignRole($data['rol']);

        // Si es BryNex, agregar a pivot aliado_user con el aliado seleccionado
        if ($usuario->es_brynex) {
            $usuario->aliados()->syncWithoutDetaching([
                $data['aliado_id'] => ['rol' => $data['rol'], 'activo' => true]
            ]);
        }

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->nombre}' creado correctamente.");
    }

    public function edit(User $usuario)
    {
        $aliados = Aliado::activos()->orderBy('nombre')->get();
        $roles   = Role::orderBy('name')->get()->pluck('name', 'name');
        return view('admin.usuarios.form', compact('usuario', 'aliados', 'roles'));
    }

    public function update(Request $request, User $usuario)
    {
        $authUser = auth()->user();
        $esBrynexAdmin = $authUser->es_brynex;
        $esSuperBrynex = $authUser->hasRole('superadmin') && $authUser->es_brynex;

        // Si no es BryNex, forzar aliado_id desde la sesión
        if (!$esBrynexAdmin) {
            $request->merge(['aliado_id' => session('aliado_id_activo')]);
        }

        $data = $request->validate([
            'nombre'    => 'required|string|max:150',
            'cedula'    => ['required','string','max:20', Rule::unique('users','cedula')->ignore($usuario->id)->whereNull('deleted_at')],
            'email'     => ['nullable','email','max:150', Rule::unique('users','email')->ignore($usuario->id)->whereNull('deleted_at')],
            'telefono'  => 'nullable|string|max:30',
            'aliado_id' => 'required|integer|exists:aliados,id',
            'rol'       => 'required|string|exists:roles,name',
            'es_brynex' => 'boolean',
            'activo'    => 'boolean',
            'password'  => 'nullable|string|min:8|confirmed',
        ], [
            'cedula.unique' => 'Ya existe un usuario activo con esa cédula.',
            'email.unique'  => 'Ya existe un usuario activo con ese correo.',
        ]);

        $usuario->update([
            'nombre'    => $data['nombre'],
            'cedula'    => $data['cedula'],
            'email'     => $data['email'] ?? $usuario->email,
            'telefono'  => $data['telefono'] ?? null,
            'aliado_id' => $data['aliado_id'],
            // es_brynex solo lo puede cambiar superadmin BryNex; de lo contrario se preserva el valor actual
            'es_brynex' => $esSuperBrynex ? $request->boolean('es_brynex') : $usuario->es_brynex,
            'activo'    => $request->boolean('activo'),
            'password'  => $data['password'] ? Hash::make($data['password']) : $usuario->password,
        ]);

        $usuario->syncRoles([$data['rol']]);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->nombre}' actualizado.");
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->withErrors(['No puede eliminarse a sí mismo.']);
        }
        $usuario->delete();
        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->nombre}' desactivado.");
    }

    public function restore($id)
    {
        $usuario = User::withTrashed()->findOrFail($id);
        $usuario->restore();
        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->nombre}' restaurado.");
    }
}
