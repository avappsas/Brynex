<?php

namespace App\Services\ArlSura;

use App\Models\ArlAfiliacion;
use App\Models\Contrato;
use App\Models\DocumentoCliente;
use App\Models\Radicado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orquesta el ciclo del contrato ante ARL Sura: afiliar, retirar y archivar los
 * documentos que el portal genera.
 *
 * Cada operación queda registrada en `arl_afiliaciones` —también cuando falla—
 * porque el API no tiene ambiente de pruebas y sin el payload guardado no hay
 * forma de reconstruir qué se envió.
 *
 * Los PDF (soporte y carné) van al disco `local`, nunca a `public`: llevan
 * cédula, dirección y salario. Ver el hallazgo C-4 de la auditoría.
 */
class ArlAfiliacionService
{
    public const DOC_SOPORTE = 'arl_soporte';
    public const DOC_CARNE   = 'arl_carne';

    public function __construct(
        private ArlSuraApiService $api,
        private ArlSuraPayloadBuilder $builder,
    ) {
    }

    public static function paraContrato(Contrato $contrato): self
    {
        $poliza = $contrato->razonSocial?->arl_poliza
            ?? throw new RuntimeException("La razón social del contrato {$contrato->id} no tiene póliza ARL.");

        $api = new ArlSuraApiService((int) $contrato->aliado_id, $poliza);

        return new self($api, new ArlSuraPayloadBuilder($api));
    }

    // ─── Afiliación ──────────────────────────────────────────────────

    /**
     * Afilia al trabajador y archiva soporte y carné.
     *
     * `$inicioCobertura` por defecto es mañana: salvo cobertura por horas, Sura
     * no cubre el mismo día en que se afilia.
     */
    public function afiliar(Contrato $contrato, ?Carbon $inicioCobertura = null, ?int $usuarioId = null): ArlAfiliacion
    {
        $inicio  = $inicioCobertura ?: now()->addDay();
        $payload = $this->builder->paraAfiliacion($contrato, $inicio);

        $registro = new ArlAfiliacion([
            'aliado_id'              => $contrato->aliado_id,
            'contrato_id'            => $contrato->id,
            'razon_social_id'        => $contrato->razon_social_id,
            'cedula'                 => $contrato->cedula,
            'operacion'              => ArlAfiliacion::OP_AFILIACION,
            'poliza'                 => $payload['poliza'],
            'tipo_afiliado'          => $payload['tipoAfiliado'],
            'tipo_cotizante'         => $payload['tipoCotizante']['cdTipoCotizante'],
            'codigo_centro'          => $payload['sitioTrabajo']['centroTrabajo']['cdSucursal'],
            'nivel_riesgo'           => (int) $contrato->n_arl,
            'fecha_inicio_cobertura' => $inicio->toDateString(),
            'payload'                => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'usuario_id'             => $usuarioId,
        ]);

        try {
            $respuesta = $this->api->afiliar($payload);
        } catch (Throwable $e) {
            $registro->fill([
                'estado'        => ArlAfiliacion::ESTADO_FALLIDA,
                'mensaje_error' => Str::limit($e->getMessage(), 500),
            ])->save();

            throw $e;
        }

        $registro->fill([
            'estado'             => ArlAfiliacion::ESTADO_EXITOSA,
            'codigo_transaccion' => $respuesta['codigoTransaccion'] ?? null,
            'fecha_proceso'      => $this->fechaProceso($respuesta['fechaProceso'] ?? null),
            'respuesta'          => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
        ])->save();

        // La fecha de ARL del contrato es lo que mueve el semáforo de Gestión
        // ARL. Se actualiza aquí y no en el controlador para que valga igual
        // cuando la afiliación se dispara desde un job o desde consola.
        $contrato->update(['fecha_arl' => $registro->fecha_inicio_cobertura]);

        // Los documentos son un extra: si el portal falla al generarlos, la
        // afiliación ya está hecha y no tiene sentido deshacerla ni ocultarla.
        try {
            $docs = $this->archivarDocumentos($contrato, $payload['tipoAfiliado'], $usuarioId);
            $this->cerrarRadicado($contrato, $registro, $docs[self::DOC_SOPORTE] ?? null, $usuarioId);
        } catch (Throwable $e) {
            $registro->update(['mensaje_error' => 'Afiliado, pero sin documentos: '.Str::limit($e->getMessage(), 400)]);
        }

        return $registro;
    }

    /**
     * Deja el radicado de ARL en OK con el soporte adjunto.
     *
     * El radicado es como el equipo sigue los trámites: si la afiliación quedó
     * hecha y el certificado descargado, no tiene sentido que alguien entre a
     * marcarlo a mano. El número que se guarda es el código de transacción, que
     * es lo que Sura pide cuando hay algo que reclamar.
     */
    private function cerrarRadicado(Contrato $contrato, ArlAfiliacion $afiliacion, ?DocumentoCliente $soporte, ?int $usuarioId): void
    {
        Radicado::updateOrCreate(
            [
                'contrato_id' => $contrato->id,
                'tipo'        => Radicado::TIPO_ARL,
            ],
            [
                'aliado_id'          => $contrato->aliado_id,
                'estado'             => Radicado::ESTADO_OK,
                'numero_radicado'    => $afiliacion->codigo_transaccion,
                'canal_envio'        => Radicado::CANAL_WEB,
                'ruta_pdf'           => $soporte?->ruta,
                'fecha_confirmacion' => now(),
                'user_id'            => $usuarioId,
                'observacion'        => 'Afiliación automática en ARL Sura desde BryNex. Cobertura desde '.
                    $afiliacion->fecha_inicio_cobertura->format('d/m/Y').'.',
            ]
        );
    }

    // ─── Retiro ──────────────────────────────────────────────────────

    /**
     * Retira al trabajador. `$fechaFin` no puede ser anterior al inicio de la
     * cobertura ni posterior a un año; Sura rechaza ambos extremos.
     */
    public function retirar(Contrato $contrato, Carbon $fechaFin, ?int $usuarioId = null): ArlAfiliacion
    {
        $dni         = $this->dni($contrato);
        $coberturas  = $this->api->coberturasRetirables($dni);

        if (! $coberturas) {
            throw new RuntimeException("El trabajador {$contrato->cedula} no tiene coberturas activas para retirar.");
        }

        // La forma del payload sale del propio portal (`registrarRetiro` en el
        // bundle de SelWEB3): en la raíz van los datos del afiliado y la póliza
        // como `npoliza`, y cada cobertura seleccionada viaja tal cual la
        // devolvió el listado, agregándole `fechaRetiro` en dd/mm/aaaa.
        $cobertura = $coberturas[0];
        $payload   = [
            'tipoId'               => $this->tipoId($contrato->cliente->tipo_doc),
            'numDoc'               => (string) $contrato->cedula,
            'npoliza'              => $contrato->razonSocial->arl_poliza,
            'snRetiroPorMuerte'    => 'N',
            'fechaMuerte'          => null,
            'listCoberturasRetiro' => [array_merge($cobertura, [
                'fechaRetiro' => $fechaFin->format('d/m/Y'),
            ])],
        ];

        $registro = new ArlAfiliacion([
            'aliado_id'           => $contrato->aliado_id,
            'contrato_id'         => $contrato->id,
            'razon_social_id'     => $contrato->razon_social_id,
            'cedula'              => $contrato->cedula,
            'operacion'           => ArlAfiliacion::OP_RETIRO,
            'poliza'              => $payload['npoliza'],
            'tipo_afiliado'       => $cobertura['tipoAfiliado'] ?? null,
            'codigo_centro'       => $cobertura['dsCentroTrabajo'] ?? null,
            'fecha_fin_cobertura' => $fechaFin->toDateString(),
            'payload'             => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'usuario_id'          => $usuarioId,
        ]);

        try {
            $respuesta = $this->api->retirar($payload);
        } catch (Throwable $e) {
            $registro->fill([
                'estado'        => ArlAfiliacion::ESTADO_FALLIDA,
                'mensaje_error' => Str::limit($e->getMessage(), 500),
            ])->save();

            throw $e;
        }

        $registro->fill([
            'estado'             => ArlAfiliacion::ESTADO_EXITOSA,
            'codigo_transaccion' => $respuesta['codigoTransaccion'] ?? null,
            'fecha_proceso'      => $this->fechaProceso($respuesta['fechaProceso'] ?? null),
            'respuesta'          => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
        ])->save();

        return $registro;
    }

    // ─── Anulación ───────────────────────────────────────────────────

    /**
     * Anula la afiliación: la cobertura desaparece, no queda como retirada.
     *
     * Es la única operación del ciclo **sin API**: vive en el Struts legacy, así
     * que se conduce con el navegador (`scripts/arl-sura-anular.mjs`) usando la
     * misma sesión que ya está abierta.
     *
     * Solo aplica dentro de los 30 días siguientes al inicio de la cobertura.
     * Pasado ese plazo la salida es el retiro, que deja historial en vez de
     * borrarlo.
     */
    public function anular(Contrato $contrato, ?int $usuarioId = null): ArlAfiliacion
    {
        $vigente = ArlAfiliacion::vigenteDe($contrato->id);

        // Los contratos afiliados a mano antes de la integración no tienen
        // historial en BryNex, pero su cobertura sí existe en Sura. La fecha de
        // ARL del contrato alcanza para saber si sigue dentro de los 30 días.
        // El portal manda: su fecha es la real, y existe aunque BryNex no la
        // tenga registrada.
        $inicioCobertura = $this->inicioEnSura($contrato)
            ?? $vigente?->fecha_inicio_cobertura
            ?? $contrato->fecha_arl;

        if (! $inicioCobertura) {
            throw new RuntimeException('Este contrato no tiene una afiliación registrada que anular.');
        }

        $anulable = $inicioCobertura->diffInDays(now(), false) <= 30;

        if (! $anulable) {
            throw new RuntimeException(
                'Ya pasaron los 30 días desde el inicio de la cobertura ('.
                $inicioCobertura->format('d/m/Y').'). Para cerrarla hay que retirar, no anular.'
            );
        }

        $cliente = $contrato->cliente;
        $poliza  = (string) $contrato->razonSocial?->arl_poliza;

        $registro = new ArlAfiliacion([
            'aliado_id'              => $contrato->aliado_id,
            'contrato_id'            => $contrato->id,
            'razon_social_id'        => $contrato->razon_social_id,
            'cedula'                 => $contrato->cedula,
            'operacion'              => ArlAfiliacion::OP_ANULACION,
            'poliza'                 => $poliza,
            'fecha_inicio_cobertura' => $inicioCobertura,
            'codigo_transaccion'     => $vigente?->codigo_transaccion,
            'usuario_id'             => $usuarioId,
        ]);

        // El tipo decide en cuál de las dos pantallas de anulación está la
        // cobertura, y lo dice el portal: es donde está la verdad, incluso si
        // el contrato quedó mal marcado. Si no se puede consultar, se cae al
        // plan del contrato, que es la intención declarada.
        $tipoAfiliado = $this->builder->tipoAfiliado($contrato);

        try {
            $tipoAfiliado = $this->coberturaEnSura($contrato)['tipoAfiliado'] ?: $tipoAfiliado;
        } catch (Throwable $e) {
            // Sin portal se sigue con lo que diga el plan.
        }

        $resultado = ArlSuraSesionService::anular(
            (int) $contrato->aliado_id,
            $poliza,
            ArlSuraPayloadBuilder::tipoDocumento($cliente->tipo_doc),
            (string) $contrato->cedula,
            $tipoAfiliado
        );

        if (! ($resultado['ok'] ?? false)) {
            $registro->fill([
                'estado'        => ArlAfiliacion::ESTADO_FALLIDA,
                'mensaje_error' => Str::limit($resultado['error'] ?? 'No se pudo anular.', 500),
            ])->save();

            throw new RuntimeException($resultado['error'] ?? 'No se pudo anular la afiliación.');
        }

        $registro->fill([
            'estado'        => ArlAfiliacion::ESTADO_EXITOSA,
            'fecha_proceso' => now(),
            'respuesta'     => json_encode($resultado, JSON_UNESCAPED_UNICODE),
        ])->save();

        // La afiliación anulada deja de estar vigente.
        $vigente?->update(['estado' => ArlAfiliacion::ESTADO_ANULADA]);

        // El radicado vuelve a quedar pendiente: la cobertura ya no existe, y
        // dejarlo en OK con un certificado que ya no vale sería peor que nada.
        Radicado::where('contrato_id', $contrato->id)
            ->where('tipo', Radicado::TIPO_ARL)
            ->update([
                'estado'             => Radicado::ESTADO_PENDIENTE,
                'numero_radicado'    => null,
                'ruta_pdf'           => null,
                'fecha_confirmacion' => null,
                'observacion'        => 'Afiliación anulada en ARL Sura el '.now()->format('d/m/Y H:i').
                    ($vigente?->codigo_transaccion ? ' (transacción '.$vigente->codigo_transaccion.').' : '.'),
            ]);

        $contrato->update(['fecha_arl' => null]);

        return $registro;
    }

    /**
     * La cobertura viva del trabajador según el portal.
     *
     * Es la única fuente confiable: los contratos afiliados a mano antes de la
     * integración tienen `fecha_arl` vacía en BryNex aunque su cobertura exista
     * en Sura, y renovar sin mirar aquí crearía una segunda cobertura sobre la
     * primera en vez de reemplazarla.
     */
    public function coberturaEnSura(Contrato $contrato): ?array
    {
        foreach ($this->api->coberturasRetirables($this->dni($contrato)) as $cobertura) {
            if (($cobertura['fechaRetiro'] ?? null) === null) {
                return $cobertura;
            }
        }

        return null;
    }

    /** La fecha en que arrancó esa cobertura, tal como la reporta el portal. */
    private function inicioEnSura(Contrato $contrato): ?Carbon
    {
        try {
            $cobertura = $this->coberturaEnSura($contrato);
        } catch (Throwable $e) {
            return null; // sin portal se sigue con lo que haya en BryNex
        }

        $fecha = $cobertura['fechaInicioCobertura'] ?? null;

        return $fecha ? Carbon::createFromFormat('d/m/Y', $fecha)->startOfDay() : null;
    }

    // ─── Renovación ──────────────────────────────────────────────────

    /**
     * Renueva la cobertura: anula la vigente y crea una nueva desde la fecha
     * indicada.
     *
     * Son dos pasos porque Sura no tiene forma de mover la fecha de una
     * cobertura ya creada: su portal solo ofrece afiliar, retirar y novedades
     * masivas por archivo. Se revisa antes que la nueva afiliación sea viable,
     * porque anular y quedarse sin poder afiliar deja al trabajador sin ARL.
     *
     * @return array{anulacion: ?ArlAfiliacion, afiliacion: ArlAfiliacion}
     */
    public function renovar(Contrato $contrato, Carbon $nuevoInicio, ?int $usuarioId = null): array
    {
        if ($faltantes = $this->builder->problemas($contrato)) {
            throw new RuntimeException(
                'No se puede renovar porque al contrato le falta: '.implode(' · ', $faltantes)
            );
        }

        // Se le pregunta al portal si ya hay cobertura viva. Fiarse de
        // `fecha_arl` dejaría dos coberturas encima en los contratos que se
        // afiliaron a mano y nunca quedaron registrados aquí.
        $cobertura = $this->coberturaEnSura($contrato);

        // Sin cobertura viva en el portal no hay nada que mover: se afilia.
        if (! $cobertura) {
            return [
                'modificacion' => null,
                'afiliacion'   => $this->afiliar($contrato, $nuevoInicio, $usuarioId),
            ];
        }

        // Se mueve la fecha de la cobertura que ya existe. No se cae a anular y
        // reafiliar: la anulación pide la misma ventana de 30 días que el
        // movimiento, así que como respaldo no salva ningún caso, y sí abriría
        // el hueco en que el trabajador se queda sin ARL si el alta falla.
        return [
            'modificacion' => $this->moverCobertura($contrato, $cobertura, $nuevoInicio, $usuarioId),
            'afiliacion'   => null,
        ];
    }

    /**
     * Mueve la fecha de inicio de la cobertura que ya existe.
     *
     * Si el portal la rechaza, se propaga su motivo tal cual: la cobertura
     * vieja sigue intacta, así que no hay nada que deshacer ni que avisar más
     * allá de por qué no se pudo.
     */
    private function moverCobertura(Contrato $contrato, array $cobertura, Carbon $nuevoInicio, ?int $usuarioId): ArlAfiliacion
    {
        $poliza = (string) $contrato->razonSocial?->arl_poliza;
        $desde  = $cobertura['fechaInicioCobertura'] ?? null;

        $registro = new ArlAfiliacion([
            'aliado_id'              => $contrato->aliado_id,
            'contrato_id'            => $contrato->id,
            'razon_social_id'        => $contrato->razon_social_id,
            'cedula'                 => $contrato->cedula,
            'operacion'              => ArlAfiliacion::OP_MODIFICACION,
            'poliza'                 => $poliza,
            'tipo_afiliado'          => $cobertura['tipoAfiliado'] ?? null,
            'tipo_cotizante'         => $cobertura['cdTipoCotizante'] ?? null,
            'nivel_riesgo'           => (int) $contrato->n_arl,
            'fecha_inicio_cobertura' => $nuevoInicio->toDateString(),
            // De dónde venía la cobertura. En el portal ese dato se pierde al
            // moverla, así que es el único sitio donde queda desde cuándo
            // estaba afiliado antes de esta renovación.
            'fecha_fin_cobertura'    => $desde ? Carbon::createFromFormat('d/m/Y', $desde)->toDateString() : null,
            'usuario_id'             => $usuarioId,
        ]);

        $resultado = ArlSuraSesionService::modificarCobertura(
            (int) $contrato->aliado_id,
            $poliza,
            ArlSuraPayloadBuilder::tipoDocumento($contrato->cliente?->tipo_doc),
            (string) $contrato->cedula,
            $nuevoInicio,
            ($cobertura['cdTipoCotizante'] ?? '01') === '59' ? '02' : '01',
        );

        if (! ($resultado['ok'] ?? false)) {
            $motivo = $resultado['error'] ?? 'No se pudo mover la cobertura.';

            $registro->fill([
                'estado'        => ArlAfiliacion::ESTADO_FALLIDA,
                'mensaje_error' => Str::limit($motivo, 500),
            ])->save();

            throw new RuntimeException($motivo);
        }

        $registro->fill([
            'estado'        => ArlAfiliacion::ESTADO_EXITOSA,
            'fecha_proceso' => now(),
            'respuesta'     => json_encode($resultado + ['fechaAnterior' => $desde], JSON_UNESCAPED_UNICODE),
        ])->save();

        // La cobertura anterior deja de ser la vigente: la que vale es esta.
        ArlAfiliacion::vigenteDe($contrato->id)?->update(['fecha_inicio_cobertura' => $nuevoInicio->toDateString()]);

        $contrato->update(['fecha_arl' => $nuevoInicio->toDateString()]);

        // El certificado viejo dice la fecha vieja, así que se vuelve a bajar.
        try {
            $docs = $this->archivarDocumentos($contrato, $registro->tipo_afiliado ?: 'D', $usuarioId);
            $this->cerrarRadicado($contrato, $registro, $docs[self::DOC_SOPORTE] ?? null, $usuarioId);
        } catch (Throwable $e) {
            $registro->update(['mensaje_error' => 'Cobertura movida, pero sin documentos: '.Str::limit($e->getMessage(), 400)]);
        }

        return $registro;
    }

    // ─── Documentos ──────────────────────────────────────────────────

    /** Descarga soporte y carné del portal y los archiva contra la cédula del cliente. */
    public function archivarDocumentos(Contrato $contrato, string $tipoAfiliado = 'D', ?int $usuarioId = null): array
    {
        $cliente = $contrato->cliente;
        $tipoId  = $this->tipoId($cliente->tipo_doc);
        $dni     = $this->dni($contrato);
        $rs      = $contrato->razonSocial;

        $pdfs = [
            self::DOC_SOPORTE => $this->api->certificadoAfiliacion($tipoId, (string) $contrato->cedula, $tipoAfiliado),
            self::DOC_CARNE   => $this->api->carne($dni, 'N'.preg_replace('/\D/', '', (string) $rs->nit)),
        ];

        $guardados = [];

        foreach ($pdfs as $tipo => $contenido) {
            $nombre = $tipo.'_'.$contrato->cedula.'_'.now()->format('Ymd_His').'.pdf';
            $ruta   = "documentos/{$contrato->aliado_id}/{$contrato->cedula}/{$nombre}";

            Storage::disk('local')->put($ruta, $contenido);

            $guardados[$tipo] = DocumentoCliente::create([
                'aliado_id'      => $contrato->aliado_id,
                'cc_cliente'     => $contrato->cedula,
                'tipo_documento' => $tipo,
                'nombre_archivo' => $nombre,
                'ruta'           => $ruta,
                'subido_por'     => $usuarioId,
            ]);
        }

        return $guardados;
    }

    // ─── Apoyo ───────────────────────────────────────────────────────

    /** Documento como lo pide el API en las consultas: letra del tipo + número. */
    private function dni(Contrato $contrato): string
    {
        return $this->tipoId($contrato->cliente->tipo_doc).$contrato->cedula;
    }

    /** Una sola tabla de equivalencias, la del builder. */
    private function tipoId(?string $tipoBrynex): string
    {
        return ArlSuraPayloadBuilder::tipoDocumento($tipoBrynex);
    }

    /** Sura devuelve "29/08/2026 07:34:47", que Carbon no interpreta solo. */
    private function fechaProceso(?string $valor): ?Carbon
    {
        if (! $valor) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', trim($valor));
        } catch (Throwable) {
            return null;
        }
    }
}
