<?php

namespace App\Console\Commands;

use App\Models\Finanzas\Prestamo;
use App\Services\Finanzas\PrestamoLiquidacionService;
use Illuminate\Console\Command;

class LiquidarInteresesPrestamos extends Command
{
    /**
     * Liquidación automática de intereses de préstamos de Finanzas Personales.
     * Corre a diario y solo liquida MESES CALENDARIO COMPLETOS vencidos desde
     * el último corte de cada préstamo, así el interés se causa siempre el
     * mismo día del mes del desembolso, sin fracciones.
     *
     * Ejecución manual: php artisan finanzas:liquidar-intereses [--dry-run]
     */
    protected $signature = 'finanzas:liquidar-intereses {--dry-run : Muestra qué liquidaría sin escribir en la base de datos}';

    protected $description = 'Liquida automáticamente los intereses mensuales vencidos de los préstamos activos';

    public function handle(PrestamoLiquidacionService $liquidacionService): int
    {
        $prestamos = Prestamo::whereIn('estado', ['activo', 'mora'])
            ->where('tasa_interes_mensual', '>', 0)
            ->where('sin_interes', false)
            ->where('saldo_actual', '>', 0)
            ->get();

        $hoy = now()->toDateString();
        $totalLiquidado = 0.00;
        $prestamosAfectados = 0;

        foreach ($prestamos as $prestamo) {
            $ultimoCorte = $prestamo->ultimo_corte ?: $prestamo->fecha_desembolso;
            $mesesVencidos = \Carbon\Carbon::parse($ultimoCorte)->diffInMonths(now());

            if ($mesesVencidos < 1) {
                continue;
            }

            if ($this->option('dry-run')) {
                $estimado = round($prestamo->saldo_actual * ($prestamo->tasa_interes_mensual / 100), 2);
                $this->line("[DRY] #{$prestamo->id} {$prestamo->nombre_deudor}: {$mesesVencidos} mes(es) vencido(s), ~\${$estimado} el primer mes.");
                continue;
            }

            // Solo meses completos: la fracción corriente queda pendiente hasta cumplirse
            $interes = $liquidacionService->liquidarPeriodo($prestamo, null, $hoy, [], true);

            if ($interes > 0) {
                $prestamosAfectados++;
                $totalLiquidado += $interes;
                $this->info("#{$prestamo->id} {$prestamo->nombre_deudor}: intereses liquidados \$" . number_format($interes, 2));
            }
        }

        if (!$this->option('dry-run')) {
            $this->info("Listo. Préstamos liquidados: {$prestamosAfectados}. Total intereses causados: \$" . number_format($totalLiquidado, 2));
        }

        return self::SUCCESS;
    }
}
