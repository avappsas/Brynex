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
                'pdf'        => ! $simular,
                'documentos' => $radicados->map(fn (Radicado $r) => [
                    'numero' => (string) $r->contrato->cedula,
                    // Un reingreso radicado un poco antes del ingreso también cuenta.
                    'desde'  => $r->contrato->fecha_ingreso?->copy()->subDays(45)->toDateString(),
                ])->values()->all(),
            ]);

            if (! ($salida['ok'] ?? false)) {
                foreach ($radicados as $r) {
                    $detalle[] = $this->fila($r, 'error', $salida['error'] ?? 'Nueva EPS no respondió.');
                }
                $avisar("{$empresa}: {$salida['error']}", $detalle);

                continue;
            }

            $porDocumento = collect($salida['resultados'] ?? [])->keyBy('numero');

            foreach ($radicados as $r) {
                $res = $porDocumento->get(preg_replace('/\D/', '', (string) $r->contrato->cedula));

                if (! ($res['encontrado'] ?? false)) {
                    $detalle[] = $this->fila($r, 'falta', 'No tiene reingreso en Nueva EPS: falta radicarlo.');

                    continue;
                }

                $procesado = strtoupper((string) $res['estado']) === 'PROCESADO';
                $mensaje   = "Nueva EPS: radicado {$res['radicado']} del {$res['fecha_radicacion']} ({$res['estado']}).";

                if ($simular) {
                    $detalle[] = $this->fila($r, $procesado ? 'cerraria' : 'tramite', $mensaje);

                    continue;
                }

                // El certificado solo si el radicado no tiene uno ya.
                $ruta = $r->ruta_pdf ? null : $this->reingreso->guardarPdf($r->contrato, $res['pdf'] ?? null);

                $this->reingreso->marcarRadicado($r, (string) $res['radicado'], (string) $res['estado'], $ruta,
                    "Conciliación con Nueva EPS: radicado {$res['radicado']} del {$res['fecha_radicacion']}, estado {$res['estado']}.",
                    $usuarioId);

                $detalle[] = $this->fila($r, $procesado ? 'cerrado' : 'tramite', $mensaje.($ruta ? ' Certificado adjunto.' : ''));
            }

            $avisar("{$empresa}: listo.", $detalle);
        }

        $cuenta = collect($detalle)->countBy('accion');

        return [
            'total'    => $total,
            'cerrados' => $cuenta->get('cerrado', 0) + $cuenta->get('cerraria', 0),
            'tramite'  => $cuenta->get('tramite', 0),
            'faltan'   => $cuenta->get('falta', 0),
            'revisar'  => 0,
            'errores'  => $cuenta->get('error', 0),
            'simulado' => $simular,
            'detalle'  => $detalle,
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
