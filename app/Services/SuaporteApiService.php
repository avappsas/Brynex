<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Cliente de las APIs PILA de la plataforma Enlace Operativo.
 *
 * Varios operadores de información corren la MISMA plataforma con distinto
 * dominio: ARUS Enlace (suaporte.com.co) y Simple (simple.co) publican specs
 * OpenAPI idénticos —mismos endpoints, mismos schemas, mismos headers—, así
 * que este cliente sirve para ambos cambiando solo el host.
 *
 * Documentación: https://www.suaporte.com.co/portal-apis/
 *                https://www.simple.co/portal-apis/
 *
 * El flujo para liquidar una planilla son cuatro llamadas encadenadas, y la
 * sesión NO viaja como Bearer token sino como un conjunto de headers que cada
 * paso va acumulando:
 *
 *   1. POST /auth/login                          → 5 headers de sesión
 *   2. GET  /api/gestion/aportante/{tipo}/{num}  → id interno del aportante
 *   3. GET  /api/gestion/authorization/...       → 4 headers de autorización
 *   4. POST /api/generadorPlanillas/.../validacion (multipart) → planilla
 *
 * Los 9 headers de los pasos 1 y 3 deben acompañar todas las llamadas
 * posteriores. Ver liquidarPlanilla() para el flujo completo.
 */
class SuaporteApiService
{
    /**
     * Operadores que corren la plataforma Enlace Operativo, por su `codigo`
     * en `operadores_planilla`, con el host donde la exponen.
     * Para sumar un operador nuevo basta agregarlo aquí.
     */
    public const HOSTS = [
        'ARUS'   => 'https://www.suaporte.com.co',
        'SIMPLE' => 'https://www.simple.co',
    ];

    /** Headers que devuelve el login y que identifican la sesión. */
    private const HEADERS_SESION = [
        'token',
        'refresh-token',
        'refresh-token-ttl',
        'refresh-token-date',
        'faces',
    ];

    /** Headers que devuelve la autorización sobre un aportante. */
    private const HEADERS_AUTORIZACION = [
        'profiles',
        'contributor',
        'appId',
        'refrescar',
    ];

    protected string $authUrl;
    protected string $apiUrl;
    protected string $usuario;
    protected string $contrasena;
    protected string $claveSecreta;
    protected int $timeout;

    /** Headers acumulados de sesión + autorización. */
    protected array $headers = [];

    /**
     * @param array $credenciales usuario, contrasena, clave_secreta y
     *                            opcionalmente `operador` (código: ARUS,
     *                            SIMPLE…) o `host` para apuntar a otro
     *                            dominio de la plataforma. Lo que se omita
     *                            se toma de config/services.php.
     */
    public function __construct(array $credenciales = [])
    {
        $host = $credenciales['host'] ?? self::hostDeOperador($credenciales['operador'] ?? null);

        if ($host) {
            $host          = rtrim($host, '/');
            $this->authUrl = "{$host}/auth";
            $this->apiUrl  = "{$host}/api";
        } else {
            $this->authUrl = rtrim(config('services.suaporte.auth_url'), '/');
            $this->apiUrl  = rtrim(config('services.suaporte.api_url'), '/');
        }

        $this->timeout      = (int) config('services.suaporte.timeout', 120);
        $this->usuario      = $credenciales['usuario']       ?? (string) config('services.suaporte.usuario');
        $this->contrasena   = $credenciales['contrasena']    ?? (string) config('services.suaporte.contrasena');
        $this->claveSecreta = $credenciales['clave_secreta'] ?? (string) config('services.suaporte.clave_secreta');
    }

    /** Host de la plataforma para un código de operador, o null si no aplica. */
    public static function hostDeOperador(?string $codigo): ?string
    {
        return $codigo ? (self::HOSTS[strtoupper($codigo)] ?? null) : null;
    }

    /** ¿Este operador corre sobre la plataforma Enlace Operativo? */
    public static function soportaOperador(?string $codigo): bool
    {
        return $codigo !== null && isset(self::HOSTS[strtoupper($codigo)]);
    }

    // ── 0. Cifrado (opcional) ────────────────────────────────────────────

    /**
     * Cifra un dato sensible con la llave RSA pública de Enlace.
     * El login acepta la contraseña en plano o cifrada con este servicio.
     */
    public function cifrarDato(string $dato): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->authUrl}/crypto/cifrar-datos", ['datoACifrar' => $dato]);

            if ($response->successful()) {
                return $response->json('datoCifrado');
            }

            Log::warning('Suaporte: fallo al cifrar dato', ['status' => $response->status()]);
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al cifrar dato', ['message' => $e->getMessage()]);
        }

        return null;
    }

    // ── 1. Autenticación ─────────────────────────────────────────────────

    /**
     * Autentica y guarda los 5 headers de sesión.
     * La sesión se cachea por usuario para no relogear en cada operación.
     *
     * @return array{success: bool, message?: string}
     */
    public function autenticar(bool $forzar = false): array
    {
        if (empty($this->usuario) || empty($this->contrasena) || empty($this->claveSecreta)) {
            return [
                'success' => false,
                'message' => 'Faltan credenciales de Enlace Operativo (usuario, contraseña o clave secreta).',
            ];
        }

        // El host entra en la llave: un mismo usuario puede tener sesión
        // simultánea en ARUS y en Simple, y los tokens no son intercambiables.
        $cacheKey = 'suaporte_sesion_' . md5($this->authUrl . '|' . $this->usuario . '|' . $this->claveSecreta);

        if ($forzar) {
            Cache::forget($cacheKey);
        }

        $sesion = Cache::get($cacheKey);

        if (!$sesion) {
            // La documentación dice que el login acepta la contraseña en plano,
            // pero en la práctica la rechaza ("El dato no tiene formato de
            // cifrado válido"): hay que cifrarla siempre con la llave RSA.
            // El cifrado cambia en cada llamada, así que no se puede guardar.
            $contrasena = $this->cifrarDato($this->contrasena);

            if (!$contrasena) {
                return [
                    'success' => false,
                    'message' => 'No fue posible cifrar la contraseña con el servicio de Enlace Operativo.',
                ];
            }

            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders(['clave-secreta' => $this->claveSecreta])
                    ->asJson()
                    ->post("{$this->authUrl}/login", [
                        'usuario'    => $this->usuario,
                        'contrasena' => $contrasena,
                    ]);

                if (!$response->successful()) {
                    Log::error('Suaporte: login fallido', [
                        'status'  => $response->status(),
                        'usuario' => $this->usuario,
                        'body'    => $response->body(),
                    ]);

                    return [
                        'success' => false,
                        'message' => $this->mensajeError($response, 'No fue posible autenticar en Enlace Operativo.'),
                    ];
                }

                $sesion = $this->extraerHeaders($response, self::HEADERS_SESION);

                if (empty($sesion['token'])) {
                    return [
                        'success' => false,
                        'message' => 'Enlace Operativo respondió sin token de sesión.',
                    ];
                }

                // El refresh-token-ttl viene en segundos; se descuenta un margen
                // para no usar una sesión que expire a mitad del flujo.
                $ttl = max(60, ((int) ($sesion['refresh-token-ttl'] ?? 600)) - 60);
                Cache::put($cacheKey, $sesion, $ttl);
            } catch (\Exception $e) {
                Log::error('Suaporte: excepción en login', ['message' => $e->getMessage()]);

                return [
                    'success' => false,
                    'message' => 'Error de red al conectar con Enlace Operativo: ' . $e->getMessage(),
                ];
            }
        }

        $this->headers = $sesion;

        return ['success' => true];
    }

    // ── 2. Consulta del aportante ────────────────────────────────────────

    /**
     * Obtiene el id interno que Enlace le asigna al aportante (razón social).
     * Ese id es el que exige el servicio de autorización.
     *
     * @return array{success: bool, id?: int, aportante?: array, message?: string}
     */
    public function consultarAportante(string $tipoDocumento, string $numeroDocumento): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get("{$this->apiUrl}/gestion/aportante/{$tipoDocumento}/{$numeroDocumento}");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->mensajeError($response, "El aportante {$tipoDocumento} {$numeroDocumento} no fue encontrado en Enlace."),
                ];
            }

            $data = $response->json();
            $id   = $data['id'] ?? ($data['data']['id'] ?? null);

            if (!$id) {
                return [
                    'success' => false,
                    'message' => 'Enlace no devolvió el id del aportante.',
                    'response' => $data,
                ];
            }

            return ['success' => true, 'id' => (int) $id, 'aportante' => $data];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al consultar aportante', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error al consultar el aportante: ' . $e->getMessage()];
        }
    }

    /**
     * Catálogo interno de Enlace para el tipo de vía de una dirección
     * (usado por crearAportanteIndependiente). Capturado del formulario web
     * de registro — no está documentado en ningún lado.
     */
    private const TIPO_VIA = [
        'AUTOPISTA' => 1, 'AV' => 2, 'AVENIDA' => 2,
        'AC' => 3, 'AVCL' => 3, 'AVCALLE' => 3,
        'AK' => 4, 'AVCR' => 4, 'AVCARRERA' => 4,
        'BLV' => 5, 'BULEVAR' => 5,
        'CL' => 6, 'CALLE' => 6,
        'CR' => 7, 'CRA' => 7, 'KR' => 7, 'CARRERA' => 7,
        'CT' => 8, 'CARRETERA' => 8,
        'CIRCULAR' => 9, 'CIRCUNVALAR' => 10,
        'DG' => 12, 'DIAGONAL' => 12,
        'KM' => 13, 'KILOMETRO' => 13,
        'OF' => 14, 'OFICINA' => 14,
        'PJ' => 15, 'PASAJE' => 15, 'PASEO' => 16,
        'PT' => 17, 'PEATONAL' => 17,
        'TV' => 18, 'TRANSVERSAL' => 18,
        'TRONCAL' => 19, 'VARIANTE' => 20, 'VIA' => 21,
    ];

    private const CUADRANTE_VIA = ['SUR' => 1, 'NORTE' => 2, 'ESTE' => 3, 'OESTE' => 4];

    /**
     * Descompone una dirección libre colombiana ("CR 48 # 48 - 54") en los
     * campos estructurados que pide Enlace para registrar un aportante.
     * Devuelve null si el patrón no es reconocible con confianza — mejor
     * dejar la dirección vacía que mandar datos mal separados a un registro
     * real (mismo criterio que el bug de municipio del plano PILA).
     */
    public static function parsearDireccion(string $direccion): ?array
    {
        $dir = mb_strtoupper(trim($direccion), 'UTF-8');
        $dir = preg_replace('/[.,]/', '', $dir);

        $patron = '/^([A-ZÀ-Ý]+)\s+(\d+)\s*([A-Z])?\s*(SUR|NORTE|ESTE|OESTE)?\s*#\s*(\d+)\s*([A-Z])?\s*-\s*(\d+)/u';
        if (!preg_match($patron, $dir, $m)) {
            return null;
        }

        $tipoVialId = self::TIPO_VIA[$m[1]] ?? null;
        if (!$tipoVialId) {
            return null;
        }

        return [
            'id'                     => null,
            'tipoVialId'             => (string) $tipoVialId,
            'numeroVial'             => $m[2],
            'letraVial'              => $m[3] ?? '',
            'tipoCuadranteId'        => !empty($m[4]) ? self::CUADRANTE_VIA[$m[4]] : null,
            'numeroPlaca'            => $m[5],
            'cuadranteViaGeneradora' => $m[6] ?? '',
            'numeroViaGeneradora'    => $m[7],
            'informacionAdicional'   => '',
            'direccionCompleta'      => $dir,
        ];
    }

    /**
     * Busca el id interno de una actividad económica por nombre o código
     * DANE (autocompletado del formulario de registro). Sin este id el
     * registro de aportante lo rechaza con un mensaje genérico.
     */
    public function buscarActividadEconomica(string $termino): ?int
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get("{$this->apiUrl}/gestion/economicactivities/find", ['name' => $termino]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json()[0]['id'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Suaporte: no se pudo resolver actividad económica', [
                'termino' => $termino, 'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crea un aportante independiente (persona natural que cotiza por su
     * cuenta). Solo requiere los 5 headers del login: no se puede autorizar
     * sobre un aportante que todavía no existe.
     *
     * Payload capturado directamente del formulario web de Enlace
     * (suaporte.com.co/gestion/#/segment/independent) — no está
     * documentado en ningún lado, y varios nombres de campo no coinciden
     * con lo que uno esperaría (numeroPlaca/numeroViaGeneradora no son lo
     * que sus nombres sugieren, ver parsearDireccion).
     *
     * @param array $datos tipo_documento, documento, nombre, codigo_arl,
     *                     actividad_economica (código/nombre a buscar),
     *                     contacto: [correo, correo_adicional, telefono,
     *                                celular, codigo_departamento,
     *                                codigo_municipio, direccion]
     */
    public function crearAportanteIndependiente(array $datos): array
    {
        $actividadId = $this->buscarActividadEconomica($datos['actividad_economica'] ?? '7490')
            ?? 405; // fallback: "Otras actividades profesionales, científicas y técnicas n.c.p."

        $payload = [
            'tipoIdentificacion'                   => $datos['tipo_documento'] ?? 'CC',
            'numeroIdentificacion'                 => (string) $datos['documento'],
            'razonSocial'                          => $datos['nombre'],
            'tipoAportanteId'                      => '2',   // independiente
            'clasificacionAportanteId'             => 2,
            'digitoVerificacion'                   => 0,
            'formaPresentacionId'                  => 1,   // único
            'tipoPersonaId'                        => 1,   // natural
            'naturalezaJuridicaId'                 => 2,   // privada
            'tipoAccionId'                         => 5,   // normal
            'codigoAdministradoraRiesgosLaborales' => $datos['codigo_arl'] ?? 'NIN-AR',
            'actividadEconomicaId'                 => $actividadId,
            'estado'                               => 'ACTIVE',
            'pagaEsapMin'                          => false,
            'validacionExtra'                      => [
                'duplicacionPlanilla'                   => 'N',
                'tipoComprobantePagoAsistidoId'         => 1,
                'valoresComprobante'                    => 'S',
                'novedadIngresoRetiro'                  => 'S',
                'exoneradoPagoParafiscal'               => 'S',
                'reemplazaAdministradoraSaludCotizante' => 'S',
                'reemplazaValorUpcCotizante'            => 'S',
            ],
        ];

        $contacto = $datos['contacto'] ?? [];
        if (!empty($contacto)) {
            $direccion = !empty($contacto['direccion']) ? self::parsearDireccion($contacto['direccion']) : null;

            $payload['informacionContacto'] = [
                'correoElectronico'          => $contacto['correo'] ?? '',
                'correoElectronicoAdicional' => $contacto['correo_adicional'] ?? '',
                'numeroTelefono'             => $contacto['telefono'] ?? ($contacto['celular'] ?? ''),
                'fax'                        => '',
                'numeroCelular'              => $contacto['celular'] ?? '',
                'codigoDepartamento'         => $contacto['codigo_departamento'] ?? '',
                'codigoMunicipio'            => $contacto['codigo_municipio'] ?? '',
                'datosDireccion'             => $direccion ?? [
                    'id' => null, 'tipoVialId' => null, 'numeroVial' => '',
                    'letraVial' => '', 'tipoCuadranteId' => null, 'numeroPlaca' => '',
                    'cuadranteViaGeneradora' => '', 'numeroViaGeneradora' => '',
                    'informacionAdicional' => '', 'direccionCompleta' => $contacto['direccion'] ?? '',
                ],
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->asJson()
                ->post("{$this->apiUrl}/gestion/aportante", $payload);

            if (!$response->successful()) {
                Log::warning('Suaporte: no se pudo crear el aportante', [
                    'documento' => $datos['documento'],
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);

                return [
                    'success'  => false,
                    'message'  => $this->mensajeError($response, 'No fue posible crear el aportante.'),
                    'response' => $response->json(),
                    'payload'  => $payload,
                ];
            }

            $data = $response->json();

            return [
                'success'   => true,
                'id'        => $data['id'] ?? ($data['data']['id'] ?? null),
                'aportante' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al crear aportante', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error al crear el aportante: ' . $e->getMessage()];
        }
    }

    // ── 3. Autorización sobre el aportante ───────────────────────────────

    /**
     * Autoriza al usuario autenticado sobre un aportante y acumula los
     * 4 headers resultantes. Sin este paso los servicios de planillas
     * responden 401/403.
     *
     * @return array{success: bool, message?: string}
     */
    public function autorizar(int $aportanteId, string $tipoDocumento, string $numeroDocumento, ?int $aplicacion = null): array
    {
        $query = [
            'id'                   => $aportanteId,
            'tipoIdentificacion'   => $tipoDocumento,
            'numeroIdentificacion' => $numeroDocumento,
        ];

        if ($aplicacion !== null) {
            $query['aplicacion'] = $aplicacion;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get("{$this->apiUrl}/gestion/authorization/user/contributor", $query);

            if (!$response->successful()) {
                Log::error('Suaporte: autorización fallida', [
                    'status'    => $response->status(),
                    'aportante' => "{$tipoDocumento} {$numeroDocumento}",
                    'body'      => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => $this->mensajeError(
                        $response,
                        "El usuario de Enlace no tiene permisos sobre el aportante {$tipoDocumento} {$numeroDocumento}."
                    ),
                ];
            }

            $autorizacion = $this->extraerHeaders($response, self::HEADERS_AUTORIZACION);

            if (empty($autorizacion['contributor'])) {
                return [
                    'success' => false,
                    'message' => 'Enlace autorizó la petición pero no devolvió los headers del aportante.',
                ];
            }

            $this->headers = array_merge($this->headers, $autorizacion);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al autorizar', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error al autorizar sobre el aportante: ' . $e->getMessage()];
        }
    }

    // ── 4. Validación / liquidación de la planilla ───────────────────────

    /**
     * Sube el archivo plano PILA para validación. Si el archivo no tiene
     * errores, Enlace crea la planilla y devuelve su número; si los tiene,
     * numeroPlanilla llega en 0 y se devuelve el detalle (máx. 100 líneas).
     *
     * @param array $opciones planillaUGPP, planillaNSoloNovedades, tipoArchivo
     * @return array{success: bool, codigo_planilla?: int, numero_planilla?: int, ...}
     */
    public function validarPlanilla(string $contenidoTxt, string $nombreArchivo, array $opciones = []): array
    {
        $parametros = json_encode([
            'planillaUGPP'           => (bool) ($opciones['planillaUGPP'] ?? false),
            'planillaNSoloNovedades' => (bool) ($opciones['planillaNSoloNovedades'] ?? false),
            'tipoArchivo'            => $opciones['tipoArchivo'] ?? 'I', // I = activos
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->attach('archivo', $contenidoTxt, $nombreArchivo)
                ->post("{$this->apiUrl}/generadorPlanillas/v1/planillas/validacion?" . http_build_query([
                    'parametros' => $parametros,
                ]));

            if (!$response->successful()) {
                Log::error('Suaporte: validación de planilla fallida', [
                    'status'  => $response->status(),
                    'archivo' => $nombreArchivo,
                    'body'    => $response->body(),
                ]);

                return [
                    'success'  => false,
                    'message'  => $this->mensajeError($response, 'Enlace rechazó el archivo plano.'),
                    'response' => $response->json(),
                ];
            }

            $data       = $response->json();
            $validacion = $data['validacionPlanillas'][0] ?? [];

            $numeroPlanilla = (int) ($validacion['numeroPlanilla'] ?? 0);
            $errores        = (int) ($validacion['cantidadErroresCotizante'] ?? 0)
                            + (int) ($validacion['cantidadErroresEmpresa'] ?? 0);

            return [
                'success'          => true,
                'liquidada'        => $numeroPlanilla > 0,
                'estado_validacion'=> $data['estadoValidacion'] ?? null,
                'codigo_planilla'  => (int) ($validacion['codigoPlanilla'] ?? 0),
                'numero_planilla'  => $numeroPlanilla,
                'total_errores'    => $errores,
                'errores_cotizante'=> $validacion['erroresCotizantePlanilla'] ?? [],
                'errores_empresa'  => $validacion['erroresEmpresaPlanilla'] ?? [],
                'advertencias'     => $validacion['advertenciasPlanilla'] ?? [],
                'response'         => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al validar planilla', [
                'message' => $e->getMessage(),
                'archivo' => $nombreArchivo,
            ]);

            return ['success' => false, 'message' => 'Error al enviar el archivo plano: ' . $e->getMessage()];
        }
    }

    /**
     * Detalle completo de inconsistencias de una planilla con errores.
     * La validación solo lista las primeras 100 líneas; este servicio pagina.
     */
    public function consultarInconsistencias(int $codigoPlanilla, int $registroInicial = 0, int $limite = 100): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get("{$this->apiUrl}/generadorPlanillas/v1/planillas/{$codigoPlanilla}/inconsistencias", [
                    'registro-inicial' => $registroInicial,
                    'limite'           => $limite,
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->mensajeError($response, 'No fue posible consultar las inconsistencias.'),
                ];
            }

            return ['success' => true, 'inconsistencias' => $response->json()];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al consultar inconsistencias', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error al consultar inconsistencias: ' . $e->getMessage()];
        }
    }

    /**
     * Consulta la afiliación de una persona en BDUA (salud) y RUAF (pensión).
     *
     * A diferencia del resto, solo exige los 5 headers del login: no hay que
     * autorizarse sobre ningún aportante, así que sirve para cualquier cédula
     * —útil al registrar un cliente nuevo—.
     *
     * Cuando la persona no figura, responde 200 con los nombres vacíos y las
     * administradoras en NIN-EP / NIN-AF.
     */
    public function consultarAfiliacion(string $tipoDocumento, string $numeroDocumento): array
    {
        if (empty($this->headers['token'])) {
            $auth = $this->autenticar();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get("{$this->apiUrl}/generadorPlanillas/v1/administradoras/bdua-ruaf/{$tipoDocumento}/{$numeroDocumento}");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->mensajeError($response, 'No fue posible consultar la afiliación.'),
                ];
            }

            $data = $response->json();

            // Sin registro: nombres vacíos y administradoras "ninguna".
            $registrado = !empty($data['primerApellido'])
                || (!empty($data['administradoraBDUA']) && $data['administradoraBDUA'] !== 'NIN-EP');

            return [
                'success'    => true,
                'registrado' => $registrado,
                'afiliacion' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al consultar BDUA/RUAF', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error al consultar la afiliación: ' . $e->getMessage()];
        }
    }

    // ── 5. Totales y pago ────────────────────────────────────────────────

    /**
     * Totales de la planilla liquidada: valor a pagar, mora, fecha límite y
     * desglose por administradora.
     */
    public function consultarTotales(int $numeroPlanilla): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get("{$this->apiUrl}/generadorPlanillas/v1/planillas/{$numeroPlanilla}/totales");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->mensajeError($response, 'No fue posible consultar los totales de la planilla.'),
                ];
            }

            $data = $response->json();

            // Una planilla inexistente (o de otro operador) NO da error: la API
            // responde 200 con el objeto vacío y totalPagar en 0. Se detecta por
            // la ausencia del número de planilla y del nombre del aportante.
            if (empty($data['numeroPlanilla']) && empty($data['nombreAportante'])) {
                return [
                    'success' => false,
                    'message' => "La planilla {$numeroPlanilla} no existe en este operador o no pertenece al aportante autorizado.",
                    'totales' => $data,
                ];
            }

            return [
                'success'       => true,
                'total_pagar'   => $data['totalPagar']   ?? null,
                'total_sin_mora'=> $data['totalSinMora'] ?? null,
                'valor_mora'    => $data['valorMora']    ?? null,
                'fecha_limite'  => $data['fechaLimite']  ?? null,
                'estado'        => $data['estadoPlanilla'] ?? null,
                'totales'       => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al consultar totales', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error al consultar totales: ' . $e->getMessage()];
        }
    }

    /**
     * URL de pago (PSE) de una planilla liquidada. El servicio devuelve la URL
     * como string plano, no como objeto JSON.
     */
    public function obtenerUrlPago(int $numeroPlanilla): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->get("{$this->apiUrl}/generadorPlanillas/v1/planillas/{$numeroPlanilla}/pago/url");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->mensajeError($response, 'No fue posible obtener la URL de pago.'),
                ];
            }

            $url = trim($response->body(), " \t\n\r\0\x0B\"");

            return ['success' => true, 'url_pago' => $url];
        } catch (\Exception $e) {
            Log::error('Suaporte: excepción al obtener URL de pago', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error al obtener la URL de pago: ' . $e->getMessage()];
        }
    }

    /**
     * ¿La planilla ya fue pagada?
     *
     * La plataforma no expone un campo de estado de pago: `estadoPlanilla`
     * trae códigos internos (GU = generada, OK = …) sin documentar. El único
     * indicador fiable es que el servicio de URL de pago se niega a operar
     * sobre una planilla ya pagada o en trámite de pago.
     *
     * @return array{success: bool, pagada?: bool, estado?: string, ...}
     */
    public function consultarEstadoPago(int $numeroPlanilla): array
    {
        $totales = $this->consultarTotales($numeroPlanilla);

        if (!$totales['success']) {
            return $totales;
        }

        $pago = $this->obtenerUrlPago($numeroPlanilla);

        // Con URL de pago disponible la planilla sigue pendiente.
        if ($pago['success']) {
            return [
                'success'      => true,
                'pagada'       => false,
                'estado'       => $totales['estado'],
                'total_pagar'  => $totales['total_pagar'],
                'valor_mora'   => $totales['valor_mora'],
                'fecha_limite' => $totales['fecha_limite'],
                'url_pago'     => $pago['url_pago'],
                'totales'      => $totales['totales'],
            ];
        }

        $pagada = str_contains(mb_strtolower($pago['message'] ?? ''), 'pagada');

        return [
            'success'      => true,
            'pagada'       => $pagada,
            'estado'       => $totales['estado'],
            'total_pagar'  => $totales['total_pagar'],
            'valor_mora'   => $totales['valor_mora'],
            'fecha_limite' => $totales['fecha_limite'],
            'mensaje'      => $pago['message'] ?? null,
            'totales'      => $totales['totales'],
        ];
    }

    // ── Flujo completo ───────────────────────────────────────────────────

    /**
     * Encadena el flujo completo: login → aportante → autorización →
     * validación → totales → URL de pago.
     *
     * Si la planilla trae errores se detiene después de la validación y
     * devuelve el detalle para mostrarlo al usuario.
     */
    public function liquidarPlanilla(string $nit, string $contenidoTxt, string $nombreArchivo, array $opciones = []): array
    {
        $tipoDocumento = $opciones['tipo_documento'] ?? 'NI';
        $nit           = preg_replace('/\D/', '', $nit); // sin DV ni guiones

        $auth = $this->autenticar();
        if (!$auth['success']) {
            return $auth + ['paso' => 'autenticacion'];
        }

        $aportante = $this->consultarAportante($tipoDocumento, $nit);

        // Contratista independiente que liquida por primera vez en Enlace:
        // no existe como aportante todavía. Se registra (tipoAportanteId=2)
        // y se reintenta la consulta una sola vez.
        if (!$aportante['success'] && !empty($opciones['crear_si_no_existe'])) {
            $creado = $this->crearAportanteIndependiente([
                'tipo_documento'      => $tipoDocumento,
                'documento'           => $nit,
                'nombre'              => $opciones['nombre_aportante'] ?? $nit,
                'contacto'            => $opciones['contacto_aportante'] ?? [],
                'actividad_economica' => $opciones['actividad_economica'] ?? null,
            ]);

            if ($creado['success'] ?? false) {
                $aportante = $this->consultarAportante($tipoDocumento, $nit);
            }
        }

        if (!$aportante['success']) {
            return $aportante + ['paso' => 'consulta_aportante'];
        }

        $autorizacion = $this->autorizar($aportante['id'], $tipoDocumento, $nit, $opciones['aplicacion'] ?? null);
        if (!$autorizacion['success']) {
            return $autorizacion + ['paso' => 'autorizacion'];
        }

        $validacion = $this->validarPlanilla($contenidoTxt, $nombreArchivo, $opciones);
        if (!$validacion['success']) {
            return $validacion + ['paso' => 'validacion'];
        }

        $validacion['paso']         = 'validacion';
        $validacion['aportante_id'] = $aportante['id'];

        // Con errores no hay planilla liquidada: no tiene sentido pedir totales.
        if (!$validacion['liquidada']) {
            return $validacion;
        }

        $totales = $this->consultarTotales($validacion['numero_planilla']);
        if ($totales['success']) {
            $validacion['totales'] = $totales;
        }

        $pago = $this->obtenerUrlPago($validacion['numero_planilla']);
        if ($pago['success']) {
            $validacion['url_pago'] = $pago['url_pago'];
        }

        $validacion['paso'] = 'liquidada';

        return $validacion;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Headers acumulados (sesión + autorización). */
    public function headersSesion(): array
    {
        return $this->headers;
    }

    /**
     * Restaura una sesión ya establecida (por ejemplo entre dos peticiones
     * HTTP distintas) sin volver a autenticar.
     */
    public function usarHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    private function extraerHeaders(\Illuminate\Http\Client\Response $response, array $nombres): array
    {
        $headers = [];

        foreach ($nombres as $nombre) {
            $valor = $response->header($nombre);
            if ($valor !== '') {
                $headers[$nombre] = $valor;
            }
        }

        return $headers;
    }

    /** Extrae el mensaje de error de Enlace, que llega en {"message": "..."}. */
    private function mensajeError(\Illuminate\Http\Client\Response $response, string $porDefecto): string
    {
        $mensaje = $response->json('message');

        if (is_string($mensaje) && $mensaje !== '') {
            return $mensaje;
        }

        return $porDefecto . ' (HTTP ' . $response->status() . ')';
    }
}
