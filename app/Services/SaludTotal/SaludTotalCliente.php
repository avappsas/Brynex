<?php

namespace App\Services\SaludTotal;

use App\Services\EpsPortal\EpsClavePortal;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sesión HTTP contra la Oficina Virtual de empleadores de Salud Total.
 *
 * No hace falta navegador (probado desde netcup el 14-sep-2026): el login es un
 * POST con la clave en MD5 y sin reCAPTCHA —el reCAPTCHA de la página es del
 * registro de usuarios—, y el perfil empleador no tiene 2FA activo. De ahí salen
 * tres cosas:
 *  - un JWT para el API de consulta de afiliados (`APIOFVAfiliadosPBSEmpleador`);
 *  - un JWT de la Oficina Virtual que entrega URLs firmadas a la app MVC
 *    `NovedadInicioLaboral`, cuya sesión va en cookies;
 *  - en esa app: firma del empleador, validación, registro y seguimiento.
 */
class SaludTotalCliente
{
    public const ENTIDAD = 'salud_total';

    private const BASE = 'https://transaccional.saludtotal.com.co';

    /** Tipos de documento de BryNex → los del portal. */
    public const TIPOS_DOC = ['CC' => 'C', 'CE' => 'E', 'PA' => 'P', 'PP' => 'P', 'PT' => 'PT', 'PPT' => 'PT', 'TI' => 'T', 'SC' => 'SC', 'PC' => 'PC'];

    private CookieJar $cookies;

    private ?string $jwtOficina = null;

    private ?string $jwtAfiliados = null;

    /** El GUID de sesión del portal: de él salen los demás tokens. */
    private ?string $tokenPortal = null;

    private ?string $jwtReportesCartera = null;

    private ?string $appAbierta = null;

    private function __construct(private string $nit, private string $tipoUsuario, private string $documentoUsuario)
    {
        $this->cookies = new CookieJar();
    }

    /**
     * Inicia sesión. Lanza `SaludTotalLoginException` si el portal rechaza la clave.
     */
    public static function entrar(string $nit, string $usuario, string $contrasena): self
    {
        [$tipo, $numero] = EpsClavePortal::separarUsuario($usuario);
        $cliente = new self(preg_replace('/\D/', '', $nit), self::TIPOS_DOC[$tipo] ?? 'C', $numero);

        $r = $cliente->http()->post(self::BASE.'/ApiOficinaVirtual/Login/Login', [
            'tipoUsuario'     => 2, // empleador
            'tipoIdEmpleador' => 'N',
            'IdEmpleador'     => $cliente->nit,
            'tipoIdUsuario'   => $cliente->tipoUsuario,
            'IdUsuario'       => $cliente->documentoUsuario,
            'CodigoAsesor'    => '',
            'Clave'           => md5($contrasena),
        ]);

        $data = $r->json('data') ?? [];

        if (! $r->ok() || ($data['error'] ?? 1) != 0 || empty($data['token'])) {
            throw new SaludTotalLoginException(trim((string) ($data['mensaje'] ?? $r->json('mensajeError') ?? '')) ?: "El portal no aceptó el usuario y la clave (HTTP {$r->status()}).");
        }

        $jwt = $cliente->http()->post(self::BASE.'/ApiOficinaVirtual/Login/CreateJWT', ['Token' => $data['token'], 'Origen' => 'OficinaVirtual']);
        $cliente->jwtOficina = is_string($jwt->json('data')) ? $jwt->json('data') : null;

        $cliente->tokenPortal = (string) $data['token'];

        $afi = $cliente->http()->get(self::BASE.'/APIOFVAfiliadosPBSEmpleador/api/Token/GetToken', ['Token' => $data['token'], 'Origen' => 'OficinaVirtual']);
        $cliente->jwtAfiliados = $afi->json('Valido') ? $afi->json('Token') : null;

        if (! $cliente->jwtOficina || ! $cliente->jwtAfiliados) {
            throw new RuntimeException('Salud Total aceptó la clave pero no entregó los tokens de sesión.');
        }

        return $cliente;
    }

    /**
     * Los tres reportes de cartera de un período (`'8/2026'`, sin cero delante).
     *
     * Salud Total ya los separa en el origen, que es justo lo que hace falta
     * para saber qué hacer con cada uno: lo que no se pagó, lo que se pagó de
     * más por alguien ya retirado, y lo que se pagó por quien nunca estuvo
     * afiliado. Los dos últimos son plata de la empresa, no deuda.
     *
     * Cuando hay registros el portal responde con la URL de un archivo; cuando
     * no, lo dice en `Descripcion`.
     *
     * @return array<string, array{hay:bool, url:?string, mensaje:?string}>
     */
    public function cartera(string $periodo): array
    {
        $reportes = [
            'sin_pago'              => 'ConultaCotSinPago',
            'desafiliados_con_pago' => 'ConultaCoDesafiliadoCP',
            'pago_sin_afiliacion'   => 'ConultaCotizanteConPagoNA',
        ];

        $salida = [];

        foreach ($reportes as $nombre => $ruta) {
            // Las rutas van con la errata del portal ("Conulta"): así responden.
            $r = $this->http()
                ->withHeaders(['Authorization' => 'bearer '.$this->jwtCartera()])
                ->get(self::BASE."/STAPI_ReportesCRInternet/api/Cotizantes/{$ruta}", [
                    'EmpleadorId'     => $this->nit,
                    'EmpleadorTipoId' => 'N',
                    'Periodo'         => $periodo,
                ]);

            $j = $r->json() ?: [];

            $salida[$nombre] = [
                'hay'     => (bool) ($j['Valido'] ?? false),
                'url'     => $j['Url'] ?? null,
                'mensaje' => $j['Descripcion'] ?: ($j['Error'] ?: null),
            ];
        }

        return $salida;
    }

    private function jwtCartera(): string
    {
        if (! $this->jwtReportesCartera) {
            $r = $this->http()->get(self::BASE.'/STAPI_ReportesCRInternet/api/Token/GetToken', [
                'Token' => $this->tokenPortal, 'Origen' => 'OficinaVirtual',
            ]);

            $this->jwtReportesCartera = $r->json('Valido') ? $r->json('Token') : null;

            if (! $this->jwtReportesCartera) {
                throw new RuntimeException('Salud Total no entregó el token de los reportes de cartera.');
            }
        }

        return $this->jwtReportesCartera;
    }

    /**
     * Grupo familiar de la persona con esta empresa: vacío si no está afiliada
     * con ella. Cada integrante trae parentesco, estado, contrato vigente y
     * fecha de afiliación.
     */
    public function grupoFamiliar(string $tipoDocBrynex, string $documento): array
    {
        $tipoId = $this->idTipoDocumento($tipoDocBrynex);

        $r = $this->http()->withToken($this->jwtAfiliados, 'bearer')
            ->get(self::BASE.'/APIOFVAfiliadosPBSEmpleador/api/ConsultaAfiliados/ConsultaGrupoFamiliar', [
                'BeneficiarioId' => $documento, 'BeneficiarioTipoId' => $tipoId,
                'Empleadorid' => $this->nit, 'Empleadortipoid' => 'N',
            ]);

        return is_array($r->json()) ? $r->json() : [];
    }

    /** Datos de la persona en Salud Total (nombres, estado, contratos activos e inactivos), sin importar la empresa. */
    public function datosPersona(string $tipoDocBrynex, string $documento): ?array
    {
        $this->abrirApp('NovedadLaboral');

        $r = $this->ajax()->get(self::BASE.'/NovedadInicioLaboral/NovedadInicioLaboral/ConsultarDatosAfectadoPorDocumento/', [
            'tipoDocumento' => $this->tipoDocPortal($tipoDocBrynex), 'documento' => $documento,
        ]);

        return $r->json()[0] ?? null;
    }

    /** Novedades de inicio laboral registradas por la empresa en el rango (AAAA-MM-DD). */
    public function seguimiento(string $desde, string $hasta): array
    {
        $this->abrirApp('SeguimientoLaboral');

        $r = $this->ajax()->get(self::BASE.'/NovedadInicioLaboral/SeguimientoIngreso/GetConsultaNovedadesRelacion/', [
            'FechaInicio' => $desde, 'FechaFin' => $hasta,
        ]);

        return is_array($r->json()) ? $r->json() : [];
    }

    /** Motivos de rechazo de una novedad. */
    public function inconsistencias(string $numeroFormulario): array
    {
        $this->abrirApp('SeguimientoLaboral');

        $r = $this->ajax()->get(self::BASE.'/NovedadInicioLaboral/SeguimientoIngreso/GetAfiliacionesGetInconsistenciasGrilla/', [
            'NumeroFormulario' => $numeroFormulario, 'Sucursal' => 'INTERNET',
        ]);

        return collect(is_array($r->json()) ? $r->json() : [])
            ->map(fn ($i) => trim(($i['Campo'] ?? '').': '.($i['Des_Inconsistencia'] ?? ''), ': '))
            ->filter()->values()->all();
    }

    /** PDF del certificado de una novedad aprobada. */
    public function certificado(string $numeroFormulario): ?string
    {
        $this->abrirApp('SeguimientoLaboral');

        $url = $this->ajax()->asForm()->post(self::BASE.'/NovedadInicioLaboral/SeguimientoIngreso/GenerarCertificado/', [
            'numeroFormulario' => $numeroFormulario,
        ])->json();

        return is_string($url) && str_starts_with($url, 'https://') ? $this->descargarPdf($url) : null;
    }

    /** PDF del Formulario Único de Afiliación de una novedad (existe desde que se radica). */
    public function formularioPdf(string $numeroFormulario): ?string
    {
        return $this->descargarPdf(self::BASE.'/generarpdffua/default.aspx?IDForm='.urlencode($numeroFormulario));
    }

    /** Tipos de cotizante que ofrece el formulario: código PILA → texto. */
    public function tiposCotizante(): array
    {
        $this->abrirApp('NovedadLaboral');

        $html = $this->http()->get(self::BASE.'/NovedadInicioLaboral/NovedadInicioLaboral/RegistroNovedad')->body();

        if (! preg_match('#<select[^>]*id="slcCotizante"[^>]*>(.*?)</select>#s', $html, $m)) {
            return [];
        }

        preg_match_all('#<option[^>]*value="([^"]*)"[^>]*>(.*?)</option>#s', $m[1], $ops, PREG_SET_ORDER);

        return collect($ops)->filter(fn ($o) => $o[1] !== '0')
            ->mapWithKeys(fn ($o) => [$o[1] => trim(html_entity_decode(strip_tags($o[2])))])->all();
    }

    /**
     * Registra la novedad de inicio de relación laboral.
     *
     * @return array{numeroFormulario:string, urlPdf:?string, envio:array}
     */
    public function registrarNovedad(array $p): array
    {
        $this->abrirApp('NovedadLaboral');

        $firma = $this->ajax()->get(self::BASE.'/NovedadInicioLaboral/NovedadInicioLaboral/ConsultaFirmaEmpleador/')->json()[0] ?? null;

        if (($firma['Estado'] ?? 0) != 1 || empty($firma['Firma'])) {
            throw new RuntimeException('La empresa no tiene firma digitalizada en Salud Total: cárguela en el portal (Afiliaciones → Firma digitalizada).');
        }

        $tipo = $this->tipoDocPortal($p['tipo_doc']);

        $validacion = $this->ajax()->get(self::BASE.'/NovedadInicioLaboral/NovedadInicioLaboral/ValidacionFormulario/', [
            'datosFormulario' => implode(',', [$tipo, $p['documento'], $p['fecha_ingreso'], $p['tipo_cotizante'], $p['ibc'], '']),
        ])->json();

        if ($validacion !== '' && $validacion !== null) {
            throw new RuntimeException('Salud Total rechazó los datos: '.strip_tags(str_replace(['<br>', '<br/>'], ' ', (string) $validacion)));
        }

        $envio = [
            'beneficiarioTipoId' => $tipo,
            'TipoDocumento'      => $p['tipo_doc_texto'],
            'beneficiarioId'     => $p['documento'],
            'nombre1'            => $p['nombre1'],
            'nombre2'            => $p['nombre2'],
            'apellido1'          => $p['apellido1'],
            'apellido2'          => $p['apellido2'],
            'IBC'                => $p['ibc'],
            'FechaIngreso'       => $p['fecha_ingreso'],
            'IdTipoCotizante'    => $p['tipo_cotizante'],
            'TipoCotizante'      => $p['tipo_cotizante_texto'],
            'CodigoAsesor'       => '',
            'firmaEmpleador'     => $firma['Firma'],
            'fechaFirma'         => substr((string) $firma['fechaRegistro'], 0, 10),
            'codigoCertificado'  => (string) Str::uuid(),
        ];

        $r = $this->ajax()->asForm()->post(self::BASE.'/NovedadInicioLaboral/NovedadInicioLaboral/PostFormularioUnico/', ['novedad' => $envio]);
        $numero = (string) ($r->json('numeroFormulario') ?? '');

        if (! $r->ok() || $r->json('result') !== 0 || ! preg_match('/^\d{6,}$/', $numero)) {
            throw new RuntimeException('Salud Total no confirmó el registro: '.($r->json('msg_err') ?: substr($r->body(), 0, 200)));
        }

        return ['numeroFormulario' => $numero, 'urlPdf' => $r->json('urlPdf'), 'envio' => collect($envio)->except('firmaEmpleador')->all()];
    }

    /** Abre (una vez por sesión) la app MVC con la URL firmada que entrega la Oficina Virtual. */
    private function abrirApp(string $opcion): void
    {
        if ($this->appAbierta === $opcion) {
            return;
        }

        $url = trim((string) $this->http()->withToken($this->jwtOficina, 'bearer')
            ->get(self::BASE."/ApiOficinaVirtual/NovedadLaboral/{$opcion}", [
                'empleadorId' => $this->nit, 'empleadorTipoId' => 'N',
                'beneficiarioId' => $this->documentoUsuario, 'beneficiarioTipoId' => $this->tipoUsuario,
            ])->json('data'));

        if (! str_starts_with($url, self::BASE.'/NovedadInicioLaboral/')) {
            throw new RuntimeException("Salud Total no entregó el acceso a {$opcion}.");
        }

        $this->http()->get($url);
        $this->appAbierta = $opcion;
    }

    private function descargarPdf(string $url): ?string
    {
        // Con el Accept: application/json de http() los PDF responden 406.
        $r = $this->http()->accept('application/pdf,*/*')->timeout(90)->get($url);

        return $r->ok() && str_starts_with($r->body(), '%PDF') ? $r->body() : null;
    }

    private function tipoDocPortal(string $tipoBrynex): string
    {
        return self::TIPOS_DOC[strtoupper($tipoBrynex)]
            ?? throw new RuntimeException("Tipo de documento '{$tipoBrynex}' sin equivalencia en Salud Total.");
    }

    /** El API de consulta usa ids numéricos por tipo (PT = 16); con la letra responde "The request is invalid". */
    private function idTipoDocumento(string $tipoBrynex): string
    {
        // Sacados de `api/TipoDocumento/GetTipoDocInt?TipoDocumento=<letra>` el 14-sep-2026.
        return (string) ['C' => 1, 'T' => 2, 'E' => 5, 'P' => 10, 'SC' => 12, 'PT' => 16, 'PC' => 17][$this->tipoDocPortal($tipoBrynex)];
    }

    private function http(): PendingRequest
    {
        return Http::withOptions(['cookies' => $this->cookies])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'])
            ->timeout(60)
            ->acceptJson();
    }

    private function ajax(): PendingRequest
    {
        return $this->http()->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
    }
}
