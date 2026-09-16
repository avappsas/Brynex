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
     * Sesiones abiertas en esta instancia, por operador + credencial + aportante.
     * Un envío masivo manda la planilla de toda una empresa: con una sesión por
     * empresa basta, en vez de un login por persona.
     */
    private array $sesiones = [];

    /**
     * Sesiones que no se pudieron abrir (credencial mala, NIT sin permisos,
     * portal caído). No se reintentan en la misma corrida: un portal caído no
     * debe costar 40 segundos por cada persona de un lote de cien.
     */
    private array $sesionesFallidas = [];

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
            $pdf = Storage::disk('local')->get($ruta);

            if ($operadorPlanillaId) {
                $this->registrarPago($plano, $operadorPlanillaId, $pdf);
            }

            return ['success' => true, 'pdf' => $pdf, 'origen' => 'disco'];
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
                $this->registrarPago($plano, (int) $operador->id, $pdf);

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

    /**
     * El soporte que se le entrega al cliente: el del operador si se puede, y si
     * no, el que arma BryNex. Es lo que deben usar la descarga, el envío masivo y
     * el asistente, para que los tres entreguen lo mismo.
     *
     * @return array{pdf: string, origen: string}
     */
    public function soporte(Plano $plano, ?int $operadorPlanillaId = null): array
    {
        $delOperador = $this->obtener($plano, $operadorPlanillaId);

        if ($delOperador['success']) {
            return ['pdf' => $delOperador['pdf'], 'origen' => $delOperador['origen']];
        }

        return [
            'pdf'    => app(PlanillaFormularioService::class)->generar($plano, $operadorPlanillaId),
            'origen' => 'brynex',
        ];
    }

    /**
     * Guarda la fecha y hora exactas del pago, una vez por planilla. Solo el
     * informe del operador la trae; el API no, y la lista de planillas pagadas
     * del portal solo da el día.
     */
    public function registrarPago(Plano $plano, int $operadorPlanillaId, string $pdf): void
    {
        try {
            $existe = DB::table('planillas_pago_operador')
                ->where('aliado_id', $plano->aliado_id)
                ->where('numero_planilla', (string) $plano->numero_planilla)
                ->exists();

            if ($existe) {
                return;
            }

            $informe = self::leerInforme($pdf);

            if (empty($informe['fecha_pago']) || ($informe['numero_planilla'] ?? null) !== (string) $plano->numero_planilla) {
                return;
            }

            DB::table('planillas_pago_operador')->insert([
                'aliado_id'            => $plano->aliado_id,
                'razon_social_id'      => $plano->razon_social_id,
                'operador_planilla_id' => $operadorPlanillaId,
                'numero_planilla'      => (string) $plano->numero_planilla,
                'fecha_pago'           => $informe['fecha_pago'],
                'tipo_planilla'        => $informe['tipo_planilla'],
                'periodo_cotizacion'   => $informe['periodo_cotizacion'],
                'periodo_servicio'     => $informe['periodo_servicio'],
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } catch (Throwable $e) {
            // Guardar la fecha es un extra: nunca debe impedir entregar el PDF.
            Log::warning('Informe individual: no se pudo guardar la fecha de pago', [
                'planilla' => $plano->numero_planilla,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lee el encabezado del informe individual: número de planilla, tipo,
     * períodos y fecha de pago.
     *
     * El PDF lo genera JasperReports con Helvetica, así que el texto va plano
     * dentro de streams comprimidos: cada texto es `1 0 0 1 x y Tm ... (texto)Tj`.
     * Una etiqueta y su valor están en la misma línea (misma y), el valor a la
     * derecha; así se emparejan, sin depender del orden en que vienen.
     *
     * @return array{numero_planilla: ?string, tipo_planilla: ?string, periodo_cotizacion: ?string, periodo_servicio: ?string, fecha_pago: ?string}
     */
    public static function leerInforme(string $pdf): array
    {
        $textos = [];

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams)) {
            foreach ($streams[1] as $crudo) {
                $contenido = @gzuncompress($crudo);
                if ($contenido === false) {
                    continue; // imágenes y demás
                }

                preg_match_all(
                    '/1 0 0 1 ([\d.]+) ([\d.]+) Tm\s*\/F\d+ [\d.]+ Tf\s*[\d. ]*rg\s*\(((?:\\\\.|[^\\\\)])*)\)Tj/',
                    $contenido,
                    $m,
                    PREG_SET_ORDER
                );

                foreach ($m as [, $x, $y, $texto]) {
                    $texto = trim(mb_convert_encoding(stripcslashes($texto), 'UTF-8', 'Windows-1252'));
                    if ($texto !== '') {
                        $textos[] = ['x' => (float) $x, 'y' => round((float) $y), 'texto' => $texto];
                    }
                }
            }
        }

        $valorDe = function (string $etiqueta) use ($textos): ?string {
            foreach ($textos as $t) {
                if (mb_strtolower($t['texto']) !== mb_strtolower($etiqueta)) {
                    continue;
                }
                $derecha = array_filter($textos, fn ($o) => abs($o['y'] - $t['y']) <= 1 && $o['x'] > $t['x']);
                usort($derecha, fn ($a, $b) => $a['x'] <=> $b['x']);

                return $derecha ? reset($derecha)['texto'] : null;
            }

            return null;
        };

        $fechaPago = null;
        foreach ($textos as $t) {
            if (preg_match('/^PAGADA\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $t['texto'], $f)) {
                $fechaPago = $f[1];
                break;
            }
        }

        $soloDigitos = fn ($v) => ($v = preg_replace('/\D/', '', (string) $v)) === '' ? null : $v;

        return [
            'numero_planilla'    => $soloDigitos($valorDe('Número Planilla')),
            'tipo_planilla'      => $valorDe('Tipo Planilla'),
            'periodo_cotizacion' => $soloDigitos($valorDe('Periodo Cotización')),
            'periodo_servicio'   => $soloDigitos($valorDe('Periodo Servicio')),
            'fecha_pago'         => $fechaPago,
        ];
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

        [$tipoAportante, $numeroAportante] = $this->aportanteDe($plano);
        $llave = "{$codigoOperador}|{$cred->id}|{$tipoAportante}{$numeroAportante}";

        if (isset($this->sesionesFallidas[$llave])) {
            throw new RuntimeException($this->sesionesFallidas[$llave]);
        }

        $reusada = isset($this->sesiones[$llave]);

        if (! $reusada) {
            $http = new Client([
                'cookies'         => new CookieJar(),
                'timeout'         => 40,
                'connect_timeout' => 10,
                'http_errors'     => false,
                'headers'         => ['User-Agent' => 'Mozilla/5.0'],
            ]);

            try {
                $this->abrirSesion($http, $host, $codigoOperador, $cred, $tipoAportante, $numeroAportante);
            } catch (Throwable $e) {
                $this->sesionesFallidas[$llave] = $e->getMessage();
                throw $e;
            }

            $this->sesiones[$llave] = $http;
        }

        $http = $this->sesiones[$llave];

        // 2. La pantalla: de aquí salen el ViewState y los nombres que genera JSF.
        $html = (string) $http->get($pagina)->getBody();
        $xpath = $this->xpath($html);

        if (! $xpath->query('//*[@id="btnGenerarComprobante"]')->length) {
            // La sesión del operador dura unos minutos: si la que se reusaba ya
            // venció, se abre otra una sola vez.
            if ($reusada) {
                unset($this->sesiones[$llave]);

                return $this->descargar($plano, $codigoOperador, $cred);
            }

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
    private function abrirSesion(Client $http, string $host, string $codigoOperador, OperadorCredencial $cred, string $tipoAportante, string $numeroAportante): void
    {
        $cifrador = new SuaporteApiService(['operador' => $codigoOperador]);
        $contrasena = $cifrador->cifrarDato((string) $cred->contrasena)
            ?? throw new RuntimeException('no se pudo cifrar la contraseña.');

        $login = $http->post("{$host}/auth/login", [
            'headers' => ['clave-secreta' => $cred->clave_secreta],
            'json'    => ['usuario' => SuaporteApiService::usuarioPortal($cred->usuario), 'contrasena' => $contrasena],
        ]);

        if ($login->getStatusCode() !== 200 || ! $login->getHeaderLine('token')) {
            throw new RuntimeException('el login fue rechazado.');
        }

        $headers = [];
        foreach (['token', 'refresh-token', 'refresh-token-ttl', 'refresh-token-date', 'faces'] as $nombre) {
            $headers[$nombre] = $login->getHeaderLine($nombre);
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

    /** El aportante de un independiente es la persona; el de una empresa, su NIT. */
    private function aportanteDe(Plano $plano): array
    {
        $rs = $plano->razonSocial;

        [$tipo, $numero] = ($rs?->es_independiente)
            ? [strtoupper(trim($plano->tipo_doc ?: 'CC')), (string) $plano->no_identifi]
            : ['NI', preg_replace('/\D/', '', (string) $rs?->nit)];

        if ($numero === '') {
            throw new RuntimeException('la razón social no tiene NIT.');
        }

        return [$tipo, $numero];
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
