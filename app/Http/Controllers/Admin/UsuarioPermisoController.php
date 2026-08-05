<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Modulo;
use App\Models\User;
use App\Services\PermisoService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

/**
 * Permisos individuales de un usuario, por encima de lo que le da su rol.
 *
 * Resuelve el caso que originó todo el módulo: "esto lo pueden ver solo
 * algunos admin". Dos mecanismos, según qué tan sensible sea:
 *
 *  · Permiso normal que el rol no trae (planos SS, anular facturas, gestionar
 *    razones sociales): se marca aquí y listo.
 *  · Permiso `restringido`: además, NINGÚN rol lo hereda, ni superadmin. Es
 *    para credenciales y cosas irreversibles.
 *
 * La pantalla solo pinta lo que hay que decidir: los permisos que el rol
 * `usuario` ya trae quedan fuera (columna `asignable`), porque el sistema solo
 * otorga y nunca revoca, así que la casilla estaría siempre marcada.
 *
 * La ruta ya exige `usuarios.permisos`, que solo tiene superadmin.
 */
class UsuarioPermisoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function edit(User $usuario)
    {
        $this->autorizarUsuario($usuario);

        // Solo lo que de verdad hay que decidir. Se dejan fuera:
        //  · los permisos que el rol `usuario` ya trae (columna `asignable`),
        //    porque estarían siempre marcados y en gris; son ~70 de 107.
        //  · el grupo `brynex`, que no se reparte desde aquí: es de la empresa
        //    dueña de la plataforma y se entrega con `permisos:aplicar-inicial`.
        $modulos = Modulo::activos()
            ->where('grupo', '!=', 'brynex')
            ->with('permisosAsignables')
            ->get()
            ->filter(fn (Modulo $m) => $m->permisosAsignables->isNotEmpty())
            ->groupBy('grupo');

        // El equipo se carga una sola vez con roles y permisos, y de ahí sale
        // TODO: lo del usuario editado y lo de la columna "quién lo tiene ya".
        // La BD está en un servidor remoto y cada consulta cuesta ~235 ms, así
        // que aquí lo que se cuida no son los ciclos de CPU, son los viajes.
        $equipo = User::where('aliado_id', $usuario->aliado_id)
            ->where('activo', true)
            ->with('roles.permissions', 'permissions')
            ->orderBy('nombre')
            ->get();

        // Si el usuario está pausado no aparece en el equipo: se carga aparte.
        $editado = $equipo->firstWhere('id', $usuario->id)
            ?? $usuario->load('roles.permissions', 'permissions');

        return view('admin.usuarios.permisos', [
            'usuario' => $editado,
            'modulos' => $modulos,
            'grupos' => Modulo::GRUPOS,
            'porRol' => $editado->roles->flatMap->permissions->pluck('name')->unique()->all(),
            'directos' => $editado->permissions->pluck('name')->all(),
            'esSuper' => $editado->roles->contains('name', 'superadmin'),
            'quienTiene' => $this->quienTiene($editado, $modulos, $equipo),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $this->autorizarUsuario($usuario);

        $data = $request->validate([
            'permisos' => 'nullable|array',
            'permisos.*' => 'string|exists:permissions,name',
        ]);

        $solicitados = $data['permisos'] ?? [];

        // No tiene sentido guardar como "directo" algo que el rol ya da: solo
        // ensucia la tabla y confunde al siguiente que abra esta pantalla.
        $porRol = $usuario->getPermissionsViaRoles()->pluck('name')->all();
        $nuevos = array_values(array_diff($solicitados, $porRol));

        // Los módulos solo_brynex no se le pueden dar a un usuario de aliado:
        // el Gate::before los bloquearía igual, pero mejor no guardar mentiras.
        $nuevos = array_values(array_filter(
            $nuevos,
            fn ($p) => ! PermisoService::esSoloBrynex($p) || $usuario->es_brynex
        ));

        $antes = $usuario->permissions->pluck('name')->all();

        // El formulario no pinta todos los permisos (los `asignable = false` y
        // el grupo brynex quedan fuera), así que un sync a secas borraría los
        // que ya tenía otorgados y no venían en el POST. Se conservan.
        $noPintados = Permission::whereIn('name', $antes)
            ->where(fn ($q) => $q->where('asignable', false)
                ->orWhereIn('modulo_id', Modulo::where('grupo', 'brynex')->pluck('id')))
            ->pluck('name')
            ->all();

        $nuevos = array_values(array_unique(array_merge($nuevos, $noPintados)));

        $usuario->syncPermissions($nuevos);

        $otorgados = array_values(array_diff($nuevos, $antes));
        $quitados = array_values(array_diff($antes, $nuevos));

        if ($otorgados || $quitados) {
            Bitacora::registrar(
                'permisos_actualizados',
                'User',
                $usuario->id,
                "Permisos de {$usuario->nombre}: +".count($otorgados).' / -'.count($quitados),
                ['otorgados' => $otorgados, 'quitados' => $quitados]
            );
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('admin.usuarios.permisos.edit', $usuario)
            ->with('success', 'Permisos actualizados: '.count($otorgados).' otorgados, '.count($quitados).' retirados.');
    }

    /**
     * Quién más del equipo tiene cada permiso, para pintarlo al lado.
     *
     * Sin esto, decidir es a ciegas: "¿le habilito planos a Angela?" depende de
     * si ya hay alguien más que los pueda sacar. Se listan solo los usuarios
     * ACTIVOS del mismo aliado, y se marca si les llega por el rol o se lo
     * dieron a dedo.
     *
     * Se calcula por CONJUNTOS, no llamando a `can()` por casilla: con 27
     * usuarios y 36 permisos eran ~1.000 llamadas al Gate y la página tardaba
     * 3 segundos. Aquí se arma una sola vez la lista de permisos de cada
     * usuario y el resto son búsquedas en un hash.
     *
     * La lógica replica el Gate::before de AuthServiceProvider; si aquella
     * cambia, esta tiene que cambiar con ella.
     *
     * @return array<string, array<int, array{nombre:string, directo:bool, rol:string, yo:bool}>>
     */
    private function quienTiene(User $usuario, $modulos, $equipo): array
    {
        // [nombre del permiso => ['restringido' => bool, 'solo_brynex' => bool]]
        // sacado de lo que ya está cargado, para no volver a la BD a preguntar
        // por los metadatos (PermisoService::meta() sería otra consulta).
        $meta = [];
        foreach ($modulos->flatten() as $modulo) {
            foreach ($modulo->permisosAsignables as $permiso) {
                $meta[$permiso->name] = [
                    'restringido' => (bool) $permiso->restringido || $modulo->restringido,
                    'solo_brynex' => (bool) $modulo->solo_brynex,
                ];
            }
        }

        $mapa = [];

        foreach ($equipo as $miembro) {
            $directos = $miembro->permissions->pluck('name')->flip();
            $porRol = $miembro->roles->flatMap->permissions->pluck('name')->flip();
            $esSuper = $miembro->roles->contains('name', 'superadmin');

            foreach ($meta as $nombre => $m) {
                // Gate::before, regla 1: los módulos de BryNex exigen es_brynex
                if ($m['solo_brynex'] && ! $miembro->es_brynex) {
                    continue;
                }

                $directo = $directos->has($nombre);

                // Gate::before, regla 2: superadmin lo tiene todo menos lo restringido
                $tiene = $directo || $porRol->has($nombre) || ($esSuper && ! $m['restringido']);

                if (! $tiene) {
                    continue;
                }

                $mapa[$nombre][] = [
                    'nombre' => $miembro->nombre,
                    'corto' => $this->nombreCorto($miembro->nombre),
                    'directo' => $directo,
                    'rol' => $miembro->roles->pluck('name')->first() ?? 'sin rol',
                    'yo' => $miembro->id === $usuario->id,
                ];
            }
        }

        // Se recorrió por usuario para no repetir el cálculo de sus permisos,
        // así que dentro de cada permiso el orden alfabético ya viene dado por
        // el orderBy del query.
        return $mapa;
    }

    /**
     * Nombre acortado para los chips de "quién lo tiene ya".
     *
     * Cada permiso puede listar diez personas en una tarjeta de 430 px, así que
     * los nombres largos se abrevian dejando el primero entero y el resto en
     * inicial: "Heidy Jisell Escobar" → "Heidy J. E.". El nombre completo, el
     * rol y el origen del permiso siguen en el `title` del chip.
     *
     * Si ni así cabe (un primer nombre larguísimo), se cae a iniciales puras.
     */
    private function nombreCorto(string $nombre, int $limite = 18): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));

        if (mb_strlen($nombre) <= $limite) {
            return $nombre;
        }

        $palabras = explode(' ', $nombre);
        $primero = array_shift($palabras);

        $abreviado = $primero.' '.collect($palabras)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)).'.')
            ->join(' ');

        if (mb_strlen($abreviado) <= $limite) {
            return $abreviado;
        }

        return collect(explode(' ', $nombre))
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->take(4)
            ->join('');
    }

    /**
     * Quién puede tocar los permisos de quién.
     *
     * Se apoya en `User::puedeAccederAliado()`, que ya es la regla del sistema,
     * en vez de comparar contra el aliado de la sesión:
     *
     *   - superadmin de un aliado → solo usuarios de SU aliado.
     *   - superadmin BryNex       → usuarios de cualquier aliado activo.
     *   - BryNex sin superadmin   → solo los aliados que tiene asignados.
     *
     * Comparar contra `session('aliado_id_activo')` era más estricto de la
     * cuenta: dejaba fuera al superadmin BryNex en cuanto el usuario objetivo
     * era de otro aliado, aunque tuviera acceso legítimo a ese aliado.
     * La protección contra el IDOR de C-5 se mantiene igual: quien no es
     * BryNex sigue encerrado en su propio aliado.
     */
    private function autorizarUsuario(User $usuario): void
    {
        abort_unless(
            auth()->user()->puedeAccederAliado((int) $usuario->aliado_id),
            403,
            'Ese usuario pertenece a un aliado al que no tienes acceso.'
        );
    }
}
