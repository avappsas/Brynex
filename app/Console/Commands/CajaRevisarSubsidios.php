<?php

namespace App\Console\Commands;

use App\Models\CajaRevision;
use App\Services\Caja\ComfandiSubsidiosHeadless;
use App\Services\Caja\SubsidioCandidatosService;
use App\Services\Caja\SubsidioTareasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Revisión nocturna de los subsidios que Comfandi tiene bloqueados.
 *
 * Le pregunta al portal, empresa por empresa, por los sospechosos del día
 * —quien pagó con mora, quien ya tiene una tarea abierta y los afiliados
 * nuevos— y convierte cada bloqueo en una tarea; las que la caja dejó de
 * reportar se cierran solas. Es el mismo trabajo que hace la extensión cuando
 * alguien abre Afiliaciones, con un Chrome del servidor en lugar del suyo.
 *
 * Una vez al día por aliado: si alguien ya lo corrió desde el portal, esta no
 * repite. Programado en el Kernel; a mano:
 *
 *   php artisan caja:revisar-subsidios --aliado=2 --nit=901603738 --simular
 */
class CajaRevisarSubsidios extends Command
{
    protected $signature = 'caja:revisar-subsidios
                            {--aliado= : Solo este aliado (por defecto, todos los que tengan afiliados con la caja)}
                            {--nit= : Solo esta empresa}
                            {--completa : Barrido de todos los afiliados, no solo de los sospechosos}
                            {--forzar : Revisa aunque ya se haya hecho hoy}
                            {--simular : Consulta el portal pero no crea ni cierra tareas}';

    protected $description = 'Revisa en Comfandi los subsidios bloqueados y abre (o cierra) las tareas que correspondan';

    public function handle(
        SubsidioCandidatosService $candidatos,
        SubsidioTareasService $tareas,
        ComfandiSubsidiosHeadless $portal,
    ): int {
        $simular = (bool) $this->option('simular');
        $completa = (bool) $this->option('completa');
        $alcance = $completa ? CajaRevision::ALCANCE_COMPLETA : CajaRevision::ALCANCE_CANDIDATOS;

        foreach ($this->aliados() as $aliadoId) {
            if (! $simular && ! $this->option('forzar') && CajaRevision::yaSeHizo($aliadoId)) {
                $this->line("Aliado {$aliadoId}: ya se revisó hoy.");

                continue;
            }

            $lista = $completa
                ? $candidatos->todos($aliadoId, $this->option('nit') ?: null)
                : $candidatos->candidatos($aliadoId, $this->option('nit') ?: null);

            if (! $lista) {
                $this->line("Aliado {$aliadoId}: nadie por revisar.");

                continue;
            }

            $this->revisarAliado($aliadoId, $lista, $alcance, $simular, $tareas, $portal);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array>  $porEmpresa  candidatos agrupados por NIT
     */
    private function revisarAliado(
        int $aliadoId,
        array $porEmpresa,
        string $alcance,
        bool $simular,
        SubsidioTareasService $tareas,
        ComfandiSubsidiosHeadless $portal,
    ): void {
        $revision = $simular ? null : CajaRevision::abrir($aliadoId, CajaRevision::ENTIDAD_COMFANDI, $alcance);
        $totales = ['revisados' => 0, 'bloqueados' => 0, 'tareas_nuevas' => 0, 'tareas_cerradas' => 0];
        $fallos = [];

        $sinClave = 0;

        foreach ($porEmpresa as $nit => $gente) {
            $documentos = array_column($gente, 'cedula');

            // Sin clave guardada no hay nada que intentar. Son la mayoría de las
            // empresas, así que van aparte: si entraran como fallo, el mensaje
            // de la revisión sería una lista de NIT y no se vería lo que sí pasó.
            if (! $portal->credencial((string) $nit)) {
                $sinClave += count($documentos);

                continue;
            }

            $this->line("[{$aliadoId}] {$nit}: ".count($documentos).' trabajador(es)…');

            try {
                $leido = $portal->bloqueos((string) $nit, $documentos);
            } catch (Throwable $e) {
                $leido = ['ok' => false, 'error' => $e->getMessage()];
            }

            if (! ($leido['ok'] ?? false)) {
                $fallos[] = "{$nit}: ".($leido['error'] ?? 'sin detalle');
                $this->warn("  ⚠️ {$leido['error']}");

                continue;
            }

            // Sin nadie consultado no hay nada que concluir: cerrar tareas aquí
            // sería darlas por resueltas sin haberlas mirado.
            if (! $leido['revisados']) {
                $fallos[] = "{$nit}: el portal no dejó abrir el subsidio monetario de ninguno.";

                continue;
            }

            $r = $tareas->procesar($aliadoId, $leido['movimientos'], $leido['revisados'], $simular, (string) $nit);

            $totales['revisados'] += count($leido['revisados']);
            $totales['bloqueados'] += $r['bloqueos'];
            $totales['tareas_nuevas'] += $r['nuevas'];
            $totales['tareas_cerradas'] += $r['cerradas'];

            $this->info("  ✅ ".count($leido['revisados'])." revisados · {$r['bloqueos']} bloqueos · {$r['nuevas']} tarea(s) nueva(s) · {$r['cerradas']} cerrada(s)");

            // A quién no se pudo consultar importa tanto como lo encontrado: su
            // tarea no se cierra, y si se repite hay algo que arreglar.
            foreach ($leido['errores'] ?? [] as $fallo) {
                $this->warn("  · {$fallo['documento']}: {$fallo['error']}");
            }
        }

        $resumen = "{$totales['revisados']} revisados · {$totales['bloqueados']} bloqueos · "
            ."{$totales['tareas_nuevas']} nuevas · {$totales['tareas_cerradas']} cerradas";

        if ($sinClave) {
            $resumen .= " · {$sinClave} sin clave de Comfandi guardada";
        }

        $this->line("Aliado {$aliadoId}: {$resumen}");

        if (! $revision) {
            return;
        }

        // Una corrida en la que ninguna empresa se pudo consultar no vale como
        // revisión del día: queda fallida para poder reintentarla.
        $aviso = $fallos ? 'Con fallos: '.implode(' | ', $fallos) : null;
        if ($sinClave) {
            $aviso = trim(($aviso ?? '')." · {$sinClave} trabajador(es) de empresas sin clave de Comfandi.");
        }

        $totales['revisados'] > 0
            ? $revision->terminar($totales, $aviso)
            : $revision->fallar($fallos ? implode(' | ', $fallos) : 'No se pudo consultar ninguna empresa.');
    }

    /** @return array<int> */
    private function aliados(): array
    {
        if ($this->option('aliado')) {
            return [(int) $this->option('aliado')];
        }

        return DB::table('contratos as c')
            ->join('cajas as k', 'k.id', '=', 'c.caja_id')
            ->where('c.estado', 'vigente')
            ->where('k.nombre', 'like', '%COMFANDI%')
            ->distinct()
            ->pluck('c.aliado_id')
            ->map(fn ($a) => (int) $a)
            ->all();
    }
}
