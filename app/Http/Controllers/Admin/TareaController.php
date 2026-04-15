<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use App\Models\TareaGestion;
use App\Models\TareaDocumento;
use App\Models\TareaSemaforoConfig;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TareaController extends Controller
{
    // ── INDEX ───────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $alidoId   = session('aliado_id_activo') ?? Auth::user()->aliado_id;
        $user      = Auth::user();

        $query = Tarea::with(['encargado', 'creadoPor', 'razonSocial'])
            ->where('aliado_id', $alidoId);

        // Filtros
        if ($request->filled('encargado_id')) {
            $query->where('encargado_id', $request->encargado_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('cedula')) {
            $query->where('cedula', 'like', '%' . $request->cedula . '%');
        }
        // Filtro semáforo
        if ($request->filled('semaforo')) {
            $hoy = now()->toDateString();
            if ($request->semaforo === 'urgente') {
                $query->where('estado', '!=', 'cerrada')
                      ->where(function ($q) use ($hoy) {
                          $q->where('fecha_limite', '<=', $hoy)
                            ->orWhereNull('fecha_limite');
                      });
            } elseif ($request->semaforo === 'en_espera') {
                $query->where('estado', 'en_espera')->where('fecha_alerta', '<=', $hoy);
            }
        }

        // Ordenar por urgencia: rojo > naranja > amarillo > verde > cerradas
        $query->orderByRaw("
            CASE
                WHEN estado = 'cerrada' THEN 5
                WHEN estado = 'en_espera' AND fecha_alerta <= CAST(GETDATE() AS DATE) THEN 1
                WHEN fecha_limite < CAST(GETDATE() AS DATE) THEN 1
                WHEN fecha_limite <= DATEADD(day, 5, CAST(GETDATE() AS DATE)) THEN 2
                ELSE 3
            END ASC
        ")->orderBy('fecha_limite', 'asc');

        // Paginación
        $mostrarCerradas = $request->boolean('cerradas', false);
        if (!$mostrarCerradas) {
            $query->where('estado', '!=', 'cerrada');
        }

        $tareas = $query->paginate(50)->withQueryString();

        // Resúmenes
        $resumenEstados = DB::table('tareas')
            ->where('aliado_id', $alidoId)
            ->whereNull('deleted_at')
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $resumenTipos = DB::table('tareas')
            ->where('aliado_id', $alidoId)
            ->whereNull('deleted_at')
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
            ->select('tipo', DB::raw('COUNT(*) as total'))
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $vencidas = DB::table('tareas')
            ->where('aliado_id', $alidoId)
            ->whereNull('deleted_at')
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
            ->where('fecha_limite', '<', now()->toDateString())
            ->count();

        // Datos para selects
        $trabajadores = User::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get();
        $razonesSociales = DB::table('razones_sociales')->where('aliado_id', $alidoId)->where('estado', 'Activa')->orderBy('razon_social')->get(['id', 'razon_social']);
        $epsList = DB::table('eps')->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.tareas.index', compact(
            'tareas', 'resumenEstados', 'resumenTipos', 'vencidas',
            'trabajadores', 'razonesSociales', 'epsList'
        ));
    }

    // ── STORE ───────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'tipo'    => 'required|string',
            'cedula'  => 'required|string|max:20',
            'tarea'   => 'required|string',
            'encargado_id' => 'required|exists:users,id',
        ]);

        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        // Calcular fecha límite según semáforo config
        $fechaLimite = TareaSemaforoConfig::fechaLimiteParaTipo($request->tipo, $alidoId);

        $tarea = Tarea::create([
            'aliado_id'      => $alidoId,
            'tipo'           => $request->tipo,
            'estado'         => Tarea::ESTADO_PENDIENTE,
            'cedula'         => $request->cedula,
            'contrato_id'    => $request->contrato_id ?: null,
            'razon_social_id'=> $request->razon_social_id ?: null,
            'entidad'        => $request->entidad,
            'tarea'          => $request->tarea,
            'observacion'    => $request->observacion,
            'encargado_id'   => $request->encargado_id,
            'creado_por'     => Auth::id(),
            'fecha_limite'   => $fechaLimite,
            'fecha_radicado' => $request->fecha_radicado ?: null,
            'numero_radicado'=> $request->numero_radicado,
            'correo'         => $request->correo,
        ]);

        // Registro inicial en bitácora
        TareaGestion::create([
            'tarea_id'    => $tarea->id,
            'user_id'     => Auth::id(),
            'tipo_accion' => 'tramite_realizado',
            'observacion' => '✅ Tarea creada: ' . $tarea->tarea,
            'estado_tarea'=> Tarea::ESTADO_PENDIENTE,
            'created_at'  => now(),
        ]);

        return redirect()->route('admin.tareas.index')->with('success', 'Tarea creada correctamente.');
    }

    // ── UPDATE ──────────────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $request->validate([
            'tipo'         => 'required|string',
            'cedula'       => 'required|string|max:20',
            'tarea'        => 'required|string',
            'encargado_id' => 'required|exists:users,id',
        ]);

        $tarea = Tarea::findOrFail($id);
        $tarea->update([
            'tipo'           => $request->tipo,
            'cedula'         => $request->cedula,
            'contrato_id'    => $request->contrato_id ?: null,
            'razon_social_id'=> $request->razon_social_id ?: null,
            'entidad'        => $request->entidad,
            'tarea'          => $request->tarea,
            'observacion'    => $request->observacion,
            'encargado_id'   => $request->encargado_id,
            'fecha_radicado' => $request->fecha_radicado ?: null,
            'numero_radicado'=> $request->numero_radicado,
            'correo'         => $request->correo,
        ]);

        return response()->json(['ok' => true, 'message' => 'Tarea actualizada.']);
    }

    // ── DESTROY ─────────────────────────────────────────────────────────────
    public function destroy(int $id)
    {
        $tarea = Tarea::findOrFail($id);
        $tarea->delete();
        return redirect()->route('admin.tareas.index')->with('success', 'Tarea eliminada.');
    }

    // ── SHOW (JSON para modal) ───────────────────────────────────────────────
    public function show(int $id)
    {
        $tarea = Tarea::with([
            'encargado', 'creadoPor', 'razonSocial',
            'gestiones.user', 'gestiones.encargadoAnterior', 'gestiones.encargadoNuevo',
            'documentos.user',
        ])->findOrFail($id);

        // Enriquecer con datos del cliente
        $cliente = DB::table('clientes')->where('cedula', $tarea->cedula)
            ->select('cedula', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'celular', 'correo')
            ->first();

        return response()->json([
            'tarea'    => $tarea,
            'cliente'  => $cliente,
            'semaforo' => $tarea->colorSemaforo(),
            'icono'    => $tarea->iconoSemaforo(),
            'dias'     => $tarea->diasRestantes(),
        ]);
    }

    // ── GESTIÓN (bitácora) ───────────────────────────────────────────────────
    public function gestion(Request $request, int $id)
    {
        $request->validate([
            'tipo_accion'  => 'required|string',
            'observacion'  => 'required|string',
        ]);

        $tarea = Tarea::findOrFail($id);

        // Calcular fecha_alerta si pide recordatorio
        $fechaAlerta = null;
        $recordarDias = null;
        if ($request->filled('recordar_dias') && (int)$request->recordar_dias > 0) {
            $recordarDias = (int)$request->recordar_dias;
            $fechaAlerta  = now()->addDays($recordarDias)->toDateString();
        }

        // Cambiar estado según tipo_accion
        $nuevoEstado = $tarea->estado;
        if ($request->tipo_accion === 'tramite_realizado') {
            $nuevoEstado = Tarea::ESTADO_EN_GESTION;
            // Si pone recordatorio → en_espera
            if ($fechaAlerta) {
                $nuevoEstado = Tarea::ESTADO_EN_ESPERA;
            }
        } elseif ($request->tipo_accion === 'cambio_estado' && $request->filled('nuevo_estado')) {
            $nuevoEstado = $request->nuevo_estado;
        }

        // Grabar gestión en bitácora
        TareaGestion::create([
            'tarea_id'    => $tarea->id,
            'user_id'     => Auth::id(),
            'tipo_accion' => $request->tipo_accion,
            'observacion' => $request->observacion,
            'recordar_dias'=> $recordarDias,
            'fecha_alerta' => $fechaAlerta,
            'estado_tarea' => $nuevoEstado,
            'created_at'   => now(),
        ]);

        // Actualizar tarea
        $tarea->update([
            'estado'      => $nuevoEstado,
            'fecha_alerta'=> $fechaAlerta ?? $tarea->fecha_alerta,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Gestión registrada.',
            'estado'  => $nuevoEstado,
            'alerta'  => $fechaAlerta,
        ]);
    }

    // ── TRASLADAR ────────────────────────────────────────────────────────────
    public function trasladar(Request $request, int $id)
    {
        $request->validate([
            'encargado_id' => 'required|exists:users,id',
            'observacion'  => 'required|string',
        ]);

        $tarea = Tarea::findOrFail($id);
        $anterior = $tarea->encargado_id;

        // Bitácora de traslado
        TareaGestion::create([
            'tarea_id'           => $tarea->id,
            'user_id'            => Auth::id(),
            'tipo_accion'        => 'traslado',
            'observacion'        => $request->observacion,
            'encargado_anterior' => $anterior,
            'encargado_nuevo'    => $request->encargado_id,
            'estado_tarea'       => $tarea->estado,
            'created_at'         => now(),
        ]);

        $tarea->update(['encargado_id' => $request->encargado_id]);

        return response()->json(['ok' => true, 'message' => 'Tarea trasladada correctamente.']);
    }

    // ── CERRAR ───────────────────────────────────────────────────────────────
    public function cerrar(Request $request, int $id)
    {
        $request->validate([
            'resultado'   => 'required|in:positivo,negativo',
            'observacion' => 'required|string',
        ]);

        $tarea = Tarea::findOrFail($id);

        TareaGestion::create([
            'tarea_id'    => $tarea->id,
            'user_id'     => Auth::id(),
            'tipo_accion' => 'cambio_estado',
            'observacion' => '🏁 Tarea cerrada (' . ($request->resultado === 'positivo' ? '✅ Positiva' : '❌ Negativa') . '): ' . $request->observacion,
            'estado_tarea'=> Tarea::ESTADO_CERRADA,
            'created_at'  => now(),
        ]);

        $tarea->update([
            'estado'    => Tarea::ESTADO_CERRADA,
            'resultado' => $request->resultado,
        ]);

        return response()->json(['ok' => true, 'message' => 'Tarea cerrada.']);
    }

    // ── SUBIR DOCUMENTO ──────────────────────────────────────────────────────
    public function subirDocumento(Request $request, int $id)
    {
        $request->validate([
            'archivo' => 'required|file|max:10240',
            'nombre'  => 'required|string|max:200',
        ]);

        $tarea = Tarea::findOrFail($id);
        $file  = $request->file('archivo');
        $ext   = strtolower($file->getClientOriginalExtension());
        $ruta  = $file->store("tareas/{$tarea->id}", 'public');

        TareaDocumento::create([
            'tarea_id'    => $tarea->id,
            'user_id'     => Auth::id(),
            'nombre'      => $request->nombre,
            'ruta'        => $ruta,
            'tipo_archivo'=> $ext,
            'created_at'  => now(),
        ]);

        // Registrar en bitácora
        TareaGestion::create([
            'tarea_id'    => $tarea->id,
            'user_id'     => Auth::id(),
            'tipo_accion' => 'nota',
            'observacion' => '📎 Documento adjuntado: ' . $request->nombre,
            'estado_tarea'=> $tarea->estado,
            'created_at'  => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Documento subido correctamente.']);
    }

    // ── DESCARGAR DOCUMENTO ──────────────────────────────────────────────────
    public function descargarDocumento(int $docId)
    {
        $doc = TareaDocumento::findOrFail($docId);
        return Storage::disk('public')->download($doc->ruta, $doc->nombre . '.' . $doc->tipo_archivo);
    }

    // ── REPORTE ──────────────────────────────────────────────────────────────
    public function reporte(Request $request)
    {
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        $mes  = $request->get('mes', now()->month);
        $anio = $request->get('anio', now()->year);

        $trabajadores = User::where('aliado_id', $alidoId)->where('activo', true)->orderBy('nombre')->get();

        $datos = [];
        foreach ($trabajadores as $worker) {
            // Tareas asignadas en el periodo donde fue encargado
            $tareasTotales = DB::table('tareas')
                ->where('aliado_id', $alidoId)
                ->whereNull('deleted_at')
                ->where('encargado_id', $worker->id)
                ->count();

            if ($tareasTotales === 0) continue;

            $cerradasPositivo = DB::table('tareas')
                ->where('aliado_id', $alidoId)
                ->whereNull('deleted_at')
                ->where('encargado_id', $worker->id)
                ->where('estado', 'cerrada')
                ->where('resultado', 'positivo')
                ->count();

            $cerradasNegativo = DB::table('tareas')
                ->where('aliado_id', $alidoId)
                ->whereNull('deleted_at')
                ->where('encargado_id', $worker->id)
                ->where('estado', 'cerrada')
                ->where('resultado', 'negativo')
                ->count();

            $vencidas = DB::table('tareas')
                ->where('aliado_id', $alidoId)
                ->whereNull('deleted_at')
                ->where('encargado_id', $worker->id)
                ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
                ->where('fecha_limite', '<', now()->toDateString())
                ->count();

            // Promedio gestiones por tarea
            $promedioGestiones = DB::table('tarea_gestiones as g')
                ->join('tareas as t', 't.id', '=', 'g.tarea_id')
                ->where('t.aliado_id', $alidoId)
                ->whereNull('t.deleted_at')
                ->where('t.encargado_id', $worker->id)
                ->select(DB::raw('COUNT(g.id) as total'), DB::raw('COUNT(DISTINCT g.tarea_id) as tareas'))
                ->first();

            $avgGestiones = ($promedioGestiones && $promedioGestiones->tareas > 0)
                ? round($promedioGestiones->total / $promedioGestiones->tareas, 1)
                : 0;

            // Gestiones hechas a tiempo (antes de que se venciera la tarea)
            $totalGestiones = DB::table('tarea_gestiones as g')
                ->join('tareas as t', 't.id', '=', 'g.tarea_id')
                ->where('t.aliado_id', $alidoId)
                ->whereNull('t.deleted_at')
                ->where('t.encargado_id', $worker->id)
                ->count('g.id');

            $gestionesATiempo = DB::table('tarea_gestiones as g')
                ->join('tareas as t', 't.id', '=', 'g.tarea_id')
                ->where('t.aliado_id', $alidoId)
                ->whereNull('t.deleted_at')
                ->where('t.encargado_id', $worker->id)
                ->whereNotNull('t.fecha_limite')
                ->whereRaw('g.created_at <= t.fecha_limite')
                ->count('g.id');

            $puntualidad = $totalGestiones > 0
                ? round(($gestionesATiempo / $totalGestiones) * 100, 1)
                : 100;

            $datos[] = [
                'trabajador'        => $worker,
                'total'             => $tareasTotales,
                'cerradas_positivo' => $cerradasPositivo,
                'cerradas_negativo' => $cerradasNegativo,
                'vencidas'          => $vencidas,
                'avg_gestiones'     => $avgGestiones,
                'puntualidad'       => $puntualidad,
            ];
        }

        // Ordenar por más tareas totales
        usort($datos, fn($a, $b) => $b['total'] <=> $a['total']);

        return view('admin.tareas.reporte', compact('datos', 'trabajadores', 'mes', 'anio'));
    }

    // ── API: buscar cliente por cédula ───────────────────────────────────────
    public function buscarCliente(Request $request)
    {
        $cedula = $request->get('cedula');
        $cliente = DB::table('clientes')
            ->where('cedula', 'like', '%' . $cedula . '%')
            ->limit(10)
            ->get(['cedula', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'celular']);

        return response()->json($cliente);
    }

    // ── API: contratos por cédula ────────────────────────────────────────────
    public function contratosPorCedula(Request $request)
    {
        $cedula  = $request->get('cedula');
        $alidoId = session('aliado_id_activo') ?? Auth::user()->aliado_id;

        $contratos = DB::table('contratos')
            ->where('cedula', $cedula)
            ->where('aliado_id', $alidoId)
            ->orderByDesc('fecha_ingreso')
            ->get(['id', 'cedula', 'fecha_ingreso', 'estado']);

        return response()->json($contratos);
    }
}
