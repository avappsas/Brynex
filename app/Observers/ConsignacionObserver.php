<?php

namespace App\Observers;

use App\Jobs\EmitirFacturaDataicoJob;
use App\Models\Consignacion;
use App\Models\DataicoConfiguracion;
use Illuminate\Support\Facades\DB;

/**
 * Dispara la facturación electrónica cuando entra plata a la cuenta de la
 * razón social emisora.
 *
 * Se engancha aquí y no en `Factura::created` porque el criterio de negocio es
 * la cuenta bancaria, no la factura: una factura sin consignación en la cuenta
 * de BRYGAR SAS no se emite. Además, al crear la factura todavía no existe la
 * consignación, así que la selección daría vacío.
 *
 * El despacho lleva 3 minutos de retraso a propósito: un lote empresarial crea
 * la factura y sus consignaciones en varias escrituras, y emitir en mitad del
 * lote facturaría una base incompleta.
 */
class ConsignacionObserver
{
    private const RETRASO_MINUTOS = 3;

    public function created(Consignacion $consignacion): void
    {
        if (! $consignacion->factura_id || ! $consignacion->banco_cuenta_id) {
            return;
        }

        $aliadoId = (int) $consignacion->aliado_id;
        $cfg = DataicoConfiguracion::activaDe($aliadoId);

        if (! $cfg
            || $cfg->modo !== DataicoConfiguracion::MODO_FACTURA
            || ! $cfg->estaCompleta()
            // sqlsrv devuelve los ids como string: comparar con == y castear.
            || (int) $cfg->banco_cuenta_id !== (int) $consignacion->banco_cuenta_id) {
            return;
        }

        $numeroFactura = DB::table('facturas')
            ->where('id', $consignacion->factura_id)
            ->value('numero_factura');

        if (! $numeroFactura) {
            return;
        }

        EmitirFacturaDataicoJob::dispatch($aliadoId, (int) $numeroFactura)
            ->delay(now()->addMinutes(self::RETRASO_MINUTOS));
    }
}
