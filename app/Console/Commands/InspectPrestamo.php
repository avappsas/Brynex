<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\PrestamoMovimiento;

class InspectPrestamo extends Command
{
    protected $signature = 'inspect:prestamo {id}';
    protected $description = 'Inspecciona un préstamo y sus movimientos';

    public function handle()
    {
        $id = $this->argument('id');
        $prestamo = Prestamo::find($id);

        if (!$prestamo) {
            $this->error('Préstamo no encontrado');
            return;
        }

        $this->info("=== PRÉSTAMO ID: {$id} ===");
        $this->line("Nombre: " . $prestamo->nombre_deudor);
        $this->line("Fecha Desembolso: " . $prestamo->fecha_desembolso);
        $this->line("Último Corte: " . ($prestamo->ultimo_corte ?? 'null'));
        $this->line("Saldo Actual: " . $prestamo->saldo_actual);
        $this->line("Estado: " . $prestamo->estado);

        $this->info("\n=== MOVIMIENTOS ===");
        $movs = $prestamo->movimientos()->orderBy('fecha', 'asc')->orderBy('id', 'asc')->get();
        foreach ($movs as $mov) {
            $this->line("[ID: {$mov->id}] Fecha: {$mov->fecha} | Tipo: {$mov->tipo} | Monto: {$mov->monto} | Antes: {$mov->saldo_antes} | Después: {$mov->saldo_despues} | Obs: {$mov->observacion}");
        }
    }
}
