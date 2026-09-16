<?php

namespace App\Services\ArlColmena;

use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cruce nocturno contra el informe de vigentes de Colmena: los radicados de ARL
 * de contratos vigentes con ARL Colmena que ya aparecen en el informe de la
 * empresa pasan a OK confirmado (`confirmado_por = arl_colmena`).
 *
 * Es el gemelo del de EPS SURA y sigue las mismas reglas del usuario: se
 * excluyen los retirados y los ya confirmados, así que después de la primera
 * noche solo trabaja con lo nuevo. Solo lee en Colmena y solo confirma: quien no
 * aparece se queda como estaba.
 *
 * Un informe por empresa, no una consulta por persona: bajarlo cuesta lo mismo
 * para 1 que para 300.
 */
class ColmenaConfirmacionService
{
    /** ARL COLMENA en BryNex. */
    public const ARL_ID = 2;

    /** Estados que se cruzan. Traslado es otro trámite y no se toca. */
    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR, Radicado::ESTADO_OK];

    public function __construct(private ColmenaAfiliadosService $afiliados) {}

    /**
     * Radicados de ARL sin confirmar, de contratos vigentes con ARL Colmena.
     *
     * @return Collection<int, Radicado>
     */
    public function candidatos(?int $aliadoId = null, ?string $nit = null): Collection
    {
        $nit = $nit ? preg_replace('/\D/', '', $nit) : null;

        return Radicado::query()
            ->where('radicados.tipo', Radicado::TIPO_ARL)
            ->whereIn('radicados.estado', self::ESTADOS)
            ->whereNull('radicados.confirmado_por')
            ->when($aliadoId, fn ($q) => $q->where('radicados.aliado_id', $aliadoId))
            ->whereHas('contrato', fn ($c) => $c
                ->whereColumn('contratos.aliado_id', 'radicados.aliado_id')
                ->where('estado', 'vigente')
                ->whereDate('fecha_ingreso', '<=', today())
                ->where('arl_id', self::ARL_ID)
                ->whereHas('plan', fn ($p) => $p->where('incluye_arl', true))
                ->whereHas('razonSocial', fn ($rs) => $rs
                    ->whereNotNull('nit')
                    ->when($nit, fn ($q) => $q->where('nit', $nit))))
            ->with(['contrato.cliente', 'contrato.razonSocial'])
            ->orderBy('radicados.id')
            ->get();
    }

    /**
     * @param  callable|null  $avisar  fn(string $mensaje)
     * @return array{empresas:int, candidatos:int, confirmados:int, no_aparecen:int, revisar:int, errores:int, sin_clave:int, simulado:bool, detalle:array}
     */
    public function confirmar(?int $aliadoId = null, ?string $nit = null, bool $simular = false, ?callable $avisar = null): array
    {
        $avisar ??= fn () => null;
        $detalle = [];

        $porEmpresa = $this->candidatos($aliadoId, $nit)
            ->groupBy(fn (Radicado $r) => preg_replace('/\D/', '', (string) $r->contrato->razonSocial->nit));

        $total = $porEmpresa->flatten()->count();
        $avisar("{$total} radicados de ARL Colmena sin confirmar en {$porEmpresa->count()} empresas.");

        foreach ($porEmpresa as $nitEmpresa => $radicados) {
            $empresa = $radicados->first()->contrato->razonSocial->razon_social;

            // Sin clave en el módulo de claves no hay informe que bajar. No es
            // un fallo del cruce: es una empresa que falta por cargar.
            if (! ColmenaSesionService::credencialPara((string) $nitEmpresa)) {
                foreach ($radicados as $r) {
                    $detalle[] = $this->fila($r, 'sin_clave', 'La empresa no tiene clave de ARL Colmena en el módulo de claves.');
                }
                $avisar("{$empresa}: sin clave de Colmena, se omite.");

                continue;
            }

            $avisar("{$empresa}: bajando el informe de vigentes ({$radicados->count()} por cruzar)…");

            try {
                $vigentes = $this->afiliados->vigentes((string) $nitEmpresa);
            } catch (Throwable $e) {
                foreach ($radicados as $r) {
                    $detalle[] = $this->fila($r, 'error', $e->getMessage());
                }
                $avisar("{$empresa}: {$e->getMessage()}");

                continue;
            }

            foreach ($radicados as $r) {
                $detalle[] = $this->cruzar(
                    $r,
                    $vigentes->get(ColmenaAfiliadosService::documento((string) $r->contrato->cedula)),
                    $simular
                );
            }

            $avisar("{$empresa}: listo ({$vigentes->count()} vigentes en Colmena).");
        }

        $cuenta = collect($detalle)->countBy('accion');

        return [
            'empresas' => $porEmpresa->count(),
            'candidatos' => $total,
            'confirmados' => $cuenta->get('confirmado', 0) + $cuenta->get('confirmaria', 0),
            'no_aparecen' => $cuenta->get('no_aparece', 0),
            'revisar' => $cuenta->get('revisar', 0),
            'errores' => $cuenta->get('error', 0),
            'sin_clave' => $cuenta->get('sin_clave', 0),
            'simulado' => $simular,
            'detalle' => $detalle,
        ];
    }

    private function cruzar(Radicado $r, ?array $a, bool $simular): array
    {
        if (! $a) {
            return $this->fila($r, 'no_aparece', 'No está entre los vigentes de la empresa en ARL Colmena.');
        }

        // Protección contra cruzar la cédula con otra persona: el informe trae
        // el nombre completo empezando por los apellidos.
        $apellido = self::texto((string) $r->contrato->cliente?->primer_apellido);

        if ($apellido === '' || ! str_contains(self::texto($a['nombre']), $apellido)) {
            return $this->fila($r, 'revisar', "En Colmena figura como {$a['nombre']}, que no coincide con el apellido de BryNex.");
        }

        $observacion = sprintf(
            'Confirmado en ARL Colmena el %s: vigente en %s desde %s%s.',
            now()->format('d/m/Y'),
            $r->contrato->razonSocial->razon_social,
            $a['inicio'] ?? '—',
            $a['centro'] ? ' ('.$a['centro'].')' : ''
        );

        if ($simular) {
            return $this->fila($r, 'confirmaria', $observacion);
        }

        $hecho = DB::transaction(function () use ($r, $observacion) {
            $fresco = Radicado::whereKey($r->id)->lockForUpdate()->first();

            // Pudo cambiar a mano mientras se bajaba el informe.
            if (! $fresco || $fresco->confirmado_por || ! in_array($fresco->estado, self::ESTADOS, true)) {
                return false;
            }

            $anterior = $fresco->estado;
            $fresco->update([
                'estado' => Radicado::ESTADO_OK,
                'fecha_confirmacion' => $fresco->fecha_confirmacion ?? now(),
                'observacion' => trim(($fresco->observacion ? $fresco->observacion.' | ' : '').$observacion),
            ] + $fresco->datosConfirmacion('arl_colmena'));

            RadicadoMovimiento::create([
                'radicado_id' => $fresco->id,
                'contrato_id' => $fresco->contrato_id,
                'tipo_proceso' => 'afiliacion',
                'entidad' => Radicado::TIPO_ARL,
                'user_id' => null,
                'estado_anterior' => $anterior,
                'estado_nuevo' => Radicado::ESTADO_OK,
                'observacion' => $observacion,
            ]);

            return true;
        });

        return $hecho
            ? $this->fila($r, 'confirmado', $observacion)
            : $this->fila($r, 'revisar', 'El radicado cambió mientras se bajaba el informe; no se tocó.');
    }

    private function fila(Radicado $r, string $accion, string $mensaje): array
    {
        $c = $r->contrato;

        return [
            'radicado_id' => $r->id,
            'aliado_id' => $r->aliado_id,
            'contrato_id' => $c->id,
            'cedula' => (string) $c->cedula,
            'nombre' => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa' => $c->razonSocial?->razon_social,
            'estado_antes' => $r->estado,
            'accion' => $accion,
            'mensaje' => $mensaje,
        ];
    }

    private static function texto(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii($s))));
    }
}
