<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarAlertaOperativa;
use App\Models\Aliado;
use App\Models\Bitacora;
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
        $esBrynex = auth()->user()->es_brynex;

        $buscar = $request->input('q');
        $filtroRol = $request->input('rol');
        $filtroEstado = $request->input('estado');

        // Obtener los roles para el filtro
        $roles = Role::orderBy('name')->pluck('name');

        $usuarios = User::with(['aliado', 'roles'])
            ->when(! auth()->user()->hasRole('superadmin') || ! $esBrynex, fn ($q) => $q->where('aliado_id', $alidoActivoId)
            )
            ->when(auth()->user()->hasRole('superadmin') && $esBrynex, fn ($q) => $q->where('aliado_id', $alidoActivoId)
            )
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('cedula', 'like', "%{$buscar}%");
                });
            })
            ->when($filtroRol, function ($q) use ($filtroRol) {
                $q->whereHas('roles', fn ($sub) => $sub->where('name', $filtroRol));
            })
            ->when($filtroEstado, function ($q) use ($filtroEstado) {
                if ($filtroEstado === 'activo') {
                    $q->where('activo', 1);
                } elseif ($filtroEstado === 'pausado') {
                    $q->where('activo', 0);
                } elseif ($filtroEstado === 'inactivo') {
                    $q->onlyTrashed();
                }
            })
            // Sin filtro de estado se muestran solo los vigentes. Los borrados
            // lógicamente (incluidas las cuentas duplicadas ya fusionadas) se
            // ven eligiendo «Inactivo», no de entrada: si no, la misma persona
            // aparece dos veces en la lista.
            ->orderByRaw('CASE WHEN deleted_at IS NULL AND activo = 1 THEN 0 WHEN deleted_at IS NULL AND activo = 0 THEN 1 ELSE 2 END')
            ->orderBy('nombre')
            ->get();

        return view('admin.usuarios.index', compact('usuarios', 'roles', 'buscar', 'filtroRol', 'filtroEstado'));
    }

    public function create()
    {
        $aliados = Aliado::activos()->orderBy('nombre')->get();

        return view('admin.usuarios.form', [
            'usuario' => new User,
            'aliados' => $aliados,
            'roles' => $this->rolesAsignables(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        $esBrynexAdmin = $authUser->es_brynex; // puede elegir aliado
        $esSuperBrynex = $authUser->hasRole('superadmin') && $authUser->es_brynex; // puede marcar es_brynex

        // Si no es BryNex, forzar aliado_id desde la sesión (ignorar el del request)
        if (! $esBrynexAdmin) {
            $request->merge(['aliado_id' => session('aliado_id_activo')]);
        }

        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'cedula' => ['required', 'string', 'max:20', Rule::unique('users', 'cedula')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'telefono' => 'nullable|string|max:30',
            'aliado_id' => 'required|integer|exists:aliados,id',
            'rol' => 'required|string|exists:roles,name',
            'es_brynex' => 'boolean',
            'activo' => 'boolean',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'cedula.unique' => 'Ya existe un usuario activo con esa cédula.',
            'email.unique' => 'Ya existe un usuario activo con ese correo.',
        ]);

        $this->autorizarRol($data['rol']);

        $usuario = User::create([
            'nombre' => $data['nombre'],
            'cedula' => $data['cedula'],
            'email' => $data['email'] ?? $data['cedula'].'@brynex.local',
            'telefono' => $data['telefono'] ?? null,
            'aliado_id' => $data['aliado_id'],
            // es_brynex solo lo puede asignar superadmin BryNex
            'es_brynex' => $esSuperBrynex ? $request->boolean('es_brynex') : false,
            'activo' => $request->boolean('activo', true),
            'password' => Hash::make($data['password']),
        ]);

        $usuario->assignRole($data['rol']);

        Bitacora::registrar(
            'usuario_creado',
            'User',
            $usuario->id,
            "Se creó el usuario '{$usuario->nombre}' con rol {$data['rol']}.",
            ['cedula' => $usuario->cedula, 'rol' => $data['rol'], 'creado_por' => $authUser->nombre],
            (int) $usuario->aliado_id
        );

        // Una cuenta nueva en un aliado es el momento exacto en que entra al
        // sistema alguien a quien BryNex no invitó. Cuando la crea BryNex no
        // hay nada que avisar: ya lo sabe quien la está creando.
        if (! $authUser->es_brynex) {
            EnviarAlertaOperativa::dispatch(
                'Usuario nuevo en un aliado',
                sprintf(
                    '%s creó la cuenta "%s" (CC %s) con rol %s en %s.',
                    $authUser->nombre,
                    $usuario->nombre,
                    $usuario->cedula,
                    $data['rol'],
                    $usuario->aliado->nombre ?? ('aliado '.$usuario->aliado_id)
                )
            );
        }

        // Si es BryNex, agregar a pivot aliado_user con el aliado seleccionado
        if ($usuario->es_brynex) {
            $usuario->aliados()->syncWithoutDetaching([
                $data['aliado_id'] => ['rol' => $data['rol'], 'activo' => true],
            ]);
        }

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->nombre}' creado correctamente.");
    }

    public function edit(User $usuario)
    {
        $this->autorizarUsuario($usuario);

        $aliados = Aliado::activos()->orderBy('nombre')->get();
        $roles = $this->rolesAsignables($usuario);

        return view('admin.usuarios.form', compact('usuario', 'aliados', 'roles'));
    }

    public function update(Request $request, User $usuario)
    {
        $this->autorizarUsuario($usuario);

        $authUser = auth()->user();
        $esBrynexAdmin = $authUser->es_brynex;
        $esSuperBrynex = $authUser->hasRole('superadmin') && $authUser->es_brynex;

        // Si no es BryNex, forzar aliado_id desde la sesión
        if (! $esBrynexAdmin) {
            $request->merge(['aliado_id' => session('aliado_id_activo')]);
        }

        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'cedula' => ['required', 'string', 'max:20', Rule::unique('users', 'cedula')->ignore($usuario->id)->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario->id)->whereNull('deleted_at')],
            'telefono' => 'nullable|string|max:30',
            'aliado_id' => 'required|integer|exists:aliados,id',
            'rol' => 'required|string|exists:roles,name',
            'es_brynex' => 'boolean',
            'activo' => 'boolean',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'cedula.unique' => 'Ya existe un usuario activo con esa cédula.',
            'email.unique' => 'Ya existe un usuario activo con ese correo.',
        ]);

        $this->autorizarRol($data['rol'], $usuario);

        $rolAnterior = $usuario->getRoleNames()->first();

        $usuario->update([
            'nombre' => $data['nombre'],
            'cedula' => $data['cedula'],
            'email' => $data['email'] ?? $usuario->email,
            'telefono' => $data['telefono'] ?? null,
            'aliado_id' => $data['aliado_id'],
            // es_brynex solo lo puede cambiar superadmin BryNex; de lo contrario se preserva el valor actual
            'es_brynex' => $esSuperBrynex ? $request->boolean('es_brynex') : $usuario->es_brynex,
            'activo' => $request->boolean('activo'),
            'password' => $data['password'] ? Hash::make($data['password']) : $usuario->password,
        ]);

        $usuario->syncRoles([$data['rol']]);

        if ($rolAnterior !== $data['rol']) {
            Bitacora::registrar(
                'usuario_rol_cambiado',
                'User',
                $usuario->id,
                "Rol de '{$usuario->nombre}': {$rolAnterior} → {$data['rol']}.",
                ['antes' => $rolAnterior, 'despues' => $data['rol'], 'cambiado_por' => $authUser->nombre],
                (int) $usuario->aliado_id
            );
        }

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->nombre}' actualizado.");
    }

    public function destroy(User $usuario)
    {
        $this->autorizarUsuario($usuario);

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
        $this->autorizarUsuario($usuario);
        $usuario->restore();

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->nombre}' restaurado.");
    }

    /**
     * Quién puede tocar la ficha de otro usuario.
     *
     * `index` filtra por aliado, pero `edit`/`update`/`destroy`/`restore`
     * reciben el usuario por la URL: sin esto, el superadmin de un aliado
     * podía editar a CUALQUIER usuario de la plataforma con solo cambiar el
     * id, y como `update` conserva el `es_brynex` del registro, se quedaba con
     * una cuenta BryNex + superadmin y acceso a todos los aliados.
     *
     * Son dos reglas, no una. La del aliado sola no basta: los usuarios BryNex
     * tienen un `aliado_id` real (hoy el 1 y el 2), que comparten con
     * superadmins de aliado; `puedeAccederAliado` les diría que sí.
     */
    /**
     * Roles que el usuario autenticado puede repartir.
     *
     * `superadmin` solo lo entrega BryNex. Se sigue mostrando cuando el editado
     * YA es superadmin: si no apareciera en el select, guardar el formulario
     * para corregirle el teléfono lo degradaría sin querer.
     */
    private function rolesAsignables(?User $editado = null)
    {
        // El pluck va en PHP, no en SQL: `->pluck('name','name')` sobre el query
        // genera `select [name], [name]` y sqlsrv lo rechaza por ambiguo.
        $roles = Role::orderBy('name')->get()->pluck('name', 'name');

        if (auth()->user()->es_brynex || ($editado && $editado->hasRole('superadmin'))) {
            return $roles;
        }

        return $roles->forget('superadmin');
    }

    /**
     * Nadie fuera de BryNex otorga `superadmin`.
     *
     * El rol hereda todo el catálogo salvo lo `restringido` (ver el Gate::before
     * de AuthServiceProvider), así que repartirlo equivale a entregar el aliado
     * entero. Antes lo podía hacer cualquier superadmin de aliado sobre cuentas
     * nuevas, que es justo por donde entra un tercero al que nadie invitó.
     *
     * No es escalada mantener el rol de quien ya lo tenía: eso se permite para
     * poder editarle los datos sin degradarlo.
     */
    private function autorizarRol(string $rol, ?User $editado = null): void
    {
        if ($rol !== 'superadmin' || auth()->user()->es_brynex) {
            return;
        }

        abort_if(
            ! $editado || ! $editado->hasRole('superadmin'),
            403,
            'Solo BryNex puede otorgar el rol superadmin. Solicítalo a tu contacto en BryNex.'
        );
    }

    private function autorizarUsuario(User $usuario): void
    {
        abort_unless(
            auth()->user()->puedeAccederAliado((int) $usuario->aliado_id),
            403,
            'Ese usuario pertenece a un aliado al que no tienes acceso.'
        );

        abort_if(
            $usuario->es_brynex && ! auth()->user()->es_brynex,
            403,
            'Solo un usuario de BryNex puede modificar una cuenta de BryNex.'
        );
    }
}
