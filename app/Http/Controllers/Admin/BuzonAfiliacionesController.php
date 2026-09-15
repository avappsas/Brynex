<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorreoAfiliacion;
use App\Models\CorreoRecibido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Bandeja del buzón de afiliaciones: lo que el agente dejó por revisar, lo que
 * aplicó solo y los correos enviados que esperan respuesta.
 */
class BuzonAfiliacionesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $aliadoId = (int) session('aliado_id_activo');
        $estado = in_array($request->query('estado'), ['por_revisar', 'aplicado', 'informativo', 'revisado', 'ignorado', 'todos'], true)
            ? $request->query('estado') : 'por_revisar';

        $recibidos = CorreoRecibido::with(['contrato.cliente', 'correoAfiliacion'])
            ->where('aliado_id', $aliadoId)
            ->when($estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('recibido_at')->limit(200)->get();

        $esperando = CorreoAfiliacion::with('contrato.cliente')
            ->where('aliado_id', $aliadoId)->whereIn('estado', ['enviado', 'observaciones'])
            ->orderBy('vence_at')->get();

        $conteos = CorreoRecibido::where('aliado_id', $aliadoId)->selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado');

        return view('admin.afiliaciones.buzon', compact('recibidos', 'esperando', 'estado', 'conteos'));
    }

    /** Marca un correo recibido como revisado o ignorado. */
    public function marcar(Request $request, int $id)
    {
        $datos = $request->validate(['estado' => 'required|in:revisado,ignorado,por_revisar']);
        $correo = CorreoRecibido::where('aliado_id', (int) session('aliado_id_activo'))->findOrFail($id);
        $correo->update(['estado' => $datos['estado'], 'revisado_por' => Auth::id(), 'revisado_at' => now()]);

        return back()->with('success', 'Correo marcado como '.str_replace('_', ' ', $datos['estado']).'.');
    }

    /** Cierra el seguimiento de un correo enviado (p. ej. se resolvió por teléfono). */
    public function cerrarEnviado(int $id)
    {
        $correo = CorreoAfiliacion::where('aliado_id', (int) session('aliado_id_activo'))->findOrFail($id);
        $correo->update(['estado' => 'respondido', 'respondido_at' => $correo->respondido_at ?? now(),
            'respuesta_resumen' => trim(($correo->respuesta_resumen ? $correo->respuesta_resumen.' | ' : '').'Cerrado a mano por '.Auth::user()?->nombre)]);

        return back()->with('success', 'Seguimiento del correo cerrado.');
    }

    public function adjunto(int $id, int $indice)
    {
        $correo = CorreoRecibido::where('aliado_id', (int) session('aliado_id_activo'))->findOrFail($id);
        $adj = ($correo->adjuntos ?? [])[$indice] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($adj['ruta']), 404);

        return Storage::disk('local')->response($adj['ruta'], $adj['nombre']);
    }
}
