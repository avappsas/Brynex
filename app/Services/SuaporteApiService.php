<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SuaporteApiService
{
    protected string $baseUrl;
    protected string $usuario;
    protected string $claveSecreta;
    protected string $authUrl;

    public function __construct(?string $usuario = null, ?string $claveSecreta = null)
    {
        $this->baseUrl = config('services.suaporte.base_url', 'https://www.suaporte.com.co/api');
        $this->authUrl = config('services.suaporte.auth_url', 'https://www.suaporte.com.co/auth');
        $this->usuario = $usuario ?? config('services.suaporte.usuario', '');
        $this->claveSecreta = $claveSecreta ?? config('services.suaporte.clave_secreta', '');
    }

    /**
     * Cifra un dato sensible (como contraseña) si la API de autenticación lo requiere.
     */
    public function cifrarDato(string $dato): ?string
    {
        try {
            $response = Http::post("{$this->authUrl}/crypto/cifrar-datos", [
                'dato' => $dato,
            ]);

            if ($response->successful()) {
                return $response->json('dato_cifrado') ?? $response->body();
            }
        } catch (\Exception $e) {
            Log::error('Error al cifrar dato sensible para Suaporte', ['message' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Obtiene el token de autenticación JWT de Suaporte.
     * Utiliza caché parametrizada por usuario para evitar colisiones de tokens.
     */
    public function obtenerToken(): ?string
    {
        if (empty($this->usuario) || empty($this->claveSecreta)) {
            Log::error('Intento de autenticación en Suaporte sin credenciales configuradas.');
            return null;
        }

        $cacheKey = 'suaporte_api_token_' . md5($this->usuario);

        return Cache::remember($cacheKey, 300, function () {
            try {
                $response = Http::post("{$this->authUrl}/login", [
                    'usuario' => $this->usuario,
                    'clave_secreta' => $this->claveSecreta,
                ]);

                if ($response->successful()) {
                    return $response->json('token') ?? $response->json('access_token');
                }

                Log::error('Suaporte API login fallido', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            } catch (\Exception $e) {
                Log::error('Excepción al conectar con la API de Suaporte', [
                    'message' => $e->getMessage()
                ]);
            }

            return null;
        });
    }

    /**
     * Envía la información consolidada de los cotizantes (o archivo plano en Base64) a la API de Suaporte.
     */
    public function enviarPlanilla(string $contenidoPlanoTxt, array $datosPlanilla): array
    {
        $token = $this->obtenerToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'No se pudo obtener el token de autenticación con Suaporte.'
            ];
        }

        try {
            // El formato dependerá si SuAporte recibe el TXT o JSON.
            // Generalmente, reciben multipart o payload JSON con el plano codificado.
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/generadorPlanillas/cargar", [
                    'nit_aportante' => $datosPlanilla['nit'],
                    'periodo_pago'  => "{$datosPlanilla['anio']}-" . str_pad($datosPlanilla['mes'], 2, '0', STR_PAD_LEFT),
                    'n_plano'       => $datosPlanilla['n_plano'],
                    'archivo_pila'  => base64_encode($contenidoPlanoTxt),
                    'nombre_archivo'=> "PILA_{$datosPlanilla['nit']}_{$datosPlanilla['mes']}_{$datosPlanilla['anio']}.txt"
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'planilla_id' => $response->json('planilla_id'),
                    'numero_planilla' => $response->json('numero_planilla'),
                    'valor_total' => $response->json('valor_total'),
                    'response' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Error en la validación/carga de la planilla: ' . ($response->json('message') ?? $response->body()),
                'response' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Excepción al enviar planilla a Suaporte', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Excepción de red al enviar planilla: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene la URL de PSE (Pago) asociada a la planilla liquidada.
     */
    public function obtenerUrlPago(string $planillaId): array
    {
        $token = $this->obtenerToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'No se pudo obtener el token de autenticación con Suaporte.'
            ];
        }

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/generadorPlanillas/planilla/{$planillaId}/url-pago");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'url_pago' => $response->json('url_pago') ?? $response->json('url_pse'),
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al obtener URL de pago: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Excepción al obtener URL de pago de Suaporte', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Excepción al consultar URL de pago: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta el estado del pago de una planilla en Enlace Operativo.
     */
    public function consultarEstadoPago(string $planillaId): array
    {
        $token = $this->obtenerToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'No se pudo obtener el token.'
            ];
        }

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/gestion-pagos/planilla/{$planillaId}/estado");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'estado' => $response->json('estado'), // p. ej. 'pagada', 'pendiente'
                    'fecha_pago' => $response->json('fecha_pago'),
                    'response' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al consultar estado de pago: ' . $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('Excepción al consultar estado de pago en Suaporte', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Excepción al consultar pago: ' . $e->getMessage()
            ];
        }
    }
}
