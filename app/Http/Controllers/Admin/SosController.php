<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\Sos\SosNovedadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Novedad de inicio laboral en S.O.S. desde el radicado de EPS de Afiliaciones.
 *
 * El portal lo opera la extensión BryNex Portales en el navegador de la persona
 * (el login pide captcha). Aquí solo: `precheck` arma los datos para la
 * extensión, `ladoB` entrega la página 2 del formulario firmada y `aplicar`
 * registra en el radicado lo que la extensión trajo del portal.
 */
class SosController extends Controller
{
    public function __construct(private SosNovedadService $servicio)
    {
        $this->middleware('auth');
    }

    public function precheck(int $contratoId)
    {
        $prep = $this->servicio->preparar($this->contrato($contratoId));

        return response()->json(['ok' => ! $prep['problemas']] + $prep);
    }

    /**
     * Usuario (y clave, si la persona tiene permiso de ver contraseñas del módulo
     * de claves) para que la extensión llene el login de S.O.S. Va aparte del
     * precheck para que la clave solo viaje al abrir el portal.
     */
    public function credencial(int $contratoId)
    {
        $contrato = $this->contrato($contratoId)->loadMissing('razonSocial');
        $cred = $this->servicio->credencial((string) $contrato->razonSocial?->nit);

        if (isset($cred['error'])) {
            return response()->json(['ok' => false, 'error' => $cred['error']], 422);
        }
        if (! Auth::user()->can('claves_acceso.ver_contrasena')) {
            unset($cred['contrasena']);
        }

        return response()->json(['ok' => true] + $cred)->header('Cache-Control', 'no-store');
    }

    /** Imagen del lado B (página 2 del formulario, firmada) para que la extensión la adjunte. */
    public function ladoB(int $contratoId)
    {
        try {
            [, $imagen] = $this->servicio->ladoB($this->contrato($contratoId));
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response(Storage::disk('local')->get($imagen), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function aplicar(Request $request, int $contratoId)
    {
        $datos = $request->validate([
            'novedad'           => 'nullable|array',
            'novedad.radicado'  => 'nullable|string|max:20',
            'novedad.estado'    => 'nullable|string|max:120',
            'novedad.causal'    => 'nullable|string|max:300',
            'registro'          => 'nullable|array',
            'registro.fecha'    => 'required_with:registro|date',
            'registro.envio'    => 'nullable|array',
            'registro.resultado' => 'nullable|array',
            'adjunto'           => 'nullable|boolean',
            'certificado'       => 'nullable|string|max:8000000',
        ]);

        try {
            $contrato = $this->contrato($contratoId);
            if (isset($datos['registro'])) {
                $this->servicio->validarFecha($datos['registro']['fecha']);
            }

            return response()->json($this->servicio->aplicar($contrato, $datos, Auth::id()));
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Siempre del aliado activo: el id llega por la URL. */
    private function contrato(int $id): Contrato
    {
        return Contrato::where('aliado_id', (int) session('aliado_id_activo'))->findOrFail($id);
    }
}
