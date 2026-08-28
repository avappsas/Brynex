<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Le devuelve el valor a las consignaciones que quedaron en 1 peso.
 *
 * Un arreglo posterior a la migración importó consignaciones sueltas del
 * legacy y a varias les escribió el monto en la observación —«$ 65.000»,
 * «509.100»— dejando la columna `valor` en 1. A unas sí les puso el valor
 * bien, así que no fue una conversión que falló de raíz sino a medias.
 *
 * La plata existe y entró: son 21 movimientos por 8,1 millones, casi todos de
 * la primera semana de mayo de 2026 en la cuenta de BRYGAR. Mientras estén en 1
 * peso, las entradas de ese mes salen cortas por esa cifra en la ficha de la
 * razón social y en cualquier cuadre contra el extracto.
 *
 * Solo toca la fila cuando la observación es el monto y nada más. Una que diga
 * «Transferencia/Consignación bancaria — Incapacidad #4544» o «Migrada de
 * factura legacy #64625» no lleva monto, y sacarle un número a esa frase sería
 * inventarlo: esas se listan aparte y se quedan como están.
 *
 * La observación no se borra: es la prueba de dónde salió el valor. Y el
 * comando es idempotente por construcción —una fila ya corregida deja de
 * cumplir `valor <= tope` y no se vuelve a mirar—.
 *
 * Sin `--ejecutar` solo reporta.
 */
class ConsignacionesRestaurarValor extends Command
{
    protected $signature = 'consignaciones:restaurar-valor
        {--aliado= : Aliado cuyas consignaciones se corrigen}
        {--cuenta= : Solo las de esta cuenta bancaria}
        {--desde= : Desde esta fecha (YYYY-MM-DD)}
        {--hasta= : Hasta esta fecha, sin incluirla (YYYY-MM-DD)}
        {--tope=1 : Valor por debajo del cual se considera que la fila está mala}
        {--minimo=1000 : Monto mínimo creíble para una consignación}
        {--ejecutar : Escribe los cambios. Sin esto, solo reporta}';

    protected $description = 'Restaura el valor de las consignaciones que lo tienen escrito en la observación';

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');

        if (! $aliadoId) {
            $this->error('Falta --aliado.');

            return self::FAILURE;
        }

        $filas = DB::table('consignaciones as c')
            ->leftJoin('banco_cuentas as b', 'b.id', '=', 'c.banco_cuenta_id')
            ->where('c.aliado_id', $aliadoId)
            ->where('c.valor', '<=', (int) $this->option('tope'))
            ->whereNull('c.deleted_at')
            ->when($this->option('cuenta'), fn ($q) => $q->where('c.banco_cuenta_id', (int) $this->option('cuenta')))
            ->when($this->option('desde'), fn ($q) => $q->whereDate('c.fecha', '>=', $this->option('desde')))
            ->when($this->option('hasta'), fn ($q) => $q->whereDate('c.fecha', '<', $this->option('hasta')))
            ->orderBy('c.fecha')
            ->get(['c.id', 'c.valor', 'c.fecha', 'c.banco_cuenta_id', 'c.referencia', 'c.observacion',
                'b.nombre as cuenta']);

        if ($filas->isEmpty()) {
            $this->info('No hay consignaciones con el valor perdido.');

            return self::SUCCESS;
        }

        $minimo = (int) $this->option('minimo');
        $recuperables = collect();
        $sinMonto = collect();

        foreach ($filas as $f) {
            $monto = $this->montoDe($f->observacion);

            $monto !== null && $monto >= $minimo
                ? $recuperables->push([$f, $monto])
                : $sinMonto->push($f);
        }

        $this->line("consignaciones con el valor perdido: {$filas->count()}");
        $this->newLine();

        foreach ($recuperables as [$f, $monto]) {
            $this->line(sprintf('  #%-7s %s  %-14s ref %-20s  1 → %s', $f->id, substr((string) $f->fecha, 0, 10),
                mb_substr((string) $f->cuenta, 0, 14), mb_substr((string) $f->referencia, 0, 20),
                number_format($monto)));
        }

        $this->newLine();
        $this->info('recuperables: '.$recuperables->count().'  por '
                   .number_format($recuperables->sum(fn ($r) => $r[1])));

        if ($sinMonto->isNotEmpty()) {
            $this->newLine();
            $this->warn('sin monto en la observación — se quedan como están:');
            foreach ($sinMonto as $f) {
                $this->line(sprintf('  #%-7s %s  %-14s ref %-20s obs «%s»', $f->id, substr((string) $f->fecha, 0, 10),
                    mb_substr((string) $f->cuenta, 0, 14), mb_substr((string) $f->referencia, 0, 20),
                    mb_substr(trim((string) $f->observacion), 0, 46)));
            }
        }

        if (! $this->option('ejecutar')) {
            $this->newLine();
            $this->warn('SIMULACIÓN — no se escribió nada. Agregue --ejecutar.');

            return self::SUCCESS;
        }

        $n = 0;

        DB::transaction(function () use ($recuperables, &$n) {
            foreach ($recuperables as [$f, $monto]) {
                $n += DB::table('consignaciones')->where('id', $f->id)->update(['valor' => $monto]);
            }
        });

        $this->newLine();
        $this->info("{$n} consignaciones recuperaron su valor.");

        return self::SUCCESS;
    }

    /**
     * El monto que dice la observación, o null si no dice uno.
     *
     * Exige que la observación sea el monto y nada más. Buscar el primer número
     * dentro de una frase le sacaría «4544» a «Incapacidad #4544» y lo
     * escribiría como si fueran pesos.
     */
    private function montoDe(?string $observacion): ?int
    {
        $texto = trim(str_replace(['$', ' ', "\u{00A0}"], '', (string) $observacion));

        if ($texto === '') {
            return null;
        }

        // Formato colombiano: el punto separa miles y nunca hay decimales en
        // una consignación. «1.708.000» y «65000» valen; «65.0» o «12,5» no.
        if (! preg_match('/^\d{1,3}(\.\d{3})+$|^\d+$/', $texto)) {
            return null;
        }

        return (int) str_replace('.', '', $texto);
    }
}
