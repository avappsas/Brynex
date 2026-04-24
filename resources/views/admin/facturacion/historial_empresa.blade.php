@extends('layouts.app')
@section('modulo', 'Historial Empresa')

@php
use Carbon\Carbon;
$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fmt   = fn($v) => '$'.number_format($v ?? 0, 0, ',', '.');
$tipoLabel = fn($t) => match($t) {
    'planilla'     => ['🧾 Planilla',   '#dbeafe','#1d4ed8'],
    'afiliacion'   => ['📋 Afiliación', '#f3e8ff','#7c3aed'],
    'otro_ingreso' => ['💼 Trámite',    '#dcfce7','#065f46'],
    default        => [ucfirst($t),     '#f1f5f9','#64748b'],
};
$estadoLabel = fn($e) => match($e) {
    'pagada'      => ['✅ Pagado',    '#dcfce7','#15803d'],
    'prestamo'    => ['💳 Préstamo',  '#ede9fe','#6d28d9'],
    'abono'       => ['🔄 Abono',     '#fef3c7','#92400e'],
    'pre_factura' => ['📝 Pre-fac.',  '#f1f5f9','#64748b'],
    'anulada'     => ['❌ Anulada',   '#fee2e2','#dc2626'],
    default       => [ucfirst($e),    '#f1f5f9','#64748b'],
};
@endphp

@section('contenido')
<style>
.hist-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:14px;color:#fff;padding:1rem 1.4rem;margin-bottom:1rem}
.fil-btn{padding:.3rem .8rem;border-radius:20px;font-size:.75rem;font-weight:600;border:1.5px solid #e2e8f0;background:#f8fafc;cursor:pointer;transition:all .15s}
.fil-btn.active{border-color:#2563eb;background:#eff6ff;color:#1d4ed8}
table.htbl{width:100%;border-collapse:collapse;font-size:.8rem}
.htbl th{background:#0f172a;color:#94a3b8;font-size:.64rem;text-transform:uppercase;letter-spacing:.05em;padding:.4rem .55rem;white-space:nowrap;position:sticky;top:0;z-index:2}
.htbl td{padding:.38rem .55rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}
.htbl tr:hover td{background:#f8fafc}
.htbl tr.anulada td{opacity:.5;text-decoration:line-through}
.num{font-family:monospace;text-align:right}
.badge{padding:.13rem .45rem;border-radius:20px;font-size:.68rem;font-weight:700;white-space:nowrap}
.cant-badge{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#0f172a;color:#fff;font-size:.65rem;font-weight:800;margin-left:.3rem}

/* ── Columna de saldo ────────────────────────────────────────── */
.saldo-pill {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .18rem .55rem;
    border-radius: 20px;
    font-size: .68rem;
    font-weight: 800;
    font-family: monospace;
    white-space: nowrap;
    letter-spacing: .01em;
}
/* Verde: generó saldo a favor → sobró dinero */
.saldo-generado {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
}
/* Rojo: consumió saldo previo → gastó el anticipo */
.saldo-consumido {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}
/* Gris: sin movimiento de saldo */
.saldo-neutro {
    background: #f1f5f9;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
}
/* Etiqueta explicativa pequeña debajo del pill */
.saldo-hint {
    font-size: .58rem;
    font-weight: 500;
    font-family: sans-serif;
    color: #94a3b8;
    display: block;
    margin-top: .06rem;
    letter-spacing: 0;
}
/* Resumen en el pie de la tabla */
.tfoot-saldo {
    font-size: .72rem;
    font-weight: 700;
    text-align: right;
    padding: .4rem .55rem;
}
</style>

{{-- Header --}}
<div class="hist-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
        <div>
            <a href="{{ route('admin.facturacion.empresa', $empresa->id) }}"
               style="color:#94a3b8;font-size:.78rem;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem">
                ← Volver a facturación
            </a>
            <div style="font-size:1.2rem;font-weight:800;margin-top:.3rem">📋 Historial — {{ $empresa->empresa }}</div>
            <div style="font-size:.78rem;color:#94a3b8;margin-top:.2rem;display:flex;gap:1rem;flex-wrap:wrap">
                @if($empresa->nit)<span>NIT: {{ $empresa->nit }}</span>@endif
                <span>{{ $grupos->count() }} registros ({{ $facturas->count() }} facturas)</span>
            </div>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div style="background:#fff;border-radius:10px;border:1px solid #e2e8f0;padding:.6rem .9rem;margin-bottom:.8rem;display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
    <span class="fil-btn active" onclick="filtrarHist(this,'todos')">Todos</span>
    <span class="fil-btn" onclick="filtrarHist(this,'planilla')">🧾 Planilla</span>
    <span class="fil-btn" onclick="filtrarHist(this,'afiliacion')">📋 Afiliación</span>
    <span class="fil-btn" onclick="filtrarHist(this,'otro_ingreso')">💼 Trámites</span>
    <span class="fil-btn" onclick="filtrarHist(this,'anulada')">❌ Anuladas</span>
    <div style="width:1px;height:20px;background:#e2e8f0;margin:0 .2rem"></div>
    <span class="fil-btn" onclick="filtrarHist(this,'con_saldo')">🟢 Generó saldo</span>
    <span class="fil-btn" onclick="filtrarHist(this,'consumio_saldo')">🔴 Consumió saldo</span>
</div>

{{-- Tabla --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden">
<div style="overflow-x:auto">
<table class="htbl" id="tblHist">
<thead><tr>
    <th>N° Recibo</th>
    <th>Fecha</th>
    <th>Tipo</th>
    <th>Descripción / Info</th>
    <th>Período</th>
    <th class="num">Total</th>
    <th style="text-align:center">Saldo</th>
    <th>Estado</th>
    <th>Facturó</th>
    <th style="text-align:center">Recibo</th>
</tr></thead>
<tbody>
@php $totalSaldoAcumulado = 0; @endphp
@forelse($grupos as $g)
@php
[$tipoTxt,$tipoBg,$tipoColor] = $tipoLabel($g->tipo);
[$estTxt,$estBg,$estColor]    = $estadoLabel($g->estado);
$desc = $g->descripcion_tramite;
if (!$desc) {
    $desc = ($g->cantidad > 1)
        ? $g->cantidad.' trabajadores'
        : ($g->np ? 'NP '.$g->np : '—');
}

// ── Lógica del saldo ────────────────────────────────────────────
$sp  = (int)($g->saldo_proximo ?? 0);         // lo que generó/consumió este NP
$saf = (int)($g->saldo_a_favor_aplicado ?? 0); // saldo previo que traía

if ($sp > 0) {
    // Sobró dinero → generó anticipo para el siguiente mes
    $saldoPillClass = 'saldo-generado';
    $saldoIcono     = '↑';
    $saldoTexto     = '+' . number_format($sp, 0, ',', '.');
    $saldoHint      = 'Anticipo → sig. mes';
    $saldoData      = 'con_saldo';
} elseif ($sp < 0) {
    // Consumió saldo previo → descontó anticipo que venía de antes
    $saldoPillClass = 'saldo-consumido';
    $saldoIcono     = '↓';
    $saldoTexto     = number_format($sp, 0, ',', '.');   // ya es negativo
    $saldoHint      = 'Consumió anticipo';
    $saldoData      = 'consumio_saldo';
} else {
    // Sin movimiento de saldo
    $saldoPillClass = 'saldo-neutro';
    $saldoIcono     = '·';
    $saldoTexto     = '$0';
    $saldoHint      = 'Sin movimiento';
    $saldoData      = 'neutro';
}
@endphp
<tr data-tipo="{{ $g->tipo }}"
    data-saldo="{{ $saldoData }}"
    class="{{ $g->estado === 'anulada' ? 'anulada' : '' }}">
    <td>
        <strong style="font-family:monospace">{{ str_pad($g->numero_factura,6,'0',STR_PAD_LEFT) }}</strong>
        @if($g->np)<span style="font-size:.65rem;color:#94a3b8;margin-left:.3rem">NP {{ $g->np }}</span>@endif
    </td>
    <td style="font-size:.75rem">{{ $g->fecha_pago ? sqldate($g->fecha_pago)->format('d/m/Y') : '—' }}</td>
    <td>
        <span class="badge" style="background:{{ $tipoBg }};color:{{ $tipoColor }}">{{ $tipoTxt }}</span>
        @if($g->cantidad > 1)<span class="cant-badge" title="{{ $g->cantidad }} registros">{{ $g->cantidad }}</span>@endif
    </td>
    <td style="max-width:240px;white-space:normal;font-size:.76rem">{{ $desc }}</td>
    <td style="font-size:.75rem">{{ $meses[($g->mes ?? 1)-1] ?? '?' }} {{ $g->anio }}</td>
    <td class="num"><strong>{{ $fmt($g->total) }}</strong></td>

    {{-- ── Columna Saldo ────────────────────────────────────────── --}}
    <td style="text-align:center">
        @if($g->estado !== 'anulada' && $g->tipo !== 'otro_ingreso')
        <span class="saldo-pill {{ $saldoPillClass }}"
              title="{{ $sp > 0 ? 'Generó $'.number_format($sp,0,',','.').' de anticipo para el siguiente período' : ($sp < 0 ? 'Usó $'.number_format(abs($sp),0,',','.').' del saldo a favor acumulado' : 'Sin saldo pendiente ni anticipo') }}">
            {{ $saldoIcono }} ${{ number_format(abs($sp), 0, ',', '.') }}
        </span>
        <span class="saldo-hint">{{ $saldoHint }}</span>
        @if($saf > 0)
            <span class="saldo-hint" style="color:#7c3aed" title="Traía este saldo a favor de meses anteriores">
                traía +${{ number_format($saf,0,',','.') }}
            </span>
        @endif
        @else
            <span style="color:#e2e8f0;font-size:.8rem">—</span>
        @endif
    </td>

    <td><span class="badge" style="background:{{ $estBg }};color:{{ $estColor }}">{{ $estTxt }}</span></td>
    <td style="font-size:.72rem;color:#64748b">{{ $g->usuario?->nombre ?? $g->usuario?->name ?? '—' }}</td>
    <td style="text-align:center">
        <button onclick="abrirRecibo('{{ route('admin.facturacion.recibo', $g->id) }}?modal=1')"
            style="font-size:.72rem;padding:.22rem .55rem;background:#0f172a;color:#fff;border:none;border-radius:5px;cursor:pointer;font-weight:600">
            🧾 Recibo
        </button>
    </td>
</tr>
@empty
<tr><td colspan="10" style="text-align:center;padding:1.5rem;color:#94a3b8">Sin registros para esta empresa</td></tr>
@endforelse
</tbody>

{{-- Pie de tabla: saldo neto acumulado de toda la empresa --}}
@php
    $saldoNetoTotal = $grupos->where('tipo','!=','otro_ingreso')->where('estado','!=','anulada')->sum('saldo_proximo');
@endphp
<tfoot>
<tr style="background:#0f172a">
    <td colspan="6" style="padding:.45rem .55rem;font-size:.72rem;color:#94a3b8;font-weight:700">
        SALDO NETO ACUMULADO EMPRESA
    </td>
    <td style="text-align:center;padding:.45rem .55rem">
        @if($saldoNetoTotal > 0)
            <span class="saldo-pill saldo-generado">↑ +${{ number_format($saldoNetoTotal,0,',','.') }}</span>
            <span class="saldo-hint" style="color:#4ade80">A favor</span>
        @elseif($saldoNetoTotal < 0)
            <span class="saldo-pill saldo-consumido">↓ ${{ number_format($saldoNetoTotal,0,',','.') }}</span>
            <span class="saldo-hint" style="color:#fca5a5">Pendiente</span>
        @else
            <span class="saldo-pill saldo-neutro">· $0</span>
            <span class="saldo-hint">Equilibrado</span>
        @endif
    </td>
    <td colspan="3"></td>
</tr>
</tfoot>
</table>
</div>
</div>

{{-- Leyenda --}}
<div style="margin-top:.6rem;display:flex;gap:.6rem;flex-wrap:wrap;font-size:.7rem;color:#64748b;align-items:center">
    <span style="font-weight:600">Saldo:</span>
    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#16a34a;margin-right:.3rem"></span>↑ Generó anticipo — sobró dinero que va al siguiente mes</span>
    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#dc2626;margin-right:.3rem"></span>↓ Consumió saldo — descontó anticipo ya acumulado</span>
    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#94a3b8;margin-right:.3rem"></span>· Sin movimiento de saldo</span>
</div>

{{-- Modal Recibo —  mismo que empresa.blade.php --}}
<div id="recibo-modal-ov"
     onclick="if(event.target.id==='recibo-modal-ov')cerrarRecibo()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:center;justify-content:center;">
    <div style="position:relative;width:96vw;max-width:1100px;height:93vh;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.5);display:flex;flex-direction:column;">
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:.6rem 1rem;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:.5rem;">
                <span style="font-size:1.1rem;">🧾</span>
                <span style="color:#fff;font-size:.9rem;font-weight:700;letter-spacing:.02em;">Recibo de Pago</span>
            </div>
            <button onclick="cerrarRecibo()"
                    style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:6px;width:28px;height:28px;font-size:1rem;cursor:pointer;line-height:1;font-weight:700;transition:background .15s;"
                    onmouseover="this.style.background='rgba(255,255,255,.28)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">&#x2715;</button>
        </div>
        <div style="flex:1;background:#e8edf2;padding:.35rem 0 0;overflow:hidden;">
            <iframe id="recibo-frame" src="" style="width:100%;height:100%;border:none;display:block;"></iframe>
        </div>
    </div>
</div>

<script>
function filtrarHist(btn, tipo) {
    document.querySelectorAll('.fil-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#tblHist tbody tr').forEach(tr => {
        if (tipo === 'todos') { tr.style.display = ''; return; }
        if (tipo === 'anulada') {
            tr.style.display = tr.classList.contains('anulada') ? '' : 'none';
        } else if (tipo === 'con_saldo' || tipo === 'consumio_saldo') {
            tr.style.display = tr.dataset.saldo === tipo ? '' : 'none';
        } else {
            tr.style.display = tr.dataset.tipo === tipo ? '' : 'none';
        }
    });
}

function abrirRecibo(url) {
    document.getElementById('recibo-frame').src = url;
    document.getElementById('recibo-modal-ov').style.display = 'flex';
}

function cerrarRecibo() {
    document.getElementById('recibo-modal-ov').style.display = 'none';
    document.getElementById('recibo-frame').src = '';
}
</script>
@endsection
