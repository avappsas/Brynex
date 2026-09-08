<?php

namespace App\Exceptions;

/**
 * Se lanza cuando una factura marcada PAGADA no tiene registrado el dinero que
 * la cubre. Aborta la transacción de facturación para que no quede una factura
 * verde con una deuda invisible (ni préstamo, ni cartera cobrable).
 */
class PagoIncompletoException extends \RuntimeException
{
    public function __construct(
        public readonly int $totalLote,
        public readonly int $pagadoLote,
        public readonly int $saldoFavor,
        public readonly int $faltante,
    ) {
        parent::__construct('Pago incompleto: faltan '.$faltante.' de '.$totalLote);
    }

    /**
     * Se renderiza sola para no envolver la transacción de facturación en un
     * try/catch: Laravel la convierte en el 422 que el modal ya sabe mostrar.
     */
    public function render($request)
    {
        $fmt = fn (int $v) => '$'.number_format($v, 0, ',', '.');

        $detalle = $this->saldoFavor > 0
            ? $fmt($this->pagadoLote).' + '.$fmt($this->saldoFavor).' de saldo a favor'
            : $fmt($this->pagadoLote);

        return response()->json([
            'ok' => false,
            'error' => true,
            'pago_incompleto' => true,
            'faltante' => $this->faltante,
            'mensaje' => '🚫 No se generó la factura. La marcaste PAGADA pero el pago registrado no alcanza: '
                .'total '.$fmt($this->totalLote).', registrado '.$detalle.' → faltan '.$fmt($this->faltante).'. '
                .'Agrega la consignación o el efectivo que recibiste, o cambia el estado a PRÉSTAMO '
                .'por lo que queda debiendo (así sí entra al módulo de Préstamos y alguien lo cobra).',
        ], 422);
    }
}
