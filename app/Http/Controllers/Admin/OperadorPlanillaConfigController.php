<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Configuración de operadores de planilla por aliado.
 * URL:  GET  admin/configuracion/operadores-planilla
 * URL:  POST admin/configuracion/operadores-planilla
 * URL:  PATCH admin/configuracion/operadores-planilla/{id}/toggle
 */
class OperadorPlanillaConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /** Vista principal: lista operadores globales + estado activo/inactivo para este aliado */
    public function index()
    {
        $aliadoId = session('aliado_id_activo');

        // Todos los operadores globales (aliado_id = null), ordenados
        $operadoresGlobales = DB::table('operadores_planilla')
            ->whereNull('aliado_id')
            ->orderBy('orden')
            ->get();

        // Estado actual de este aliado (pivot). Si no existe fila → tratamos como activo
        $pivotActivo = DB::table('aliado_operadores_planilla')
            ->where('aliado_id', $aliadoId)
            ->pluck('activo', 'operador_id'); // [operador_id => activo]

        // ¿Tiene alguna configuración guardada?
        $tieneConfig = DB::table('aliado_operadores_planilla')
            ->where('aliado_id', $aliadoId)
            ->exists();

        return view('admin.configuracion.operadores_planilla', compact(
            'operadoresGlobales',
            'pivotActivo',
            'tieneConfig'
        ));
    }

    /** Toggle AJAX: activa/inactiva un operador para este aliado */
    public function toggle(int $operadorId)
    {
        $aliadoId = session('aliado_id_activo');

        // Verificar que el operador existe y es global
        $operador = DB::table('operadores_planilla')
            ->whereNull('aliado_id')
            ->where('id', $operadorId)
            ->first();

        if (!$operador) {
            return response()->json(['ok' => false, 'mensaje' => 'Operador no encontrado.'], 404);
        }

        // Buscar pivot
        $pivot = DB::table('aliado_operadores_planilla')
            ->where('aliado_id', $aliadoId)
            ->where('operador_id', $operadorId)
            ->first();

        if ($pivot) {
            // Alternar estado
            $nuevoEstado = !$pivot->activo;
            DB::table('aliado_operadores_planilla')
                ->where('aliado_id', $aliadoId)
                ->where('operador_id', $operadorId)
                ->update(['activo' => $nuevoEstado, 'updated_at' => now()]);
        } else {
            // Primera vez que se toca: crear entrada inactiva (el operador estaba activo por defecto)
            $nuevoEstado = false;
            DB::table('aliado_operadores_planilla')->insert([
                'aliado_id'   => $aliadoId,
                'operador_id' => $operadorId,
                'activo'      => $nuevoEstado,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return response()->json([
            'ok'     => true,
            'activo' => $nuevoEstado,
            'nombre' => $operador->nombre,
        ]);
    }

    /** Guardar orden de los operadores (drag & drop futuro - por ahora reordena por orden numérico) */
    public function guardarOrden(Request $request)
    {
        $validated = $request->validate([
            'orden' => 'required|array',
            'orden.*' => 'integer',
        ]);

        foreach ($validated['orden'] as $posicion => $operadorId) {
            DB::table('operadores_planilla')
                ->where('id', $operadorId)
                ->update(['orden' => $posicion + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
