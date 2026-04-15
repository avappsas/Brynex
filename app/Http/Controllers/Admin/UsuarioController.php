<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function __construct()
    {
        // Solo superadmin y admin pueden gestionar usuarios
        $this->middleware(['auth', 'role:superadmin|admin']);
    }

    public function index()
    {
        $alidoActivoId = session('aliado_id_activo');
        $esBrynex      = auth()->user()->es_brynex;

        // SuperAdmin BryNex ve todos los usuarios del aliado activo
        // Admin normal ve solo usuarios de su aliado
        $usuarios = User::with(['aliado', 'roles'])
            ->when(!auth()->user()->hasRole('superadmin') || !$esBrynex, fn($q) =>
                $q->where('aliado_id', $alidoActivoId)
            )
            ->when(auth()->user()->hasRole('superadmin') && $esBrynex, fn($q) =>
                $q->where('aliado_id', $alidoActivoId)
            )
            ->withTrashed()
            ->orderBy('nombre')
            ->get();

        return view('admin.usuarios.index', compact('usuarios'));
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
        $data = $request->validate([
            'nombre'    => 'required|string|max:150',
            'cedula'    => 'required|string|max:20|unique:users,cedula',
            'email'     => 'nullable|email|max:150|unique:users,email',
            'telefono'  => 'nullable|string|max:30',
            'aliado_id' => 'required|integer|exists:aliados,id',
            'rol'       => 'required|string|exists:roles,name',
            'es_brynex' => 'boolean',
            'activo'    => 'boolean',
            'password'  => 'required|string|min:8|confirmed',
        ]);

        $usuario = User::create([
            'nombre'    => $data['nombre'],
            'cedula'    => $data['cedula'],
            'email'     => $data['email'] ?? $data['cedula'] . '@brynex.local',
            'telefono'  => $data['telefono'] ?? null,
            'aliado_id' => $data['aliado_id'],
            'es_brynex' => $request->boolean('es_brynex'),
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
        $data = $request->validate([
            'nombre'    => 'required|string|max:150',
            'cedula'    => "required|string|max:20|unique:users,cedula,{$usuario->id}",
            'email'     => "nullable|email|max:150|unique:users,email,{$usuario->id}",
            'telefono'  => 'nullable|string|max:30',
            'aliado_id' => 'required|integer|exists:aliados,id',
            'rol'       => 'required|string|exists:roles,name',
            'es_brynex' => 'boolean',
            'activo'    => 'boolean',
            'password'  => 'nullable|string|min:8|confirmed',
        ]);

        $usuario->update([
            'nombre'    => $data['nombre'],
            'cedula'    => $data['cedula'],
            'email'     => $data['email'] ?? $usuario->email,
            'telefono'  => $data['telefono'] ?? null,
            'aliado_id' => $data['aliado_id'],
            'es_brynex' => $request->boolean('es_brynex'),
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
