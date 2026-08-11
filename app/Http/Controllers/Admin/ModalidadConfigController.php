<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoModalidad;
use App\Models\ConfiguracionBrynex;
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

        // Traer TODAS las modalidades (activas e inactivas) para gestionar su estado
        $modalidades = TipoModalidad::where('id', '!=', -100)
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

        // Valor actual de la regla AFP obligatorio
        $reglaAfpActiva = ConfiguracionBrynex::reglaAfpObligatorio();

        // Combinaciones activas que el aliado todavía no ha tarifado, para marcarlas en la
        // grilla: activar un plan en una modalidad sin ponerle precio la deja cotizando con
        // el valor general del plan, que casi nunca es el correcto.
        $sinTarifar = $this->combinacionesSinTarifar((int) $aliadoId);

        return view('admin.configuracion.modalidades', compact(
            'modalidades', 'planes', 'mapa', 'razionesSociales', 'reglaAfpActiva', 'sinTarifar'
        ));
    }

    /**
     * Mapa [modalidad][plan] = true para las combinaciones vendibles que no tienen ninguna
     * celda de tarifario en este aliado.
     */
    private function combinacionesSinTarifar(int $aliadoId): array
    {
        $celdas = \App\Services\TarifaAsesorService::celdasDelAliado($aliadoId);

        $sinTarifar = [];
        foreach (\App\Services\TarifaAsesorService::combinaciones() as $combo) {
            $planId      = (int) $combo['plan']->id;
            $modalidadId = (int) $combo['modalidad']->id;

            // Basta con que un nivel de riesgo tenga valor para no marcarla como pendiente.
            $tiene = collect($combo['niveles'])->contains(
                fn ($n) => $celdas->has("{$planId}_{$modalidadId}_{$n}")
            );

            if (! $tiene) {
                $sinTarifar[$modalidadId][$planId] = true;
            }
        }

        return $sinTarifar;
    }

    /**
     * Activar / Inactivar una modalidad via AJAX.
     * URL: PATCH admin/configuracion/modalidades/{id}/toggle
     */
    public function toggleActivo(int $id)
    {
        $modalidad = TipoModalidad::findOrFail($id);
        $modalidad->activo = !$modalidad->activo;
        $modalidad->save();

        return response()->json([
            'ok'     => true,
            'activo' => $modalidad->activo,
            'label'  => $modalidad->observacion ?: $modalidad->tipo_modalidad,
        ]);
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

        // Combinaciones que existían antes de guardar, para saber cuáles quedaron nuevas y
        // avisar que les falta precio.
        $antes = DB::table('modalidad_planes')
            ->get()
            ->map(fn ($r) => $r->tipo_modalidad_id.'_'.$r->plan_id)
            ->flip();

        DB::transaction(function () use ($nuevos) {
            // delete() en vez de truncate(): hace lo mismo, pero truncate es DDL y en este
            // proyecto está prohibido (ver CLAUDE.md) — además delete sí respeta el rollback
            // de la transacción en todos los motores.
            DB::table('modalidad_planes')->delete();
            if (!empty($nuevos)) {
                DB::table('modalidad_planes')->insert($nuevos);
            }
        });

        $recienActivadas = collect($nuevos)
            ->reject(fn ($n) => $antes->has($n['tipo_modalidad_id'].'_'.$n['plan_id']))
            ->values();

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

        // 3. Guardar regla AFP obligatorio
        $reglaAfp = $request->has('regla_afp_obligatorio') ? '1' : '0';
        ConfiguracionBrynex::establecer('regla_afp_obligatorio', $reglaAfp);

        // 4. Avisar de las combinaciones recién habilitadas que aún no tienen precio: si nadie
        //    las tarifa, cotizan con el valor general del plan sin que se note.
        \App\Services\TarifaAsesorService::limpiarCache();
        $aviso = $this->avisoSinTarifar($recienActivadas, (int) $aliadoId);

        return redirect()
            ->route('admin.configuracion.modalidades')
            ->with('success', '✅ Configuración actualizada correctamente.')
            ->with('pendientes_tarifar', $aviso);
    }

    /**
     * Texto del aviso: qué combinaciones nuevas quedaron sin precio. Devuelve null si no hay
     * nada que avisar (nada nuevo, o todo lo nuevo ya tenía tarifa).
     */
    private function avisoSinTarifar($recienActivadas, int $aliadoId): ?array
    {
        if ($recienActivadas->isEmpty()) {
            return null;
        }

        $sinTarifar = $this->combinacionesSinTarifar($aliadoId);
        $planes = DB::table('planes_contrato')->pluck('nombre', 'id');
        $modalidades = TipoModalidad::pluck('observacion', 'id');
        $modalidadesAlt = TipoModalidad::pluck('tipo_modalidad', 'id');

        $faltan = $recienActivadas
            ->filter(fn ($n) => isset($sinTarifar[$n['tipo_modalidad_id']][$n['plan_id']]))
            ->map(fn ($n) => ($modalidades[$n['tipo_modalidad_id']] ?: $modalidadesAlt[$n['tipo_modalidad_id']] ?? '?')
                .' · '.($planes[$n['plan_id']] ?? '?'))
            ->values();

        return $faltan->isEmpty() ? null : $faltan->all();
    }
}
