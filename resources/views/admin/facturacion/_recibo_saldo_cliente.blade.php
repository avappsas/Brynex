{{-- ══════════════════════════════════════════════════════════════════════
     ESTADO DE CUENTA — copia CLIENTE
     ---------------------------------------------------------------------
     Dice si el cliente queda debiendo o a favor DESPUÉS de este recibo.
     Hasta sep-2026 esto solo existía en la copia empresa, que únicamente
     GiMave imprime: en los demás aliados el cliente pagaba de menos y no se
     enteraba nadie hasta el mes siguiente, cuando la deuda reaparecía como
     cartera al facturar (caso Daniel Arroyave, jul-2026).

     saldo_proximo = (efectivo + consignado + anticipo) − total
        negativo → PENDIENTE      positivo → A FAVOR
     $saldoAnterior lo calcula el controlador: la suma de los saldo_proximo
     de las facturas previas del mismo cliente (o empresa).
══════════════════════════════════════════════════════════════════════════ --}}
@php
$sAntCli = (int) ($saldoAnterior ?? 0);
$sFilasCli = 0;
foreach ($filas as $fSaldo) {
    $sFilasCli += (int) ($fSaldo->saldo_proximo ?? 0);
}
// Lo que queda para el mes siguiente: lo que traía más lo de este recibo.
$sTotalCli = $sAntCli + $sFilasCli;

// Un préstamo no se cobra con la factura siguiente sino por el módulo de
// Préstamos, así que la leyenda no puede decir lo mismo en los dos casos.
$esPrestamoCli = ($factura->estado ?? '') === 'prestamo';
@endphp

<div style="border-top:1.5px solid #e2e8f0;padding:.5rem 1.2rem;
            background:{{ $sTotalCli < 0 ? '#fef2f2' : ($sTotalCli > 0 ? '#f0fdf4' : '#f8fafc') }};">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
        @if($sTotalCli < 0)
        <span style="font-size:.78rem;font-weight:800;color:#b91c1c;">
            ⚠️ Queda debiendo: {{ $fmt(abs($sTotalCli)) }}
        </span>
        <span style="font-size:.66rem;color:#991b1b;font-weight:600;">
            {{ $esPrestamoCli ? 'queda como préstamo por cobrar' : 'se cobra con la próxima factura' }}
        </span>
        @elseif($sTotalCli > 0)
        <span style="font-size:.78rem;font-weight:800;color:#15803d;">
            ✅ Saldo a favor: {{ $fmt($sTotalCli) }}
        </span>
        <span style="font-size:.66rem;color:#15803d;font-weight:600;">
            se descuenta del próximo pago
        </span>
        @else
        <span style="font-size:.78rem;font-weight:800;color:#15803d;">✔ Al día</span>
        <span style="font-size:.66rem;color:#64748b;font-weight:600;">sin saldos pendientes</span>
        @endif
    </div>

    {{-- Si la deuda viene de atrás, decirlo: el TOTAL A PAGAR de arriba es
         solo el de este período y si no se aclara parece que no cuadra. --}}
    @if($sTotalCli < 0 && $sFilasCli >= 0)
    <div style="font-size:.66rem;color:#991b1b;margin-top:.2rem;">
        ↳ de períodos anteriores, no incluido en el total de este recibo
    </div>
    @elseif($sTotalCli < 0 && $sAntCli < 0 && $sFilasCli < 0)
    <div style="font-size:.66rem;color:#991b1b;margin-top:.2rem;">
        ↳ {{ $fmt(abs($sAntCli)) }} de períodos anteriores + {{ $fmt(abs($sFilasCli)) }} de este recibo
    </div>
    @endif
</div>
