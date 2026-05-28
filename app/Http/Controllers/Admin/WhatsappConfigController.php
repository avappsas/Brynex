<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Aliado, WhatsappConfig};
use App\Services\WhatsappApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Gestión de la configuración de WhatsApp por aliado.
 * Solo accesible por el superadmin de Brynex.
 */
class WhatsappConfigController extends Controller
{
    public function __construct(protected WhatsappApiService $apiService) {}

    /**
     * Lista todos los aliados con su estado de configuración WhatsApp.
     */
    public function index()
    {
        $this->autorizarSuperadminBrynex();

        $aliados = Aliado::activos()
            ->with('whatsappConfig')
            ->orderBy('nombre')
            ->get();

        return view('admin.whatsapp.configuracion.index', compact('aliados'));
    }

    /**
     * Formulario de configuración para un aliado específico.
     */
    public function edit(int $alidoId)
    {
        $this->autorizarSuperadminBrynex();

        $aliado = Aliado::findOrFail($alidoId);
        $config = WhatsappConfig::paraAliado($alidoId);

        return view('admin.whatsapp.configuracion.edit', compact('aliado', 'config'));
    }

    /**
     * Guarda la configuración de WhatsApp para un aliado.
     */
    public function update(Request $request, int $alidoId)
    {
        $this->autorizarSuperadminBrynex();

        $aliado = Aliado::findOrFail($alidoId);
        $config = WhatsappConfig::paraAliado($alidoId);

        $validated = $request->validate([
            'usa_cuenta_brynex' => 'required|boolean',
            'waba_id'           => 'required_if:usa_cuenta_brynex,0|nullable|string|max:100',
            'phone_number_id'   => 'required_if:usa_cuenta_brynex,0|nullable|string|max:100',
            'access_token'      => 'nullable|string|max:1000',
            'numero_telefono'   => 'nullable|string|max:25',
            'nombre_cuenta'     => 'nullable|string|max:150',
            'activo'            => 'boolean',
        ]);

        // Solo actualizar el token si se proporcionó uno nuevo (no sobreescribir con vacío)
        if (empty($validated['access_token'])) {
            unset($validated['access_token']);
        }

        $config->fill($validated);
        $config->webhook_verificado = false; // resetear al cambiar credenciales
        $config->save();

        return redirect()
            ->route('admin.whatsapp.config.index')
            ->with('ok', "Configuración de WhatsApp actualizada para {$aliado->nombre}");
    }

    /**
     * Verifica la conectividad con Meta enviando una solicitud de prueba.
     */
    public function verificarWebhook(Request $request)
    {
        $this->autorizarSuperadminBrynex();

        $alidoId = $request->validate(['aliado_id' => 'required|integer'])['aliado_id'];
        $config  = WhatsappConfig::paraAliado($alidoId);

        if (!$config->credencialesCompletas()) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Las credenciales están incompletas.',
            ]);
        }

        try {
            // Intentar listar las plantillas como prueba de conectividad
            $creds    = $config->credencialesEfectivas();
            $response = Http::withToken($creds['access_token'])
                ->get("https://graph.facebook.com/v19.0/{$creds['waba_id']}");

            $data = $response->json();
            $config->update(['webhook_verificado' => true]);

            return response()->json([
                'ok'      => true,
                'mensaje' => 'Conexión exitosa con Meta. Cuenta: ' . ($data['name'] ?? 'OK'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al conectar con Meta: ' . $e->getMessage(),
            ]);
        }
    }

    private function autorizarSuperadminBrynex(): void
    {
        $user = Auth::user();
        abort_unless($user->hasRole('superadmin') && $user->es_brynex, 403);
    }
}
