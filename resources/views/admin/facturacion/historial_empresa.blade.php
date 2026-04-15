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
    <th>Estado</th>
    <th>Facturó</th>
    <th style="text-align:center">Recibo</th>
</tr></thead>
<tbody>
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
@endphp
<tr data-tipo="{{ $g->tipo }}" class="{{ $g->estado === 'anulada' ? 'anulada' : '' }}">
    <td>
        <strong style="font-family:monospace">{{ str_pad($g->numero_factura,6,'0',STR_PAD_LEFT) }}</strong>
        @if($g->np)<span style="font-size:.65rem;color:#94a3b8;margin-left:.3rem">NP {{ $g->np }}</span>@endif
    </td>
    <td style="font-size:.75rem">{{ $g->fecha_pago ? \Carbon\Carbon::parse($g->fecha_pago)->format('d/m/Y') : '—' }}</td>
    <td>
        <span class="badge" style="background:{{ $tipoBg }};color:{{ $tipoColor }}">{{ $tipoTxt }}</span>
        @if($g->cantidad > 1)<span class="cant-badge" title="{{ $g->cantidad }} registros">{{ $g->cantidad }}</span>@endif
    </td>
    <td style="max-width:240px;white-space:normal;font-size:.76rem">{{ $desc }}</td>
    <td style="font-size:.75rem">{{ $meses[($g->mes ?? 1)-1] ?? '?' }} {{ $g->anio }}</td>
    <td class="num"><strong>{{ $fmt($g->total) }}</strong></td>
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
<tr><td colspan="9" style="text-align:center;padding:1.5rem;color:#94a3b8">Sin registros para esta empresa</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

{{-- Modal Recibo — mismo que empresa.blade.php (sin botones propios, los del iframe manejan todo) --}}
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
