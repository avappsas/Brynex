<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\ComisionAsesor;
use App\Models\Departamento;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AsesorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:superadmin|admin|usuario']);
    }

    // ─── Listado de asesores del aliado activo ────────────────────────
    public function index()
    {
        $alidoId = session('aliado_id_activo');

        $asesores = Asesor::delAliado($alidoId)
            ->withTrashed()
            ->withCount('comisiones')
            ->orderBy('nombre')
            ->get()
            ->map(function ($a) {
                $a->total_pendiente = $a->totalPendiente();
                return $a;
            });

        return view('admin.asesores.index', compact('asesores'));
    }

    // ─── Formulario crear ─────────────────────────────────────────────
    public function create()
    {
        $departamentos = Departamento::orderBy('nombre')->get();
        return view('admin.asesores.form', [
            'asesor'        => new Asesor(),
            'departamentos' => $departamentos,
        ]);
    }

    // ─── Guardar nuevo asesor ─────────────────────────────────────────
    public function store(Request $request)
    {
        $alidoId = session('aliado_id_activo');

        $data = $request->validate([
            'cedula'               => "required|string|max:20|unique:asesores,cedula,NULL,id,aliado_id,{$alidoId}",
            'nombre'               => 'required|string|max:200',
            'telefono'             => 'nullable|string|max:50',
            'celular'              => 'nullable|string|max:50',
            'correo'               => 'nullable|email|max:150',
            'direccion'            => 'nullable|string|max:255',
            'ciudad'               => 'nullable|string|max:100',
            'departamento'         => 'nullable|string|max:100',
            'cuenta_bancaria'      => 'nullable|string|max:100',
            'comision_afil_tipo'   => 'required|in:fijo,porcentaje',
            'comision_afil_valor'  => 'required|numeric|min:0',
            'comision_admon_tipo'  => 'required|in:fijo,porcentaje',
            'comision_admon_valor' => 'required|numeric|min:0',
            'fecha_ingreso'        => 'nullable|date',
            'activo'               => 'boolean',
        ], $this->mensajes());

        $data['aliado_id'] = $alidoId;
        $data['activo']    = $request->boolean('activo', true);

        $asesor = Asesor::create($data);

        return redirect()->route('admin.asesores.show', $asesor)
            ->with('success', "Asesor '{$asesor->nombre}' creado correctamente.");
    }

    // ─── Detalle del asesor con historial de comisiones ───────────────
    public function show(Asesor $asesor)
    {
        $this->autorizarAliado($asesor);

        $comisiones = $asesor->comisiones()
            ->orderByDesc('periodo')
            ->orderBy('tipo')
            ->paginate(20);

        return view('admin.asesores.show', compact('asesor', 'comisiones'));
    }

    // ─── Formulario editar ────────────────────────────────────────────
    public function edit(Asesor $asesor)
    {
        $this->autorizarAliado($asesor);
        $departamentos = Departamento::orderBy('nombre')->get();
        return view('admin.asesores.form', compact('asesor', 'departamentos'));
    }

    // ─── Actualizar asesor ────────────────────────────────────────────
    public function update(Request $request, Asesor $asesor)
    {
        $this->autorizarAliado($asesor);
        $alidoId = session('aliado_id_activo');

        $data = $request->validate([
            'cedula'               => "required|string|max:20|unique:asesores,cedula,{$asesor->id},id,aliado_id,{$alidoId}",
            'nombre'               => 'required|string|max:200',
            'telefono'             => 'nullable|string|max:50',
            'celular'              => 'nullable|string|max:50',
            'correo'               => 'nullable|email|max:150',
            'direccion'            => 'nullable|string|max:255',
            'ciudad'               => 'nullable|string|max:100',
            'departamento'         => 'nullable|string|max:100',
            'cuenta_bancaria'      => 'nullable|string|max:100',
            'comision_afil_tipo'   => 'required|in:fijo,porcentaje',
            'comision_afil_valor'  => 'required|numeric|min:0',
            'comision_admon_tipo'  => 'required|in:fijo,porcentaje',
            'comision_admon_valor' => 'required|numeric|min:0',
            'fecha_ingreso'        => 'nullable|date',
            'activo'               => 'boolean',
        ], $this->mensajes());

        $data['activo'] = $request->boolean('activo');
        $asesor->update($data);

        return redirect()->route('admin.asesores.show', $asesor)
            ->with('success', "Asesor '{$asesor->nombre}' actualizado.");
    }

    // ─── Desactivar (soft delete) ─────────────────────────────────────
    public function destroy(Asesor $asesor)
    {
        $this->autorizarAliado($asesor);
        $asesor->delete();
        return redirect()->route('admin.asesores.index')
            ->with('success', "Asesor '{$asesor->nombre}' desactivado.");
    }

    // ─── Restaurar ────────────────────────────────────────────────────
    public function restore(int $id)
    {
        $asesor = Asesor::withTrashed()->findOrFail($id);
        $this->autorizarAliado($asesor);
        $asesor->restore();
        return redirect()->route('admin.asesores.index')
            ->with('success', "Asesor '{$asesor->nombre}' restaurado.");
    }

    // ─── Reporte mensual de comisiones ───────────────────────────────
    public function reporteMensual(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $anio = (int) $request->get('anio', now()->year);
        $mes  = (int) $request->get('mes',  now()->month);

        $asesores = Asesor::delAliado($alidoId)
            ->activos()
            ->with(['comisiones' => fn($q) => $q->delPeriodo($anio, $mes)])
            ->orderBy('nombre')
            ->get();

        // Totales del periodo
        $totalAfiliacion    = ComisionAsesor::where('aliado_id', $alidoId)->delPeriodo($anio, $mes)->where('tipo', 'afiliacion')->sum('valor_comision');
        $totalAdmon         = ComisionAsesor::where('aliado_id', $alidoId)->delPeriodo($anio, $mes)->where('tipo', 'administracion')->sum('valor_comision');
        $totalPendiente     = ComisionAsesor::where('aliado_id', $alidoId)->delPeriodo($anio, $mes)->where('pagado', false)->sum('valor_comision');
        $totalPagado        = ComisionAsesor::where('aliado_id', $alidoId)->delPeriodo($anio, $mes)->where('pagado', true)->sum('valor_comision');

        $periodoLabel = Carbon::createFromDate($anio, $mes, 1)->locale('es')->isoFormat('MMMM [de] YYYY');

        return view('admin.asesores.reporte-mensual', compact(
            'asesores', 'anio', 'mes', 'periodoLabel',
            'totalAfiliacion', 'totalAdmon', 'totalPendiente', 'totalPagado'
        ));
    }

    // ─── Marcar comisión como pagada ─────────────────────────────────
    public function marcarPagada(Request $request, ComisionAsesor $comision)
    {
        $comision->update([
            'pagado'     => true,
            'fecha_pago' => $request->get('fecha_pago', now()->toDateString()),
        ]);
        return back()->with('success', 'Comisión marcada como pagada.');
    }

    // ─── Registrar comisión manual ────────────────────────────────────
    public function registrarComision(Request $request, Asesor $asesor)
    {
        $this->autorizarAliado($asesor);

        $data = $request->validate([
            'contrato_ref'   => 'nullable|string|max:50',
            'tipo'           => 'required|in:afiliacion,administracion',
            'periodo'        => 'required|date',
            'valor_base'     => 'required|numeric|min:0',
            'tipo_calculo'   => 'required|in:fijo,porcentaje',
            'valor_comision' => 'required|numeric|min:0',
            'observacion'    => 'nullable|string|max:255',
        ]);

        ComisionAsesor::create(array_merge($data, [
            'aliado_id' => $asesor->aliado_id,
            'asesor_id' => $asesor->id,
            'pagado'    => false,
        ]));

        return back()->with('success', 'Comisión registrada correctamente.');
    }

    // ─── Privados ─────────────────────────────────────────────────────
    private function autorizarAliado(Asesor $asesor): void
    {
        if ((int) $asesor->aliado_id !== (int) session('aliado_id_activo')) {
            abort(403, 'No tiene acceso a este asesor.');
        }
    }

    private function mensajes(): array
    {
        return [
            'cedula.required'               => 'La cédula es obligatoria.',
            'cedula.unique'                 => 'Ya existe un asesor con esa cédula en este aliado.',
            'nombre.required'               => 'El nombre es obligatorio.',
            'comision_afil_tipo.required'   => 'Seleccione el tipo de comisión de afiliación.',
            'comision_afil_valor.required'  => 'Ingrese el valor de la comisión de afiliación.',
            'comision_admon_tipo.required'  => 'Seleccione el tipo de comisión de administración.',
            'comision_admon_valor.required' => 'Ingrese el valor de la comisión de administración.',
        ];
    }
}
