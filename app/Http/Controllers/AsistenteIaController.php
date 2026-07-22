<?php

namespace App\Http\Controllers;

use App\Models\IaConfiguracionAliado;
use App\Services\Ia\AsistenteIaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AsistenteIaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Indica si el widget debe mostrarse para el aliado activo. */
    public function activo()
    {
        $alidoId = session('aliado_id_activo');
        $activo = $alidoId
            ? (bool) IaConfiguracionAliado::where('aliado_id', $alidoId)->value('activo_web')
            : false;

        return response()->json(['activo' => $activo]);
    }

    public function chat(Request $request, AsistenteIaService $servicio)
    {
        $request->validate(['mensaje' => 'required|string|max:2000']);

        $alidoId = session('aliado_id_activo');
        if (!$alidoId) {
            return response()->json(['error' => 'No hay un aliado activo en la sesión.'], 422);
        }

        try {
            $resultado = $servicio->responderWeb($alidoId, Auth::id(), $request->input('mensaje'));
            return response()->json($resultado);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('AsistenteIaController@chat', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Ocurrió un error consultando al asistente. Intenta de nuevo.'], 500);
        }
    }
}
