<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\Sos\SosCorreoService;
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

    // ── Plan B: afiliación por correo al asesor ────────────────────────────

    /** Vista previa del correo al asesor: destinatario, asunto, texto, adjuntos y lo que falta. */
    public function correoPreparar(Request $request, SosCorreoService $correo, int $contratoId)
    {
        $motivo = in_array($request->query('motivo'), ['portal_rechazo', 'independiente', 'manual'], true) ? $request->query('motivo') : 'manual';

        return response()->json(['ok' => true] + $correo->preparar(
            $this->contrato($contratoId), $motivo, $request->boolean('con_beneficiarios', true), mb_substr((string) $request->query('detalle', ''), 0, 300)
        ));
    }

    /** Sube la copia del documento de identidad del cliente, para poder enviarla. */
    public function correoDocumento(Request $request, SosCorreoService $correo, int $contratoId)
    {
        $request->validate(['archivo' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png']);
        $doc = $correo->subirDocumento($this->contrato($contratoId), $request->file('archivo'), Auth::id());

        return response()->json(['ok' => true, 'documento_id' => $doc->id]);
    }

    public function correoEnviar(Request $request, SosCorreoService $correo, int $contratoId)
    {
        $datos = $request->validate([
            'para'              => 'required|string|max:500',
            'cc'                => 'nullable|string|max:500',
            'asunto'            => 'required|string|max:300',
            'cuerpo'            => 'required|string|max:10000',
            'motivo'            => 'required|in:portal_rechazo,independiente,manual',
            'con_beneficiarios' => 'nullable|boolean',
            'detalle'           => 'nullable|string|max:300',
        ]);

        try {
            $registro = $correo->enviar($this->contrato($contratoId), $datos, Auth::id());

            return response()->json(['ok' => true, 'correo_id' => $registro->id, 'para' => $registro->para, 'vence' => $registro->vence_at?->format('d/m/Y H:i')]);
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
