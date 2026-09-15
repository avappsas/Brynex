<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\Sos\SosNovedadService;
use App\Services\Sos\SosSesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Novedad de inicio laboral en S.O.S. desde el radicado de EPS de Afiliaciones.
 *
 * Antes de consultar o registrar hay que abrir la sesión del portal: el login
 * pide captcha y lo resuelve la persona, con la imagen que muestra `sesionEstado`
 * y los clics que manda `sesionClic`. La sesión es por empresa y queda abierta
 * un rato, así que los siguientes contratos de esa empresa ya no lo piden.
 */
class SosController extends Controller
{
    public function __construct(private SosNovedadService $servicio)
    {
        $this->middleware('auth');
    }

    public function precheck(int $contratoId)
    {
        $contrato = $this->contrato($contratoId);
        $prep = $this->servicio->preparar($contrato);

        return response()->json(['ok' => ! $prep['problemas'], 'sesion' => $this->estadoSesion($contrato)] + $prep);
    }

    public function sesionIniciar(int $contratoId)
    {
        $contrato = $this->contrato($contratoId);

        try {
            return response()->json(['ok' => true, 'sesion' => SosSesion::iniciar($contrato->aliado_id, (string) $contrato->razonSocial?->nit)]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function sesionEstado(int $contratoId)
    {
        return response()->json(['ok' => true, 'sesion' => $this->estadoSesion($this->contrato($contratoId))]);
    }

    public function sesionClic(Request $request, int $contratoId)
    {
        $datos = $request->validate(['x' => 'required|numeric', 'y' => 'required|numeric', 'reiniciar' => 'nullable|boolean']);
        $contrato = $this->contrato($contratoId);
        $nit = (string) $contrato->razonSocial?->nit;

        try {
            if ($request->boolean('reiniciar')) {
                SosSesion::reiniciarCaptcha($contrato->aliado_id, $nit);

                return response()->json(['ok' => true, 'sesion' => $this->estadoSesion($contrato)]);
            }

            // El clic ya trae la foto nueva del reto: sin otra vuelta al proceso.
            $sesion = SosSesion::clic($contrato->aliado_id, $nit, (float) $datos['x'], (float) $datos['y']);
            unset($sesion['ok']);

            return response()->json(['ok' => true, 'sesion' => $sesion]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function consultar(int $contratoId)
    {
        @set_time_limit(300);

        try {
            return response()->json($this->servicio->consultar($this->contrato($contratoId)));
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function registrar(Request $request, int $contratoId)
    {
        @set_time_limit(420);
        $datos = $request->validate(['fecha' => 'required|date']);

        try {
            return response()->json($this->servicio->registrar($this->contrato($contratoId), $datos['fecha'], Auth::id()));
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function estadoSesion(Contrato $contrato): array
    {
        $nit = (string) $contrato->razonSocial?->nit;

        return ($nit ? SosSesion::estado($contrato->aliado_id, $nit) : null) ?? ['etapa' => 'cerrada', 'mensaje' => 'Sin sesión abierta.'];
    }

    /** Siempre del aliado activo: el id llega por la URL. */
    private function contrato(int $id): Contrato
    {
        return Contrato::where('aliado_id', (int) session('aliado_id_activo'))->with('razonSocial')->findOrFail($id);
    }
}
