<?php

namespace App\Console\Commands\Finanzas;

use App\Models\Finanzas\Prestamo;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CorregirDiaCobroPrestamos extends Command
{
    /**
     * Alinea `dia_cobro` con el día en que realmente está corriendo el ciclo
     * de cada préstamo (el día de su `ultimo_corte`).
     *
     * El desfase viene de la migración que creó la columna: rellenó el campo con
     * el día del desembolso para todos, pero muchos préstamos ya tenían el ciclo
     * en otro día — la importación del Excel dejó los cortes el 15, y hubo
     * re-anclajes anteriores a que la columna existiera.
     *
     * Casi siempre es inofensivo, porque `siguienteCorte` solo lee `dia_cobro`
     * en dos casos borde de fin de mes. Pero cuando el ciclo corre a fin de mes
     * y el campo dice otra cosa, el corte no recupera el día 31 y se desliza
     * hacia atrás hasta quedarse clavado en 28 (caso de Ángela Ortiz y Nikol).
     *
     * Sin --apply solo simula. Ejecución: php artisan finanzas:corregir-dia-cobro --apply
     */
    protected $signature = 'finanzas:corregir-dia-cobro
                            {ids?* : Ids de préstamo; sin ids, todos los que no estén pagados}
                            {--apply : Escribe los cambios; sin esta opción solo simula}';

    protected $description = 'Alinea el dia_cobro de los préstamos con el día real de su ciclo de corte';

    public function handle(): int
    {
        $ids = $this->argument('ids');
        $aplicar = (bool) $this->option('apply');

        $prestamos = Prestamo::query()
            ->whereNotNull('ultimo_corte')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            // Un préstamo pagado no vuelve a liquidar: tocarlo no arregla nada
            // y ensucia el histórico.
            ->when(! $ids, fn ($q) => $q->where('estado', '!=', 'pagado'))
            ->orderBy('id')
            ->get();

        $porCorregir = [];
        $preservados = 0;

        foreach ($prestamos as $prestamo) {
            $corte = Carbon::parse($prestamo->ultimo_corte);
            $diaCorte = $corte->day;
            $diaCobro = $prestamo->dia_cobro !== null ? (int) $prestamo->dia_cobro : null;

            if ($diaCobro === $diaCorte) {
                continue;
            }

            if ($diaCobro !== null && $this->explicaUnTruncamiento($corte, $diaCobro)) {
                // El corte cayó donde cayó porque el día de cobro no cabía en ese
                // mes: aquí el valor guardado es el bueno y es lo que permite
                // recuperar el día original. No se toca.
                $preservados++;

                continue;
            }

            $porCorregir[] = [
                'prestamo' => $prestamo,
                'antes' => $diaCobro,
                'despues' => $diaCorte,
            ];
        }

        if (empty($porCorregir)) {
            $this->info("Nada que corregir. Préstamos revisados: {$prestamos->count()}, con día de cobro preservado: {$preservados}.");

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Deudor', 'Estado', 'Último corte', 'dia_cobro', '→'],
            collect($porCorregir)->map(fn ($c) => [
                $c['prestamo']->id,
                mb_substr($c['prestamo']->nombre_deudor, 0, 28),
                $c['prestamo']->estado,
                Carbon::parse($c['prestamo']->ultimo_corte)->toDateString(),
                $c['antes'] ?? '(vacío)',
                $c['despues'],
            ])->all()
        );

        if (! $aplicar) {
            $this->warn('Simulación: no se escribió nada. Repite con --apply para aplicar los '.count($porCorregir).' cambios.');

            return self::SUCCESS;
        }

        foreach ($porCorregir as $cambio) {
            // update() directo: no toca saldos ni movimientos, solo el día del ciclo.
            $cambio['prestamo']->update(['dia_cobro' => $cambio['despues']]);
        }

        $this->info('Listo. Préstamos corregidos: '.count($porCorregir).". Con día de cobro preservado: {$preservados}.");

        return self::SUCCESS;
    }

    /**
     * ¿El `dia_cobro` guardado explica que el corte haya caído donde cayó?
     *
     * Son los dos casos que `PrestamoLiquidacionService::siguienteCorte()` sabe
     * deshacer: el corte que aterrizó en el último día de un mes que no alcanzaba
     * para el día de cobro, y el corte viejo que desbordó a los primeros días del
     * mes siguiente. En ambos el valor guardado es el día real y hay que dejarlo.
     */
    private function explicaUnTruncamiento(Carbon $corte, int $diaCobro): bool
    {
        $truncadoAFinDeMes = $corte->day === $corte->daysInMonth && $diaCobro > $corte->day;
        $desbordadoAlMesSiguiente = $corte->day <= 3 && $diaCobro >= 29;

        return $truncadoAFinDeMes || $desbordadoAlMesSiguiente;
    }
}
