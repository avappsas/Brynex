<?php

namespace App\Services\Sos;

use App\Models\PortalPeticion;
use App\Models\Radicado;
use App\Services\EpsPortal\EpsRadicado;
use App\Services\TareaAutomaticaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone al día los radicados de S.O.S. con lo que muestra su portal.
 *
 * S.O.S. no se puede consultar desde el servidor: su login pide reCAPTCHA. Así
 * que la persona abre la sesión en su propio Chrome, la extensión BryNex
 * Portales busca cada cédula en «Novedades → Consultas y Envío/Firma» y aquí se
 * interpreta lo que trajo.
 *
 * Lo que más importa no es cerrar los aprobados: es ver las **devueltas**. Una
 * novedad que S.O.S. no aprobó no avisa a nadie, y el trabajador se queda sin
 * EPS hasta que alguien mira el portal. Por eso cada devolución abre una tarea
 * con el motivo que da S.O.S.
 *
 * Solo lee en S.O.S.; lo único que escribe es el radicado y la tarea en BryNex.
 */
class SosConciliacionService
{
    public const ENTIDAD = 'S.O.S.';

    /** Código de S.O.S. en la tabla `eps`. */
    public const CODIGO_EPS = 'EPS018';

    /** Estados del radicado que todavía esperan gestión. */
    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR];

    private const PREFIJO_TAREA = 'sos:devuelta';

    public function __construct(private TareaAutomaticaService $tareas) {}

    /**
     * Los radicados de S.O.S. abiertos, de contratos vigentes.
     *
     * @param  array<int>  $aliados
     * @return Collection<int, Radicado>
     */
    public function pendientes(array $aliados, ?string $nit = null): Collection
    {
        $nit = $nit ? preg_replace('/\D/', '', $nit) : null;

        return Radicado::query()
            ->whereIn('radicados.aliado_id', $aliados)
            ->where('radicados.tipo', Radicado::TIPO_EPS)
            ->whereIn('radicados.estado', self::ESTADOS)
            ->whereHas('contrato', fn ($c) => $c
                ->whereIn('aliado_id', $aliados)
                ->where('estado', 'vigente')
                ->whereHas('eps', fn ($e) => $e->where('codigo', self::CODIGO_EPS))
                ->when($nit, fn ($q) => $q->whereHas('razonSocial', fn ($rs) => $rs->where('nit', $nit))))
            ->with(['contrato.cliente', 'contrato.razonSocial'])
            ->orderBy('radicados.id')
            ->get();
    }

    /**
     * Qué cédulas hay que buscar en el portal, para que la extensión sepa.
     *
     * @param  array<int>  $aliados
     * @return array{empresa:?string, desde:string, hasta:string, documentos:array<int, array{tipo:string, numero:string}>}
     */
    public function porConsultar(array $aliados, ?string $nit = null): array
    {
        $radicados = $this->pendientes($aliados, $nit);

        // La consulta de S.O.S. pide un rango de fechas. Se mira desde el
        // ingreso más viejo que haya pendiente, con un mes de margen: una
        // novedad se radica días después del ingreso.
        $masViejo = $radicados->min(fn (Radicado $r) => $r->contrato->fecha_ingreso?->timestamp) ?: now()->subMonths(6)->timestamp;

        return [
            'empresa' => $radicados->first()?->contrato->razonSocial?->razon_social,
            'desde' => Carbon::createFromTimestamp($masViejo)->subMonth()->format('d/m/Y'),
            'hasta' => now()->format('d/m/Y'),
            'documentos' => $radicados->map(fn (Radicado $r) => [
                'tipo' => Str::upper((string) ($r->contrato->cliente?->tipo_doc ?: 'CC')),
                'numero' => preg_replace('/\D/', '', (string) $r->contrato->cedula),
            ])->unique('numero')->values()->all(),
        ];
    }

    /**
     * Interpreta lo que la extensión trajo del portal.
     *
     * @param  array<int>  $aliados
     * @param  array  $resultados  cédula => filas de la consulta del portal
     * @return array{total:int, cerrados:int, tramite:int, faltan:int, revisar:int, errores:int, tareas:int, detalle:array}
     */
    public function conciliar(array $aliados, ?string $nit, array $resultados, bool $simular = false, ?int $usuarioId = null): array
    {
        $porDocumento = [];

        foreach ($resultados as $documento => $filas) {
            $porDocumento[ltrim(preg_replace('/\D/', '', (string) $documento), '0')] = is_array($filas) ? $filas : [];
        }

        $detalle = [];
        $tareas = 0;

        foreach ($this->pendientes($aliados, $nit) as $radicado) {
            $documento = ltrim(preg_replace('/\D/', '', (string) $radicado->contrato->cedula), '0');

            if (! array_key_exists($documento, $porDocumento)) {
                $detalle[] = $this->fila($radicado, 'error', 'La extensión no trajo respuesta del portal para esta cédula.');

                continue;
            }

            // La última novedad manda: si se radicó dos veces, la que cuenta es
            // la de ahora, no la que devolvieron el mes pasado.
            $novedad = $this->ultima($porDocumento[$documento]);

            if (! $novedad) {
                $detalle[] = $this->fila($radicado, 'falta', 'No hay ninguna novedad de esta cédula en S.O.S.: falta radicarla (🏥 Novedad S.O.S.).');

                continue;
            }

            [$estado, $accion, $mensaje] = $this->interpretar($novedad);

            if ($accion === 'error_portal') {
                $tareas += $this->abrirTarea($radicado, $novedad, $simular) ? 1 : 0;
            }

            $detalle[] = $this->aplicar($radicado, $novedad, $estado, $accion, $mensaje, $simular, $usuarioId);
        }

        $cuenta = collect($detalle)->countBy('accion');

        return [
            'total' => count($detalle),
            'cerrados' => $cuenta->get('cerrado', 0) + $cuenta->get('cerraria', 0),
            'tramite' => $cuenta->get('tramite', 0),
            'sin_cambio' => $cuenta->get('confirmado', 0),
            'faltan' => $cuenta->get('falta', 0),
            'revisar' => $cuenta->get('revisar', 0),
            'errores' => $cuenta->get('error', 0),
            'tareas' => $tareas,
            'detalle' => $detalle,
        ];
    }

    /**
     * La fila más reciente de las que trajo el portal.
     *
     * S.O.S. las da con la fecha de radicación en dd/mm/aaaa; a igual fecha
     * manda el número de radicado, que crece.
     */
    private function ultima(array $filas): ?array
    {
        return collect($filas)
            ->filter(fn ($f) => is_array($f) && trim((string) ($f['radicado'] ?? '')) !== '')
            ->sortByDesc(fn ($f) => [self::orden($f['fecha_radicacion'] ?? null), (int) preg_replace('/\D/', '', (string) $f['radicado'])])
            ->first();
    }

    /**
     * Qué dice el portal de esa novedad.
     *
     * Se usa el mismo criterio que al radicar una desde BryNex
     * (SosNovedadService): "Aprobado" cierra, "No aprobado" y sus parientes son
     * una devolución, "cara B" es un pendiente con plazo de 48 horas, y
     * cualquier otra cosa queda en trámite.
     *
     * @return array{0:string, 1:string, 2:string} estado del radicado, acción, mensaje
     */
    private function interpretar(array $novedad): array
    {
        $numero = trim((string) ($novedad['radicado'] ?? ''));
        $textoEstado = trim((string) ($novedad['estado'] ?? ''));
        $estado = Str::lower(Str::ascii($textoEstado));
        $causal = trim((string) ($novedad['causal'] ?? ''));

        if (str_contains($estado, 'cara b')) {
            return [Radicado::ESTADO_TRAMITE, 'tramite',
                "Radicado S.O.S. {$numero}: falta adjuntar el lado B (plazo 48 h). Se hace desde el radicado (🏥 Novedad S.O.S.)."];
        }

        if (preg_match('/no aprobad|incorrect|declinad|devuelt|rechaz/', $estado)) {
            return [Radicado::ESTADO_ERROR, 'error_portal',
                "S.O.S. devolvió el radicado {$numero} ({$textoEstado})"
                .($causal ? ". Motivo: {$causal}" : ' (el portal no dio motivo)').'. Se tramita por correo con el asesor.'];
        }

        if (str_contains($estado, 'aprobad')) {
            return [Radicado::ESTADO_OK, 'cerrado', "Radicado S.O.S. {$numero} APROBADO el ".($novedad['fecha_radicacion'] ?? '—').'.'];
        }

        return [Radicado::ESTADO_TRAMITE, 'tramite', "Radicado S.O.S. {$numero}: {$textoEstado}. S.O.S. responde en unas 24 horas."];
    }

    /** Deja el radicado como lo dejó el portal. */
    private function aplicar(Radicado $radicado, array $novedad, string $estado, string $accion, string $mensaje, bool $simular, ?int $usuarioId): array
    {
        $numero = trim((string) ($novedad['radicado'] ?? '')) ?: null;

        // Ya estaba así: no se toca, para no llenar la bitácora de líneas iguales.
        if ($radicado->estado === $estado && (string) $radicado->numero_radicado === (string) $numero) {
            return $this->fila($radicado, 'confirmado', $mensaje.' (ya estaba así en BryNex)');
        }

        if ($simular) {
            return $this->fila($radicado, match ($accion) {
                'cerrado' => 'cerraria',
                'error_portal' => 'error',
                default => $accion,
            }, $mensaje);
        }

        $observacion = 'S.O.S. (conciliación del portal el '.now()->format('d/m/Y').'): '.$mensaje;

        EpsRadicado::marcar($radicado, $numero, $estado, null, $observacion, $usuarioId);

        if ($estado === Radicado::ESTADO_OK) {
            $radicado->update($radicado->datosConfirmacion('eps_sos'));
        }

        return $this->fila($radicado, $accion === 'error_portal' ? 'error' : $accion, $mensaje);
    }

    /**
     * Una tarea por cada devolución: es el hallazgo que nadie ve.
     *
     * La llave lleva el número del radicado de S.O.S., así que una devolución
     * nueva abre tarea nueva aunque la anterior ya se hubiera cerrado.
     */
    private function abrirTarea(Radicado $radicado, array $novedad, bool $simular): bool
    {
        $contrato = $radicado->contrato;
        $numero = trim((string) ($novedad['radicado'] ?? '')) ?: 'sin-numero';
        $llave = self::PREFIJO_TAREA.':'.$contrato->cedula.':'.$numero;

        if ($this->tareas->activaPorLlave((int) $contrato->aliado_id, $llave)) {
            return false;
        }

        if ($simular) {
            return true;
        }

        $causal = trim((string) ($novedad['causal'] ?? ''));

        return (bool) $this->tareas->abrir([
            'aliado_id' => (int) $contrato->aliado_id,
            'tipo' => 'mora_eps',
            'cedula' => (string) $contrato->cedula,
            'contrato_id' => $contrato->id,
            'razon_social_id' => $contrato->razon_social_id,
            'entidad' => self::ENTIDAD,
            'numero_radicado' => $numero,
            'tarea' => 'S.O.S. devolvió la afiliación: volver a radicarla o enviarla por correo al asesor.',
            'observacion' => "S.O.S. no aprobó el radicado {$numero} (".trim((string) ($novedad['estado'] ?? 'devuelta')).')'
                .($causal ? ". Motivo del portal: {$causal}" : ' y el portal no dio motivo')
                .'. Mientras no se corrija, la persona no tiene EPS.',
            'llave_auto' => $llave,
        ]);
    }

    /** Los aliados que comparten esa razón social, para cuando entra alguien de BryNex. */
    public function aliadosDelNit(string $nit): array
    {
        return DB::table('razones_sociales')
            ->where('nit', preg_replace('/\D/', '', $nit))
            ->distinct()
            ->pluck('aliado_id')
            ->map(fn ($a) => (int) $a)
            ->all();
    }

    private function fila(Radicado $r, string $accion, string $mensaje): array
    {
        $c = $r->contrato;

        return [
            'radicado_id' => $r->id,
            'contrato_id' => $c->id,
            'cedula' => (string) $c->cedula,
            'nombre' => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa' => $c->razonSocial?->razon_social,
            'estado_antes' => $r->estado,
            'accion' => $accion,
            'mensaje' => $mensaje,
        ];
    }

    /** Cierra la petición que pedía entrar al portal, si había una. */
    public function cerrarPeticion(?int $usuarioId, array $resumen): void
    {
        PortalPeticion::abiertaDe('sos')?->atender($usuarioId, sprintf(
            '%d radicados revisados: %d cerrados, %d en trámite, %d por radicar, %d devueltos.',
            $resumen['total'] ?? 0, $resumen['cerrados'] ?? 0, $resumen['tramite'] ?? 0,
            $resumen['faltan'] ?? 0, $resumen['errores'] ?? 0,
        ));
    }

    /** '25/09/2026' → 20260925, y lo que no sea fecha a 0. */
    private static function orden(?string $fecha): int
    {
        return preg_match('#^(\d{2})/(\d{2})/(\d{4})#', trim((string) $fecha), $m) ? (int) ($m[3].$m[2].$m[1]) : 0;
    }
}
