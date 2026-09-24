<?php

namespace App\Console\Commands;

use App\Services\NuevaEps\NuevaEpsMoraService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revisa en el portal de la EPS qué trabajadores aparecen en mora y abre la
 * tarea que corresponda.
 *
 * Va por empresa porque la clave es de la empresa, y el reporte que se pide es
 * de la empresa entera: una sola entrada al portal cubre toda su nómina.
 */
class EpsRevisarMora extends Command
{
    protected $signature = 'eps:revisar-mora
                            {--nit= : Solo esta empresa}
                            {--corte= : Primer día del mes de corte (AAAA-MM-01); por defecto, el mes en curso}
                            {--simular : Consulta el portal pero no crea ni cierra tareas}';

    protected $description = 'Revisa la mora por trabajador en Nueva EPS y abre las tareas que correspondan';

    public function handle(NuevaEpsMoraService $mora): int
    {
        $empresas = $this->empresas();

        if (! $empresas) {
            $this->warn('Ninguna empresa tiene clave de Nueva EPS en el módulo de claves.');

            return self::SUCCESS;
        }

        $simular = (bool) $this->option('simular');
        $fallos = 0;

        foreach ($empresas as $empresa) {
            $this->line("{$empresa->nit} {$empresa->razon_social}…");

            $r = $mora->revisar($empresa->nit, $simular, $this->option('corte'));

            if (! ($r['ok'] ?? false)) {
                $fallos++;
                $this->error('  '.($r['error'] ?? 'sin detalle'));

                continue;
            }

            $porCausa = collect($r['detalle'] ?? [])
                ->filter(fn ($d) => isset($d['causa']))
                ->countBy('causa')
                ->map(fn ($n, $causa) => "{$causa}: {$n}")
                ->implode(' · ');

            $this->info('  '.($simular ? 'abriría ' : '').$r['nuevas'].' tarea(s) · '.$r['cerradas'].' cerrada(s)'
                .($porCausa ? "  [{$porCausa}]" : '  sin mora'));
        }

        return $fallos && $fallos === count($empresas) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Las empresas con clave de Nueva EPS, una vez cada una.
     *
     * La misma razón social existe en varios aliados y la clave es de la
     * empresa: entrar una vez por aliado sería entrar varias veces al mismo
     * portal con el mismo usuario.
     */
    private function empresas(): array
    {
        $consulta = DB::table('clave_accesos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('c.tipo', 'EPS')
            ->where('c.entidad', 'like', '%NUEVA%')
            ->where('c.activo', true)
            ->whereNotNull('c.usuario')->where('c.usuario', '<>', '')
            ->whereNotNull('c.contrasena')->where('c.contrasena', '<>', '')
            // Los NIT de verdad tienen nueve dígitos: en el llavero hay filas
            // viejas apuntando a razones sociales que no lo son.
            ->whereRaw('LEN(rs.nit) >= 9');

        if ($nit = $this->option('nit')) {
            $consulta->where('rs.nit', preg_replace('/\D/', '', $nit));
        }

        return $consulta->distinct()->orderBy('rs.nit')->get(['rs.nit', 'rs.razon_social'])
            ->unique('nit')->values()->all();
    }
}
