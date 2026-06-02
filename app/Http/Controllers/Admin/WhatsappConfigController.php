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

        // Buscar el aliado 'BryNex' para obtener sus plantillas
        $aliadoBrynex = Aliado::where('nombre', 'BryNex')->first();
        $brynexId = $aliadoBrynex ? $aliadoBrynex->id : 1;

        // Incluimos tanto las plantillas del aliado actual como las globales de Brynex
        $plantillasBrynex = \App\Models\WhatsappPlantilla::whereIn('aliado_id', [$alidoId, $brynexId])
            ->orderBy('nombre_display')
            ->get();

        return view('admin.whatsapp.configuracion.edit', compact('aliado', 'config', 'plantillasBrynex'));
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
            'usa_cuenta_brynex'   => 'required|boolean',
            'waba_id'             => 'required_if:usa_cuenta_brynex,0|nullable|string|max:100',
            'phone_number_id'     => 'required_if:usa_cuenta_brynex,0|nullable|string|max:100',
            'access_token'        => 'nullable|string|max:1000',
            'numero_telefono'     => 'nullable|string|max:25',
            'nombre_cuenta'       => 'nullable|string|max:150',
            'activo'              => 'boolean',
            'cobro_plantilla_id'  => 'nullable|exists:whatsapp_plantillas,id',
            'cobro_header_imagen' => 'nullable|image|max:5120',
        ]);

        // Solo actualizar el token si se proporcionó uno nuevo (no sobreescribir con vacío)
        if (empty($validated['access_token'])) {
            unset($validated['access_token']);
        }

        // Subida de la imagen para el encabezado de cobros
        if ($request->hasFile('cobro_header_imagen')) {
            if ($config->cobro_header_imagen) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($config->cobro_header_imagen);
            }
            $path = $request->file('cobro_header_imagen')->store('whatsapp/cobros', 'public');
            $validated['cobro_header_imagen'] = $path;
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

    public function editGlobal()
    {
        $this->autorizarSuperadminBrynex();

        $rawToken = \App\Models\ConfiguracionBrynex::obtener('whatsapp_global_access_token');
        $decryptedToken = null;
        if ($rawToken) {
            try {
                $decryptedToken = \Illuminate\Support\Facades\Crypt::decryptString($rawToken);
            } catch (\Exception $e) {
                $decryptedToken = $rawToken;
            }
        }

        $config = (object)[
            'waba_id'         => \App\Models\ConfiguracionBrynex::obtener('whatsapp_global_waba_id') ?: config('services.whatsapp.waba_id'),
            'phone_number_id' => \App\Models\ConfiguracionBrynex::obtener('whatsapp_global_phone_number_id') ?: config('services.whatsapp.phone_number_id'),
            'access_token'    => $decryptedToken ?: config('services.whatsapp.token'),
            'numero_telefono' => \App\Models\ConfiguracionBrynex::obtener('whatsapp_global_numero_telefono') ?: config('services.whatsapp.numero'),
            'nombre_cuenta'   => \App\Models\ConfiguracionBrynex::obtener('whatsapp_global_nombre_cuenta', 'Brynex Global'),
            'tiene_token'     => !empty($decryptedToken) || !empty(config('services.whatsapp.token')),
        ];

        return view('admin.whatsapp.configuracion.global', compact('config'));
    }

    public function updateGlobal(Request $request)
    {
        $this->autorizarSuperadminBrynex();

        $validated = $request->validate([
            'waba_id'           => 'required|string|max:100',
            'phone_number_id'   => 'required|string|max:100',
            'access_token'      => 'nullable|string|max:1000',
            'numero_telefono'   => 'nullable|string|max:25',
            'nombre_cuenta'     => 'nullable|string|max:150',
        ]);

        \App\Models\ConfiguracionBrynex::establecer('whatsapp_global_waba_id',         $validated['waba_id']);
        \App\Models\ConfiguracionBrynex::establecer('whatsapp_global_phone_number_id', $validated['phone_number_id']);
        
        if (!empty($validated['access_token'])) {
            $encryptedToken = \Illuminate\Support\Facades\Crypt::encryptString($validated['access_token']);
            \App\Models\ConfiguracionBrynex::establecer('whatsapp_global_access_token', $encryptedToken);
        }

        \App\Models\ConfiguracionBrynex::establecer('whatsapp_global_numero_telefono', $validated['numero_telefono']);
        \App\Models\ConfiguracionBrynex::establecer('whatsapp_global_nombre_cuenta',   $validated['nombre_cuenta']);

        return redirect()
            ->route('admin.whatsapp.config.index')
            ->with('ok', 'Configuración global de WhatsApp (Brynex) actualizada correctamente.');
    }

    private function autorizarSuperadminBrynex(): void
    {
        $user = Auth::user();
        abort_unless($user->hasRole('superadmin') && $user->es_brynex, 403);
    }
}
