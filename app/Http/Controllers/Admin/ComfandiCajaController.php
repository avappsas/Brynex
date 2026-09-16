<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\Caja\ComfandiCajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Afiliación a la caja Comfandi por su Sucursal Virtual Empresas, desde el
 * radicado de caja. La extensión BryNex Portales llena el formulario de
 * Afiliación individual; aquí se arman los datos, se entrega la credencial y
 * se registra el número de radicado que devuelve el portal.
 */
class ComfandiCajaController extends Controller
{
    public function __construct(private ComfandiCajaService $servicio)
    {
        $this->middleware('auth');
    }

    public function precheck(int $contratoId)
    {
        $prep = $this->servicio->preparar($this->contrato($contratoId));

        return response()->json(['ok' => ! $prep['problemas'], 'listas' => [
            'estados_civil' => ComfandiCajaService::ESTADOS_CIVIL,
            'contratos' => ComfandiCajaService::CONTRATOS,
            'salarios' => ComfandiCajaService::SALARIOS,
            'horas' => ComfandiCajaService::HORAS,
            'niveles' => ComfandiCajaService::NIVELES,
            'generos' => ['1' => 'Femenino', '2' => 'Masculino'],
        ]] + $prep);
    }

    public function credencial(int $contratoId)
    {
        $cred = $this->servicio->credencial($this->contrato($contratoId));
        if (isset($cred['error'])) {
            return response()->json(['ok' => false, 'error' => $cred['error']], 422);
        }
        if (! Auth::user()->can('claves_acceso.ver_contrasena')) {
            unset($cred['contrasena']);
        }

        return response()->json(['ok' => true] + $cred)->header('Cache-Control', 'no-store');
    }

    public function aplicar(Request $request, int $contratoId)
    {
        $datos = $request->validate([
            'numero' => 'nullable|string|max:40',
            'texto' => 'nullable|string|max:20000',
            'error' => 'nullable|string|max:1000',
        ]);

        try {
            return response()->json($this->servicio->aplicar($this->contrato($contratoId), $datos, Auth::id()));
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
