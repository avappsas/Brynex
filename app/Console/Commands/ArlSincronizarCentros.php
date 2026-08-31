<?php

namespace App\Console\Commands;

use App\Models\ArlCentroTrabajo;
use App\Models\RazonSocial;
use App\Services\ArlSura\ArlCentrosService;
use App\Services\ArlSura\ArlSuraApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

/**
 * Trae los centros de trabajo que una razón social tiene creados en ARL Sura.
 *
 * No se digitan porque no hay convención que adivinar: BRYGAR llamó a los suyos
 * `000RIESGO1`, `000RIESGO3` y `0000000001`, y otra razón social pudo ponerles
 * "SEDE PRINCIPAL". El afiliar exige el `cdSucursal` exacto, así que la única
 * fuente confiable es el propio portal.
 *
 * Lo que se guarda es un caché: si en Sura crean un centro nuevo, hay que volver
 * a correr esto. Los que desaparecen del portal se marcan inactivos en vez de
 * borrarse, para no romper el historial de `arl_afiliaciones` que los referencia.
 *
 * Varias razones sociales pueden compartir la misma póliza —es lo normal: el
 * aliado afilia a los trabajadores de sus clientes bajo una sola—, así que el
 * API se consulta UNA vez por póliza y el resultado se replica a todas las que
 * la usen. Sin eso se le pediría lo mismo al portal media docena de veces.
 */
class ArlSincronizarCentros extends Command
{
    protected $signature = 'arl:sincronizar-centros
                            {--aliado=1 : Aliado dueño de la sesión}
                            {--razon-social= : Id de la razón social; si se omite, todas las que tengan póliza}
                            {--cookie= : Cookie de una sesión abierta en el portal}
                            {--dry-run : Muestra lo que haría sin escribir en la BD}';

    protected $description = 'Sincroniza los centros de trabajo de ARL Sura por razón social';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $seco     = (bool) $this->option('dry-run');

        $razones = RazonSocial::query()
            ->where('aliado_id', $aliadoId)
            ->whereNotNull('arl_poliza')
            ->when($this->option('razon-social'), fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'razon_social', 'nit', 'arl_poliza']);

        if ($razones->isEmpty()) {
            $this->error('Ninguna razón social del aliado '.$aliadoId.' tiene arl_poliza registrada.');
            $this->line('Regístrala primero: es el número de contrato que muestra el portal de Sura.');
            return self::FAILURE;
        }

        $totalCentros = 0;

        foreach ($razones->groupBy('arl_poliza') as $poliza => $grupo) {
            $this->newLine();
            $this->line("── Póliza {$poliza} · ".$grupo->pluck('razon_social')->implode(', ').' ──');

            if ($cookie = $this->option('cookie')) {
                ArlSuraApiService::guardarSesion($aliadoId, $poliza, $cookie);
            }

            if ($seco) {
                $this->line('  [dry-run] no se escribe nada.');
                continue;
            }

            try {
                // La misma rutina que corre al registrar las credenciales de una
                // empresa: una sola implementación para los dos caminos.
                $r = ArlCentrosService::sincronizarPorPoliza((string) $poliza, $aliadoId);
            } catch (\Throwable $e) {
                $this->error('  '.$e->getMessage());
                continue;
            }

            if (! $r['centros']) {
                $this->warn('  El portal no devolvió centros para esta póliza.');
                continue;
            }

            $totalCentros += $r['centros'];
            $this->line("  {$r['centros']} centro(s) aplicados a {$r['razones']} razón(es) social(es).");

            foreach (ArlCentroTrabajo::whereIn('razon_social_id', $grupo->pluck('id'))
                ->where('activo', true)->orderBy('codigo_centro')
                ->get(['codigo_centro', 'nivel_riesgo', 'tasa', 'nombre_centro'])->unique('codigo_centro') as $c) {
                $this->line(sprintf('    %-14s riesgo %s  tasa %-7s %s',
                    $c->codigo_centro, $c->nivel_riesgo, $c->tasa ?? '-', $c->nombre_centro ?? ''));
            }

            foreach ($grupo as $rs) {
                $this->comprobarCobertura($rs->id, $seco);
            }
        }

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '')."Centros sincronizados: {$totalCentros}");

        return self::SUCCESS;
    }

    /**
     * Avisa de los niveles de riesgo que se usan en contratos vigentes pero no
     * tienen centro: son afiliaciones que van a fallar cuando alguien pulse el
     * botón, y es mejor saberlo ahora que en medio del trámite.
     */
    private function comprobarCobertura(int $razonSocialId, bool $seco): void
    {
        if ($seco) {
            return;
        }

        $conCentro = ArlCentroTrabajo::where('razon_social_id', $razonSocialId)
            ->where('activo', true)->pluck('nivel_riesgo')->unique();

        $enUso = DB::table('contratos')
            ->where('razon_social_id', $razonSocialId)
            ->where('estado', 'vigente')
            ->whereNotNull('n_arl')
            ->distinct()->pluck('n_arl');

        $faltantes = $enUso->diff($conCentro);

        if ($faltantes->isNotEmpty()) {
            $this->warn('  Sin centro para el nivel de riesgo: '.$faltantes->implode(', ').
                        ' (hay contratos vigentes con ese riesgo)');
        }
    }
}
