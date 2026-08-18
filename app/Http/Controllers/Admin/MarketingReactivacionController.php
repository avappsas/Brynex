<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappEnvioMasivo;
use App\Models\Aliado;
use App\Models\WhatsappEnvioMasivoDetalle;
use App\Services\Marketing\CandidatosReactivacion;
use App\Services\Marketing\EnvioReactivacion;
use Illuminate\Http\Request;

/**
 * Informe de la campaña de reactivación: a quién se le va a escribir y cuántos faltan.
 *
 * Es solo lectura a propósito. El envío se dispara por comando y no desde el panel: son
 * mensajes a gente real con costo por plantilla, y ese paso merece una decisión explícita,
 * no un botón a un clic de distancia.
 */
class MarketingReactivacionController extends Controller
{
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $desde = max(1, (int) $request->input('desde', CandidatosReactivacion::DIAS_DESDE));
        $hasta = max($desde + 1, (int) $request->input('hasta', CandidatosReactivacion::DIAS_HASTA));

        $r = CandidatosReactivacion::elegibles($aliadoId, $desde, $hasta);

        $envios = WhatsappEnvioMasivo::where('aliado_id', $aliadoId)
            ->where('tipo_envio', 'reactivacion')
            ->withCount('detalles')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // A quién ya se le escribió este mes: se muestra siempre, para que quede claro por
        // qué no aparece en la fila y no parezca que el filtro se lo comió.
        $enviadosMes = WhatsappEnvioMasivoDetalle::whereIn('envio_id', $envios->pluck('id'))
            ->whereHas('envio', fn ($q) => $q->where('created_at', '>=', now('America/Bogota')->startOfMonth()))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('admin.marketing.reactivacion', [
            'desde'       => $desde,
            'hasta'       => $hasta,
            'resumen'     => $r,
            'pendientes'  => $r['elegibles'],
            'envios'      => $envios,
            'enviadosMes' => $enviadosMes,
            'plantilla'   => 'reactivacion_afiliacion',
        ]);
    }

    /**
     * Lanza una tanda desde el panel.
     *
     * Pide la cantidad de forma explícita y no manda "todos" por defecto: 55 mensajes salen
     * en segundos y no se recogen.
     */
    public function enviar(Request $request)
    {
        $datos = $request->validate([
            'cantidad'  => 'required|integer|min:1|max:200',
            'plantilla' => 'required|string|max:120',
            'desde'     => 'required|integer|min:1',
            'hasta'     => 'required|integer|min:2',
        ]);

        $aliado = Aliado::find(session('aliado_id_activo'));
        $r = CandidatosReactivacion::elegibles($aliado->id, (int) $datos['desde'], (int) $datos['hasta']);

        $destinatarios = $r['elegibles']->take((int) $datos['cantidad']);

        $envio = EnvioReactivacion::lanzar($aliado, $destinatarios, $datos['plantilla'], [
            'dias_desde' => (int) $datos['desde'],
            'dias_hasta' => (int) $datos['hasta'],
            'origen'     => 'panel',
        ]);

        return redirect()
            ->route('admin.marketing.reactivacion', ['desde' => $datos['desde'], 'hasta' => $datos['hasta']])
            ->with($envio['ok'] ? 'exito' : 'error', $envio['mensaje']);
    }
}
