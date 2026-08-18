<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappEnvioMasivo;
use App\Services\Marketing\CandidatosReactivacion;
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

        return view('admin.marketing.reactivacion', [
            'desde'      => $desde,
            'hasta'      => $hasta,
            'resumen'    => $r,
            'pendientes' => $r['elegibles'],
            'envios'     => $envios,
        ]);
    }
}
