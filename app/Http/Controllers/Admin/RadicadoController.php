<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Contrato;
use App\Models\DocumentoCliente;
use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RadicadoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Actualiza el estado de un radicado y registra el movimiento en la bitácora.
     */
    public function update(Request $request, $id)
    {
        $radicado = Radicado::findOrFail($id);

        $data = $request->validate([
            'estado'             => 'required|in:pendiente,tramite,traslado,error,ok',
            'observacion'        => 'nullable|string|max:2000',
            'canal_envio'        => 'nullable|string|max:30',
            'numero_radicado'    => 'nullable|string|max:80',
        ]);

        $estadoAnterior = $radicado->estado;

        // Actualizar radicado
        $radicado->update([
            'estado'          => $data['estado'],
            'observacion'     => $data['observacion'] ?? $radicado->observacion,
            'canal_envio'     => $data['canal_envio'] ?? $radicado->canal_envio,
            'numero_radicado' => $data['numero_radicado'] ?? $radicado->numero_radicado,
            'user_id'         => Auth::id(),
            'fecha_inicio_tramite' => ($data['estado'] === 'tramite' && !$radicado->fecha_inicio_tramite)
                ? now() : $radicado->fecha_inicio_tramite,
            'fecha_confirmacion'   => ($data['estado'] === 'ok' && !$radicado->fecha_confirmacion)
                ? now() : $radicado->fecha_confirmacion,
        ]);

        // Registrar movimiento en bitácora
        RadicadoMovimiento::create([
            'radicado_id'    => $radicado->id,
            'contrato_id'    => $radicado->contrato_id,
            'tipo_proceso'   => 'afiliacion',
            'entidad'        => $radicado->tipo,
            'user_id'        => Auth::id(),
            'estado_anterior'=> $estadoAnterior,
            'estado_nuevo'   => $data['estado'],
            'observacion'    => $data['observacion'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'     => true,
                'estado' => $data['estado'],
                'color'  => $radicado->fresh()->estadoColor(),
                'icono'  => $radicado->fresh()->estadoIcono(),
                'dias'   => $radicado->fresh()->diasEnEstado(),
            ]);
        }

        return back()->with('success', 'Radicado actualizado.');
    }

    /**
     * Sube el PDF del radicado confirmado.
     * Ruta: storage/radicados/{aliado_id}/{contrato_id}/{cedula}/
     * Máximo 3MB.
     */
    public function subirPdf(Request $request, $id)
    {
        $radicado = Radicado::with('contrato')->findOrFail($id);
        $contrato = $radicado->contrato;

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:3072', // 3 MB
        ]);

        // Si ya existe un PDF anterior, eliminarlo
        if ($radicado->ruta_pdf && Storage::disk('local')->exists($radicado->ruta_pdf)) {
            Storage::disk('local')->delete($radicado->ruta_pdf);
        }

        $archivo = $request->file('pdf');
        $alidoId = $contrato->aliado_id;
        $cedula  = $contrato->cedula;
        $nombre  = Str::slug($radicado->tipo) . '_' . now()->format('Ymd_His') . '.pdf';
        $ruta    = "radicados/{$alidoId}/{$contrato->id}/{$cedula}/{$nombre}";

        Storage::disk('local')->put($ruta, file_get_contents($archivo->getRealPath()));

        $radicado->update(['ruta_pdf' => $ruta]);

        // Registrar en bitácora
        RadicadoMovimiento::create([
            'radicado_id'    => $radicado->id,
            'contrato_id'    => $radicado->contrato_id,
            'tipo_proceso'   => 'afiliacion',
            'entidad'        => $radicado->tipo,
            'user_id'        => Auth::id(),
            'estado_anterior'=> $radicado->estado,
            'estado_nuevo'   => $radicado->estado,
            'observacion'    => 'PDF del radicado subido: ' . $nombre,
        ]);

        return response()->json(['ok' => true, 'ruta' => $ruta]);
    }

    /**
     * Descarga el PDF del radicado.
     */
    public function descargarPdf($id)
    {
        $radicado = Radicado::findOrFail($id);

        if (!$radicado->ruta_pdf || !Storage::disk('local')->exists($radicado->ruta_pdf)) {
            abort(404, 'PDF no encontrado.');
        }

        $filename = 'radicado_' . strtoupper($radicado->tipo) . '_' . $radicado->contrato_id . '.pdf';
        return Storage::disk('local')->download($radicado->ruta_pdf, $filename);
    }

    /**
     * Marca el radicado como enviado al cliente.
     */
    public function marcarEnviado(Request $request, $id)
    {
        $radicado = Radicado::findOrFail($id);

        $data = $request->validate([
            'enviado_al_cliente'   => 'required|boolean',
            'canal_envio_cliente'  => 'nullable|string|max:30',
            'observacion_envio'    => 'nullable|string|max:1000',
        ]);

        $radicado->update([
            'enviado_al_cliente'  => $data['enviado_al_cliente'],
            'canal_envio_cliente' => $data['canal_envio_cliente'] ?? $radicado->canal_envio_cliente,
            'fecha_envio_cliente' => $data['enviado_al_cliente'] ? now() : null,
        ]);

        // Registrar en bitácora
        if ($data['enviado_al_cliente']) {
            RadicadoMovimiento::create([
                'radicado_id'    => $radicado->id,
                'contrato_id'    => $radicado->contrato_id,
                'tipo_proceso'   => 'afiliacion',
                'entidad'        => $radicado->tipo,
                'user_id'        => Auth::id(),
                'estado_anterior'=> $radicado->estado,
                'estado_nuevo'   => $radicado->estado,
                'observacion'    => 'Radicado enviado al cliente vía ' . ($data['canal_envio_cliente'] ?? 'sin especificar')
                    . ($data['observacion_envio'] ? '. ' . $data['observacion_envio'] : ''),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Retorna la bitácora completa de un radicado (JSON).
     */
    public function bitacora($id)
    {
        $radicado = Radicado::with([
            'movimientos.user:id,nombre',
        ])->findOrFail($id);

        $movimientos = $radicado->movimientos->map(function ($m, $i) use ($radicado) {
            // Calcular días entre este movimiento y el anterior
            $siguiente = $radicado->movimientos->get($i - 1); // están en desc
            $dias = $siguiente
                ? (int) $m->created_at->diffInDays($siguiente->created_at)
                : 0;

            return [
                'id'              => $m->id,
                'fecha'           => $m->created_at->format('d/m/Y H:i'),
                'fecha_rel'       => $m->created_at->diffForHumans(),
                'usuario'         => $m->user?->nombre ?? 'Sistema',
                'estado_anterior' => $m->estado_anterior,
                'estado_nuevo'    => $m->estado_nuevo,
                'observacion'     => $m->observacion,
                'entidad'         => $m->entidadLabel(),
                'dias_desde_prev' => $dias,
            ];
        });

        return response()->json([
            'radicado' => [
                'tipo'        => $radicado->tipoLabel(),
                'estado'      => $radicado->estado,
                'dias_actual' => $radicado->diasEnEstado(),
                'tiene_pdf'   => (bool) $radicado->ruta_pdf,
            ],
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * Retorna documentos del cotizante y sus beneficiarios (JSON).
     */
    public function documentosCotizante($id)
    {
        $radicado = Radicado::with('contrato')->findOrFail($id);
        $contrato = $radicado->contrato;
        $alidoId  = $contrato->aliado_id;
        $cedula   = $contrato->cedula;

        // Documentos del cotizante
        $docsCliente = DocumentoCliente::with('subidor:id,nombre')
            ->where('aliado_id', $alidoId)
            ->where('cc_cliente', $cedula)
            ->whereNull('doc_beneficiario')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => [
                'id'      => $d->id,
                'tipo'    => $d->tipo_legible,
                'archivo' => $d->nombre_archivo,
                'fecha'   => $d->created_at->format('d/m/Y'),
                'para'    => 'Cotizante',
                'url_dl'  => route('admin.documentos.download', $d->id),
            ]);

        // Documentos de beneficiarios
        $docsBendef = DocumentoCliente::with('subidor:id,nombre')
            ->where('aliado_id', $alidoId)
            ->where('cc_cliente', $cedula)
            ->whereNotNull('doc_beneficiario')
            ->orderBy('doc_beneficiario')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => [
                'id'         => $d->id,
                'tipo'       => $d->tipo_legible,
                'archivo'    => $d->nombre_archivo,
                'fecha'      => $d->created_at->format('d/m/Y'),
                'para'       => 'Beneficiario: ' . $d->doc_beneficiario,
                'url_dl'     => route('admin.documentos.download', $d->id),
            ]);

        // Beneficiarios del contrato (para contexto)
        $beneficiarios = Beneficiario::where('aliado_id', $alidoId)
            ->where('cc_cliente', $cedula)
            ->get(['id', 'nombres', 'n_documento', 'parentesco']);

        return response()->json([
            'cotizante'    => $docsCliente,
            'beneficiarios'=> $docsBendef,
            'lista_benef'  => $beneficiarios,
        ]);
    }
}
