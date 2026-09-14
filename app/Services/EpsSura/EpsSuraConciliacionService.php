<?php

namespace App\Services\EpsSura;

use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Cierra los radicados de EPS SURA que siguen pendientes en BryNex pero que en
 * el portal ya están hechos.
 *
 * El 14-sep-2026 los cuatro pendientes de EPS SURA de ELITES CREACIONES ya eran
 * cotizantes vigentes en Sura: alguien los afilió y nadie marcó el radicado. Por
 * eso, antes de automatizar reingresos, conviene preguntarle al portal qué falta
 * de verdad. Esta conciliación solo LEE en Sura; lo único que escribe es el
 * radicado en BryNex, y solo cuando el portal confirma la vigencia con esa
 * empresa y el apellido coincide.
 *
 * Solo dependientes: los independientes entran al portal con su propio usuario
 * y por ahora no se gestionan desde aquí.
 */
class EpsSuraConciliacionService
{
    /** Código oficial de EPS SURA en la tabla `eps`. */
    public const CODIGO_EPS = 'EPS010';

    /** Estados del radicado que todavía esperan gestión. */
    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR];

    /** Cédulas por cada proceso de Chrome: el login cuesta, pero un proceso eterno se cae. */
    private const POR_LOTE = 20;

    /**
     * Los radicados de EPS SURA abiertos de un aliado, de contratos vigentes de
     * dependientes.
     *
     * @return Collection<int, Radicado>
     */
    public function pendientes(int $aliadoId, ?string $nit = null): Collection
    {
        $nit = $nit ? preg_replace('/\D/', '', $nit) : null;

        return Radicado::query()
            ->where('radicados.aliado_id', $aliadoId)
            ->where('radicados.tipo', Radicado::TIPO_EPS)
            ->whereIn('radicados.estado', self::ESTADOS)
            ->whereHas('contrato', fn ($c) => $c
                ->where('aliado_id', $aliadoId)
                ->where('estado', 'vigente')
                ->whereHas('eps', fn ($e) => $e->where('codigo', self::CODIGO_EPS))
                ->whereHas('razonSocial', fn ($rs) => $rs
                    ->where('es_independiente', false)
                    ->when($nit, fn ($q) => $q->where('nit', $nit))))
            ->with(['contrato.cliente', 'contrato.razonSocial'])
            ->orderBy('radicados.id')
            ->get();
    }

    /**
     * Consulta cada pendiente en el portal y cierra los que ya estén vigentes.
     *
     * @param  bool  $simular  Consulta pero no toca ningún radicado.
     * @param  callable|null  $progreso  fn(string $mensaje, array $parcial)
     * @return array{total:int, cerrados:int, faltan:int, revisar:int, errores:int, detalle:array}
     */
    public function conciliar(int $aliadoId, ?string $nit = null, bool $simular = false, ?int $usuarioId = null, ?callable $progreso = null): array
    {
        $avisar  = $progreso ?? fn () => null;
        $detalle = [];

        $porEmpresa = $this->pendientes($aliadoId, $nit)
            ->groupBy(fn (Radicado $r) => preg_replace('/\D/', '', (string) $r->contrato->razonSocial->nit));

        $total = $porEmpresa->flatten()->count();
        $avisar("{$total} radicados de EPS SURA por revisar en {$porEmpresa->count()} empresas.", $detalle);

        foreach ($porEmpresa as $nitEmpresa => $radicados) {
            $empresa = $radicados->first()->contrato->razonSocial->razon_social;
            $credencial = ArlSuraSesionService::credencialPara($aliadoId, '', (string) $nitEmpresa);

            if (! $credencial) {
                foreach ($radicados as $r) {
                    $detalle[] = $this->fila($r, 'error', 'No hay usuario del portal de Sura registrado para esta empresa.');
                }
                $avisar("{$empresa}: sin usuario del portal.", $detalle);

                continue;
            }

            foreach ($radicados->chunk(self::POR_LOTE) as $lote) {
                $avisar("{$empresa}: consultando {$lote->count()} en el portal…", $detalle);

                $salida = $this->consultarPortal($credencial, (string) $nitEmpresa, $lote);
                $porDocumento = collect($salida['resultados'] ?? [])->keyBy('numero');

                foreach ($lote as $r) {
                    $cedula = preg_replace('/\D/', '', (string) $r->contrato->cedula);
                    $res = $porDocumento->get($cedula);

                    if (! $res) {
                        $detalle[] = $this->fila($r, 'error', $salida['error'] ?? 'El portal no devolvió respuesta para esta cédula.');

                        continue;
                    }

                    $detalle[] = $this->resolver($r, $res, $simular, $usuarioId);
                }

                $avisar("{$empresa}: listo.", $detalle);
            }
        }

        $cuenta = collect($detalle)->countBy('accion');

        return [
            'total'    => $total,
            'cerrados' => $cuenta->get('cerrado', 0) + $cuenta->get('cerraria', 0),
            'faltan'   => $cuenta->get('falta', 0),
            'revisar'  => $cuenta->get('revisar', 0),
            'errores'  => $cuenta->get('error', 0),
            'simulado' => $simular,
            'detalle'  => $detalle,
        ];
    }

    /**
     * Decide qué hacer con un radicado según lo que respondió el portal.
     */
    private function resolver(Radicado $r, array $res, bool $simular, ?int $usuarioId): array
    {
        if (! ($res['encontrado'] ?? false)) {
            // "No existe como cotizante de la empresa" es la única respuesta
            // que prueba que falta el trámite; cualquier otra cosa es un error.
            return str_contains((string) ($res['mensaje'] ?? ''), 'no existe como cotizante')
                ? $this->fila($r, 'falta', 'No es cotizante de la empresa en EPS SURA: falta el trámite.')
                : $this->fila($r, 'error', $res['mensaje'] ?? 'Respuesta no reconocida del portal.');
        }

        $estado = trim((string) ($res['estado'] ?? ''));

        // "NO TIENE DERECHO POR FIN DE VIGENCIA" también contiene "TIENE DERECHO".
        if (! preg_match('/^TIENE DERECHO/i', $estado) || ($res['cotiza'] ?? null) === 'N') {
            return $this->fila($r, 'revisar', "En Sura figura, pero: {$estado}".(($res['cotiza'] ?? null) === 'N' ? ' (no cotiza)' : ''), $res);
        }

        if (self::normalizar((string) $r->contrato->cliente?->primer_apellido) === '') {
            return $this->fila($r, 'revisar', "Vigente en Sura como {$res['nombre']}, pero el contrato no tiene cliente en BryNex con qué comparar el nombre.", $res);
        }

        if (! $this->mismoApellido($r, (string) ($res['nombre'] ?? ''))) {
            return $this->fila($r, 'revisar', "El nombre en Sura ({$res['nombre']}) no coincide con BryNex.", $res);
        }

        $observacion = sprintf(
            'Ya vigente en EPS SURA con %s al %s (conciliación automática: %s%s).',
            $r->contrato->razonSocial->razon_social,
            now()->format('d/m/Y'),
            Str::lower($estado),
            ! empty($res['parentesco']) && Str::upper($res['parentesco']) !== 'TITULAR' ? ', en Sura figura como '.Str::lower($res['parentesco']) : ''
        );

        if ($simular) {
            return $this->fila($r, 'cerraria', $observacion, $res);
        }

        $cerrado = DB::transaction(function () use ($r, $observacion, $usuarioId) {
            $fresco = Radicado::whereKey($r->id)->lockForUpdate()->first();

            // Alguien pudo cerrarlo a mano mientras el portal respondía.
            if (! $fresco || ! in_array($fresco->estado, self::ESTADOS, true)) {
                return false;
            }

            $anterior = $fresco->estado;
            $fresco->update([
                'estado'             => Radicado::ESTADO_OK,
                'canal_envio'        => 'portal',
                'fecha_confirmacion' => now(),
                'user_id'            => $usuarioId,
                'observacion'        => trim(($fresco->observacion ? $fresco->observacion.' | ' : '').$observacion),
            ]);

            RadicadoMovimiento::create([
                'radicado_id'     => $fresco->id,
                'contrato_id'     => $fresco->contrato_id,
                'tipo_proceso'    => 'afiliacion',
                'entidad'         => Radicado::TIPO_EPS,
                'user_id'         => $usuarioId,
                'estado_anterior' => $anterior,
                'estado_nuevo'    => Radicado::ESTADO_OK,
                'observacion'     => $observacion,
            ]);

            return true;
        });

        return $cerrado
            ? $this->fila($r, 'cerrado', $observacion, $res)
            : $this->fila($r, 'revisar', 'El radicado cambió de estado mientras se consultaba; no se tocó.', $res);
    }

    /**
     * Corre el Chrome headless que consulta el portal.
     *
     * @return array{ok:bool, empresa?:string, resultados?:array, error?:string}
     */
    private function consultarPortal($credencial, string $nit, Collection $lote): array
    {
        $entrada = json_encode([
            'tipoDocumento' => $credencial->tipo_documento,
            'usuario'       => $credencial->usuario,
            'contrasena'    => $credencial->contrasena,
            'nitEmpresa'    => $nit,
            'documentos'    => $lote->map(fn (Radicado $r) => [
                'tipo'   => $r->contrato->cliente?->tipo_doc ?: 'CC',
                'numero' => (string) $r->contrato->cedula,
            ])->values()->all(),
        ], JSON_UNESCAPED_UNICODE);

        $resultado = Process::path(base_path())
            // Login ~40 s y unos 12 s por cédula; con holgura.
            ->timeout(180 + 25 * $lote->count())
            ->input($entrada)
            ->run(ArlSuraSesionService::binarioNode().' scripts/eps-sura-consultar.mjs');

        $salida = json_decode(trim($resultado->output()), true) ?: [];

        if (! ($salida['ok'] ?? false)) {
            // El mensaje nunca trae la clave: el script no la imprime.
            Log::warning('EPS SURA: la consulta del portal falló', [
                'nit'   => $nit,
                'paso'  => $salida['paso'] ?? null,
                'error' => $salida['error'] ?? trim($resultado->errorOutput()),
            ]);

            $salida['error'] ??= trim($resultado->errorOutput()) ?: 'El proceso del portal no devolvió respuesta.';
        }

        return $salida;
    }

    /** Protección contra cruzar la cédula con otra persona: el primer apellido de BryNex debe estar en el nombre de Sura. */
    private function mismoApellido(Radicado $r, string $nombreSura): bool
    {
        $apellido = self::normalizar((string) $r->contrato->cliente?->primer_apellido);

        return $apellido !== '' && str_contains(self::normalizar($nombreSura), $apellido);
    }

    private static function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii($texto))));
    }

    private function fila(Radicado $r, string $accion, string $mensaje, array $sura = []): array
    {
        $c = $r->contrato;

        return [
            'radicado_id' => $r->id,
            'contrato_id' => $c->id,
            'cedula'      => (string) $c->cedula,
            'nombre'      => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa'     => $c->razonSocial?->razon_social,
            'estado_antes' => $r->estado,
            'accion'      => $accion,
            'mensaje'     => $mensaje,
            'sura_estado' => $sura['estado'] ?? null,
        ];
    }
}
