<?php

namespace App\Services\Adres;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del worker de Node que maneja el navegador contra ADRES.
 *
 * Vive aparte porque el captcha de ADRES va atado a la sesión de ASP.NET: entre
 * pedirlo y responderlo pasan minutos y el contexto del navegador tiene que
 * seguir abierto. PHP no sostiene eso; el worker sí.
 */
class AdresWorkerClient
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.adres_worker.url', 'http://127.0.0.1:8801'), '/');
        $this->token   = (string) config('services.adres_worker.token', '');
        $this->timeout = (int) config('services.adres_worker.timeout', 90);
    }

    protected function peticion()
    {
        return Http::withHeaders(['X-Worker-Token' => $this->token])
            ->timeout($this->timeout)
            ->acceptJson();
    }

    public function disponible(): bool
    {
        try {
            return Http::timeout(5)->get("{$this->baseUrl}/salud")->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Abre la consulta y devuelve el captcha para que lo resuelva una persona.
     *
     * @return array{ok:bool, sesion_id?:string, captcha_png?:string, intentos_restantes?:int, error?:string}
     */
    public function abrirConsulta(string $cedula, string $tipoDocumento = 'Cedula de Ciudadania'): array
    {
        try {
            $r = $this->peticion()->post("{$this->baseUrl}/consultas", [
                'cedula'         => $cedula,
                'tipo_documento' => $tipoDocumento,
            ]);

            $data = $r->json() ?? [];

            if (!$r->successful() || !($data['ok'] ?? false)) {
                return ['ok' => false, 'error' => $data['error'] ?? 'El worker de ADRES no respondió correctamente.'];
            }

            return [
                'ok'                 => true,
                'sesion_id'          => $data['sesion_id'],
                'captcha_png'        => base64_decode($data['captcha_png_base64']),
                'intentos_restantes' => $data['intentos_restantes'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('ADRES: falló abrir consulta', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'No se pudo contactar el worker de ADRES.'];
        }
    }

    /**
     * Entrega el texto que resolvió un humano y cierra la consulta.
     *
     * Puede devolver ok=false con motivo 'captcha_incorrecto' y un captcha nuevo:
     * ADRES rota la imagen tras cada fallo, así que hay que reenviar la nueva.
     */
    public function responderCaptcha(string $sesionId, string $texto): array
    {
        try {
            $r = $this->peticion()->post("{$this->baseUrl}/consultas/{$sesionId}/captcha", [
                'texto' => $texto,
            ]);

            $data = $r->json() ?? [];

            if (!$r->successful()) {
                return ['ok' => false, 'motivo' => 'error_worker', 'error' => $data['error'] ?? 'Error del worker.'];
            }

            if (!($data['ok'] ?? false)) {
                return [
                    'ok'                 => false,
                    'motivo'             => $data['motivo'] ?? 'desconocido',
                    'intentos_restantes' => $data['intentos_restantes'] ?? 0,
                    'captcha_png'        => isset($data['captcha_png_base64'])
                        ? base64_decode($data['captcha_png_base64'])
                        : null,
                ];
            }

            return [
                'ok'              => true,
                'cedula'          => $data['cedula'] ?? null,
                'filas'           => $data['filas'] ?? [],
                'total_filas'     => $data['total_filas'] ?? 0,
                'total_declarado' => $data['total_declarado'] ?? null,
                'completo'        => (bool) ($data['completo'] ?? false),
                'pdf'             => isset($data['pdf_base64']) ? base64_decode($data['pdf_base64']) : null,
                'nombre_pdf'      => $data['nombre_pdf'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('ADRES: falló responder captcha', ['error' => $e->getMessage()]);
            return ['ok' => false, 'motivo' => 'error_worker', 'error' => 'No se pudo contactar el worker de ADRES.'];
        }
    }

    public function cerrarConsulta(string $sesionId): bool
    {
        try {
            return $this->peticion()->delete("{$this->baseUrl}/consultas/{$sesionId}")->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
