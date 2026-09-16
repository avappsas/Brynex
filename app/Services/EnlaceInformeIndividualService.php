<?php

namespace App\Services;

use App\Models\OperadorCredencial;
use App\Models\Plano;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Baja del operador el PDF original del "Informe individual" de un cotizante.
 *
 * El soporte que arma BryNex se calcula por su cuenta y no siempre coincide con
 * lo que quedó radicado; el del operador es, por definición, el de verdad —y es
 * el único que trae la hora exacta del pago—. El API documentado de Enlace no lo
 * expone, pero el portal viejo (JSF) sí, y acepta la sesión del login del API:
 * ese login también deja cookies, y con ellas la página abre con el aportante ya
 * autorizado. Todo por HTTP, sin navegador.
 *
 * El recorrido imita lo que hace la pantalla, paso por paso:
 *
 *   1. login + aportante + autorización (API)  → cookies de sesión
 *   2. GET  individuales.xhtml                  → ViewState y campos dinámicos
 *   3. AJAX obtenerPeriodos                     → la planilla aparece pagada
 *   4. AJAX btn_consultar                       → se acepta ese período
 *   5. POST btnGenerarComprobante (multipart)   → el reporte queda en cola
 *   6. AJAX btnVerifyFile                       → hasta que esté listo
 *   7. POST btnGetFile                          → application/pdf
 *
 * Trampa comprobada: hay que mandar SOLO los campos que manda el navegador. Los
 * que la pantalla deja deshabilitados no viajan; si se mandan, el portal
 * responde "Este campo es requerido" y el reporte nunca se genera.
 *
 * Es una pantalla interna, no un API: los `j_idtNNN` los genera JSF y cambian
 * con cada versión del portal, así que se leen del HTML y nunca se quemen.
 *
 * El PDF se guarda en el disco `local` (datos personales, nunca `public`) la
 * primera vez que se baja: una planilla pagada no cambia, y así no se abre
 * sesión con el operador cada vez que alguien lo pide.
 */
class EnlaceInformeIndividualService
{
    private const PAGINA = '/Web/faces/pages/comprobantes/individuales/individuales.xhtml';

    /** Cuántas veces se pregunta si el reporte ya está, con un segundo entre cada una. */
    private const ESPERAS_MAXIMAS = 25;

    /**
     * PDF del operador para el cotizante del plano, del disco si ya se bajó.
     *
     * Con `$operadorPlanillaId` se intenta solo ese operador; sin él, cada
     * operador de Enlace para el que el aliado tenga credenciales.
     *
     * @return array{success: bool, pdf?: string, origen?: string, message?: string}
     */
    public function obtener(Plano $plano, ?int $operadorPlanillaId = null): array
    {
        if (empty($plano->numero_planilla) || empty($plano->no_identifi)) {
            return ['success' => false, 'message' => 'El plano no tiene número de planilla o cédula.'];
        }

        $ruta = self::rutaEnDisco($plano);

        if (Storage::disk('local')->exists($ruta)) {
            return ['success' => true, 'pdf' => Storage::disk('local')->get($ruta), 'origen' => 'disco'];
        }

        $operadores = DB::table('operadores_planilla')
            ->whereIn('codigo', array_keys(SuaporteApiService::HOSTS))
            ->when($operadorPlanillaId, fn ($q) => $q->where('id', $operadorPlanillaId))
            ->get(['id', 'codigo', 'nombre']);

        if ($operadores->isEmpty()) {
            return ['success' => false, 'message' => 'El operador de la planilla no corre sobre Enlace Operativo.'];
        }

        $mensajes = [];

        foreach ($operadores as $operador) {
            $cred = OperadorCredencial::paraOperador(
                (int) $plano->aliado_id, (int) $operador->id, $plano->razon_social_id ? (int) $plano->razon_social_id : null
            )->first();

            if (! $cred) {
                $mensajes[] = "{$operador->nombre}: sin credenciales.";
                continue;
            }

            try {
                $pdf = $this->descargar($plano, $operador->codigo, $cred);
                Storage::disk('local')->put($ruta, $pdf);

                return ['success' => true, 'pdf' => $pdf, 'origen' => 'operador'];
            } catch (Throwable $e) {
                $mensajes[] = "{$operador->nombre}: {$e->getMessage()}";
            }
        }

        Log::info('Informe individual: no se pudo bajar del operador', [
            'plano_id' => $plano->id,
            'planilla' => $plano->numero_planilla,
            'motivos'  => $mensajes,
        ]);

        return ['success' => false, 'message' => implode(' ', $mensajes)];
    }

    /** Dónde queda el PDF de un cotizante: una carpeta por aliado y planilla. */
    public static function rutaEnDisco(Plano $plano): string
    {
        $limpiar = fn ($v) => preg_replace('/[^A-Za-z0-9]/', '', (string) $v);

        return sprintf(
            'planillas-operador/%d/%s/%s-%s.pdf',
            (int) $plano->aliado_id,
            $limpiar($plano->numero_planilla),
            $limpiar($plano->tipo_doc ?: 'CC'),
            $limpiar($plano->no_identifi)
        );
    }

    /** El recorrido completo contra un operador. Lanza con un mensaje legible si algo falla. */
    private function descargar(Plano $plano, string $codigoOperador, OperadorCredencial $cred): string
    {
        $host = SuaporteApiService::hostDeOperador($codigoOperador);
        $pagina = $host.self::PAGINA;

        $http = new Client([
            'cookies'         => new CookieJar(),
            'timeout'         => 40,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'headers'         => ['User-Agent' => 'Mozilla/5.0'],
        ]);

        $this->abrirSesion($http, $host, $codigoOperador, $cred, $plano);

        // 2. La pantalla: de aquí salen el ViewState y los nombres que genera JSF.
        $html = (string) $http->get($pagina)->getBody();
        $xpath = $this->xpath($html);

        if (! $xpath->query('//*[@id="btnGenerarComprobante"]')->length) {
            throw new RuntimeException('el portal no abrió el informe individual (sesión no reconocida).');
        }

        $viewState = $this->valor($xpath, '//form[@id="form"]//input[@name="javax.faces.ViewState"]');
        $codigoOperadorPortal = $this->valor($xpath, '//input[@name="tx_ntu:operador"]');
        $estadosPago = $this->valor($xpath, '//input[@name="tx_ntu:estadoPago"]') ?: 'OK,PD,PWS';
        $campoToken = $xpath->query('//input[contains(concat(" ", @class, " "), " token_download ")]')->item(0)?->getAttribute('name');

        $tipoDoc = strtoupper(trim($plano->tipo_doc ?: 'CC'));
        $tiposValidos = array_map(
            fn ($o) => $o->getAttribute('value'),
            iterator_to_array($xpath->query('//select[@name="tipoDocumentoCotizante"]/option'))
        );

        if ($tiposValidos && ! in_array($tipoDoc, $tiposValidos, true)) {
            throw new RuntimeException("el portal no maneja el tipo de documento {$tipoDoc}.");
        }

        // 3. ¿La planilla figura pagada para este aportante?
        $respuesta = $this->ajax($http, $pagina, $viewState, 'tx_ntu:obtenerPeriodos', 'click', 'click', [
            'javax.faces.partial.execute' => 'tx_ntu:obtenerPeriodos tx_ntu:numeroPlanilla tx_ntu:estadoPago tx_ntu:codigoEmpresaConsultante',
            'javax.faces.partial.render'  => 'tx_ntu:panelPeriodos',
            'tx_ntu:numeroPlanilla'       => (string) $plano->numero_planilla,
            'tx_ntu:estadoPago'           => $estadosPago,
            'tx_ntu:codigoEmpresaConsultante' => '',
        ]);
        $viewState = $this->viewStateParcial($respuesta) ?? $viewState;

        preg_match('/name="(tx_ntu:tableComponentePlanilla:0:j_idt\d+)"/', $respuesta, $radio);

        if (empty($radio[1])) {
            throw new RuntimeException("la planilla {$plano->numero_planilla} no figura pagada para este aportante.");
        }

        $seleccion = [$radio[1] => 'true'];

        // 4. Se acepta el período, como el botón del modal.
        $respuesta = $this->ajax($http, $pagina, $viewState, 'tx_ntu:btn_consultar', 'click', 'action', [
            'javax.faces.partial.execute' => 'tx_ntu:btn_consultar tx_ntu:listaSeleccion',
        ] + $seleccion);
        $viewState = $this->viewStateParcial($respuesta) ?? $viewState;

        // 5. Generar. Exactamente los campos que manda el navegador.
        $hoy = now();
        $campos = [
            'form' => 'form',
            'includeAyuda037:A32-nameHelp' => '',
            'includeAyuda037:A32-textoAyuda' => '',
            'radio_nroPlanilla' => 'PLANILLA',
            'tx_ntu:estadoPago' => $estadosPago,
            'tx_ntu:codigoEmpresaConsultante' => '',
            'tx_ntu:operador' => $codigoOperadorPortal,
            'tx_ntu:numeroPlanilla' => (string) $plano->numero_planilla,
            'tx_fechaInicial:fechaServidor' => $hoy->format('d/m/Y'),
            'tx_fechaInicial:textoFecha' => '',
            'tx_fechaFinal:fechaServidor' => $hoy->format('d/m/Y'),
            'tx_fechaFinal:textoFecha' => '',
            'periodoCotizacion:anioServidor' => $hoy->format('Y'),
            'periodoCotizacion:mesServidor' => (string) $hoy->month,
            'periodoCotizacion:textoPeriodo' => '',
            'periodoCotizacion:mes' => (string) $hoy->month,
            'periodoCotizacion:anio' => $hoy->format('Y'),
            'sucursales:codigoSucursalBusqueda' => '',
            'sucursales:nombreSucursalBusqueda' => '',
            'sucursales:listaSucursales:paginador_sucu:page' => '1',
            'sucursales:listaSucursales:paginador_sucu:lastPage' => '0',
            'sucursales:listaSucursales:paginador_sucu:pageSize' => '10',
            'sucursales:listaSucursales:paginador_sucu:onevent' => 'invalid',
            'sucursales:listaSucursales:paginador_sucu:pageSizeDrop' => '10',
            'radio_reporte' => 'REPORTE',
            'radioTipoReporte' => 'ACTIVOS',
            'radio_cotizante' => 'COTIZANTE',
            'tipoDocumentoCotizante' => $tipoDoc,
            'inputNroDocCotizante' => (string) $plano->no_identifi,
            'inputNroDocCot' => '',
            'areaDocCot' => '',
            'input_centroT' => '',
            'formatoSalida' => 'CONSOLIDADO',
            'javax.faces.ViewState' => $viewState,
            'btnGenerarComprobante' => 'btnGenerarComprobante',
        ] + $seleccion;

        if ($campoToken) {
            $campos[$campoToken] = (string) round(microtime(true) * 1000);
        }

        $html = (string) $http->post($pagina, [
            'multipart' => array_map(
                fn ($k, $v) => ['name' => $k, 'contents' => (string) $v],
                array_keys($campos),
                $campos
            ),
        ])->getBody();

        $estado = $this->fileready($html);

        if ($estado === 'stop') {
            throw new RuntimeException('el portal rechazó la solicitud'.$this->mensajePortal($html));
        }

        $viewState = $this->valor($this->xpath($html), '//form[@id="form"]//input[@name="javax.faces.ViewState"]') ?: $viewState;

        // 6. Esperar a que el reporte esté listo.
        for ($i = 0; $estado === 'waitingReport' && $i < self::ESPERAS_MAXIMAS; $i++) {
            $respuesta = $this->ajax($http, $pagina, $viewState, 'btnVerifyFile', 'action', 'action', [
                'javax.faces.partial.execute' => 'btnVerifyFile',
                'javax.faces.partial.render'  => 'fileready',
            ]);
            $viewState = $this->viewStateParcial($respuesta) ?? $viewState;
            $estado = $this->fileready($respuesta);

            if ($estado === 'waitingReport') {
                sleep(1);
            }
        }

        // 7. El archivo.
        $archivo = $http->post($pagina, ['form_params' => [
            'form' => 'form',
            'javax.faces.ViewState' => $viewState,
            'btnGetFile' => 'btnGetFile',
        ]]);
        $cuerpo = (string) $archivo->getBody();

        if (! str_starts_with($cuerpo, '%PDF')) {
            throw new RuntimeException('el portal no entregó el PDF'.$this->mensajePortal($cuerpo));
        }

        return $cuerpo;
    }

    /** Login, aportante y autorización. El mismo jar de cookies sirve después para el portal. */
    private function abrirSesion(Client $http, string $host, string $codigoOperador, OperadorCredencial $cred, Plano $plano): void
    {
        $cifrador = new SuaporteApiService(['operador' => $codigoOperador]);
        $contrasena = $cifrador->cifrarDato((string) $cred->contrasena)
            ?? throw new RuntimeException('no se pudo cifrar la contraseña.');

        $login = $http->post("{$host}/auth/login", [
            'headers' => ['clave-secreta' => $cred->clave_secreta],
            'json'    => ['usuario' => $cred->usuario, 'contrasena' => $contrasena],
        ]);

        if ($login->getStatusCode() !== 200 || ! $login->getHeaderLine('token')) {
            throw new RuntimeException('el login fue rechazado.');
        }

        $headers = [];
        foreach (['token', 'refresh-token', 'refresh-token-ttl', 'refresh-token-date', 'faces'] as $nombre) {
            $headers[$nombre] = $login->getHeaderLine($nombre);
        }

        // El aportante de un independiente es la persona; el de una empresa, su NIT.
        $rs = $plano->razonSocial;
        [$tipoAportante, $numeroAportante] = ($rs?->es_independiente)
            ? [strtoupper(trim($plano->tipo_doc ?: 'CC')), (string) $plano->no_identifi]
            : ['NI', preg_replace('/\D/', '', (string) $rs?->nit)];

        if ($numeroAportante === '') {
            throw new RuntimeException('la razón social no tiene NIT.');
        }

        $aportante = $http->get("{$host}/api/gestion/aportante/{$tipoAportante}/{$numeroAportante}", ['headers' => $headers]);
        $idAportante = json_decode((string) $aportante->getBody(), true)['id'] ?? null;

        if (! $idAportante) {
            throw new RuntimeException("el aportante {$tipoAportante} {$numeroAportante} no existe en el operador.");
        }

        $autorizacion = $http->get("{$host}/api/gestion/authorization/user/contributor", [
            'headers' => $headers,
            'query'   => ['id' => $idAportante, 'tipoIdentificacion' => $tipoAportante, 'numeroIdentificacion' => $numeroAportante],
        ]);

        if ($autorizacion->getStatusCode() !== 200) {
            throw new RuntimeException("el usuario no tiene permisos sobre {$tipoAportante} {$numeroAportante}.");
        }
    }

    /** Petición AJAX de JSF (mojarra.ab). */
    private function ajax(Client $http, string $pagina, string $viewState, string $fuente, string $evento, string $comportamiento, array $extra): string
    {
        return (string) $http->post($pagina, [
            'headers'     => ['Faces-Request' => 'partial/ajax'],
            'form_params' => [
                'form' => 'form',
                'javax.faces.ViewState' => $viewState,
                'javax.faces.source' => $fuente,
                'javax.faces.partial.event' => $evento,
                'javax.faces.behavior.event' => $comportamiento,
                'javax.faces.partial.ajax' => 'true',
            ] + $extra,
        ])->getBody();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function valor(DOMXPath $xpath, string $consulta): string
    {
        return (string) $xpath->query($consulta)->item(0)?->getAttribute('value');
    }

    private function viewStateParcial(string $respuesta): ?string
    {
        return preg_match('/<update id="[^"]*ViewState[^"]*"><!\[CDATA\[([^\]]+)\]\]>/', $respuesta, $m) ? $m[1] : null;
    }

    private function fileready(string $html): string
    {
        return preg_match('/<span id="fileready"[^>]*>([^<]*)</', $html, $m) ? trim($m[1]) : '';
    }

    /** Lo que el portal le mostraría al usuario en el modal de error, si dijo algo. */
    private function mensajePortal(string $html): string
    {
        foreach (['/id="errorMessage"[^>]*>([^<]+)</', '/<ul[^>]*class="messages"[^>]*>(.*?)<\/ul>/s'] as $patron) {
            if (preg_match($patron, $html, $m) && trim(strip_tags($m[1])) !== '') {
                return ': '.mb_substr(trim(html_entity_decode(strip_tags($m[1]))), 0, 200);
            }
        }

        return '.';
    }
}
