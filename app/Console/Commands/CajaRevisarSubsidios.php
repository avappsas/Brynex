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
 * **La unidad es la empresa, no el aliado.** La clave del portal vive en la
 * razón social, así que una sola consulta sirve para todos los aliados que
 * compartan la empresa: se entra una vez y las tareas se reparten a cada uno
 * según sus contratos. Por eso el candado del día también es por empresa.
 *
 * Programado en el Kernel; a mano:
 *
 *   php artisan caja:revisar-subsidios --nit=901603738 --simular
 */
class CajaRevisarSubsidios extends Command
{
    protected $signature = 'caja:revisar-subsidios
                            {--aliado= : Solo los afiliados de este aliado (por defecto, todos)}
                            {--nit= : Solo esta empresa}
                            {--caja=COMFANDI : Qué caja se revisa}
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
        $caja = mb_strtoupper(trim((string) $this->option('caja'))) ?: SubsidioCandidatosService::CAJA_POR_DEFECTO;

        // Por ahora el único portal con recorrido propio es el de Comfandi. El
        // resto del proceso —candidatos, tareas, candado— ya no depende de la
        // caja, así que enchufar otra es traer su lector, no rehacer esto.
        if ($caja !== SubsidioCandidatosService::CAJA_POR_DEFECTO) {
            $this->error("Todavía no hay recorrido del portal de {$caja}.");

            return self::FAILURE;
        }
        $alcance = $completa ? CajaRevision::ALCANCE_COMPLETA : CajaRevision::ALCANCE_CANDIDATOS;

        $empresas = $this->empresas($candidatos, $completa);

        if (! $empresas) {
            $this->line('Nadie por revisar.');

            return self::SUCCESS;
        }

        foreach ($empresas as $nit => $porAliado) {
            $this->revisarEmpresa((string) $nit, $porAliado, $alcance, $simular, $tareas, $portal);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string>>  $porAliado  aliado => cédulas suyas en esa empresa
     */
    private function revisarEmpresa(
        string $nit,
        array $porAliado,
        string $alcance,
        bool $simular,
        SubsidioTareasService $tareas,
        ComfandiSubsidiosHeadless $portal,
    ): void {
        $aliados = implode(', ', array_keys($porAliado));

        if (! $portal->credencial($nit)) {
            $this->line("{$nit}: sin clave de Comfandi guardada, no se puede consultar.");

            return;
        }

        if (! $simular && ! $this->option('forzar') && CajaRevision::yaSeHizo($nit)) {
            $this->line("{$nit}: ya se revisó hoy.");

            return;
        }

        // Una sola consulta para todos los aliados: lo caro es el recorrido por
        // el portal, y lo que devuelve es de la empresa, no de quien la factura.
        $documentos = array_values(array_unique(array_merge(...array_values($porAliado))));

        $this->line("{$nit} (aliados {$aliados}): ".count($documentos).' trabajador(es)…');

        $revision = $simular ? null : CajaRevision::abrir($nit, CajaRevision::ENTIDAD_COMFANDI, $alcance, (int) array_key_first($porAliado));

        try {
            $leido = $portal->bloqueos($nit, $documentos);
        } catch (Throwable $e) {
            $leido = ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! ($leido['ok'] ?? false)) {
            $this->warn('  ⚠️ '.$leido['error']);
            $revision?->fallar($leido['error']);

            return;
        }

        // Sin nadie consultado no hay nada que concluir: cerrar tareas aquí
        // sería darlas por resueltas sin haberlas mirado.
        if (! $leido['revisados']) {
            $this->warn('  ⚠️ El portal no dejó abrir el subsidio monetario de ninguno.');
            $revision?->fallar('El portal no dejó abrir el subsidio monetario de ninguno.');

            return;
        }

        $totales = ['revisados' => count($leido['revisados']), 'bloqueados' => 0, 'tareas_nuevas' => 0, 'tareas_cerradas' => 0];

        foreach ($porAliado as $aliadoId => $suyos) {
            // Cada aliado solo responde por su gente: las tareas y los cierres
            // se calculan con las cédulas que él tiene en esa empresa.
            $revisadosSuyos = array_values(array_intersect($leido['revisados'], $suyos));

            if (! $revisadosSuyos) {
                continue;
            }

            $movimientosSuyos = array_values(array_filter(
                $leido['movimientos'],
                fn ($m) => in_array((string) ($m['documento'] ?? ''), $suyos, true)
            ));

            $r = $tareas->procesar((int) $aliadoId, $movimientosSuyos, $revisadosSuyos, $simular, $nit);

            $totales['bloqueados'] += $r['bloqueos'];
            $totales['tareas_nuevas'] += $r['nuevas'];
            $totales['tareas_cerradas'] += $r['cerradas'];

            $this->info("  [aliado {$aliadoId}] {$r['bloqueos']} bloqueos · {$r['nuevas']} tarea(s) nueva(s) · {$r['cerradas']} cerrada(s)");
        }

        // A quién no se pudo consultar importa tanto como lo encontrado: su
        // tarea no se cierra, y si se repite hay algo que arreglar.
        foreach ($leido['errores'] ?? [] as $fallo) {
            $this->warn("  · {$fallo['documento']}: {$fallo['error']}");
        }

        $revision?->terminar($totales, $this->aviso($leido['errores'] ?? []));
    }

    private function aviso(array $errores): ?string
    {
        if (! $errores) {
            return null;
        }

        return 'No se pudo consultar a: '.implode(', ', array_map(fn ($e) => $e['documento'], $errores));
    }

    /**
     * Los candidatos del día, por empresa y dentro de ella por aliado.
     *
     * @return array<string, array<int, array<string>>>
     */
    private function empresas(SubsidioCandidatosService $candidatos, bool $completa): array
    {
        $nit = $this->option('nit') ?: null;
        $empresas = [];

        foreach ($this->aliados() as $aliadoId) {
            $lista = $completa ? $candidatos->todos($aliadoId, $nit) : $candidatos->candidatos($aliadoId, $nit);

            foreach ($lista as $nitEmpresa => $gente) {
                $empresas[(string) $nitEmpresa][$aliadoId] = array_values(array_unique(array_column($gente, 'cedula')));
            }
        }

        return $empresas;
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
