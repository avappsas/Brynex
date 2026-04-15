<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $data['activo'] = $request->boolean('activo', true);
        $data['afiliaciones_brynex'] = $request->boolean('afiliaciones_brynex', false);
        $data['encargado_afil_id']   = $request->input('encargado_afil_id') ?: null;

        Aliado::create($data);

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
            if ($aliado->logo) Storage::disk('public')->delete($aliado->logo);
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $data['activo'] = $request->boolean('activo');
        $data['afiliaciones_brynex'] = $request->boolean('afiliaciones_brynex', false);
        $data['encargado_afil_id']   = $request->input('encargado_afil_id') ?: null;

        $aliado->update($data);

        return redirect()->route('admin.aliados.index')
            ->with('success', "Aliado '{$aliado->nombre}' actualizado.");
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
