<?php

namespace App\Services\NuevaEps;

use App\Models\Contrato;
use App\Models\Radicado;
use Illuminate\Support\Collection;

/**
 * Pone al día los radicados de EPS de Nueva EPS con lo que dice el portal.
 *
 * Nueva EPS radica el reingreso al momento y lo procesa días después, así que
 * el radicado de BryNex queda en trámite hasta que alguien vuelva a mirar. Esto
 * mira por todos: en una sola consulta por empresa trae sus reingresos y a cada
 * radicado abierto le pone el número, el estado (PROCESADO → ok) y el
 * certificado. También encuentra los que se radicaron a mano y nadie marcó
 * (Yolanda Martinez: procesada el 02-jun-2026, radicado en BryNex en trámite).
 *
 * Además de la corrida completa (`conciliar`), cada consulta o reingreso de una
 * persona revisa de paso los abiertos de su empresa con la misma sesión del
 * portal (`deLaEmpresa` + `aplicar`): quien trabaja una empresa la deja al día.
 *
 * Solo lee en Nueva EPS. Solo dependientes.
 */
class NuevaEpsConciliacionService
{
    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR];

    public function __construct(private NuevaEpsReingresoService $reingreso) {}

    /** @return Collection<int, Radicado> */
    public function pendientes(int $aliadoId, ?string $nit = null): Collection
    {
        $nit = $nit ? preg_replace('/\D/', '', $nit) : null;

        return Radicado::query()
            ->where('aliado_id', $aliadoId)
            ->where('tipo', Radicado::TIPO_EPS)
            ->whereIn('estado', self::ESTADOS)
            ->whereHas('contrato', fn ($c) => $c
                ->where('aliado_id', $aliadoId)
                ->where('estado', 'vigente')
                ->whereHas('eps', fn ($e) => $e->whereIn('codigo', NuevaEpsPortalService::CODIGOS_EPS))
                ->whereHas('razonSocial', fn ($rs) => $rs
                    ->where('es_independiente', false)
                    ->when($nit, fn ($q) => $q->where('nit', $nit))))
            ->with(['contrato.cliente', 'contrato.razonSocial'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Los radicados abiertos de la empresa del contrato, sin el suyo (ese lo
     * resuelve el propio reingreso).
     *
     * @return Collection<int, Radicado>
     */
    public function deLaEmpresa(Contrato $contrato): Collection
    {
        return $this->pendientes((int) $contrato->aliado_id, (string) $contrato->razonSocial?->nit)
            ->reject(fn (Radicado $r) => (int) $r->contrato_id === (int) $contrato->id)
            ->values();
    }

    /**
     * Lo que el script necesita para buscarlos: documento, desde cuándo cuenta un
     * reingreso y si hace falta el certificado (solo si el radicado no tiene).
     */
    public function documentos(Collection $radicados, bool $conPdf = true): array
    {
        return $radicados->map(fn (Radicado $r) => [
            'numero' => (string) $r->contrato->cedula,
            // Un reingreso radicado un poco antes del ingreso también cuenta.
            'desde'  => $r->contrato->fecha_ingreso?->copy()->subDays(45)->toDateString(),
            'pdf'    => $conPdf && ! $r->ruta_pdf,
        ])->values()->all();
    }

    /**
     * @param  callable|null  $progreso  fn(string $mensaje, array $parcial)
     */
    public function conciliar(int $aliadoId, ?string $nit = null, bool $simular = false, ?int $usuarioId = null, ?callable $progreso = null): array
    {
        $avisar  = $progreso ?? fn () => null;
        $detalle = [];

        $porEmpresa = $this->pendientes($aliadoId, $nit)
            ->groupBy(fn (Radicado $r) => preg_replace('/\D/', '', (string) $r->contrato->razonSocial->nit));

        $total = $porEmpresa->flatten()->count();
        $avisar("{$total} radicados de Nueva EPS por revisar en {$porEmpresa->count()} empresas.", $detalle);

        foreach ($porEmpresa as $nitEmpresa => $radicados) {
            $empresa = $radicados->first()->contrato->razonSocial->razon_social;
            $avisar("{$empresa}: consultando novedades en Nueva EPS…", $detalle);

            $salida = NuevaEpsPortalService::ejecutar((string) $nitEmpresa, [
                'modo'       => 'novedades',
                'documentos' => $this->documentos($radicados, ! $simular),
            ]);

            if (! ($salida['ok'] ?? false)) {
                foreach ($radicados as $r) {
                    $detalle[] = $this->fila($r, 'error', $salida['error'] ?? 'Nueva EPS no respondió.');
                }
                $avisar("{$empresa}: {$salida['error']}", $detalle);

                continue;
            }

            array_push($detalle, ...$this->aplicar($radicados, $salida['resultados'] ?? [], $simular, $usuarioId));
            $avisar("{$empresa}: listo.", $detalle);
        }

        return $this->resumen($detalle, $total, $simular);
    }

    /**
     * Aplica a cada radicado lo que el portal dijo de su documento. No escribe
     * nada si el radicado ya tenía ese número y estado: cada consulta de la
     * empresa pasa por aquí y llenaría de movimientos repetidos los que siguen
     * en trámite.
     *
     * @return array<int, array> filas de detalle
     */
    public function aplicar(Collection $radicados, array $resultados, bool $simular, ?int $usuarioId): array
    {
        $porDocumento = collect($resultados)->keyBy('numero');
        $detalle = [];

        foreach ($radicados as $r) {
            $res = $porDocumento->get(preg_replace('/\D/', '', (string) $r->contrato->cedula));

            if (! ($res['encontrado'] ?? false)) {
                $detalle[] = $this->fila($r, 'falta', 'No tiene reingreso en Nueva EPS: falta radicarlo.');

                continue;
            }

            $procesado = strtoupper((string) $res['estado']) === 'PROCESADO';
            $mensaje   = "Nueva EPS: radicado {$res['radicado']} del {$res['fecha_radicacion']} ({$res['estado']}).";
            $estadoNuevo = $procesado ? Radicado::ESTADO_OK : Radicado::ESTADO_TRAMITE;

            if ($simular) {
                $detalle[] = $this->fila($r, $procesado ? 'cerraria' : 'tramite', $mensaje);

                continue;
            }

            $sinCambio = (string) $r->numero_radicado === (string) $res['radicado']
                && $r->estado === $estadoNuevo
                && ($r->ruta_pdf || empty($res['pdf']));

            if ($sinCambio) {
                $detalle[] = $this->fila($r, 'sin_cambio', $mensaje);

                continue;
            }

            // El certificado solo si el radicado no tiene uno ya.
            $ruta = $r->ruta_pdf ? null : $this->reingreso->guardarPdf($r->contrato, $res['pdf'] ?? null);

            $this->reingreso->marcarRadicado($r, (string) $res['radicado'], (string) $res['estado'], $ruta,
                "Conciliación con Nueva EPS: radicado {$res['radicado']} del {$res['fecha_radicacion']}, estado {$res['estado']}.",
                $usuarioId);

            $detalle[] = $this->fila($r, $procesado ? 'cerrado' : 'tramite', $mensaje.($ruta ? ' Certificado adjunto.' : ''));
        }

        return $detalle;
    }

    public function resumen(array $detalle, int $total, bool $simular = false): array
    {
        $cuenta = collect($detalle)->countBy('accion');

        return [
            'total'      => $total,
            'cerrados'   => $cuenta->get('cerrado', 0) + $cuenta->get('cerraria', 0),
            // Los que ya estaban al día siguen en trámite: cuentan igual.
            'tramite'    => $cuenta->get('tramite', 0) + $cuenta->get('sin_cambio', 0),
            'faltan'     => $cuenta->get('falta', 0),
            'revisar'    => 0,
            'errores'    => $cuenta->get('error', 0),
            'simulado'   => $simular,
            'detalle'    => $detalle,
        ];
    }

    private function fila(Radicado $r, string $accion, string $mensaje): array
    {
        $c = $r->contrato;

        return [
            'radicado_id'  => $r->id,
            'contrato_id'  => $c->id,
            'cedula'       => (string) $c->cedula,
            'nombre'       => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa'      => $c->razonSocial?->razon_social,
            'estado_antes' => $r->estado,
            'accion'       => $accion,
            'mensaje'      => $mensaje,
        ];
    }
}
