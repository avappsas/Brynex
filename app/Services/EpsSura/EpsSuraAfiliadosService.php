<?php

namespace App\Services\EpsSura;

use App\Models\ArlCredencial;
use App\Models\Contrato;
use App\Models\RazonSocial;
use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Cruza los cotizantes que EPS SURA tiene en una empresa contra los contratos
 * de BryNex de todos los aliados con ese NIT.
 *
 * Nació del caso de ELITES CREACIONES (sep-2026): a trabajadores de Gestión ARL
 * —que solo compran la ARL— les quedó activa la EPS SURA con la empresa, uno de
 * ellos con el contrato ya retirado. Nadie la tramitó; aparecieron con fecha de
 * ingreso igual al inicio de su cobertura ARL. Si no se depuran, EPS espera
 * aportes de la empresa por gente que no los paga.
 *
 * Solo lee en Sura. La depuración se pide a la EPS: el portal no permite anular,
 * y el retiro solo acepta fechas desde el primer día del mes anterior.
 */
class EpsSuraAfiliadosService
{
    /** Login más recorrer el informe de a 10: una empresa grande pasa del minuto. */
    private const TIMEOUT_SEGUNDOS = 300;

    /**
     * @return array{nit:string, empresa:?string, en_eps:int, sobran:array, faltan:array}
     */
    public function conciliar(string $nit): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $razones = RazonSocial::where('nit', $nit)->get(['id', 'aliado_id', 'arl_poliza', 'razon_social']);

        if ($razones->isEmpty()) {
            throw new RuntimeException("No hay razón social con NIT {$nit}.");
        }

        $sura     = $this->afiliadosEnEps($nit, $razones);
        $porDoc   = $sura->keyBy(fn ($a) => self::normalizar($a['numero']));
        $contratos = Contrato::whereIn('razon_social_id', $razones->pluck('id'))
            ->with(['plan:id,nombre,incluye_eps', 'tipoModalidad:id,tipo_modalidad', 'aliado:id,nombre', 'eps:id,nombre,codigo', 'cliente.eps:id,nombre,codigo'])
            ->get(['id', 'aliado_id', 'cedula', 'estado', 'plan_id', 'tipo_modalidad_id', 'eps_id', 'fecha_ingreso', 'fecha_retiro', 'razon_social_id'])
            ->groupBy(fn ($c) => self::normalizar((string) $c->cedula));

        $sobran = [];

        foreach ($porDoc as $doc => $a) {
            // El vigente manda; si no hay, el más reciente dice por qué ya no.
            $c = ($contratos->get($doc) ?? collect())
                ->sortByDesc(fn ($x) => [$x->estado === 'vigente' ? 1 : 0, $x->id])
                ->first();

            $situacion = $this->situacion($c);

            if ($situacion === null) {
                continue; // vigente, con EPS en el plan y EPS SURA: está donde debe
            }

            $sobran[] = [
                'documento'     => $a['tipo'].' '.$a['numero'],
                'nombre'        => trim($a['nombres'].' '.$a['apellido1'].' '.$a['apellido2']),
                'ingreso_eps'   => $a['fecha_ingreso'],
                'tipo_afiliado' => $a['tipo_afiliado'],
                'parentesco'    => $a['parentesco'],
                'estado_eps'    => $a['estado'],
                'motivo'        => $situacion['motivo'],
                'grave'         => $situacion['grave'],
                'contrato_id'   => $c?->id,
                'plan'          => $c?->plan?->nombre,
                'modalidad'     => $c?->tipoModalidad?->tipo_modalidad,
                'aliado'        => $c?->aliado?->nombre,
            ];
        }

        // Los graves primero: son los que generan cobros a la empresa.
        usort($sobran, fn ($x, $y) => [$y['grave'], $x['nombre']] <=> [$x['grave'], $y['nombre']]);

        $faltan = $contratos->flatten()
            ->filter(fn ($c) => $c->estado === 'vigente'
                && $c->plan?->incluye_eps
                && $this->epsDe($c)?->codigo === EpsSuraConciliacionService::CODIGO_EPS
                && ! $porDoc->has(self::normalizar((string) $c->cedula)))
            ->map(fn ($c) => [
                'documento'   => (string) $c->cedula,
                'nombre'      => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
                'contrato_id' => $c->id,
                'plan'        => $c->plan?->nombre,
                'desde'       => $c->fecha_ingreso?->format('d/m/Y'),
                'aliado'      => $c->aliado?->nombre,
            ])
            ->values()->all();

        return [
            'nit'     => $nit,
            'empresa' => $razones->first()->razon_social,
            'en_eps'  => $sura->count(),
            'sobran'  => $sobran,
            'faltan'  => $faltan,
        ];
    }

    /**
     * Por qué alguien que está en EPS SURA con la empresa no debería estarlo, o
     * null si todo cuadra.
     *
     * @return array{motivo:string, grave:bool}|null
     */
    private function situacion(?Contrato $c): ?array
    {
        if (! $c) {
            return ['motivo' => 'No tiene contrato con esta empresa en BryNex', 'grave' => true];
        }

        if ($c->estado !== 'vigente') {
            return [
                'motivo' => 'Contrato '.$c->estado.($c->fecha_retiro ? ' el '.$c->fecha_retiro->format('d/m/Y') : '').' en BryNex',
                'grave'  => true,
            ];
        }

        if (! $c->plan?->incluye_eps) {
            return [
                'motivo' => 'Vigente con plan '.($c->plan?->nombre ?? 'sin plan')
                    .($c->tipoModalidad ? ' ('.$c->tipoModalidad->tipo_modalidad.')' : '').', que no incluye EPS',
                'grave'  => true,
            ];
        }

        $eps = $this->epsDe($c);

        if ($eps?->codigo !== EpsSuraConciliacionService::CODIGO_EPS) {
            return ['motivo' => 'Vigente, pero en BryNex su EPS es '.($eps?->nombre ?? 'ninguna'), 'grave' => false];
        }

        return null;
    }

    /** La EPS efectiva: la del contrato, o la del cliente si el contrato no la tiene. */
    private function epsDe(Contrato $c)
    {
        return $c->eps ?: $c->cliente?->eps;
    }

    /**
     * Los cotizantes de la empresa en EPS SURA, según el informe del portal.
     *
     * @return Collection<int, array>
     */
    public function afiliadosEnEps(string $nit, Collection $razones): Collection
    {
        $credencial = $this->credencial($nit, $razones);

        $resultado = Process::path(base_path())
            ->timeout(self::TIMEOUT_SEGUNDOS)
            ->input(json_encode([
                'tipoDocumento' => $credencial->tipo_documento,
                'usuario'       => $credencial->usuario,
                'contrasena'    => $credencial->contrasena,
                'nitEmpresa'    => $nit,
            ], JSON_UNESCAPED_UNICODE))
            ->run(ArlSuraSesionService::binarioNode().' scripts/eps-sura-afiliados.mjs');

        $salida = json_decode(trim($resultado->output()), true) ?: [];

        if (! ($salida['ok'] ?? false)) {
            $error = $salida['error'] ?? (trim($resultado->errorOutput()) ?: 'El proceso del portal no devolvió respuesta.');

            if (($salida['paso'] ?? null) === 'login') {
                $credencial->update(['ultimo_error' => mb_substr($error, 0, 300)]);
            }
            Log::warning('EPS SURA: no se pudo leer el informe de afiliados', ['nit' => $nit, 'paso' => $salida['paso'] ?? null, 'error' => $error]);

            throw new RuntimeException("No se pudo leer el informe de EPS SURA: {$error}");
        }

        return collect($salida['afiliados'] ?? []);
    }

    /**
     * Solo usuarios registrados: las claves del módulo de claves suelen ser
     * relleno, y reintentarlas termina bloqueando al usuario en Sura.
     */
    private function credencial(string $nit, Collection $razones): ArlCredencial
    {
        foreach ($razones as $rs) {
            $credencial = ArlSuraSesionService::credencialPara((int) $rs->aliado_id, (string) $rs->arl_poliza, $nit);

            if ($credencial?->exists) {
                return $credencial;
            }
        }

        throw new RuntimeException('Esta empresa no tiene usuario del portal de Sura registrado en BryNex.');
    }

    private static function normalizar(string $documento): string
    {
        return ltrim(preg_replace('/\D/', '', $documento), '0');
    }
}
