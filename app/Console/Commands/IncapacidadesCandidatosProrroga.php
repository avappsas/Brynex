<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\IncapacidadController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
                            {--csv : Guarda el detalle en storage/app/informes}
                            {--excel : Igual que --csv pero en xlsx, con una columna para marcar la decisión}';

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
                   a.valor_esperado, a.salario_base,
                   al.nombre AS aliado_nombre,
                   b.dias_incapacidad AS padre_dias, b.estado AS padre_estado
            FROM incapacidades a
            LEFT JOIN aliados al ON al.id = a.aliado_id
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

        // Los nombres van aparte a propósito: unir clientes en la consulta de
        // arriba duplicaba pares, porque la misma cédula puede estar registrada
        // más de una vez en esa tabla.
        $nombres = DB::table('clientes')
            ->whereIn('cedula', collect($filas)->pluck('cedula_usuario')->unique()->values())
            ->select('cedula', DB::raw("MIN(LTRIM(RTRIM(CONCAT(primer_nombre, ' ', primer_apellido, ' ', ISNULL(segundo_apellido, ''))))) AS nombre"))
            ->groupBy('cedula')
            ->pluck('nombre', 'cedula');

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
                'aliado_nombre' => $f->aliado_nombre ?: ('Aliado '.$f->aliado_id),
                'cedula' => $f->cedula_usuario,
                'afiliado' => trim($nombres[$f->cedula_usuario] ?? '') ?: '(sin cliente registrado)',
                'padre_periodo' => "{$f->padre_inicio} → {$f->padre_fin}",
                'padre_dias' => $f->padre_dias,
                'padre_estado' => $f->padre_estado,
                'hija_periodo' => "{$f->hija_inicio} → {$f->hija_fin}",
                'hija_dias' => $f->dias_incapacidad,
                'ya_pagada' => $yaPagada,
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
                $c['aliado_nombre'], $c['cedula'], $c['padre'], $c['hija'], $c['relacion'], $c['periodos'],
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
            $csv = "aliado_id;aliado;cedula;afiliado;padre_id;padre_periodo;hija_id;hija_periodo;relacion;entidad;estado;valor;cambio_valor\n";
            foreach ($candidatos as $c) {
                $csv .= implode(';', [$c['aliado'], $c['aliado_nombre'], $c['cedula'], $c['afiliado'],
                    $c['padre'], $c['padre_periodo'], $c['hija'], $c['hija_periodo'], $c['relacion'],
                    $c['entidad'], $c['estado'], $c['valor'], $c['cambio_valor']])."\n";
            }
            Storage::disk('local')->put($ruta, $csv);
            $this->info('CSV: storage/app/'.$ruta);
        }

        if ($this->option('excel')) {
            $this->info('Excel: storage/app/'.$this->generarExcel($candidatos, $pagadas));
        }

        $this->newLine();
        $this->line('<fg=gray>Nada de esto se modificó. Para unir un par: el botón "Unir a otra incapacidad"</>');
        $this->line('<fg=gray>en el detalle de la incapacidad que quedó suelta.</>');

        return self::SUCCESS;
    }

    /**
     * Hoja para revisar caso por caso: una fila por par, con la columna
     * "¿Unir?" en blanco para que quien revisa marque su decisión.
     */
    private function generarExcel(array $candidatos, int $pagadas): string
    {
        $libro = new Spreadsheet;
        $h = $libro->getActiveSheet();
        $h->setTitle('Candidatos');

        $cols = ['Aliado', 'Cédula', 'Afiliado', 'Original #', 'Período original', 'Días',
            'Suelta #', 'Período suelta', 'Días', 'Relación', 'Entidad', 'Estado de la suelta',
            'Valor actual', 'Sube si se une', '¿Unir? (SI/NO)', 'Notas'];
        $h->fromArray($cols, null, 'A1');

        $fila = 2;
        foreach ($candidatos as $c) {
            $h->fromArray([
                $c['aliado_nombre'], $c['cedula'], $c['afiliado'],
                $c['padre'], $c['padre_periodo'], $c['padre_dias'],
                $c['hija'], $c['hija_periodo'], $c['hija_dias'],
                $c['relacion'], $c['entidad'], $c['estado'],
                (float) $c['valor'], (float) $c['cambio_valor'], '', '',
            ], null, 'A'.$fila);

            // Lo que no es continuidad limpia va en ámbar: ahí suele haber un día
            // repetido o una fecha mal digitada, no una prórroga.
            if ($c['relacion'] !== 'continua') {
                $h->getStyle("A{$fila}:P{$fila}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FEF3C7');
            }
            $fila++;
        }

        $ultima = $fila - 1;
        $h->getStyle('A1:P1')->getFont()->setBold(true);
        $h->getStyle('A1:P1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');
        $h->getStyle("M2:N{$ultima}")->getNumberFormat()->setFormatCode('$#,##0');
        $h->setAutoFilter("A1:P{$ultima}");
        $h->freezePane('A2');
        foreach (range('A', 'P') as $col) {
            $h->getColumnDimension($col)->setAutoSize(true);
        }

        // Hoja aparte con el resumen, para no mezclarlo con las filas a revisar.
        $resumen = $libro->createSheet();
        $resumen->setTitle('Resumen');
        $porRelacion = collect($candidatos)->groupBy('relacion');
        $resumen->fromArray(['Relación', 'Pares', 'Sube el valor esperado'], null, 'A1');
        $r = 2;
        foreach ($porRelacion as $rel => $items) {
            $resumen->fromArray([$rel, $items->count(), $items->sum('cambio_valor')], null, 'A'.$r++);
        }
        $resumen->fromArray(['TOTAL', count($candidatos), collect($candidatos)->sum('cambio_valor')], null, 'A'.$r++);
        $r++;
        $resumen->fromArray(['Pares omitidos por estar ya pagados', $pagadas], null, 'A'.$r++);
        $resumen->fromArray(['Generado', now('America/Bogota')->format('d/m/Y H:i')], null, 'A'.$r++);
        $resumen->fromArray(['Ojo', 'Solo en EPS cambia el valor: la prórroga no descuenta los 2 primeros días.'], null, 'A'.$r++);
        $resumen->getStyle('A1:C1')->getFont()->setBold(true);
        $resumen->getStyle("C2:C{$r}")->getNumberFormat()->setFormatCode('$#,##0');
        foreach (range('A', 'C') as $col) {
            $resumen->getColumnDimension($col)->setAutoSize(true);
        }

        // Disco local: lleva cédulas y nombres, no puede quedar servido por URL.
        $ruta = 'informes/candidatos-prorroga-'.now()->format('Ymd-His').'.xlsx';
        Storage::disk('local')->makeDirectory('informes');
        (new Xlsx($libro))->save(Storage::disk('local')->path($ruta));
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
