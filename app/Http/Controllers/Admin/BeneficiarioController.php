<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Cliente;
use Illuminate\Http\Request;

class BeneficiarioController extends Controller
{
    /** Lista beneficiarios de un cliente (respuesta JSON para AJAX) */
    public function index(Request $request, $cedula)
    {
        $alidoId = session('aliado_id_activo');

        $beneficiarios = Beneficiario::where('aliado_id', $alidoId)
            ->where('cc_cliente', $cedula)
            ->orderBy('nombres')
            ->get();

        if ($request->expectsJson()) {
            return response()->json($beneficiarios);
        }

        return back();
    }

    /** Crear un beneficiario */
    public function store(Request $request, $cedula)
    {
        $request->validate([
            'nombres'          => 'required|string|max:255',
            'tipo_doc'         => 'nullable|string|max:10',
            'n_documento'      => 'nullable|string|max:20',
            'parentesco'       => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date',
            'fecha_expedicion' => 'nullable|date',
            'observacion'      => 'nullable|string|max:500',
        ]);

        $alidoId = session('aliado_id_activo');

        Beneficiario::create([
            'aliado_id'        => $alidoId,
            'cc_cliente'       => $cedula,
            'tipo_doc'         => $request->tipo_doc,
            'n_documento'      => $request->n_documento,
            'nombres'          => strtoupper($request->nombres),
            'fecha_expedicion' => $request->fecha_expedicion ?: null,
            'fecha_nacimiento' => $request->fecha_nacimiento ?: null,
            'parentesco'       => $request->parentesco,
            'observacion'      => $request->observacion,
            'fecha_ingreso'    => now()->toDateString(),
        ]);

        return back()->with('success', 'Beneficiario registrado correctamente.');
    }

    /** Actualizar un beneficiario */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombres'          => 'required|string|max:255',
            'tipo_doc'         => 'nullable|string|max:10',
            'n_documento'      => 'nullable|string|max:20',
            'parentesco'       => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date',
            'fecha_expedicion' => 'nullable|date',
            'observacion'      => 'nullable|string|max:500',
        ]);

        $alidoId = session('aliado_id_activo');
        $beneficiario = Beneficiario::where('aliado_id', $alidoId)->findOrFail($id);

        $beneficiario->update([
            'tipo_doc'         => $request->tipo_doc,
            'n_documento'      => $request->n_documento,
            'nombres'          => strtoupper($request->nombres),
            'fecha_expedicion' => $request->fecha_expedicion ?: null,
            'fecha_nacimiento' => $request->fecha_nacimiento ?: null,
            'parentesco'       => $request->parentesco,
            'observacion'      => $request->observacion,
        ]);

        return back()->with('success', 'Beneficiario actualizado.');
    }

    /** Eliminar un beneficiario */
    public function destroy($id)
    {
        $alidoId = session('aliado_id_activo');
        $beneficiario = Beneficiario::where('aliado_id', $alidoId)->findOrFail($id);
        $beneficiario->delete();

        return back()->with('success', 'Beneficiario eliminado.');
    }
}
