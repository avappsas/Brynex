<?php

namespace App\Console\Commands;

use App\Models\Factura;
use App\Services\Dataico\NotaCreditoService;
use Illuminate\Console\Command;

/**
 * Nota crédito para una FE cuyo recibo ya se anuló en Brynex sin ella.
 *
 * Antes de sep-2026 anular un recibo no tocaba la FE: quedaron vivas ante la
 * DIAN facturas de recibos anulados y re-facturados (FE2314 y FE2321). Desde
 * entonces el botón Anular emite la nota él mismo; este comando es para lo que
 * quedó atrás.
 *
 * Solo actúa si el recibo completo está anulado: anular la FE de un recibo
 * vigente lo dejaría cobrado sin factura. Para eso está el botón Anular.
 */
class DataicoNotaCredito extends Command
{
    protected $signature = 'dataico:nota-credito
        {numero_factura : número de recibo de Brynex (facturas.numero_factura)}
        {--aliado=2 : aliado dueño del recibo}
        {--motivo= : motivo que queda en la nota}
        {--usuario=2 : a quién se le atribuye}
        {--sin-correo : no le envía la nota al correo del cliente}
        {--simular : muestra el JSON sin enviar nada}';

    protected $description = 'Emite la nota crédito (anulación) de la FE de un recibo ya anulado';

    public function handle(NotaCreditoService $servicio): int
    {
        $aliado = (int) $this->option('aliado');
        $numero = (int) $this->argument('numero_factura');

        $vivas = Factura::where('aliado_id', $aliado)->where('numero_factura', $numero)->count();
        if ($vivas > 0) {
            $this->error("El recibo #{$numero} sigue vigente ({$vivas} fila(s)). Anúlelo desde el recibo: el botón emite la nota crédito.");

            return self::FAILURE;
        }

        $envio = NotaCreditoService::feVigente($aliado, $numero);
        if (! $envio) {
            $this->warn("El recibo #{$numero} no tiene una factura electrónica vigente.");

            return self::SUCCESS;
        }

        $motivo = trim((string) $this->option('motivo')) ?: "Anulación del recibo {$numero}";
        $this->line("Recibo #{$numero} → {$envio->dataico_numero} · {$envio->cliente_nombre} · $".number_format((float) $envio->base_admon, 0, ',', '.'));

        $r = $servicio->anular($envio, $motivo, (int) $this->option('usuario'), (bool) $this->option('simular'), ! $this->option('sin-correo'));

        if ($r['payload']) {
            $this->line(json_encode($r['payload'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        $r['ok'] ? $this->info($r['mensaje']) : $this->error($r['mensaje']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
