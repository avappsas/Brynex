<?php

namespace App\Console\Commands;

use App\Models\Incapacidad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pone el `numero_proroga` de cada familia en 1..N por fecha de inicio.
 *
 * El número se venía asignando con un `count() + 1` al crear, o sea el orden en
 * que se registraron: una prórroga vieja cargada después de una más reciente
 * quedaba con el número cruzado contra las fechas (la "3" empezando antes que
 * la "2"). Desde ahora la app renumera sola en cada alta, unión, desligue o
 * cambio de fecha; este comando arregla lo que quedó de antes.
 *
 * Solo toca `numero_proroga` — ni fechas, ni estados, ni valores — y no mueve
 * `updated_at`: renumerar es contabilidad interna, no una gestión del caso.
 *
 *   php artisan incapacidades:renumerar-prorrogas --dry-run
 *   php artisan incapacidades:renumerar-prorrogas --aliado=1
 */
class IncapacidadesRenumerarProrrogas extends Command
{
    protected $signature = 'incapacidades:renumerar-prorrogas
                            {--aliado= : Solo las familias de este aliado}
                            {--dry-run : Muestra qué cambiaría, sin escribir}';

    protected $description = 'Renumera las prórrogas de cada incapacidad como 1..N en orden de fecha de inicio';

    public function handle(): int
    {
        $seco = (bool) $this->option('dry-run');
        $aliado = $this->option('aliado');

        // Familias que tienen al menos una prórroga viva.
        $padres = DB::table('incapacidades')
            ->whereNotNull('incapacidad_padre_id')
            ->whereNull('deleted_at')
            ->when($aliado, fn ($q) => $q->where('aliado_id', $aliado))
            ->distinct()
            ->pluck('incapacidad_padre_id');

        $this->info(($seco ? '[dry-run] ' : '')."Revisando {$padres->count()} familia(s)…");

        $familiasTocadas = 0;
        $registros = 0;

        foreach ($padres as $padreId) {
            $cambios = $seco
                ? $this->simular((int) $padreId)
                : Incapacidad::renumerarFamilia((int) $padreId);

            if (! $cambios) {
                continue;
            }

            $familiasTocadas++;
            $registros += count($cambios);

            $this->line("  familia #{$padreId}: ".collect($cambios)
                ->map(fn ($c) => "#{$c['id']} {$c['antes']}→{$c['despues']}")
                ->implode(', '));
        }

        $this->newLine();
        $this->info($seco
            ? "[dry-run] Cambiarían {$registros} prórroga(s) en {$familiasTocadas} familia(s). Nada se escribió."
            : "Renumeradas {$registros} prórroga(s) en {$familiasTocadas} familia(s).");

        return self::SUCCESS;
    }

    /** Lo mismo que renumerarFamilia(), pero sin escribir. */
    private function simular(int $padreId): array
    {
        $hermanas = Incapacidad::where('incapacidad_padre_id', $padreId)
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get(['id', 'numero_proroga']);

        $cambios = [];

        foreach ($hermanas as $i => $hermana) {
            $nuevo = $i + 1;
            if ((int) $hermana->numero_proroga !== $nuevo) {
                $cambios[] = [
                    'id' => (int) $hermana->id,
                    'antes' => (int) $hermana->numero_proroga,
                    'despues' => $nuevo,
                ];
            }
        }

        return $cambios;
    }
}
