<?php

namespace App\Services\SaludTotal;

use App\Models\Contrato;
use App\Models\EpsAfiliacion;
use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use App\Services\EpsPortal\EpsClavePortal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Novedad de inicio de relación laboral en Salud Total desde BryNex.
 *
 * Es el mismo trámite que se hace a mano en la Oficina Virtual (primer caso:
 * Ailen Oropeza, formulario 4017531563, 14-sep-2026). El portal devuelve el
 * número de formulario al registrar y el Formulario Único en PDF queda
 * disponible de inmediato, así que el radicado de BryNex queda en trámite con
 * ambos. Pasa a OK cuando Salud Total la aprueba, lo que detecta la conciliación.
 */
class SaludTotalNovedadService
{
    /** Código de Salud Total en la tabla `eps`. */
    public const CODIGO_EPS = 'EPS002';

    /** Tipo de cotizante PILA que se tramita por ahora: dependiente. */
    private const COTIZANTES_SOPORTADOS = ['1'];

    /** El portal solo acepta fechas de ingreso a ±30 días de hoy (`ConsultaFechasLimite`). */
    private const DIAS_FECHA = 30;

    /**
     * Revisa el contrato sin tocar el portal.
     *
     * @return array{problemas: string[], resumen: array, datos: array|null}
     */
    public function preparar(Contrato $contrato): array
    {
        $contrato->loadMissing(['cliente.eps', 'eps', 'plan', 'razonSocial', 'tipoModalidad']);
        $cliente = $contrato->cliente;
        $rs      = $contrato->razonSocial;
        $eps     = $contrato->eps ?: $cliente?->eps;
        $cot     = (string) ($contrato->tipoModalidad?->tipo_cot ?? '');
        $ibc     = (int) round((float) ($contrato->ibc ?: $contrato->salario));
        $problemas = [];

        if ($contrato->estado !== 'vigente') {
            $problemas[] = 'El contrato no está vigente.';
        }
        if ($eps?->codigo !== self::CODIGO_EPS) {
            $problemas[] = 'La EPS del contrato no es Salud Total.';
        }
        if (! $contrato->plan?->incluye_eps) {
            $problemas[] = 'El plan del contrato no incluye EPS.';
        }
        if (! $rs || $rs->es_independiente) {
            $problemas[] = 'Solo se tramitan dependientes: los independientes entran al portal con su propio usuario.';
        }
        if (! in_array($cot, self::COTIZANTES_SOPORTADOS, true)) {
            $problemas[] = 'Por ahora solo se tramitan dependientes (cotizante 1); este contrato es '
                .($contrato->tipoModalidad?->tipo_modalidad ?? 'sin modalidad').' (cotizante '.($cot ?: '—').').';
        }
        if (! $cliente) {
            $problemas[] = 'El contrato no tiene cliente en BryNex.';
        } else {
            if (! isset(SaludTotalCliente::TIPOS_DOC[strtoupper((string) $cliente->tipo_doc)])) {
                $problemas[] = "Tipo de documento '{$cliente->tipo_doc}' sin equivalencia en Salud Total.";
            }
            if (! trim((string) $cliente->primer_apellido)) {
                $problemas[] = 'El cliente no tiene primer apellido para comparar con Salud Total.';
            }
        }
        if ($ibc <= 0) {
            $problemas[] = 'El contrato no tiene IBC ni salario.';
        }
        // Fuera de plazo no se puede radicar una novedad nueva, pero sí consultar y vincular una existente.
        $fueraDePlazo = null;
        if (! $contrato->fecha_ingreso) {
            $problemas[] = 'El contrato no tiene fecha de ingreso.';
        } elseif (abs(today()->diffInDays($contrato->fecha_ingreso, false)) > self::DIAS_FECHA) {
            $fueraDePlazo = 'Salud Total solo acepta novedades con fecha de ingreso a '.self::DIAS_FECHA.' días de hoy; la del contrato es '
                .$contrato->fecha_ingreso->format('d/m/Y').'. Una novedad nueva se tramita por asesor o PQR.';
        }

        $cred = $rs ? $this->credencial((string) $rs->nit) : ['error' => 'Sin razón social.'];
        if (isset($cred['error'])) {
            $problemas[] = $cred['error'];
        }

        $resumen = [
            'trabajador'   => trim(implode(' ', array_filter([$cliente?->primer_nombre, $cliente?->segundo_nombre, $cliente?->primer_apellido, $cliente?->segundo_apellido]))),
            'documento'    => trim(($cliente?->tipo_doc ?? '').' '.$contrato->cedula),
            'razon_social' => $rs?->razon_social,
            'nit'          => $rs?->nit,
            'eps'          => $eps?->nombre,
            'plan'         => $contrato->plan?->nombre,
            'ibc'          => $ibc,
            'fecha_inicio' => $contrato->fecha_ingreso?->format('d/m/Y'),
            'cotizante'    => $cot === '1' ? 'Dependiente' : ($cot ?: null),
            'fuera_de_plazo' => $fueraDePlazo,
        ];

        return ['problemas' => $problemas, 'resumen' => $resumen, 'datos' => $problemas ? null : [
            'tipo_doc'      => strtoupper((string) $cliente->tipo_doc),
            'documento'     => (string) $contrato->cedula,
            'fecha_ingreso' => $contrato->fecha_ingreso->toDateString(),
            'tipo_cotizante' => $cot,
            'ibc'           => $ibc,
        ]];
    }

    /**
     * Entra al portal sin registrar nada: nombre en Salud Total, si ya está
     * activo con la empresa y si ya hay una novedad radicada.
     */
    public function consultar(Contrato $contrato): array
    {
        $prep = $this->preparar($contrato);

        if ($prep['problemas']) {
            return ['ok' => false, 'problemas' => $prep['problemas'], 'resumen' => $prep['resumen']];
        }

        $st = $this->sesion((string) $contrato->razonSocial->nit);
        $d  = $prep['datos'];

        $persona  = $st->datosPersona($d['tipo_doc'], $d['documento']);
        $grupo    = $st->grupoFamiliar($d['tipo_doc'], $d['documento']);
        $titular  = collect($grupo)->first(fn ($g) => (string) ($g['BeneficiarioId'] ?? '') === $d['documento']);
        $novedad  = $this->novedadExistente($st, $d['documento'], $contrato->fecha_ingreso);

        return [
            'ok'               => true,
            'resumen'          => $prep['resumen'],
            'nombre_eps'       => $persona ? trim(implode(' ', array_filter([$persona['PrimerNombre'] ?? null, $persona['SegundoNombre'] ?? null, $persona['Apellido1'] ?? null, $persona['Apellido2'] ?? null]))) : null,
            'estado_eps'       => $persona ? trim((string) ($persona['estadoAfiliado'] ?? '')) : null,
            'apellido_coincide' => $persona ? $this->mismoApellido($contrato, (string) ($persona['Apellido1'] ?? '').' '.($persona['Apellido2'] ?? '')) : false,
            'activo_empresa'   => $titular ? [
                'estado' => $titular['EstadoGeneral'] ?? null,
                'contrato_vigente' => (bool) ($titular['TieneContratoVigente'] ?? false),
                'desde' => substr((string) ($titular['FechaAfiliacion'] ?? ''), 0, 10),
            ] : null,
            'novedad'          => $novedad,
        ];
    }

    /**
     * Registra la novedad y deja el radicado de EPS en trámite con el número y
     * el Formulario Único. Si ya hay una novedad o la persona ya está activa con
     * la empresa, no repite: actualiza el radicado.
     */
    public function registrar(Contrato $contrato, ?int $usuarioId): array
    {
        $prep = $this->preparar($contrato);

        if ($prep['problemas']) {
            throw new RuntimeException(implode(' ', $prep['problemas']));
        }

        $d        = $prep['datos'];
        $radicado = $this->radicadoEps($contrato);
        $st       = $this->sesion((string) $contrato->razonSocial->nit);

        // Idempotencia: el portal no impide radicar dos veces la misma persona.
        if ($existente = $this->novedadExistente($st, $d['documento'], $contrato->fecha_ingreso)) {
            $this->aplicarNovedad($st, $contrato, $radicado, $existente, $usuarioId, 'Ya tenía novedad en Salud Total');
            $this->bitacora($contrato, $radicado, 'existente', $existente['numero'], $d, $existente, null, $usuarioId);

            return ['ok' => true, 'ya_existia' => true, 'radicado' => $existente['numero'], 'estado_eps' => $existente['estado']];
        }

        $titular = collect($st->grupoFamiliar($d['tipo_doc'], $d['documento']))
            ->first(fn ($g) => (string) ($g['BeneficiarioId'] ?? '') === $d['documento']);

        if ($titular && ($titular['TieneContratoVigente'] ?? false) && str_starts_with(strtolower((string) ($titular['EstadoGeneral'] ?? '')), 'activo')) {
            $obs = 'Ya activo en Salud Total con '.$contrato->razonSocial->razon_social.' desde '.substr((string) $titular['FechaAfiliacion'], 0, 10)
                .' (contrato '.($titular['Contrato'] ?? '—').'). No se radicó novedad.';
            $this->marcarRadicado($radicado, null, Radicado::ESTADO_OK, null, $obs, $usuarioId);
            $this->bitacora($contrato, $radicado, 'existente', null, $d, $titular, null, $usuarioId);

            return ['ok' => true, 'ya_activo' => true, 'desde' => substr((string) $titular['FechaAfiliacion'], 0, 10)];
        }

        if ($prep['resumen']['fuera_de_plazo']) {
            throw new RuntimeException($prep['resumen']['fuera_de_plazo']);
        }

        $persona = $st->datosPersona($d['tipo_doc'], $d['documento']);

        if (! $persona) {
            throw new RuntimeException('Salud Total no encuentra a la persona con ese documento: no es un inicio de relación laboral sino una afiliación nueva o un traslado.');
        }
        if (! $this->mismoApellido($contrato, (string) ($persona['Apellido1'] ?? '').' '.($persona['Apellido2'] ?? ''))) {
            throw new RuntimeException('El nombre en Salud Total ('.trim(($persona['PrimerNombre'] ?? '').' '.($persona['Apellido1'] ?? '')).') no coincide con el de BryNex.');
        }

        $tipos = $st->tiposCotizante();
        if (! isset($tipos[$d['tipo_cotizante']])) {
            throw new RuntimeException("El tipo de cotizante {$d['tipo_cotizante']} no está en el formulario de Salud Total.");
        }

        try {
            $res = $st->registrarNovedad($d + [
                'tipo_doc_texto'       => $this->textoTipoDoc($d['tipo_doc']),
                'nombre1'              => trim((string) ($persona['PrimerNombre'] ?? '')),
                'nombre2'              => trim((string) ($persona['SegundoNombre'] ?? '')),
                'apellido1'            => trim((string) ($persona['Apellido1'] ?? '')),
                'apellido2'            => trim((string) ($persona['Apellido2'] ?? '')),
                'tipo_cotizante_texto' => $tipos[$d['tipo_cotizante']],
            ]);
        } catch (Throwable $e) {
            $this->bitacora($contrato, $radicado, 'fallida', null, $d, null, $e->getMessage(), $usuarioId);
            throw $e;
        }

        $ruta = $this->guardarPdf($contrato, $st->formularioPdf($res['numeroFormulario']));
        $obs  = sprintf('Novedad de inicio laboral radicada automáticamente en Salud Total el %s (formulario %s, dependiente, IBC %s, ingreso %s). Pendiente de que Salud Total la apruebe.',
            now()->format('d/m/Y'), $res['numeroFormulario'], number_format($d['ibc'], 0, ',', '.'), $contrato->fecha_ingreso->format('d/m/Y'));

        $this->marcarRadicado($radicado, $res['numeroFormulario'], Radicado::ESTADO_TRAMITE, $ruta, $obs, $usuarioId);
        $this->bitacora($contrato, $radicado, 'exitosa', $res['numeroFormulario'], $res['envio'], $res, null, $usuarioId, $ruta);

        return ['ok' => true, 'radicado' => $res['numeroFormulario'], 'pdf' => (bool) $ruta];
    }

    /**
     * La novedad más reciente de esa persona desde poco antes del ingreso.
     *
     * @return array{numero:string, estado:string, fecha_ingreso:string, certificado:bool, inconsistencia:bool}|null
     */
    public function novedadExistente(SaludTotalCliente $st, string $documento, ?CarbonInterface $fechaIngreso, ?array $lista = null): ?array
    {
        $desde = ($fechaIngreso ?? today())->copy()->subDays(45);
        $lista ??= $st->seguimiento($desde->toDateString(), today()->addDay()->toDateString());

        // La lista puede ser de toda la empresa y más larga: una novedad de un ingreso anterior no cuenta.
        $n = collect($lista)
            ->filter(fn ($x) => (string) ($x['BeneficiarioId'] ?? '') === $documento
                && substr((string) ($x['FechaIngreso'] ?? ''), 0, 10) >= $desde->toDateString())
            ->sortByDesc(fn ($x) => (int) ($x['NumeroFormulario'] ?? 0))
            ->first();

        return $n ? [
            'numero'         => (string) $n['NumeroFormulario'],
            'estado'         => trim((string) ($n['Estado'] ?? '')),
            'fecha_ingreso'  => substr((string) ($n['FechaIngreso'] ?? ''), 0, 10),
            'certificado'    => ($n['Certificado'] ?? '') === 'Descargar',
            'inconsistencia' => trim((string) ($n['Inconsistencia'] ?? '')) !== '',
        ] : null;
    }

    /**
     * Pone el radicado según el estado de la novedad: Aprobado → OK con el
     * certificado; En Validación → trámite con el Formulario Único; con
     * inconsistencias → error con el motivo.
     *
     * @return string la acción aplicada: ok | tramite | error
     */
    public function aplicarNovedad(SaludTotalCliente $st, Contrato $contrato, Radicado $radicado, array $n, ?int $usuarioId, string $prefijo = 'Salud Total'): string
    {
        $estado = strtolower(Str::ascii($n['estado']));

        if (str_contains($estado, 'aprobad')) {
            // El certificado reemplaza al formulario; sin certificado, el formulario solo si no hay PDF.
            $pdf = $n['certificado'] ? $st->certificado($n['numero']) : null;
            if (! $pdf && ! $radicado->ruta_pdf) {
                $pdf = $st->formularioPdf($n['numero']);
            }
            $this->marcarRadicado($radicado, $n['numero'], Radicado::ESTADO_OK, $this->guardarPdf($contrato, $pdf),
                "{$prefijo}: novedad {$n['numero']} APROBADA (ingreso {$n['fecha_ingreso']}).", $usuarioId);

            return 'ok';
        }

        if ($n['inconsistencia'] || str_contains($estado, 'rechaz')) {
            $motivos = $st->inconsistencias($n['numero']);
            $this->marcarRadicado($radicado, $n['numero'], Radicado::ESTADO_ERROR, null,
                "{$prefijo}: novedad {$n['numero']} con inconsistencias: ".($motivos ? implode('; ', $motivos) : $n['estado']).'.', $usuarioId);

            return 'error';
        }

        // Ya estaba así: no se repite la observación ni el movimiento en cada conciliación.
        if ($radicado->estado === Radicado::ESTADO_TRAMITE && (string) $radicado->numero_radicado === $n['numero'] && $radicado->ruta_pdf) {
            return 'tramite';
        }

        $this->marcarRadicado($radicado, $n['numero'], Radicado::ESTADO_TRAMITE,
            $radicado->ruta_pdf ? null : $this->guardarPdf($contrato, $st->formularioPdf($n['numero'])),
            "{$prefijo}: novedad {$n['numero']} en estado \"{$n['estado']}\" (ingreso {$n['fecha_ingreso']}).", $usuarioId);

        return 'tramite';
    }

    /** Abre sesión con la clave del módulo de claves; si la rechaza, la bloquea. */
    public function sesion(string $nit): SaludTotalCliente
    {
        $cred = $this->credencial($nit);

        if (isset($cred['error'])) {
            throw new RuntimeException($cred['error']);
        }

        try {
            $st = SaludTotalCliente::entrar($nit, $cred['usuario'], $cred['contrasena']);
        } catch (SaludTotalLoginException $e) {
            EpsClavePortal::rechazada($cred['empresa'], $cred['usuario'], $cred['contrasena'], $e->getMessage());
            Log::warning('Salud Total: login rechazado', ['nit' => $nit, 'error' => $e->getMessage()]);
            throw new RuntimeException('Salud Total rechazó la clave: '.$e->getMessage());
        }

        EpsClavePortal::exito($cred['empresa']);

        return $st;
    }

    public function credencial(string $nit): array
    {
        return EpsClavePortal::para(SaludTotalCliente::ENTIDAD, '%SALUD%TOTAL%', 'Salud Total', $nit);
    }

    public function marcarRadicado(Radicado $radicado, ?string $numero, string $nuevo, ?string $rutaPdf, string $observacion, ?int $usuarioId): void
    {
        DB::transaction(function () use ($radicado, $numero, $nuevo, $rutaPdf, $observacion, $usuarioId) {
            $r = Radicado::whereKey($radicado->id)->lockForUpdate()->first();
            $anterior = $r->estado;

            // Nunca se retrocede un radicado ya cerrado.
            if ($anterior === Radicado::ESTADO_OK) {
                $nuevo = Radicado::ESTADO_OK;
            }

            $r->update([
                'estado'               => $nuevo,
                'numero_radicado'      => $numero ?? $r->numero_radicado,
                'canal_envio'          => 'portal',
                'user_id'              => $usuarioId,
                'fecha_inicio_tramite' => $r->fecha_inicio_tramite ?? now(),
                'fecha_confirmacion'   => $nuevo === Radicado::ESTADO_OK ? ($r->fecha_confirmacion ?? now()) : $r->fecha_confirmacion,
                'ruta_pdf'             => $rutaPdf ?? $r->ruta_pdf,
                'observacion'          => trim(($r->observacion ? $r->observacion.' | ' : '').$observacion),
            ]);

            RadicadoMovimiento::create([
                'radicado_id'     => $r->id,
                'contrato_id'     => $r->contrato_id,
                'tipo_proceso'    => 'afiliacion',
                'entidad'         => Radicado::TIPO_EPS,
                'user_id'         => $usuarioId,
                'estado_anterior' => $anterior,
                'estado_nuevo'    => $nuevo,
                'observacion'     => $observacion,
            ]);
        });

        $radicado->refresh();
    }

    public function guardarPdf(Contrato $contrato, ?string $binario): ?string
    {
        if (! $binario || ! str_starts_with($binario, '%PDF')) {
            return null;
        }

        $ruta = "radicados/{$contrato->aliado_id}/{$contrato->id}/{$contrato->cedula}/eps_".now()->format('Ymd_His').'_'.Str::random(4).'.pdf';
        Storage::disk('local')->put($ruta, $binario);

        return $ruta;
    }

    public function radicadoEps(Contrato $contrato): Radicado
    {
        return Radicado::firstOrCreate(
            ['contrato_id' => $contrato->id, 'tipo' => Radicado::TIPO_EPS],
            ['aliado_id' => $contrato->aliado_id, 'estado' => Radicado::ESTADO_PENDIENTE]
        );
    }

    private function bitacora(Contrato $contrato, Radicado $radicado, string $estado, ?string $numero, array $payload, ?array $respuesta, ?string $error, ?int $usuarioId, ?string $ruta = null): void
    {
        EpsAfiliacion::create([
            'aliado_id'       => $contrato->aliado_id,
            'contrato_id'     => $contrato->id,
            'radicado_id'     => $radicado->id,
            'entidad'         => SaludTotalCliente::ENTIDAD,
            'operacion'       => 'inicio_laboral',
            'estado'          => $estado,
            'numero_radicado' => $numero,
            'payload'         => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'respuesta'       => $respuesta ? json_encode($respuesta, JSON_UNESCAPED_UNICODE) : null,
            'mensaje_error'   => $error ? mb_substr($error, 0, 500) : null,
            'ruta_pdf'        => $ruta,
            'usuario_id'      => $usuarioId,
        ]);
    }

    private function mismoApellido(Contrato $contrato, string $apellidosEps): bool
    {
        $norm = fn ($s) => trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii((string) $s))));
        $apellido = $norm($contrato->cliente?->primer_apellido);

        return $apellido !== '' && str_contains($norm($apellidosEps), $apellido);
    }

    private function textoTipoDoc(string $tipo): string
    {
        return [
            'CC' => 'CEDULA DE CIUDADANIA', 'CE' => 'CEDULA DE EXTRANJERIA', 'PA' => 'PASAPORTE', 'PP' => 'PASAPORTE',
            'PT' => 'PERMISO POR PROTECCION TEMPORAL', 'PPT' => 'PERMISO POR PROTECCION TEMPORAL',
            'TI' => 'TARJETA DE IDENTIDAD', 'SC' => 'SALVOCONDUCTO', 'PC' => 'PEP-TUTOR',
        ][strtoupper($tipo)] ?? strtoupper($tipo);
    }
}
