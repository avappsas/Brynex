<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\IncapacidadController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lista las incapacidades sueltas que, por fechas, parecen prórrogas de otra.
 *
 * Solo informa: no liga nada. Ligar cambia el valor esperado en EPS (la prórroga
 * no descuenta los 2 primeros días), así que la decisión es de quien conoce el
 * caso, no del script. Las que ya están pagadas quedan fuera por definición: ahí
 * el valor ya se giró y moverlo descuadra la plata.
 *
 *   php artisan incapacidades:candidatos-prorroga
 *   php artisan incapacidades:candidatos-prorroga --aliado=2 --csv
 */
class IncapacidadesCandidatosProrroga extends Command
{
    protected $signature = 'incapacidades:candidatos-prorroga
                            {--aliado= : Solo este aliado}
                            {--incluir-pagadas : Listarlas también, marcadas (por defecto se excluyen)}
                            {--csv : Guarda el detalle en storage/app/informes}';

    protected $description = 'Lista incapacidades sueltas que encadenan por fechas con otra (posibles prórrogas sin ligar)';

    public function handle(): int
    {
        $aliado = $this->option('aliado');

        // Pares (padre candidato, hija candidata) que se tocan por fechas. El gap
        // es cuántos días hay entre el fin de una y el inicio de la otra:
        // 1 = arranca al día siguiente, 0 = el mismo día, negativo = se solapan.
        $filas = DB::select('
            SELECT b.id AS padre_id, a.id AS hija_id, a.aliado_id, a.cedula_usuario,
                   DATEDIFF(day, b.fecha_terminacion, a.fecha_inicio) AS gap,
                   CONVERT(varchar, b.fecha_inicio, 23)     AS padre_inicio,
                   CONVERT(varchar, b.fecha_terminacion, 23) AS padre_fin,
                   CONVERT(varchar, a.fecha_inicio, 23)     AS hija_inicio,
                   CONVERT(varchar, a.fecha_terminacion, 23) AS hija_fin,
                   a.dias_incapacidad, a.tipo_entidad, a.entidad_nombre,
                   b.tipo_entidad AS padre_entidad, a.estado, a.estado_pago,
                   a.valor_esperado, a.salario_base
            FROM incapacidades a
            JOIN incapacidades b
              ON b.cedula_usuario = a.cedula_usuario
             AND b.aliado_id      = a.aliado_id
             AND b.id <> a.id
             AND b.fecha_inicio  <= a.fecha_inicio
             AND DATEDIFF(day, b.fecha_terminacion, a.fecha_inicio) BETWEEN -3 AND 1
            WHERE a.deleted_at IS NULL AND b.deleted_at IS NULL
              AND a.incapacidad_padre_id IS NULL
              AND b.incapacidad_padre_id IS NULL
              '.($aliado ? 'AND a.aliado_id = '.(int) $aliado : '').'
            ORDER BY a.aliado_id, a.cedula_usuario, a.fecha_inicio
        ');

        $finales = IncapacidadController::ESTADOS_FINALES;
        $pagadas = 0;
        $candidatos = [];

        foreach ($filas as $f) {
            $yaPagada = in_array($f->estado, $finales, true) || $f->estado_pago !== 'pendiente';

            if ($yaPagada && ! $this->option('incluir-pagadas')) {
                $pagadas++;

                continue;
            }

            // Solo en EPS cambia la plata: la original descuenta 2 días y la
            // prórroga no. En ARL y AFP el valor es el mismo se ligue o no.
            $diferencia = 0.0;
            if ($f->tipo_entidad === 'eps' && $f->salario_base > 0) {
                $diario = (float) $f->salario_base / 30;
                $diferencia = round(min(2, (int) $f->dias_incapacidad) * $diario, 2);
            }

            $candidatos[] = [
                'aliado' => $f->aliado_id,
                'cedula' => $f->cedula_usuario,
                'padre' => $f->padre_id,
                'hija' => $f->hija_id,
                'relacion' => match (true) {
                    $f->gap == 1 => 'continua',
                    $f->gap == 0 => 'mismo dia',
                    default => 'solapa '.abs($f->gap).'d',
                },
                'periodos' => "{$f->padre_inicio}→{$f->padre_fin}  |  {$f->hija_inicio}→{$f->hija_fin}",
                'entidad' => strtoupper($f->tipo_entidad).($f->tipo_entidad !== $f->padre_entidad ? ' (≠ del padre)' : ''),
                'estado' => $f->estado.($yaPagada ? ' ⚠ pagada' : ''),
                'valor' => (float) $f->valor_esperado,
                'cambio_valor' => $diferencia,
            ];
        }

        if (! $candidatos) {
            $this->info('No hay candidatos'.($pagadas ? " (se omitieron $pagadas pares ya pagados)." : '.'));

            return self::SUCCESS;
        }

        $porRelacion = collect($candidatos)->groupBy('relacion');
        $this->newLine();
        $this->line('<fg=cyan>Incapacidades sueltas que encadenan por fechas con otra</>');
        $this->line(str_repeat('─', 70));
        foreach ($porRelacion as $rel => $items) {
            $this->line(sprintf('  %-14s %4d pares   cambio de valor si se unen: $%s',
                $rel, $items->count(), number_format($items->sum('cambio_valor'), 0, ',', '.')));
        }
        $this->line(str_repeat('─', 70));
        $this->line('  TOTAL          '.count($candidatos).' pares');
        if ($pagadas) {
            $this->line("  <fg=yellow>Omitidos: $pagadas pares donde la segunda ya está pagada (no se tocan).</>");
        }
        $this->newLine();

        $this->table(
            ['Aliado', 'Cédula', 'Padre', 'Hija', 'Relación', 'Períodos (padre | hija)', 'Entidad', 'Estado', 'Valor', 'Δ valor'],
            collect($candidatos)->take(40)->map(fn ($c) => [
                $c['aliado'], $c['cedula'], $c['padre'], $c['hija'], $c['relacion'], $c['periodos'],
                $c['entidad'], $c['estado'],
                '$'.number_format($c['valor'], 0, ',', '.'),
                $c['cambio_valor'] ? '+$'.number_format($c['cambio_valor'], 0, ',', '.') : '—',
            ])->all()
        );

        if (count($candidatos) > 40) {
            $this->line('  … y '.(count($candidatos) - 40).' más. Usa --csv para verlos todos.');
        }

        if ($this->option('csv')) {
            // Disco local: lleva cédulas, no puede quedar servido por URL.
            $ruta = 'informes/candidatos-prorroga-'.now()->format('Ymd-His').'.csv';
            $csv = "aliado;cedula;padre_id;hija_id;relacion;padre_periodo;hija_periodo;entidad;estado;valor;cambio_valor\n";
            foreach ($candidatos as $c) {
                [$pp, $hp] = explode('  |  ', $c['periodos']);
                $csv .= implode(';', [$c['aliado'], $c['cedula'], $c['padre'], $c['hija'], $c['relacion'],
                    $pp, $hp, $c['entidad'], $c['estado'], $c['valor'], $c['cambio_valor']])."\n";
            }
            Storage::disk('local')->put($ruta, $csv);
            $this->info('CSV: storage/app/'.$ruta);
        }

        $this->newLine();
        $this->line('<fg=gray>Nada de esto se modificó. Para unir un par: el botón "Unir a otra incapacidad"</>');
        $this->line('<fg=gray>en el detalle de la incapacidad que quedó suelta.</>');

        return self::SUCCESS;
    }
}
