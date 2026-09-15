<?php

namespace App\Services\Sos;

use App\Models\Contrato;
use App\Models\Radicado;
use App\Services\EpsPortal\EpsRadicado;
use App\Services\FormularioEpsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Novedad de inicio de relación laboral en S.O.S. desde el radicado de EPS.
 *
 * Mismo trámite que se hace a mano en la Oficina Virtual (primer caso: Elizabeth
 * Acosta, radicado 00276553, 14-sep-2026): se carga y valida el registro, se
 * guarda, y se adjunta el lado B del formulario firmado. S.O.S. lo deja
 * "Pendiente de aprobación" y responde en unas 24 horas.
 *
 * Trabaja sobre la sesión abierta con captcha asistido (`SosSesion`).
 */
class SosNovedadService
{
    public const CODIGO_EPS = 'EPS018';

    /** S.O.S. solo acepta fechas de ingreso a ±10 días de hoy. */
    public const DIAS_FECHA = 10;

    /** Tipo de documento de BryNex → id del formulario de S.O.S. y texto de la consulta. */
    private const TIPOS = [
        'CC' => [1, 'CC'], 'TI' => [2, 'TI'], 'CE' => [3, 'CE'], 'PA' => [4, 'PA'], 'PP' => [4, 'PA'],
        'RC' => [5, 'RC'], 'CD' => [11, 'CD'], 'CN' => [12, 'CN'], 'SC' => [13, 'SC'], 'PE' => [14, 'PE'],
        'PT' => [15, 'PT'], 'PPT' => [15, 'PT'], 'PC' => [16, 'PC'],
    ];

    public function __construct(private FormularioEpsService $formularios) {}

    /**
     * Revisa el contrato sin tocar el portal.
     *
     * @return array{problemas: string[], resumen: array, datos: array|null}
     */
    public function preparar(Contrato $contrato): array
    {
        $contrato->loadMissing(['cliente.eps', 'eps', 'plan', 'razonSocial', 'arl', 'pension']);
        $cliente = $contrato->cliente;
        $rs      = $contrato->razonSocial;
        $eps     = $contrato->eps ?: $cliente?->eps;
        $ibc     = (int) round((float) ($contrato->ibc ?: $contrato->salario));
        $tipo    = strtoupper((string) $cliente?->tipo_doc);
        $problemas = [];

        if ($contrato->estado !== 'vigente') {
            $problemas[] = 'El contrato no está vigente.';
        }
        if ($eps?->codigo !== self::CODIGO_EPS) {
            $problemas[] = 'La EPS del contrato no es S.O.S.';
        }
        if (! $contrato->plan?->incluye_eps) {
            $problemas[] = 'El plan del contrato no incluye EPS.';
        }
        if (! $rs || $rs->es_independiente) {
            $problemas[] = 'Solo se tramitan dependientes: los independientes entran al portal con su propio usuario.';
        }
        if (! $cliente) {
            $problemas[] = 'El contrato no tiene cliente en BryNex.';
        } elseif (! isset(self::TIPOS[$tipo])) {
            $problemas[] = "Tipo de documento '{$cliente->tipo_doc}' sin equivalencia en S.O.S.";
        }
        if ($ibc <= 0) {
            $problemas[] = 'El contrato no tiene IBC ni salario.';
        }
        if (! $contrato->fecha_ingreso) {
            $problemas[] = 'El contrato no tiene fecha de ingreso.';
        }

        $cred = $rs ? SosSesion::credencial((string) $rs->nit) : ['error' => 'Sin razón social.'];
        if (isset($cred['error'])) {
            $problemas[] = $cred['error'];
        }

        [$minimo, $maximo] = $this->ventana();
        $enPlazo = $contrato->fecha_ingreso && $contrato->fecha_ingreso->between($minimo, $maximo);

        $resumen = [
            'trabajador'     => trim(implode(' ', array_filter([$cliente?->primer_nombre, $cliente?->segundo_nombre, $cliente?->primer_apellido, $cliente?->segundo_apellido]))),
            'documento'      => trim($tipo.' '.$contrato->cedula),
            'razon_social'   => $rs?->razon_social,
            'nit'            => $rs?->nit,
            'eps'            => $eps?->nombre,
            'plan'           => $contrato->plan?->nombre,
            'ibc'            => $ibc,
            'arl'            => $this->arl($contrato),
            'afp'            => $this->afp($contrato),
            'fecha_ingreso'  => $contrato->fecha_ingreso?->toDateString(),
            'fecha_minima'   => $minimo->toDateString(),
            'fecha_maxima'   => $maximo->toDateString(),
            'en_plazo'       => $enPlazo,
        ];

        return ['problemas' => $problemas, 'resumen' => $resumen, 'datos' => $problemas ? null : [
            'tipo'      => $tipo,
            'tipo_id'   => self::TIPOS[$tipo][0],
            'tipo_sos'  => self::TIPOS[$tipo][1],
            'documento' => (string) $contrato->cedula,
            'ibc'       => $ibc,
        ]];
    }

    /** Busca en S.O.S. las novedades de la persona desde poco antes del ingreso. */
    public function consultar(Contrato $contrato): array
    {
        $prep = $this->preparar($contrato);
        if ($prep['problemas']) {
            return ['ok' => false, 'problemas' => $prep['problemas'], 'resumen' => $prep['resumen']];
        }

        $novedades = $this->novedades($contrato, $prep['datos']);

        return ['ok' => true, 'resumen' => $prep['resumen'], 'novedades' => $novedades, 'novedad' => $novedades[0] ?? null];
    }

    /**
     * Radica la novedad con la fecha elegida (dentro del plazo de S.O.S.), adjunta
     * el lado B y deja el radicado en trámite. Si ya existe, no repite: completa
     * el lado B si falta y actualiza el radicado.
     */
    public function registrar(Contrato $contrato, string $fechaReportar, ?int $usuarioId): array
    {
        $prep = $this->preparar($contrato);
        if ($prep['problemas']) {
            throw new RuntimeException(implode(' ', $prep['problemas']));
        }

        $d        = $prep['datos'];
        $nit      = (string) $contrato->razonSocial->nit;
        $radicado = EpsRadicado::deContrato($contrato);

        if ($existente = $this->novedades($contrato, $d)[0] ?? null) {
            return $this->aplicar($contrato, $radicado, $existente, $d, $usuarioId, 'Ya tenía novedad en S.O.S.');
        }

        $fecha = Carbon::parse($fechaReportar)->startOfDay();
        [$minimo, $maximo] = $this->ventana();
        if (! $fecha->between($minimo, $maximo)) {
            throw new RuntimeException('S.O.S. solo acepta fechas de ingreso entre '.$minimo->format('d/m/Y').' y '.$maximo->format('d/m/Y').'.');
        }

        $envio = [
            'tipoId' => $d['tipo_id'], 'documento' => $d['documento'], 'ibc' => $d['ibc'],
            'fecha' => $fecha->format('d/m/Y'), 'arl' => $this->arl($contrato), 'afp' => $this->afp($contrato), 'guardar' => true,
        ];

        $res = SosSesion::llamar($contrato->aliado_id, $nit, '/registrar', $envio);
        if (! ($res['ok'] ?? false)) {
            EpsRadicado::bitacora($contrato, $radicado, SosSesion::ENTIDAD, 'inicio_laboral', 'fallida', null, $envio, $res, $res['error'] ?? null, $usuarioId);
            throw new RuntimeException('S.O.S.: '.($res['error'] ?? 'no se pudo registrar.'));
        }

        $novedad = $this->novedades($contrato, $d, today())[0] ?? null;
        if (! $novedad) {
            EpsRadicado::bitacora($contrato, $radicado, SosSesion::ENTIDAD, 'inicio_laboral', 'exitosa', null, $envio, $res, 'Guardada, pero no apareció en la consulta', $usuarioId);
            throw new RuntimeException('S.O.S. guardó la novedad pero todavía no aparece en la consulta. Vuelve a pulsar Registrar en un minuto: se vincula sin repetirla.');
        }

        $nota = $fecha->isSameDay($contrato->fecha_ingreso) ? '' : ' Ingreso reportado '.$fecha->format('d/m/Y').' por el plazo de S.O.S.; ingreso real '.$contrato->fecha_ingreso->format('d/m/Y').'.';
        EpsRadicado::bitacora($contrato, $radicado, SosSesion::ENTIDAD, 'inicio_laboral', 'exitosa', $novedad['radicado'], $envio, $res, null, $usuarioId);

        return $this->aplicar($contrato, $radicado, $novedad, $d, $usuarioId, 'Novedad de inicio laboral radicada automáticamente en S.O.S. el '.now()->format('d/m/Y').'.'.$nota);
    }

    /** Deja el radicado según la novedad de S.O.S., adjuntando el lado B si falta. */
    private function aplicar(Contrato $contrato, Radicado $radicado, array $n, array $d, ?int $usuarioId, string $prefijo): array
    {
        $nit    = (string) $contrato->razonSocial->nit;
        $estado = Str::lower(Str::ascii($n['estado']));

        if (str_contains($estado, 'cara b')) {
            [$rutaPdf, $rutaImagen] = $this->ladoB($contrato);
            $adj = SosSesion::llamar($contrato->aliado_id, $nit, '/adjuntar', $this->filtro($contrato, $d) + ['archivo' => Storage::disk('local')->path($rutaImagen)]);
            if (! ($adj['ok'] ?? false)) {
                EpsRadicado::marcar($radicado, $n['radicado'], Radicado::ESTADO_TRAMITE, $rutaPdf, "{$prefijo} Radicado S.O.S. {$n['radicado']}: falta adjuntar el lado B (".($adj['error'] ?? $adj['estado'] ?? 'sin detalle').').', $usuarioId);
                throw new RuntimeException("S.O.S. radicó {$n['radicado']} pero no se pudo adjuntar el lado B: ".($adj['error'] ?? 'estado '.($adj['estado'] ?? '—')).'. Plazo: 48 horas.');
            }
            EpsRadicado::marcar($radicado, $n['radicado'], Radicado::ESTADO_TRAMITE, $rutaPdf, "{$prefijo} Radicado S.O.S. {$n['radicado']}, lado B adjuntado; estado: {$adj['estado']}.", $usuarioId);

            return ['ok' => true, 'radicado' => $n['radicado'], 'estado_eps' => $adj['estado'], 'lado_b' => true];
        }

        if (str_contains($estado, 'aprobado') && ! str_contains($estado, 'no aprobado')) {
            $cert = SosSesion::llamar($contrato->aliado_id, $nit, '/certificado', $this->filtro($contrato, $d));
            $ruta = ($cert['ok'] ?? false) ? EpsRadicado::guardarPdf($contrato, base64_decode($cert['pdf'])) : null;
            EpsRadicado::marcar($radicado, $n['radicado'], Radicado::ESTADO_OK, $ruta, "{$prefijo} Radicado S.O.S. {$n['radicado']} APROBADO.", $usuarioId);

            return ['ok' => true, 'radicado' => $n['radicado'], 'estado_eps' => $n['estado'], 'certificado' => (bool) $ruta];
        }

        if (preg_match('/no aprobado|incorrecto|declinado/', $estado)) {
            EpsRadicado::marcar($radicado, $n['radicado'], Radicado::ESTADO_ERROR, null, "{$prefijo} Radicado S.O.S. {$n['radicado']}: {$n['estado']}".($n['causal'] ? " ({$n['causal']})" : '').'. Enviar formulario completo y carta de derechos.', $usuarioId);

            return ['ok' => true, 'radicado' => $n['radicado'], 'estado_eps' => $n['estado'], 'rechazada' => true];
        }

        EpsRadicado::marcar($radicado, $n['radicado'], Radicado::ESTADO_TRAMITE, null, "{$prefijo} Radicado S.O.S. {$n['radicado']}: {$n['estado']}.", $usuarioId);

        return ['ok' => true, 'radicado' => $n['radicado'], 'estado_eps' => $n['estado']];
    }

    /** Novedades de la persona en S.O.S., la más reciente primero. */
    private function novedades(Contrato $contrato, array $d, ?CarbonInterface $desde = null): array
    {
        $res = SosSesion::llamar($contrato->aliado_id, (string) $contrato->razonSocial->nit, '/consultar', $this->filtro($contrato, $d, $desde));

        return collect($res['filas'] ?? [])->sortByDesc('radicado')->values()->all();
    }

    private function filtro(Contrato $contrato, array $d, ?CarbonInterface $desde = null): array
    {
        $desde ??= ($contrato->fecha_ingreso ?? today())->copy()->subDays(45);

        return ['tipo' => $d['tipo_sos'], 'documento' => $d['documento'], 'desde' => $desde->format('d/m/Y'), 'hasta' => today()->format('d/m/Y')];
    }

    /**
     * Formulario de EPS del contrato (con la firma) y su página 2 —el lado B— en
     * PNG gris, que es lo que pide S.O.S. y pesa poco.
     *
     * @return array{0: string, 1: string} rutas en el disco local
     */
    private function ladoB(Contrato $contrato): array
    {
        $contrato->loadMissing(['cliente.municipio', 'cliente.departamento', 'cliente.beneficiarios', 'razonSocial', 'eps', 'arl', 'pension']);
        $rutaPdf = EpsRadicado::guardarPdf($contrato, $this->formularios->generar($contrato, false, []), 'eps_formulario_sos');
        if (! $rutaPdf) {
            throw new RuntimeException('No se pudo generar el formulario de EPS del contrato.');
        }

        $rutaImagen = preg_replace('/\.pdf$/', '_lado_b.png', $rutaPdf);
        $disco = Storage::disk('local');
        exec(sprintf(
            '%s -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pnggray -r120 -dFirstPage=2 -dLastPage=2 -o %s %s 2>&1',
            is_executable('/usr/bin/gs') ? '/usr/bin/gs' : 'gs',
            escapeshellarg($disco->path($rutaImagen)),
            escapeshellarg($disco->path($rutaPdf))
        ), $salida, $codigo);

        if ($codigo !== 0 || ! $disco->exists($rutaImagen)) {
            throw new RuntimeException('No se pudo convertir el lado B a imagen: '.implode(' ', $salida));
        }

        return [$rutaPdf, $rutaImagen];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function ventana(): array
    {
        return [today()->subDays(self::DIAS_FECHA), today()->addDays(self::DIAS_FECHA)];
    }

    private function arl(Contrato $contrato): string
    {
        return mb_strtoupper(trim((string) ($contrato->arl?->nombre_arl ?: $contrato->arl?->razon_social))) ?: 'NO APLICA';
    }

    private function afp(Contrato $contrato): string
    {
        return mb_strtoupper(trim((string) $contrato->pension?->razon_social)) ?: 'NO APLICA';
    }
}
