<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModalidadConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Pantalla de configuración: qué planes permite cada modalidad + RS independientes.
     * URL: GET admin/configuracion/modalidades
     */
    public function index()
    {
        $aliadoId = session('aliado_id_activo');

        $modalidades = DB::table('tipo_modalidad')
            ->where('activo', true)
            ->where('id', '!=', -100)
            ->orderBy('orden')
            ->get();

        $planes = DB::table('planes_contrato')
            ->where('activo', true)
            ->get();

        // Mapa actual: [tipo_modalidad_id][plan_id] = true
        $mapa = [];
        DB::table('modalidad_planes')->get()->each(function ($row) use (&$mapa) {
            $mapa[$row->tipo_modalidad_id][$row->plan_id] = true;
        });

        // Razones sociales del aliado activo para marcar cuáles son independientes
        $razionesSociales = DB::table('razones_sociales')
            ->where('aliado_id', $aliadoId)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'es_independiente']);

        return view('admin.configuracion.modalidades', compact('modalidades', 'planes', 'mapa', 'razionesSociales'));
    }

    /**
     * Guardar configuración de modalidades y RS independientes.
     * URL: POST admin/configuracion/modalidades
     */
    public function guardar(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        // 1. Guardar mapa modalidad → planes (global, no por aliado)
        $relaciones = $request->input('relaciones', []);
        $nuevos = [];
        foreach ($relaciones as $modalidadId => $planes) {
            foreach ($planes as $planId => $activo) {
                if ($activo) {
                    $nuevos[] = [
                        'tipo_modalidad_id' => (int) $modalidadId,
                        'plan_id'           => (int) $planId,
                    ];
                }
            }
        }
        DB::transaction(function () use ($nuevos) {
            DB::table('modalidad_planes')->truncate();
            if (!empty($nuevos)) {
                DB::table('modalidad_planes')->insert($nuevos);
            }
        });

        // 2. Guardar qué RS son independientes (por aliado)
        $rsIndependientes = $request->input('rs_independientes', []);
        // Primero poner todas las RS del aliado en es_independiente = false
        DB::table('razones_sociales')
            ->where('aliado_id', $aliadoId)
            ->update(['es_independiente' => false]);
        // Luego marcar las seleccionadas
        if (!empty($rsIndependientes)) {
            DB::table('razones_sociales')
                ->where('aliado_id', $aliadoId)
                ->whereIn('id', array_map('intval', $rsIndependientes))
                ->update(['es_independiente' => true]);
        }

        return redirect()
            ->route('admin.configuracion.modalidades')
            ->with('success', '✅ Configuración actualizada correctamente.');
    }
}
