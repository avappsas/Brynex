<?php

namespace App\Http\Controllers;

use App\Models\Aliado;
use App\Models\IaConocimiento;
use App\Models\IaPreguntaEntrenador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Revisión y entrenamiento del conocimiento del Asistente IA: aprobar/editar/rechazar
 * lo que la IA encontró en internet, responder preguntas pendientes registradas por la IA,
 * y editor manual de conocimiento (siempre aprobado, porque lo escribe el entrenador).
 * Solo accesible por superadmin BryNex.
 */
class IaConocimientoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->autorizarSuperadminBrynex();

        $pendientes = IaConocimiento::where('estado', 'pendiente')
            ->orderByDesc('created_at')
            ->get();

        $preguntas = IaPreguntaEntrenador::where('estado', 'pendiente')
            ->with('aliado:id,nombre')
            ->orderBy('created_at')
            ->get();

        $aprobados = IaConocimiento::where('estado', 'aprobado')
            ->orderByDesc('vigente_desde')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $aliados = Aliado::orderBy('nombre')->get(['id', 'nombre']);

        return view('brynex.ia.conocimiento', compact('pendientes', 'preguntas', 'aprobados', 'aliados'));
    }

    /** Aprueba una entrada pendiente (de internet), con posibilidad de editarla y de cerrar una anterior. */
    public function aprobar(Request $request, int $id)
    {
        $this->autorizarSuperadminBrynex();

        $entrada = IaConocimiento::findOrFail($id);

        $validated = $request->validate([
            'titulo'         => 'required|string|max:255',
            'contenido'      => 'required|string',
            'categoria'      => 'nullable|string|max:100',
            'aliado_id'      => 'nullable|exists:aliados,id',
            'vigente_desde'  => 'nullable|date',
            'reemplaza_a'    => 'nullable|integer|exists:ia_conocimiento,id',
        ]);

        DB::transaction(function () use ($entrada, $validated) {
            $vigenteDesde = $validated['vigente_desde'] ?: now()->toDateString();

            $entrada->fill([
                'titulo'        => $validated['titulo'],
                'contenido'     => $validated['contenido'],
                'categoria'     => $validated['categoria'] ?? $entrada->categoria,
                'aliado_id'     => $validated['aliado_id'] ?: null,
                'estado'        => 'aprobado',
                'vigente_desde' => $vigenteDesde,
                'creado_por'    => Auth::id(),
            ])->save();

            if (!empty($validated['reemplaza_a'])) {
                $anterior = IaConocimiento::find($validated['reemplaza_a']);
                if ($anterior && $anterior->id !== $entrada->id) {
                    $anterior->update(['vigente_hasta' => \Carbon\Carbon::parse($vigenteDesde)->subDay()->toDateString()]);
                }
            }
        });

        return back()->with('success', 'Conocimiento aprobado y disponible para el asistente.');
    }

    public function rechazar(int $id)
    {
        $this->autorizarSuperadminBrynex();

        IaConocimiento::findOrFail($id)->update(['estado' => 'rechazado']);

        return back()->with('success', 'Conocimiento rechazado.');
    }

    /** Crea o edita conocimiento manualmente. Al venir del entrenador, queda aprobado de inmediato. */
    public function guardar(Request $request)
    {
        $this->autorizarSuperadminBrynex();

        $validated = $request->validate([
            'id'             => 'nullable|integer|exists:ia_conocimiento,id',
            'titulo'         => 'required|string|max:255',
            'contenido'      => 'required|string',
            'categoria'      => 'nullable|string|max:100',
            'aliado_id'      => 'nullable|exists:aliados,id',
            'vigente_desde'  => 'nullable|date',
            'vigente_hasta'  => 'nullable|date',
            'reemplaza_a'    => 'nullable|integer|exists:ia_conocimiento,id',
        ]);

        DB::transaction(function () use ($validated) {
            $vigenteDesde = $validated['vigente_desde'] ?: now()->toDateString();

            $entrada = isset($validated['id']) ? IaConocimiento::findOrFail($validated['id']) : new IaConocimiento();
            $entrada->fill([
                'titulo'        => $validated['titulo'],
                'contenido'     => $validated['contenido'],
                'categoria'     => $validated['categoria'] ?? null,
                'aliado_id'     => $validated['aliado_id'] ?: null,
                'fuente'        => 'entrenador',
                'estado'        => 'aprobado',
                'vigente_desde' => $vigenteDesde,
                'vigente_hasta' => $validated['vigente_hasta'] ?? null,
                'creado_por'    => Auth::id(),
            ])->save();

            if (!empty($validated['reemplaza_a'])) {
                $anterior = IaConocimiento::find($validated['reemplaza_a']);
                if ($anterior && $anterior->id !== $entrada->id) {
                    $anterior->update(['vigente_hasta' => \Carbon\Carbon::parse($vigenteDesde)->subDay()->toDateString()]);
                }
            }
        });

        return back()->with('success', 'Conocimiento guardado.');
    }

    public function eliminar(int $id)
    {
        $this->autorizarSuperadminBrynex();

        IaConocimiento::findOrFail($id)->update(['estado' => 'rechazado']);

        return back()->with('success', 'Conocimiento retirado.');
    }

    /** El entrenador responde una pregunta pendiente: la respuesta se guarda como conocimiento aprobado. */
    public function responderPregunta(Request $request, int $id)
    {
        $this->autorizarSuperadminBrynex();

        $pregunta = IaPreguntaEntrenador::findOrFail($id);

        $validated = $request->validate([
            'respuesta' => 'required|string',
            'aliado_id' => 'nullable|exists:aliados,id',
            'categoria' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($pregunta, $validated) {
            $pregunta->update([
                'respuesta'      => $validated['respuesta'],
                'estado'         => 'respondida',
                'respondido_por' => Auth::id(),
                'respondido_at'  => now(),
            ]);

            IaConocimiento::create([
                'aliado_id'     => $validated['aliado_id'] ?: $pregunta->aliado_id,
                'titulo'        => \Illuminate\Support\Str::limit($pregunta->pregunta, 200, ''),
                'contenido'     => $validated['respuesta'],
                'categoria'     => $validated['categoria'] ?? null,
                'fuente'        => 'entrenador',
                'estado'        => 'aprobado',
                'vigente_desde' => now()->toDateString(),
                'creado_por'    => Auth::id(),
            ]);
        });

        return back()->with('success', 'Respuesta guardada y añadida al conocimiento del asistente.');
    }

    public function descartarPregunta(int $id)
    {
        $this->autorizarSuperadminBrynex();

        IaPreguntaEntrenador::findOrFail($id)->update(['estado' => 'descartada']);

        return back()->with('success', 'Pregunta descartada.');
    }

    private function autorizarSuperadminBrynex(): void
    {
        $user = Auth::user();
        abort_unless($user->es_brynex && $user->hasRole('superadmin'), 403);
    }
}
