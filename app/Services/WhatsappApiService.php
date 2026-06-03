<?php

namespace App\Services;

use App\Models\WhatsappConfig;
use App\Models\WhatsappPlantilla;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsappApiService
{
    protected Client $http;
    protected string $baseUrl = 'https://graph.facebook.com/v19.0';

    public function __construct()
    {
        $this->http = new Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
        ]);
    }

    // ── Envío de mensajes ───────────────────────────────────────────

    /**
     * Envía un mensaje de plantilla aprobada a un número.
     *
     * @param string             $to             Número E.164: +573001234567
     * @param WhatsappPlantilla  $plantilla      Plantilla aprobada por Meta
     * @param array              $params         Parámetros {{1}}, {{2}}... en orden (BODY)
     * @param WhatsappConfig     $config         Configuración del aliado
     * @param string|null        $headerImageUrl URL pública de imagen para header IMAGE
     *                                           (cuando la plantilla no guarda header_valor)
     * @return array ['ok' => bool, 'wa_message_id' => string|null, 'error' => string|null]
     */
    public function enviarTemplate(
        string $to,
        WhatsappPlantilla $plantilla,
        array $params,
        WhatsappConfig $config,
        ?string $headerImageUrl = null
    ): array {
        $creds      = $config->credencialesEfectivas();

        // Si la URL de la imagen del header apunta a un host local (127.0.0.1 o localhost),
        // la reemplazamos temporalmente con la de producción (https://brynex.co) para permitir que Meta la descargue.
        if ($headerImageUrl && (str_contains($headerImageUrl, '127.0.0.1') || str_contains($headerImageUrl, 'localhost'))) {
            $headerImageUrl = preg_replace('/https?:\/\/[^\/]+/', 'https://brynex.co', $headerImageUrl);
        }

        $componentes = $plantilla->construirComponentes($params, $headerImageUrl);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizarNumero($to),
            'type'              => 'template',
            'template'          => [
                'name'       => $plantilla->nombre,
                'language'   => ['code' => $plantilla->idioma],
                'components' => $componentes,
            ],
        ];

        Log::debug('WhatsApp: enviando template', [
            'to'          => $to,
            'plantilla'   => $plantilla->nombre,
            'idioma'      => $plantilla->idioma,
            'componentes' => $componentes,
        ]);

        return $this->llamarApi($creds, $payload);
    }

    /**
     * Envía un mensaje de texto libre (solo dentro de la ventana de 24h).
     */
    public function enviarTexto(string $to, string $texto, WhatsappConfig $config): array
    {
        $creds = $config->credencialesEfectivas();

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizarNumero($to),
            'type'              => 'text',
            'text'              => ['body' => $texto, 'preview_url' => false],
        ];

        return $this->llamarApi($creds, $payload);
    }

    /**
     * Envía un archivo de media (imagen, audio, documento) a un número.
     *
     * @param string $tipo     'image' | 'audio' | 'document' | 'video'
     * @param string $pathLocal Path relativo en storage (ej: whatsapp/image.jpg)
     * @param string $mimeType  Ej: image/jpeg, application/pdf
     * @param string $nombreArchivo Nombre visible para documentos
     */
    public function enviarMedia(
        string $to,
        string $tipo,
        string $pathLocal,
        string $mimeType,
        string $nombreArchivo,
        WhatsappConfig $config
    ): array {
        $creds = $config->credencialesEfectivas();

        // Paso 1: subir el archivo a Meta para obtener el media_id
        $mediaId = $this->subirMedia($pathLocal, $mimeType, $creds);
        if (!$mediaId) {
            return ['ok' => false, 'error' => 'No se pudo subir el archivo a Meta'];
        }

        // Paso 2: enviar el mensaje con el media_id
        $mediaPayload = ['id' => $mediaId];
        if ($tipo === 'document') {
            $mediaPayload['filename'] = $nombreArchivo;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizarNumero($to),
            'type'              => $tipo,
            $tipo               => $mediaPayload,
        ];

        return $this->llamarApi($creds, $payload);
    }

    // ── Descarga de media entrante ───────────────────────────────────

    /**
     * Descarga un media recibido del cliente y lo guarda en storage local.
     * Retorna el path relativo donde quedó guardado, o null si falló.
     */
    public function descargarMedia(string $waMediaId, WhatsappConfig $config): ?string
    {
        $creds = $config->credencialesEfectivas();

        try {
            // Paso 1: obtener la URL real del media desde Meta
            $response = $this->http->get("{$this->baseUrl}/{$waMediaId}", [
                'headers' => ['Authorization' => "Bearer {$creds['access_token']}"],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $mediaUrl  = $data['url']       ?? null;
            $mimeType  = $data['mime_type'] ?? 'application/octet-stream';

            if (!$mediaUrl) return null;

            // Paso 2: descargar el archivo real
            $contenido = $this->http->get($mediaUrl, [
                'headers' => ['Authorization' => "Bearer {$creds['access_token']}"],
            ])->getBody()->getContents();

            // Paso 3: guardar en storage/whatsapp/YYYY/MM/
            $extension = $this->extensionDesde($mimeType);
            $directorio = 'whatsapp/' . now()->format('Y/m');
            $nombreArchivo = $waMediaId . '.' . $extension;
            $path = $directorio . '/' . $nombreArchivo;

            Storage::disk('local')->put($path, $contenido);

            return $path;
        } catch (\Exception $e) {
            Log::error('WhatsApp: Error al descargar media', [
                'wa_media_id' => $waMediaId,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── Gestión de plantillas ────────────────────────────────────────

    /**
     * Crea una plantilla en Meta y retorna el meta_template_id.
     */
    public function crearPlantillaEnMeta(WhatsappPlantilla $plantilla, WhatsappConfig $config): array
    {
        $creds = $config->credencialesEfectivas();

        // Construir componentes de la plantilla para Meta
        $componentes = [];

        // Header
        if ($plantilla->header_tipo) {
            $componentes[] = [
                'type'   => 'HEADER',
                'format' => $plantilla->header_tipo,
                'text'   => $plantilla->header_tipo === 'TEXT' ? $plantilla->header_valor : null,
            ];
        }

        // Body
        $componentes[] = [
            'type' => 'BODY',
            'text' => $plantilla->cuerpo,
        ];

        // Footer
        if ($plantilla->footer) {
            $componentes[] = [
                'type' => 'FOOTER',
                'text' => $plantilla->footer,
            ];
        }

        // Botones
        if ($plantilla->tieneBotones()) {
            $botonesMeta = [];
            foreach ($plantilla->botones as $boton) {
                $bMeta = ['type' => $boton['tipo'], 'text' => $boton['texto']];
                if ($boton['tipo'] === 'URL')          $bMeta['url']       = $boton['url'] ?? '';
                if ($boton['tipo'] === 'PHONE_NUMBER') $bMeta['phone_number'] = $boton['telefono'] ?? '';
                $botonesMeta[] = $bMeta;
            }
            $componentes[] = ['type' => 'BUTTONS', 'buttons' => $botonesMeta];
        }

        try {
            $response = $this->http->post(
                "{$this->baseUrl}/{$creds['waba_id']}/message_templates",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$creds['access_token']}",
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'name'       => $plantilla->nombre,
                        'language'   => $plantilla->idioma,
                        'category'   => $plantilla->categoria,
                        'components' => $componentes,
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'ok'                => true,
                'meta_template_id'  => $data['id'] ?? null,
                'estado'            => $data['status'] ?? 'pending',
            ];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error('WhatsApp: Error al crear plantilla en Meta', [
                'plantilla' => $plantilla->nombre,
                'error'     => $body,
            ]);
            return ['ok' => false, 'error' => $this->extraerError($body)];
        }
    }

    /**
     * Obtiene las plantillas directamente desde Meta Cloud API sin guardarlas.
     *
     * @param WhatsappConfig $config
     * @return array
     */
    public function obtenerPlantillasDeMeta(WhatsappConfig $config): array
    {
        $creds = $config->credencialesEfectivas();

        try {
            $response = $this->http->get(
                "{$this->baseUrl}/{$creds['waba_id']}/message_templates",
                [
                    'headers' => ['Authorization' => "Bearer {$creds['access_token']}"],
                    'query'   => [
                        'limit'  => 250,
                        'fields' => 'id,name,status,language,category,components',
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('WhatsApp: Error al obtener plantillas desde Meta', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Lista las plantillas desde Meta y las sincroniza en BD.
     */
    public function sincronizarEstadoPlantillas(int $alidoId, WhatsappConfig $config): int
    {
        $creds = $config->credencialesEfectivas();
        $actualizadas = 0;

        try {
            $response = $this->http->get(
                "{$this->baseUrl}/{$creds['waba_id']}/message_templates",
                [
                    'headers' => ['Authorization' => "Bearer {$creds['access_token']}"],
                    'query'   => ['limit' => 200, 'fields' => 'id,name,status,language'],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $templatesMeta = $data['data'] ?? [];

            foreach ($templatesMeta as $tmpl) {
                $actualizado = WhatsappPlantilla::where('aliado_id', $alidoId)
                    ->where('nombre', $tmpl['name'])
                    ->update([
                        'estado'           => strtolower($tmpl['status']),
                        'meta_template_id' => $tmpl['id'],
                    ]);

                if ($actualizado) $actualizadas++;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp: Error al sincronizar plantillas', ['error' => $e->getMessage()]);
        }

        return $actualizadas;
    }

    // ── Internos ─────────────────────────────────────────────────────

    private function llamarApi(array $creds, array $payload): array
    {
        try {
            $response = $this->http->post(
                "{$this->baseUrl}/{$creds['phone_number_id']}/messages",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$creds['access_token']}",
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $payload,
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $waMessageId = $data['messages'][0]['id'] ?? null;

            return ['ok' => true, 'wa_message_id' => $waMessageId];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('WhatsApp: Error al enviar mensaje', [
                'payload' => $payload,
                'error'   => $body,
            ]);
            return ['ok' => false, 'wa_message_id' => null, 'error' => $this->extraerError($body)];
        }
    }

    private function subirMedia(string $pathLocal, string $mimeType, array $creds): ?string
    {
        try {
            $contenido = Storage::disk('local')->get($pathLocal);
            $nombreArchivo = basename($pathLocal);

            $response = $this->http->post(
                "{$this->baseUrl}/{$creds['phone_number_id']}/media",
                [
                    'headers'   => ['Authorization' => "Bearer {$creds['access_token']}"],
                    'multipart' => [
                        ['name' => 'messaging_product', 'contents' => 'whatsapp'],
                        ['name' => 'type',              'contents' => $mimeType],
                        ['name' => 'file',              'contents' => $contenido, 'filename' => $nombreArchivo],
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('WhatsApp: Error al subir media', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function normalizarNumero(string $numero): string
    {
        // Quitar cualquier carácter que no sea número
        $numero = preg_replace('/[^0-9]/', '', $numero);

        // Si empieza por 0, lo quitamos
        $numero = ltrim($numero, '0');

        // Si tiene 10 dígitos (celular Colombia estándar sin código de país), le agregamos el 57
        if (strlen($numero) === 10) {
            $numero = '57' . $numero;
        }

        return $numero;
    }

    private function extensionDesde(string $mimeType): string
    {
        return match(true) {
            str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') => 'jpg',
            str_contains($mimeType, 'png')                                    => 'png',
            str_contains($mimeType, 'pdf')                                    => 'pdf',
            str_contains($mimeType, 'ogg')                                    => 'ogg',
            str_contains($mimeType, 'mp3') || str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'mp4')                                    => 'mp4',
            default                                                           => 'bin',
        };
    }

    private function extraerError(string $body): string
    {
        $data = json_decode($body, true);
        return $data['error']['message'] ?? $body;
    }
}
