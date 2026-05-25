<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Factura;
use App\Models\Consignacion;

class UpdateFacturasBancoCuenta extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facturas:update-banco {--aliado=2} {--banco=145}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza la cuenta bancaria en las consignaciones de un listado de facturas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $aliadoId = $this->option('aliado');
        $bancoId = $this->option('banco');

        $this->info("Actualizando consignaciones para aliado_id={$aliadoId} hacia banco_cuenta_id={$bancoId}");

        // Pide los números de factura separados por coma
        $numerosFacturaStr = $this->ask('Ingresa los números de factura separados por comas (ej. 100,101,102)');
        
        if (empty(trim($numerosFacturaStr))) {
            $this->error("No se ingresaron números de factura.");
            return;
        }

        // Convertir a array de enteros
        $numerosFactura = array_filter(array_map('intval', array_map('trim', explode(',', $numerosFacturaStr))));

        if (empty($numerosFactura)) {
            $this->error("Los números de factura no son válidos.");
            return;
        }

        // Buscar las facturas
        $facturasIds = Factura::where('aliado_id', $aliadoId)
            ->whereIn('numero_factura', $numerosFactura)
            ->pluck('id')
            ->toArray();

        if (empty($facturasIds)) {
            $this->error("No se encontraron facturas con esos números para el aliado {$aliadoId}.");
            return;
        }

        $this->info("Se encontraron " . count($facturasIds) . " facturas válidas.");

        // Actualizar consignaciones
        $actualizadas = Consignacion::where('aliado_id', $aliadoId)
            ->whereIn('factura_id', $facturasIds)
            ->update(['banco_cuenta_id' => $bancoId]);

        $this->info("¡Éxito! Se actualizaron {$actualizadas} consignaciones a la cuenta de banco ID {$bancoId}.");
    }
}
