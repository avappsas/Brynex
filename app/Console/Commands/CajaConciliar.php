<?php

namespace App\Console\Commands;

use App\Services\Caja\ComfandiCajaConciliacionService;
use App\Services\Caja\ComfandiSubsidiosHeadless;
use App\Services\Caja\ComfenalcoCajaConciliacionService;
use App\Services\Caja\ComfenalcoSubsidiosHeadless;
use App\Services\TareaAutomaticaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cruce nocturno con el listado de afiliados de la caja.
 *
 * Lo mismo que hace el botón de Afiliaciones, pero sin nadie delante: baja de
 * la caja quiénes figuran afiliados de esa empresa y pasa a **OK confirmado**
 * los radicados de caja de quienes aparecen. Hasta ahora esto solo ocurría
 * cuando alguien abría la pantalla, y por eso casi todos los radicados en OK
 * seguían sin confirmar.
 *
 * Sirve para las dos cajas: en Comfenalco el listado es una consulta, y en
 * Comfandi es la pantalla de Gestión de trabajadores leída entera. Del portal
 * de Comfandi no se leen los radicados —eso sigue siendo cosa de la extensión,
 * con la persona delante—, así que aquí solo se confirma a quien ya figura
 * afiliado, que es lo que pasa un radicado a OK confirmado.
 *
 *   php artisan caja:conciliar --nit=901904750 --simular
 */
class CajaConciliar extends Command
{
    protected $signature = 'caja:conciliar
                            {--caja=COMFENALCO : Qué caja se concilia}
                            {--nit= : Solo esta empresa}
                            {--simular : Consulta el portal pero no toca ningún radicado}';

    protected $description = 'Confirma los radicados de caja cruzando con el listado de afiliados del portal';

    public function handle(
        ComfenalcoSubsidiosHeadless $portal,
        ComfenalcoCajaConciliacionService $conciliacion,
        ComfandiSubsidiosHeadless $comfandi,
        ComfandiCajaConciliacionService $comfandiConciliacion,
    ): int {
        $caja = mb_strtoupper(trim((string) $this->option('caja')));
        $esComfandi = str_contains($caja, 'COMFANDI');

        if (! $esComfandi && ! str_contains($caja, 'COMFENALCO')) {
            $this->error("Todavía no hay conciliación automática para {$caja}.");

            return self::FAILURE;
        }

        $simular = (bool) $this->option('simular');
        $empresas = $this->empresas($caja);

        if (! $empresas) {
            $this->line('Ninguna empresa con afiliados en esa caja.');

            return self::SUCCESS;
        }

        foreach ($empresas as $nit => $cuantos) {
            if ($esComfandi) {
                $this->deComfandi($comfandi, $comfandiConciliacion, (string) $nit, (int) $cuantos, $simular);

                continue;
            }

            if (! $portal->credencial((string) $nit)) {
                $this->line("{$nit}: sin clave de la caja, no se puede consultar.");

                continue;
            }

            $this->line("{$nit}: {$cuantos} afiliado(s) en BryNex…");

            try {
                $leido = $portal->trabajadores((string) $nit);
            } catch (Throwable $e) {
                $leido = ['ok' => false, 'error' => $e->getMessage()];
            }

            if (! ($leido['ok'] ?? false)) {
                $this->warn('  ⚠️ '.($leido['error'] ?? 'sin detalle'));

                continue;
            }

            // La conciliación vale para todos los aliados que tengan la empresa:
            // ante la caja es una sola y el listado es el mismo.
            $aliados = $conciliacion->aliadosDelNit((string) $nit);

            try {
                $r = $conciliacion->conciliar($aliados, (string) $nit, $leido['filas'], $simular, TareaAutomaticaService::USUARIO_SISTEMA);
            } catch (Throwable $e) {
                $this->warn('  ⚠️ '.$e->getMessage());

                continue;
            }

            $this->info(sprintf('  ✅ %s: %d en la caja · %d confirmado(s)%s',
                $leido['empresa'] ?? $nit,
                $r['afiliados_caja'] ?? count($leido['filas']),
                $r['cerrados'] ?? 0,
                ($r['confirmados_ok'] ?? 0) ? " · {$r['confirmados_ok']} ya estaban en OK y quedan confirmados" : ''));
        }

        return self::SUCCESS;
    }

    /** La conciliación de Comfandi: mismo cruce, otro portal. */
    private function deComfandi(
        ComfandiSubsidiosHeadless $portal,
        ComfandiCajaConciliacionService $conciliacion,
        string $nit,
        int $cuantos,
        bool $simular,
    ): void {
        if (! $portal->credencial($nit)) {
            $this->line("{$nit}: sin clave de Comfandi, no se puede consultar.");

            return;
        }

        $this->line("{$nit}: {$cuantos} afiliado(s) en BryNex…");

        try {
            $leido = $portal->trabajadores($nit);
        } catch (Throwable $e) {
            $leido = ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! ($leido['ok'] ?? false)) {
            $this->warn('  ⚠️ '.($leido['error'] ?? 'sin detalle'));

            return;
        }

        try {
            // Sin radicados del portal: se avisa con `radicadosOk: false` para
            // que no dé por inexistente lo que no se miró.
            $r = $conciliacion->conciliar(
                $conciliacion->aliadosDelNit($nit),
                $nit,
                $leido['filas'],
                [],
                $simular,
                TareaAutomaticaService::USUARIO_SISTEMA,
                radicadosOk: false,
            );
        } catch (Throwable $e) {
            $this->warn('  ⚠️ '.$e->getMessage());

            return;
        }

        $this->info(sprintf('  ✅ %d en la caja · %d confirmado(s)%s',
            count($leido['filas']),
            $r['cerrados'] ?? 0,
            ($r['confirmados_ok'] ?? 0) ? " · {$r['confirmados_ok']} ya estaban en OK y quedan confirmados" : ''));
    }

    /**
     * Las empresas con afiliados vigentes en esa caja, por NIT.
     *
     * @return array<string, int>
     */
    private function empresas(string $caja): array
    {
        return DB::table('contratos as c')
            ->join('cajas as k', 'k.id', '=', 'c.caja_id')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('c.estado', 'vigente')
            ->where('k.nombre', 'like', '%'.$caja.'%')
            ->where('rs.es_independiente', false)
            ->whereNotNull('rs.nit')->whereRaw('LEN(LTRIM(RTRIM(rs.nit))) >= 9')
            ->when($this->option('nit'), fn ($q) => $q->where('rs.nit', preg_replace('/\D/', '', $this->option('nit'))))
            ->groupBy('rs.nit')
            ->selectRaw('rs.nit, count(*) as n')
            ->pluck('n', 'nit')
            ->all();
    }
}
