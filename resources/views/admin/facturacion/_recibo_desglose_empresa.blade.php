{{-- ══════════════════════════════════════════════════════════════════════
     DESGLOSE — solo copia EMPRESA (modo doble copia por hoja)
     ---------------------------------------------------------------------
     Se calcula sobre $filas, que es el lote completo en el recibo de
     empresa (NP) y una sola factura en el recibo individual. Así los
     números cuadran siempre con el TOTAL que ya muestra el recibo.

     Sobre los saldos: en `facturas` solo existe la columna `saldo_proximo`
     (`saldo_a_favor` y `saldo_pendiente` se eliminaron en la migración
     2026_04_20_220000). Convención:
        saldo_proximo = (efectivo + consignado + anticipo) − total
        negativo → quedó PENDIENTE     positivo → quedó A FAVOR
     El saldo anterior no está guardado: lo calcula el controlador como la
     suma de los saldo_proximo de las facturas previas ($saldoAnterior).
══════════════════════════════════════════════════════════════════════════ --}}
@php
$dEps = $dArl = $dPen = $dCaj = 0;
$dAdmonEmp = $dAdmonAse = $dOtrosAdm = 0;
$dSeguro = $dAfil = $dMens = $dOtros = $dMora = $dIva = 0;
$dTotal = $dEfect = $dConsig = $dAnticipo = $dSaldoProx = 0;

foreach ($filas as $fd) {
    $dEps      += (int)($fd->v_eps        ?? 0);
    $dArl      += (int)($fd->v_arl        ?? 0);
    $dPen      += (int)($fd->v_afp        ?? 0);
    $dCaj      += (int)($fd->v_caja       ?? 0);
    $dAdmonEmp += (int)($fd->admon        ?? 0);
    // Los honorarios del asesor viven en admin_asesor (planilla/afiliación)
    // y en admon_asesor_oi (otros ingresos). Nunca están los dos a la vez.
    $dAdmonAse += (int)($fd->admin_asesor ?? 0) + (int)($fd->admon_asesor_oi ?? 0);
    $dOtrosAdm += (int)($fd->otros_admon  ?? 0);
    $dSeguro   += (int)($fd->seguro       ?? 0);
    $dAfil     += (int)($fd->afiliacion   ?? 0);
    $dMens     += (int)($fd->mensajeria   ?? 0);
    $dOtros    += (int)($fd->otros        ?? 0);
    $dMora     += (int)($fd->mora         ?? 0);
    $dIva      += (int)($fd->iva          ?? 0);
    $dTotal    += (int)($fd->total        ?? 0);
    $dEfect    += (int)($fd->valor_efectivo    ?? 0);
    $dConsig   += (int)($fd->valor_consignado  ?? 0);
    $dAnticipo += (int)($fd->anticipo_aplicado ?? 0);
    $dSaldoProx+= (int)($fd->saldo_proximo     ?? 0);
}

$dSS       = $dEps + $dArl + $dPen + $dCaj;
$dServicios = $dAdmonEmp + $dAdmonAse + $dOtrosAdm + $dSeguro
            + $dAfil + $dMens + $dOtros + $dMora + $dIva;
$dPagado   = $dEfect + $dConsig + $dAnticipo;

// Red de seguridad: el desglose SIEMPRE debe sumar el total de la factura.
// Si aparece un concepto nuevo que aquí no se contempla, sale como "Otros
// conceptos" en vez de dejar el recibo descuadrado.
$dDif = $dTotal - ($dSS + $dServicios);
if ($dDif !== 0) { $dServicios += $dDif; }

$sAnt      = (int)($saldoAnterior ?? 0);
@endphp

<div class="desglose-emp">
    <div class="desglose-emp-hdr">🏢 Desglose para la empresa &mdash; no entregar al cliente</div>
    <div class="desglose-emp-grid">

        {{-- ── Seguridad Social ─────────────────────────────── --}}
        <div class="dg-col">
            <div class="dg-col-hdr">Seguridad Social</div>
            <div class="dg-row"><span>EPS</span><b>{{ $fmt($dEps) }}</b></div>
            <div class="dg-row"><span>ARL</span><b>{{ $fmt($dArl) }}</b></div>
            <div class="dg-row"><span>Pensión</span><b>{{ $fmt($dPen) }}</b></div>
            <div class="dg-row"><span>Caja compensación</span><b>{{ $fmt($dCaj) }}</b></div>
            <div class="dg-row dg-sub"><span>Subtotal SS</span><b>{{ $fmt($dSS) }}</b></div>
        </div>

        {{-- ── Administración y servicios ───────────────────── --}}
        <div class="dg-col">
            <div class="dg-col-hdr">Administración y servicios</div>
            <div class="dg-row"><span>Admón. empresa</span><b>{{ $fmt($dAdmonEmp) }}</b></div>
            <div class="dg-row"><span>Admón. asesor</span><b>{{ $fmt($dAdmonAse) }}</b></div>
            @if($dOtrosAdm > 0)
            <div class="dg-row"><span>Otros admón.</span><b>{{ $fmt($dOtrosAdm) }}</b></div>
            @endif
            <div class="dg-row"><span>Seguro</span><b>{{ $fmt($dSeguro) }}</b></div>
            @if($dAfil > 0)
            <div class="dg-row"><span>Afiliación</span><b>{{ $fmt($dAfil) }}</b></div>
            @endif
            @if($dMens > 0)
            <div class="dg-row"><span>Mensajería</span><b>{{ $fmt($dMens) }}</b></div>
            @endif
            @if($dOtros > 0)
            <div class="dg-row"><span>Otros</span><b>{{ $fmt($dOtros) }}</b></div>
            @endif
            @if($dMora > 0)
            <div class="dg-row" style="color:#b91c1c"><span>Mora</span><b>{{ $fmt($dMora) }}</b></div>
            @endif
            <div class="dg-row"><span>IVA / 4&times;1000</span><b>{{ $fmt($dIva) }}</b></div>
            @if($dDif !== 0)
            {{-- Suele salir en filas sueltas de un lote cuyo valor quedó
                 consolidado en otra fila: total=0 pero con SS liquidada.
                 A nivel de lote completo la diferencia siempre es 0. --}}
            <div class="dg-row" style="color:#92400e">
                <span>Ajuste</span><b>{{ $dDif < 0 ? '−' : '+' }}{{ $fmt(abs($dDif)) }}</b>
            </div>
            @endif
            <div class="dg-row dg-sub"><span>Subtotal servicios</span><b>{{ $fmt($dServicios) }}</b></div>
        </div>

        {{-- ── Saldos ───────────────────────────────────────── --}}
        <div class="dg-col">
            <div class="dg-col-hdr">Saldos</div>

            {{-- Saldo anterior: suma de los saldo_proximo previos --}}
            @if($sAnt > 0)
            <div class="dg-row" style="color:#15803d"><span>Saldo anterior a favor</span><b>{{ $fmt($sAnt) }}</b></div>
            @elseif($sAnt < 0)
            <div class="dg-row" style="color:#b91c1c"><span>Saldo anterior pendiente</span><b>{{ $fmt(abs($sAnt)) }}</b></div>
            @else
            <div class="dg-row" style="color:#94a3b8"><span>Saldo anterior</span><b>{{ $fmt(0) }}</b></div>
            @endif

            <div class="dg-row" style="color:#15803d">
                <span>Anticipo aplicado</span><b>{{ $dAnticipo > 0 ? '−'.$fmt($dAnticipo) : $fmt(0) }}</b>
            </div>

            <div class="dg-row dg-sub"><span>Total factura</span><b>{{ $fmt($dTotal) }}</b></div>
            <div class="dg-row"><span>Pagado (efvo + consig + antic.)</span><b>{{ $fmt($dPagado) }}</b></div>

            {{-- Saldo próximo: lo que realmente queda para el mes siguiente --}}
            @if($dSaldoProx > 0)
            <div class="dg-row dg-saldo dg-favor">
                <span>Saldo próximo &mdash; A FAVOR</span><b>{{ $fmt($dSaldoProx) }}</b>
            </div>
            @elseif($dSaldoProx < 0)
            <div class="dg-row dg-saldo dg-debe">
                <span>Saldo próximo &mdash; PENDIENTE</span><b>{{ $fmt(abs($dSaldoProx)) }}</b>
            </div>
            @else
            <div class="dg-row dg-saldo dg-cero">
                <span>Saldo próximo</span><b>Al día</b>
            </div>
            @endif
        </div>

    </div>
</div>
