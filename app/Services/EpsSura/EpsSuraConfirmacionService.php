<?php

namespace App\Services\EpsSura;

use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use App\Models\RazonSocial;
use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cruce nocturno contra el informe de afiliados de EPS SURA: los radicados de
 * EPS de contratos vigentes con EPS SURA que la empresa ya tiene como cotizantes
 * con derecho pasan a OK confirmado (`confirmado_por = eps_sura`).
 *
 * A diferencia de la conciliación por cédula, baja UNA vez el informe completo
 * de cada empresa y cruza todo: pendientes, en trámite y también los OK que
 * alguien marcó a mano. Nunca vuelve a mirar los ya confirmados ni los contratos
 * retirados (decisión del usuario, 14-sep-2026), así que después de la primera
 * noche solo trabaja con lo nuevo.
 *
 * Solo lee en Sura. Solo confirma: quien no aparece se queda como estaba.
 */
class EpsSuraConfirmacionService
{
    /** Estados que se cruzan. Traslado es otro trámite y no se toca. */
    private const ESTADOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR, Radicado::ESTADO_OK];

    public function __construct(private EpsSuraAfiliadosService $afiliados) {}

    /**
     * Radicados de EPS sin confirmar, de contratos vigentes de dependientes cuya
     * EPS efectiva (la del contrato o, si no tiene, la del cliente) es EPS SURA.
     *
     * @return Collection<int, Radicado>
     */
    public function candidatos(?int $aliadoId = null, ?string $nit = null): Collection
    {
        $nit = $nit ? preg_replace('/\D/', '', $nit) : null;

        return Radicado::query()
            ->where('radicados.tipo', Radicado::TIPO_EPS)
            ->whereIn('radicados.estado', self::ESTADOS)
            ->whereNull('radicados.confirmado_por')
            ->when($aliadoId, fn ($q) => $q->where('radicados.aliado_id', $aliadoId))
            ->whereHas('contrato', fn ($c) => $c
                ->whereColumn('contratos.aliado_id', 'radicados.aliado_id')
                ->where('estado', 'vigente')
                ->whereDate('fecha_ingreso', '<=', today())
                ->whereHas('plan', fn ($p) => $p->where('incluye_eps', true))
                ->where(fn ($e) => $e
                    ->whereHas('eps', fn ($x) => $x->where('codigo', EpsSuraConciliacionService::CODIGO_EPS))
                    ->orWhere(fn ($sinEps) => $sinEps
                        ->whereNull('eps_id')
                        ->whereHas('cliente.eps', fn ($x) => $x->where('codigo', EpsSuraConciliacionService::CODIGO_EPS))))
                ->whereHas('razonSocial', fn ($rs) => $rs
                    ->where('es_independiente', false)
                    ->when($nit, fn ($q) => $q->where('nit', $nit))))
            ->with(['contrato.cliente', 'contrato.razonSocial'])
            ->orderBy('radicados.id')
            ->get();
    }

    /**
     * @param  callable|null  $avisar  fn(string $mensaje)
     * @return array{empresas:int, candidatos:int, confirmados:int, no_aparecen:int, revisar:int, errores:int, sin_usuario:int, simulado:bool, detalle:array}
     */
    public function confirmar(?int $aliadoId = null, ?string $nit = null, bool $simular = false, ?callable $avisar = null): array
    {
        $avisar ??= fn () => null;
        $detalle = [];

        $porEmpresa = $this->candidatos($aliadoId, $nit)
            ->groupBy(fn (Radicado $r) => preg_replace('/\D/', '', (string) $r->contrato->razonSocial->nit));

        $total = $porEmpresa->flatten()->count();
        $avisar("{$total} radicados de EPS SURA sin confirmar en {$porEmpresa->count()} empresas.");

        foreach ($porEmpresa as $nitEmpresa => $radicados) {
            $empresa = $radicados->first()->contrato->razonSocial->razon_social;
            $razones = RazonSocial::where('nit', $nitEmpresa)->get(['id', 'aliado_id', 'arl_poliza', 'razon_social']);

            // Solo empresas con usuario del portal registrado: probar claves del
            // módulo de claves en cada corrida es la forma de bloquear al usuario.
            $conUsuario = $razones->contains(fn ($rs) => ArlSuraSesionService::credencialPara((int) $rs->aliado_id, (string) $rs->arl_poliza, (string) $nitEmpresa)?->exists);

            if (! $conUsuario) {
                foreach ($radicados as $r) {
                    $detalle[] = $this->fila($r, 'sin_usuario', 'La empresa no tiene usuario del portal de Sura registrado.');
                }
                $avisar("{$empresa}: sin usuario del portal, se omite.");

                continue;
            }

            $avisar("{$empresa}: bajando el informe de afiliados ({$radicados->count()} por cruzar)…");

            try {
                $enSura = $this->afiliados->afiliadosEnEps((string) $nitEmpresa, $razones)
                    ->keyBy(fn ($a) => self::documento((string) $a['numero']));
            } catch (Throwable $e) {
                foreach ($radicados as $r) {
                    $detalle[] = $this->fila($r, 'error', $e->getMessage());
                }
                $avisar("{$empresa}: {$e->getMessage()}");

                continue;
            }

            foreach ($radicados as $r) {
                $detalle[] = $this->cruzar($r, $enSura->get(self::documento((string) $r->contrato->cedula)), $simular);
            }

            $avisar("{$empresa}: listo.");
        }

        $cuenta = collect($detalle)->countBy('accion');

        return [
            'empresas'    => $porEmpresa->count(),
            'candidatos'  => $total,
            'confirmados' => $cuenta->get('confirmado', 0) + $cuenta->get('confirmaria', 0),
            'no_aparecen' => $cuenta->get('no_aparece', 0),
            'revisar'     => $cuenta->get('revisar', 0),
            'errores'     => $cuenta->get('error', 0),
            // Empresas sin usuario del portal: se omiten cada noche, no son un fallo.
            'sin_usuario' => $cuenta->get('sin_usuario', 0),
            'simulado'    => $simular,
            'detalle'     => $detalle,
        ];
    }

    private function cruzar(Radicado $r, ?array $a, bool $simular): array
    {
        if (! $a) {
            return $this->fila($r, 'no_aparece', 'No está en el informe de afiliados de la empresa en EPS SURA.');
        }

        $estado = trim((string) ($a['estado'] ?? ''));
        $nombre = trim(($a['nombres'] ?? '').' '.($a['apellido1'] ?? '').' '.($a['apellido2'] ?? ''));

        // "NO TIENE DERECHO POR FIN DE VIGENCIA" también contiene "TIENE DERECHO".
        if (! preg_match('/^TIENE DERECHO/i', $estado)) {
            return $this->fila($r, 'revisar', 'En EPS SURA figura, pero: '.($estado ?: 'sin estado').'.');
        }

        // Protección contra cruzar la cédula con otra persona.
        $apellido = self::texto((string) $r->contrato->cliente?->primer_apellido);
        if ($apellido === '' || ! str_contains(self::texto($nombre), $apellido)) {
            return $this->fila($r, 'revisar', "En EPS SURA figura como {$nombre}, que no coincide con el apellido de BryNex.");
        }

        $observacion = sprintf('Confirmado en EPS SURA el %s: cotizante de %s con ingreso %s (%s).',
            now()->format('d/m/Y'), $r->contrato->razonSocial->razon_social, $a['fecha_ingreso'] ?? '—', Str::lower($estado));

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
                'estado'             => Radicado::ESTADO_OK,
                'fecha_confirmacion' => $fresco->fecha_confirmacion ?? now(),
                'observacion'        => trim(($fresco->observacion ? $fresco->observacion.' | ' : '').$observacion),
            ] + $fresco->datosConfirmacion('eps_sura'));

            RadicadoMovimiento::create([
                'radicado_id'     => $fresco->id,
                'contrato_id'     => $fresco->contrato_id,
                'tipo_proceso'    => 'afiliacion',
                'entidad'         => Radicado::TIPO_EPS,
                'user_id'         => null,
                'estado_anterior' => $anterior,
                'estado_nuevo'    => Radicado::ESTADO_OK,
                'observacion'     => $observacion,
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
            'radicado_id'  => $r->id,
            'aliado_id'    => $r->aliado_id,
            'contrato_id'  => $c->id,
            'cedula'       => (string) $c->cedula,
            'nombre'       => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa'      => $c->razonSocial?->razon_social,
            'estado_antes' => $r->estado,
            'accion'       => $accion,
            'mensaje'      => $mensaje,
        ];
    }

    private static function documento(string $numero): string
    {
        return ltrim(preg_replace('/\D/', '', $numero), '0');
    }

    private static function texto(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii($s))));
    }
}
