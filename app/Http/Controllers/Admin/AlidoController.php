<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\User;
use Illuminate\Http\Request;

class AlidoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:superadmin']);
    }

    public function index()
    {
        $aliados = Aliado::withCount('usuarios')
            ->withTrashed()
            ->orderBy('nombre')
            ->get();
        return view('admin.aliados.index', compact('aliados'));
    }

    public function create()
    {
        $usuariosBrynex = User::where('es_brynex', true)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        return view('admin.aliados.form', ['aliado' => new Aliado(), 'usuariosBrynex' => $usuariosBrynex]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'               => 'required|string|max:150',
            'nit'                  => 'nullable|string|max:20|unique:aliados,nit',
            'razon_social'         => 'nullable|string|max:200',
            'contacto'             => 'nullable|string|max:100',
            'telefono'             => 'nullable|string|max:30',
            'celular'              => 'nullable|string|max:30',
            'whatsapp'             => 'nullable|string|max:30',
            'correo'               => 'nullable|email|max:150',
            'direccion'            => 'nullable|string|max:255',
            'ciudad'               => 'nullable|string|max:80',
            'color_primario'       => 'nullable|string|max:10',
            'activo'               => 'boolean',
            'logo'                 => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'afiliaciones_brynex'  => 'boolean',
            'encargado_afil_id'    => 'nullable|exists:users,id',
        ]);

        if ($request->hasFile('logo')) {
            $file      = $request->file('logo');
            $filename  = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('storage/logos'), $filename);
            $data['logo'] = 'logos/' . $filename;
        }

        $data['activo'] = $request->boolean('activo', true);
        $data['afiliaciones_brynex'] = $request->boolean('afiliaciones_brynex', false);
        $data['encargado_afil_id']   = $request->input('encargado_afil_id') ?: null;
        $data['whatsapp']            = $request->input('whatsapp') ?: null;

        $aliado = Aliado::create($data);

        // Guardar relación de módulos
        $modulosInput = $request->input('modulos', []);
        foreach (\App\Models\BrynexModulo::all() as $mod) {
            $activo = isset($modulosInput[$mod->id]) ? 1 : 0;
            $moduloAliado = \App\Models\BrynexModuloAliado::firstOrNew([
                'aliado_id' => $aliado->id,
                'modulo_id' => $mod->id
            ]);
            if (!$moduloAliado->exists) {
                $moduloAliado->fecha_inicio = now();
            }
            $moduloAliado->activo = $activo;
            $moduloAliado->save();
        }

        return redirect()->route('admin.aliados.index')
            ->with('success', "Aliado '{$data['nombre']}' creado correctamente.");
    }

    public function edit(Aliado $aliado)
    {
        $usuariosBrynex = User::where('es_brynex', true)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        return view('admin.aliados.form', compact('aliado', 'usuariosBrynex'));
    }

    public function update(Request $request, Aliado $aliado)
    {
        $data = $request->validate([
            'nombre'               => 'required|string|max:150',
            'nit'                  => "nullable|string|max:20|unique:aliados,nit,{$aliado->id}",
            'razon_social'         => 'nullable|string|max:200',
            'contacto'             => 'nullable|string|max:100',
            'telefono'             => 'nullable|string|max:30',
            'celular'              => 'nullable|string|max:30',
            'whatsapp'             => 'nullable|string|max:30',
            'correo'               => 'nullable|email|max:150',
            'direccion'            => 'nullable|string|max:255',
            'ciudad'               => 'nullable|string|max:80',
            'color_primario'       => 'nullable|string|max:10',
            'activo'               => 'boolean',
            'logo'                 => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'afiliaciones_brynex'  => 'boolean',
            'encargado_afil_id'    => 'nullable|exists:users,id',
        ]);

        if ($request->hasFile('logo')) {
            // Eliminar logo anterior si existe
            if ($aliado->logo) {
                $oldPath = public_path('storage/' . $aliado->logo);
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            $file      = $request->file('logo');
            $filename  = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('storage/logos'), $filename);
            $data['logo'] = 'logos/' . $filename;
        } else {
            // Preservar el logo existente si no se sube uno nuevo
            $data['logo'] = $aliado->logo;
        }

        $data['activo'] = $request->boolean('activo');
        $data['afiliaciones_brynex'] = $request->boolean('afiliaciones_brynex', false);
        $data['encargado_afil_id']   = $request->input('encargado_afil_id') ?: null;
        $data['whatsapp']            = $request->input('whatsapp') ?: null;

        $aliado->update($data);

        // Guardar relación de módulos
        $modulosInput = $request->input('modulos', []);
        foreach (\App\Models\BrynexModulo::all() as $mod) {
            $activo = isset($modulosInput[$mod->id]) ? 1 : 0;
            $moduloAliado = \App\Models\BrynexModuloAliado::firstOrNew([
                'aliado_id' => $aliado->id,
                'modulo_id' => $mod->id
            ]);
            if (!$moduloAliado->exists) {
                $moduloAliado->fecha_inicio = now();
            }
            $moduloAliado->activo = $activo;
            $moduloAliado->save();
        }

        return redirect()->route('admin.aliados.edit', $aliado)
            ->with('success', "Aliado '{$aliado->nombre}' actualizado correctamente.");
    }

    public function destroy(Aliado $aliado)
    {
        $aliado->delete(); // SoftDelete
        return redirect()->route('admin.aliados.index')
            ->with('success', "Aliado '{$aliado->nombre}' desactivado.");
    }

    public function restore($id)
    {
        $aliado = Aliado::withTrashed()->findOrFail($id);
        $aliado->restore();
        return redirect()->route('admin.aliados.index')
            ->with('success', "Aliado '{$aliado->nombre}' restaurado.");
    }
}
