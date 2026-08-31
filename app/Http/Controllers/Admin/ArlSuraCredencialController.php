<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArlCredencial;
use App\Models\RazonSocial;
use App\Services\ArlSura\ArlSuraApiService;
use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Credenciales del portal de ARL Sura, una por aliado.
 *
 * Con ellas BryNex abre sesión sola cuando necesita afiliar, en vez de pedirle a
 * alguien que copie cookies del navegador. La contraseña se guarda cifrada y no
 * se devuelve nunca a la vista.
 */
class ArlSuraCredencialController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $aliadoId = $this->aliado();

        return view('admin.configuracion.arl_sura', [
            'credencial' => ArlCredencial::where('aliado_id', $aliadoId)->first(),
            'razones'    => RazonSocial::where('aliado_id', $aliadoId)
                ->orderByDesc('arl_poliza')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nit', 'arl_poliza']),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo_documento' => 'required|string|max:4',
            'usuario'        => 'required|string|max:30',
            // Al editar se puede dejar vacía para conservar la que ya está.
            'contrasena'     => 'nullable|string|max:100',
        ]);

        $aliadoId   = $this->aliado();
        $credencial = ArlCredencial::where('aliado_id', $aliadoId)->first();

        if (! $credencial && empty($datos['contrasena'])) {
            return back()->with('error', 'La contraseña es obligatoria la primera vez.');
        }

        $valores = [
            'tipo_documento' => $datos['tipo_documento'],
            'usuario'        => trim($datos['usuario']),
            'activo'         => true,
            'ultimo_error'   => null,
        ];

        if (! empty($datos['contrasena'])) {
            $valores['contrasena'] = $datos['contrasena'];
        }

        ArlCredencial::updateOrCreate(['aliado_id' => $aliadoId], $valores);

        return back()->with('success', 'Credenciales guardadas. Prueba la conexión para verificarlas.');
    }

    /**
     * Hace el login real contra el portal. Es la única forma de saber si sirven:
     * Sura no ofrece un endpoint para validar credenciales.
     */
    public function probar(Request $request)
    {
        $aliadoId = $this->aliado();

        $poliza = RazonSocial::where('aliado_id', $aliadoId)
            ->whereNotNull('arl_poliza')
            ->value('arl_poliza');

        if (! $poliza) {
            return back()->with('error',
                'Ninguna razón social tiene póliza ARL registrada. Regístrala primero para poder probar la conexión.');
        }

        try {
            ArlSuraSesionService::renovar($aliadoId, $poliza);

            if (! (new ArlSuraApiService($aliadoId, $poliza))->sesionViva()) {
                return back()->with('error', 'Se abrió sesión pero el portal no respondió con ella.');
            }
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Conexión correcta con la póliza {$poliza}. BryNex ya puede afiliar sin que nadie entre al portal.");
    }

    public function destroy(Request $request)
    {
        ArlCredencial::where('aliado_id', $this->aliado())->delete();

        return back()->with('success', 'Credenciales eliminadas. Las afiliaciones automáticas quedan deshabilitadas.');
    }

    private function aliado(): int
    {
        return (int) session('aliado_id_activo', Auth::user()->aliado_id);
    }
}
