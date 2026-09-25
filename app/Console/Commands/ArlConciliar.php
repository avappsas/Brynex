<?php

namespace App\Console\Commands;

use App\Models\RazonSocial;
use App\Models\Tarea;
use App\Services\ArlSura\ArlConciliacionService;
use App\Services\ArlSura\ArlSuraApiService;
use App\Services\ArlSura\ArlSuraSesionService;
use App\Services\TareaAutomaticaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cruza los afiliados de ARL Sura con los contratos vigentes de BryNex.
 *
 * El servicio que compara ya existía, pero solo corría cuando alguien abría la
 * pantalla, y nadie abre una pantalla a diario. Los dos desfases cuestan:
 *
 *  - en Sura y no en BryNex → se paga cobertura de quien ya no está;
 *  - en BryNex y no en Sura → el trabajador se cree cubierto y no lo está,
 *    y eso solo se descubre el día del accidente.
 */
class ArlConciliar extends Command
{
    protected $signature = 'arl:conciliar
                            {--nit= : Solo esta empresa}
                            {--simular : Consulta el portal pero no crea ni cierra tareas}';

    protected $description = 'Cruza los afiliados de ARL Sura con los contratos vigentes y abre las tareas que falten';

    private const PREFIJO = 'arlsura:cruce';

    public function handle(TareaAutomaticaService $tareas): int
    {
        $empresas = $this->empresas();

        if ($empresas->isEmpty()) {
            $this->warn('Ninguna empresa con póliza de ARL Sura y clave del portal.');

            return self::SUCCESS;
        }

        $fallos = 0;

        foreach ($empresas as $empresa) {
            $this->line("{$empresa->nit} {$empresa->razon_social} (póliza {$empresa->arl_poliza})…");

            try {
                // La sesión se abre aparte y antes de consultar: estrenarla
                // dentro de la primera consulta la deja colgada hasta el timeout
                // (60 s), mientras que con la sesión ya viva la misma consulta
                // tarda un segundo.
                $this->asegurarSesion($empresa);

                // Una por empresa: el servicio entra al portal con la credencial
                // de esa póliza, así que no se puede reutilizar entre empresas.
                $r = ArlConciliacionService::paraPoliza((int) $empresa->aliado_id, $empresa->arl_poliza)
                    ->conciliar($empresa->nit, $empresa->arl_poliza);
            } catch (\Throwable $e) {
                $fallos++;
                $this->error('  '.mb_substr($e->getMessage(), 0, 160));
                Log::warning('ARL Sura: falló el cruce', ['nit' => $empresa->nit, 'error' => $e->getMessage()]);

                continue;
            }

            $vistas = [];
            $nuevas = 0;

            foreach ($r['sobran'] as $caso) {
                $nuevas += $this->abrir($tareas, $empresa, $caso, $vistas,
                    "Retirar de ARL Sura a {$caso['nombre']}: {$caso['situacion']}.",
                    "ARL Sura lo tiene afiliado en la póliza {$empresa->arl_poliza}, pero en BryNex {$caso['situacion']}"
                        .($caso['otra_empresa'] ? " ({$caso['otra_empresa']})" : '')
                        .'. Mientras siga afiliado se paga su cobertura.',
                    $caso['contrato_id'] ?? null);
            }

            foreach ($r['faltan'] as $caso) {
                $nuevas += $this->abrir($tareas, $empresa, $caso, $vistas,
                    "Afiliar a ARL Sura a {$caso['nombre']}: tiene contrato vigente y no aparece en la póliza.",
                    "En BryNex el contrato está vigente desde {$caso['desde']} con riesgo {$caso['riesgo']}"
                        .($caso['plan'] ? " (plan {$caso['plan']})" : '')
                        .', pero ARL Sura no lo tiene en la póliza '.$empresa->arl_poliza
                        .'. Sin cobertura, un accidente no queda amparado.',
                    $caso['contrato_id'] ?? null);
            }

            $cerradas = $this->cerrarResueltas($tareas, $empresa->nit, $vistas);

            $this->info("  {$r['en_sura']} en Sura · {$r['en_brynex']} vigentes en BryNex · "
                .count($r['sobran']).' sobra(n) · '.count($r['faltan']).' falta(n) · '
                .($this->option('simular') ? 'abriría ' : '')."{$nuevas} tarea(s) · {$cerradas} cerrada(s)");
        }

        return $fallos && $fallos === $empresas->count() ? self::FAILURE : self::SUCCESS;
    }

    /** Deja la sesión del portal lista antes de consultar. */
    private function asegurarSesion($empresa): void
    {
        $api = new ArlSuraApiService((int) $empresa->aliado_id, (string) $empresa->arl_poliza);

        if ($api->sesionViva()) {
            return;
        }

        ArlSuraSesionService::renovar((int) $empresa->aliado_id, (string) $empresa->arl_poliza);
        // Un respiro: la sesión recién creada no atiende bien el primer golpe.
        sleep(3);
    }

    /** Abre la tarea de ese desfase, si no estaba ya. */
    private function abrir(TareaAutomaticaService $tareas, $empresa, array $caso, array &$vistas, string $texto, string $observacion, ?int $contratoId): int
    {
        $documento = ltrim(preg_replace('/\D/', '', (string) $caso['documento']), '0');
        $llave = self::PREFIJO.":{$empresa->nit}:{$documento}";
        $vistas[] = $llave;

        if ($tareas->activaPorLlave((int) $empresa->aliado_id, $llave)) {
            return 0;
        }

        if ($this->option('simular')) {
            return 1;
        }

        return $tareas->abrir([
            'aliado_id' => (int) $empresa->aliado_id,
            'tipo' => 'otros',
            'cedula' => $documento,
            'contrato_id' => $contratoId,
            'razon_social_id' => $empresa->id,
            'entidad' => 'ARL SURA',
            'tarea' => $texto,
            'observacion' => $observacion,
            'llave_auto' => $llave,
        ]) ? 1 : 0;
    }

    private function cerrarResueltas(TareaAutomaticaService $tareas, string $nit, array $vistas): int
    {
        $cerradas = 0;

        foreach (Tarea::whereNotNull('llave_auto')->where('llave_auto', 'like', self::PREFIJO.":{$nit}:%")
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)->get() as $tarea) {
            if (in_array($tarea->llave_auto, $vistas, true)) {
                continue;
            }

            if ($this->option('simular')) {
                $cerradas++;

                continue;
            }

            if ($tareas->cerrar($tarea, 'BryNex y ARL Sura ya coinciden en esta persona el '.now()->format('d/m/Y').'.')) {
                $cerradas++;
            }
        }

        return $cerradas;
    }

    /**
     * Las empresas con póliza y clave del portal, una por NIT.
     *
     * Solo del aliado 2: es el único con la automatización de portales activa.
     */
    private function empresas()
    {
        return RazonSocial::where('aliado_id', 2)
            ->whereNotNull('arl_poliza')->where('arl_poliza', '<>', '')
            ->when($this->option('nit'), fn ($q, $nit) => $q->where('nit', preg_replace('/\D/', '', $nit)))
            ->get(['id', 'nit', 'razon_social', 'arl_poliza', 'aliado_id'])
            ->unique(fn ($r) => preg_replace('/\D/', '', (string) $r->nit))
            ->filter(fn ($r) => (bool) ArlSuraSesionService::credencialPara((int) $r->aliado_id, (string) $r->arl_poliza, $r->nit))
            ->values();
    }
}
