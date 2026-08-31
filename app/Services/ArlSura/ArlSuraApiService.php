<?php

namespace App\Services\ArlSura;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente del API de ARL Sura (Servicios en Línea, "SelWEB3").
 *
 * Sura no publica APIs de ARL: este es el backend REST que usa su propio portal
 * transaccional, mapeado en agosto de 2026. Documentación no hay; lo que se sabe
 * está en la memoria del proyecto y en los comentarios de abajo.
 *
 * Dos cosas que no son obvias y rompen todo si faltan:
 *
 *   1. El header `x-auth-poliza`. Sin él cada llamada responde 500
 *      "El usuario no tiene acceso a la empresa", aunque la cookie sea válida.
 *   2. La sesión NO se puede abrir desde aquí: nace del SSO de login.sura.com
 *      (ASP.NET con teclado virtual, detrás de Imperva Incapsula) y luego de
 *      elegir empresa por NIT en la Sucursal Virtual. Un cliente HTTP no pasa
 *      ese login. Por eso la cookie se siembra desde afuera —hoy a mano, con
 *      `--cookie`— y aquí solo se reutiliza mientras siga viva.
 *
 * Endpoints en dos familias: `/sel-services/...` para catálogos y consultas, y
 * `/sel-services/portal/...` para lo que opera sobre la póliza.
 */
class ArlSuraApiService
{
    public const HOST = 'https://arpsura.suramericana.com';

    private const BASE        = '/sel-services';
    private const BASE_PORTAL = '/sel-services/portal';

    /** La sesión del portal caduca sola; guardarla más tiempo solo genera 500 confusos. */
    private const TTL_SESION_MINUTOS = 30;

    /** Se apaga mientras se comprueba una sesión, para no abrir una nueva sin querer. */
    private bool $autoRenovar = true;

    public function __construct(private int $aliadoId, private string $poliza)
    {
    }

    // ─── Sesión ──────────────────────────────────────────────────────

    /**
     * La sesión del portal pertenece a la PÓLIZA, no al aliado de BryNex: la
     * misma razón social puede estar registrada en varios aliados y todos operan
     * sobre el mismo contrato con Sura. Cachearla por aliado obligaría a abrir
     * una sesión nueva por cada uno, para la misma empresa.
     */
    private static function claveCache(int $aliadoId, string $poliza): string
    {
        return "arlsura:sesion:{$poliza}";
    }

    /** Siembra la cookie capturada de una sesión abierta en el navegador. */
    public static function guardarSesion(int $aliadoId, string $poliza, string $cookie): void
    {
        Cache::put(self::claveCache($aliadoId, $poliza), $cookie, now()->addMinutes(self::TTL_SESION_MINUTOS));
    }

    public static function olvidarSesion(int $aliadoId, string $poliza): void
    {
        Cache::forget(self::claveCache($aliadoId, $poliza));
    }

    public function cookie(): ?string
    {
        return Cache::get(self::claveCache($this->aliadoId, $this->poliza));
    }

    /**
     * Comprueba que la sesión sirve. Usa `afiliaciones/parametros` porque es la
     * llamada más barata del API y no toca datos de nadie.
     *
     * No abre sesión: solo dice si la que hay funciona.
     */
    public function sesionViva(): bool
    {
        if (! $this->cookie()) {
            return false;
        }

        try {
            $this->autoRenovar = false;

            return isset($this->get(self::BASE.'/afiliaciones/parametros')['nmDiasIngRet']);
        } catch (\Throwable $e) {
            return false;
        } finally {
            $this->autoRenovar = true;
        }
    }

    /**
     * La cookie, abriendo sesión si hace falta.
     *
     * Aquí es donde el usuario deja de copiar cookies: si la sesión caducó y el
     * aliado tiene credenciales registradas, se abre una nueva sola.
     */
    private function cookieAsegurada(): string
    {
        if ($cookie = $this->cookie()) {
            return $cookie;
        }

        if (! $this->autoRenovar) {
            throw new RuntimeException("No hay sesión de ARL Sura para la póliza {$this->poliza}.");
        }

        return ArlSuraSesionService::renovar($this->aliadoId, $this->poliza);
    }

    // ─── Transporte ──────────────────────────────────────────────────

    private function peticion(bool $reintentable = false): \Illuminate\Http\Client\PendingRequest
    {
        $cookie = $this->cookieAsegurada();

        $peticion = Http::withHeaders([
                'Accept'         => 'application/json, text/plain, */*',
                'Content-Type'   => 'application/json;charset=utf-8',
                'x-auth-poliza'  => $this->poliza,
                'Cookie'         => $cookie,
            ])
            ->timeout(60);

        // El reintento solo se aplica a lecturas. En una escritura sería un
        // desastre silencioso: un timeout en la respuesta de `trabajador/afiliar`
        // no significa que Sura no la haya procesado, y reintentar dejaría al
        // trabajador afiliado dos veces. Ante la duda se falla y se consulta.
        return $reintentable ? $peticion->retry(2, 500) : $peticion;
    }

    public function get(string $ruta): array
    {
        $r = $this->peticion(reintentable: true)->get(self::HOST.$ruta);
        return $this->interpretar($r, 'GET '.$ruta);
    }

    public function post(string $ruta, array $cuerpo = []): array
    {
        $r = $this->peticion()->post(self::HOST.$ruta, $cuerpo);
        return $this->interpretar($r, 'POST '.$ruta);
    }

    /** Para `carnet/generarPDF` y `certificado/afiliacion`, que devuelven el PDF en binario. */
    public function postPdf(string $ruta, array $cuerpo): string
    {
        $r = $this->peticion()->post(self::HOST.$ruta, $cuerpo);

        if ($r->failed() || ! str_starts_with($r->body(), '%PDF-')) {
            throw new RuntimeException('ARL Sura no devolvió un PDF en '.$ruta.': '.$this->mensajeError($r->body()));
        }

        return $r->body();
    }

    private function interpretar(\Illuminate\Http\Client\Response $r, string $contexto): array
    {
        if ($r->failed()) {
            $mensaje = $this->mensajeError($r->body());
            Log::warning('ARL Sura: fallo en '.$contexto, ['status' => $r->status(), 'mensaje' => $mensaje]);
            throw new RuntimeException("ARL Sura ({$r->status()}) en {$contexto}: {$mensaje}");
        }

        return $r->json() ?? [];
    }

    /**
     * Los errores llegan como JSON con las comillas escapadas en HTML
     * (`{&quot;responseMessage&quot;:...}`), no como JSON legible.
     */
    private function mensajeError(string $cuerpo): string
    {
        $limpio = html_entity_decode($cuerpo);
        $json   = json_decode($limpio, true);

        return $json['responseMessage'] ?? trim(strip_tags($limpio)) ?: 'sin detalle';
    }

    // ─── Catálogos ───────────────────────────────────────────────────

    /** @return array<int,array{codigoEps:string,dni:string,dsNombre:string}> */
    public function epsListado(): array
    {
        return $this->get(self::BASE.'/eps/listado');
    }

    /** @return array<int,array{codigoAfp:string,dni:string,dsNombre:string}> */
    public function afpListado(): array
    {
        return $this->get(self::BASE.'/afp/listado');
    }

    /**
     * Centros de trabajo de la póliza. El portal los pide de a pocos con
     * `filaDesde`/`filaHasta`; aquí se recorre hasta agotarlos.
     *
     * @return array<int,array<string,mixed>>
     */
    public function centrosDeTrabajo(int $porPagina = 50): array
    {
        $centros = [];
        $desde   = 0;
        $total   = null;

        do {
            $r = $this->post(self::BASE_PORTAL.'/sucursalAfiliaciones/centrosDeTrabajo', [
                'cdDepartamentoSuc' => null,
                'cdCiudadSuc'       => null,
                'cdClaseSuc'        => null,
                'filaDesde'         => $desde,
                'filaHasta'         => $desde + $porPagina,
            ]);

            $total ??= (int) ($r['numRegistros'] ?? 0);
            $pagina  = $r['listCentrosTrabajo'] ?? [];
            $centros = array_merge($centros, $pagina);
            $desde  += $porPagina;
        } while ($pagina && count($centros) < $total && $desde < 2000);

        return $centros;
    }

    // ─── Operaciones sobre trabajadores ──────────────────────────────

    /**
     * Afilia un trabajador. El payload lo arma [[ArlSuraPayloadBuilder]].
     *
     * Responde `{resultadoAfiliacion:true, codigoTransaccion, fechaProceso, …}`.
     * Ojo: un `resultadoAfiliacion` en false NO llega como error HTTP, viene con
     * 200 y hay que mirarlo.
     */
    public function afiliar(array $payload): array
    {
        $r = $this->post(self::BASE_PORTAL.'/trabajador/afiliar', $payload);

        if (! ($r['resultadoAfiliacion'] ?? false)) {
            throw new RuntimeException('ARL Sura rechazó la afiliación: '.($r['mensaje'] ?? json_encode($r)));
        }

        return $r;
    }

    /** Coberturas activas del trabajador que se pueden retirar. */
    public function coberturasRetirables(string $dniTrabajador): array
    {
        $r = $this->post(self::BASE_PORTAL.'/trabajador/coberturas/retiro/listado', [
            'dniTrabajador' => $dniTrabajador,
            'polizaEmpresa' => $this->poliza,
        ]);

        return $r['listCoberturasTrabajador'] ?? [];
    }

    public function retirar(array $payload): array
    {
        return $this->post(self::BASE_PORTAL.'/trabajador/retirar', $payload);
    }

    /** Datos que Sura ya tiene del trabajador; sirve para no pedirle al usuario lo que ya existe. */
    public function consultarDependiente(string $dniTrabajador): array
    {
        return $this->post(self::BASE.'/afiliacion/consultarDependiente', [
            'dniTrabajador' => $dniTrabajador,
            'polizaEmpresa' => $this->poliza,
        ]);
    }

    /** Sura valida que el sexo coincida con el que tiene registrado para ese documento. */
    public function validarDocumentoSexo(string $tipoId, string $numDoc, string $sexo): array
    {
        return $this->post(self::BASE.'/trabajador/validar/documentoSexo', [
            'tipoId' => $tipoId,
            'numDoc' => $numDoc,
            'sexo'   => $sexo,
        ]);
    }

    // ─── Documentos ──────────────────────────────────────────────────

    /** Certificado de afiliación ("imprimir soporte"). Devuelve el PDF crudo. */
    public function certificadoAfiliacion(string $tipoId, string $numDoc, string $tipoAfiliado = 'D'): string
    {
        // Los nombres de campo van con el typo del original: les falta la "A"
        // de "afiliado". Escritos bien, el API responde 500.
        return $this->postPdf(self::BASE_PORTAL.'/trabajador/certificado/afiliacion', [
            'poliza'        => $this->poliza,
            'tipoIdfiliado' => $tipoId,
            'numDocfiliado' => $numDoc,
            'tipoAfiliado'  => $tipoAfiliado,
        ]);
    }

    /** Carné de la ARL. Devuelve el PDF crudo. */
    public function carne(string $dniEmpleado, string $dniEmpresa, bool $movil = false): string
    {
        return $this->postPdf(self::BASE_PORTAL.'/carnet/generarPDF', [
            'dniEmpleado' => $dniEmpleado,
            'dniEmpresa'  => $dniEmpresa,
            'carnetMovil' => $movil,
        ]);
    }

    // ─── Catálogos de apoyo ──────────────────────────────────────────

    /** Municipios de un departamento, con la equivalencia DANE ↔ código interno de Sura. */
    public function municipios(string $cdDepartamentoDane): array
    {
        return $this->post(self::BASE.'/municipios/municipiospostal', [
            'genericoPaginacion' => ['numeroRegistros' => 1000, 'registroInicial' => 0],
            'cdDepartamento'     => $cdDepartamentoDane,
        ]);
    }

    /**
     * Estandariza una dirección contra el geocodificador de Sura.
     *
     * Devuelve el bloque `data`, que trae la dirección normalizada (`dirtrad`:
     * "CR 39 # 43 - 4" → "CR 39 # 43 - 04"), las coordenadas y —lo más útil— el
     * `barrio`, que es justo el dato que en BryNex casi nunca está registrado.
     */
    public function estandarizarDireccion(string $direccion, string $municipioDane): array
    {
        $r = $this->post(self::BASE.'/direccion/estandarizar', [
            'address' => $direccion,
            'city'    => $municipioDane,
        ]);

        if (! ($r['success'] ?? false)) {
            throw new RuntimeException('Sura no pudo geocodificar la dirección "'.$direccion.'": '.($r['message'] ?? 'sin detalle'));
        }

        return $r['data'] ?? [];
    }
}
